<?php
/**
 * Data Monitor Base
 *
 * @package RoxwpSiteMonitor\Monitors
 * @version 1.0.0
 * @since RoxwpSiteMonitor 1.0.0
 */

namespace AbsolutePlugins\RoxwpSiteMonitor\Monitors;

use Exception;
use Plugin_Upgrader;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Monitor_Plugins_Activity extends Activity_Monitor_Base {

	use Singleton;

	protected $check_maybe_log = false;

	protected $plugin = [];

	public function init() {
		add_action( 'activated_plugin', [ $this, 'on_plugin_activated' ], 10, 2 );
		add_action( 'deactivated_plugin', [ $this, 'on_plugin_deactivated' ], 10, 2 );

		add_action( 'delete_plugin', [ $this, 'on_before_delete' ] );
		add_action( 'deleted_plugin', [ $this, 'on_plugin_deleted' ], 10, 2 );
		add_action( 'upgrader_process_complete', [ $this, 'on_plugin_install_or_update' ], 10, 2 );

		// Plugin Editor Actions.
		add_action( 'wp_ajax_edit-theme-plugin-file', [ $this, 'on_plugin_file_modify' ], - 1 );
		add_filter( 'wp_redirect', [ $this, 'on_plugin_file_modify' ], - 1 );
	}

	protected function maybe_log_plugin( $action, $plugin, $file = null ) {

		/**
		 * Should report activity?
		 *
		 * @param bool $status
		 * @param string $plugin
		 * @param string $action
		 * @param string $file file path if one is being modified.
		 */
		return (bool) apply_filters( 'roxwp_should_log_plugins_activity', true, $plugin, $action, $file );
	}

	protected function log_plugin( $action, $plugin, $extra = [] ) {
		if ( ! $this->maybe_log_plugin( $action, $plugin ) ) {
			return;
		}

		if ( isset( $extra['name'] ) ) {
			$name = $extra['name'];
			unset( $extra['name'] );
		} else {
			$name = $this->get_name( $plugin );
		}

		$data = $this->get_plugin_data( $plugin );
		$data = empty( $data ) ? [] : $data;

		$this->log_activity(
			$action,
			0,
			$plugin,
			$name,
			array_merge( $data, $extra )
		);
	}

	public function on_plugin_activated( $plugin, $network_wide ) {
		$this->log_plugin( Activity_Monitor_Base::ITEM_ACTIVATED, $plugin, [ 'network_wide' => $network_wide ] );
	}

	public function on_plugin_deactivated( $plugin, $network_wide ) {
		$this->log_plugin( Activity_Monitor_Base::ITEM_DEACTIVATED, $plugin, [ 'network_wide' => $network_wide ] );
	}

	/**
	 * Cache plugin data before delete.
	 *
	 * @param string $plugin
	 */
	public function on_before_delete( $plugin ) {
		if ( ! $this->maybe_log_plugin( Activity_Monitor_Base::ITEM_DELETED, $plugin ) ) {
			return;
		}

		// cache plugin data
		$data           = $this->get_plugin_data( $plugin );
		$hash           = md5( $plugin );
		$data['Status'] = 2;

		set_transient( 'roxwp_plugin_data_' . $hash, $data, 60 );
	}

	public function on_plugin_deleted( $plugin, $deleted ) {
		$hash = md5( $plugin );

		$data = get_transient( 'roxwp_plugin_data_' . $hash );
		if ( $data ) {
			$this->plugin[ $hash ] = $data;
		}

		delete_transient( 'roxwp_plugin_data_' . $hash );

		$this->log_plugin( Activity_Monitor_Base::ITEM_DELETED, $plugin, [ 'deleted' => $deleted ] );
	}

	/**
	 *
	 * @param Plugin_Upgrader $upgrader
	 * @param array $extra
	 */
	public function on_plugin_install_or_update( $upgrader, $extra ) {
		if ( ! isset( $extra['type'] ) || 'plugin' !== $extra['type'] ) {
			return;
		}

		if ( 'install' === $extra['action'] ) {
			$path = $upgrader->plugin_info();
			if ( $path ) {

				// @XXX maybe we can remove this.
				$hash                  = md5( $path );
				$this->plugin[ $hash ] = roxwp_get_plugin_data( $upgrader->skin->result['local_destination'] . '/' . $path, false, false ); // @phpstan-ignore-line

				$this->log_plugin( Activity_Monitor_Base::ITEM_INSTALLED, $path );

				return;
			}
		}

		if ( isset( $extra['action'] ) && 'update' === $extra['action'] ) {
			if ( isset( $extra['bulk'] ) && true == $extra['bulk'] ) {
				$slugs = $extra['plugins'];
			} else {
				if ( ! isset( $upgrader->skin->plugin ) ) {
					return;
				}

				$slugs = [ $upgrader->skin->plugin ];
			}

			foreach ( $slugs as $slug ) {
				$this->log_plugin( Activity_Monitor_Base::ITEM_UPDATED, $slug );
			}
		}
	}

	protected function is_plugin_file_modified( $location = null ) {
		return (
			'edit-theme-plugin-file' === $_POST['action'] || // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing
			(
				'wp_redirect' === current_filter() &&
				false !== strpos( $location, 'plugin-editor.php' ) && 'update' === $_REQUEST['action'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.NonceVerification.Recommended
			)
		);
	}

	/**
	 * Hooked into plugin file edit ajax action
	 *
	 * @see wp_edit_theme_plugin_file()
	 */
	public function on_plugin_file_modify( $location = null ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST ) && isset( $_POST['action'], $_POST['plugin'], $_POST['file'] ) && ! empty( $_POST['plugin'] ) ) {
			if ( $this->is_plugin_file_modified( $location ) ) {
				$_POST  = wp_unslash( $_POST );
				$plugin = sanitize_text_field( $_POST['plugin'] );
				$file   = sanitize_text_field( $_POST['file'] );
				$_file  = WP_PLUGIN_DIR . $plugin; // @phpstan-ignore-line
				// phpcs:enable

				if ( $this->maybe_log_plugin( Activity_Monitor_Base::ITEM_UPDATED, $plugin, $file ) && file_exists( $_file ) ) {
					roxwp_switch_to_english();
					/* translators: 1. Plugin Name, 2. File path. */
					$name = $plugin === $file ? __( 'Modified main file (%2$s) of “%1$s” plugin', 'roxwp-site-mon' ) : __( 'Modified file (%2$s) of “%1$s” plugin', 'roxwp-site-mon' );
					roxwp_restore_locale();

					$extra = [
						'name' => sprintf( $name, $this->get_name( $plugin ), $file ),
						'file' => $file,
					];

					$this->log_plugin( Activity_Monitor_Base::ITEM_UPDATED, $plugin, $extra );
				}
			}
		}

		// return the location so wp_redirect can complete.
		return $location;
	}

	protected function get_name( $plugin_file ) {
		$data = $this->get_plugin_data( $plugin_file );

		return $data ? $data['Name'] : $plugin_file;
	}

	protected function get_plugin_data( $plugin_file, $header = null ) {
		$hash = md5( $plugin_file );

		if ( ! isset( $this->plugin[ $hash ] ) ) {
			$real_file = WP_PLUGIN_DIR . '/' . $plugin_file; // @phpstan-ignore-line

			$this->plugin[ $hash ] = roxwp_get_plugin_data( $real_file );
		}

		if ( $header ) {
			return isset( $this->plugin[ $hash ][ $header ] ) ? $this->plugin[ $hash ][ $header ] : null;
		}

		return $this->plugin[ $hash ];
	}
}

// End of file Monitor_Plugins_Activity.php.
