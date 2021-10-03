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
use Theme_Upgrader;
use WP_Customize_Manager;
use WP_Theme;
use function Sodium\add;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Monitor_Themes_Activity extends Activity_Monitor_Base {

	use Activity_Monitor_Trait;

	protected $check_maybe_log = false;

	protected $_theme = [];

	public function init() {

		add_action( 'switch_theme', [ $this, 'on_theme_change' ], 10, 3 );

		if ( version_compare( get_bloginfo( 'version' ), '5.8', '>=' ) ) {
			add_action( 'delete_theme', [ $this, 'on_before_delete' ] );
			add_action( 'deleted_theme', [ $this, 'on_theme_deleted' ] );
		} else {
			add_action( 'delete_site_transient_update_themes', [ $this, 'trace_on_theme_deleted' ] );
		}

		add_action( 'update_site_option_allowedthemes', [ $this, 'trace_on_disable_theme' ] );

		add_action( 'upgrader_process_complete', [ $this, 'on_theme_install_or_update' ], 10, 2 );

		// Theme customizer
		add_action( 'customize_save', [ $this, 'on_theme_customized' ] );

		// Theme Editor Actions.
		add_action( 'wp_ajax_edit-theme-plugin-file', [ $this, 'on_theme_file_modify' ], -1 );
		add_filter( 'wp_redirect', [ $this, 'on_theme_file_modify' ], -1 );

		// $plugin_file `directory_name/main_file.php`
	}

	protected function maybe_log_theme( $action, $theme, $file = null ) {

		/**
		 * Should report activity for WP Core Updates?
		 *
		 * @param bool $status
		 * @param string $plugin
		 * @param string $action
		 * @param string $file file path if one is being modified.
		 */
		return (bool) apply_filters( 'roxwp_should_log_plugins_activity', true, $theme, $action, $file );
	}

	public function on_theme_change( $new_name, WP_Theme $new_theme, WP_Theme $old_theme ) {
		if ( ! $this->maybe_log_theme( Activity_Monitor_Base::ITEM_ACTIVATED, $new_theme->get_stylesheet() ) ) {
			return;
		}

		roxwp_switch_to_site_locale();
		$name = sprintf(
			__( 'Switched to %1$s theme from %2$s', 'rwp-site-mon' ),
			$new_theme->get( 'Name' ),
			$old_theme->get( 'Name' )
		);
		roxwp_restore_locale();

		$this->log_activity(
			Activity_Monitor_Base::ITEM_ACTIVATED,
			0,
			$new_name,
			$name,
			[
				'version'    => $new_theme->get( 'Version' ),
				'author'     => $new_theme->get( 'Author' ),
				'theme_uri'  => $new_theme->get( 'ThemeURI' ),
				'author_uri' => $new_theme->get( 'AuthorURI' ),
				'old_theme'  => [
					'version'    => $old_theme->get( 'Version' ),
					'author'     => $old_theme->get( 'Author' ),
					'theme_uri'  => $old_theme->get( 'ThemeURI' ),
					'author_uri' => $old_theme->get( 'AuthorURI' ),
				]
			]
		);
	}

	public function on_before_delete( $stylesheet ) {
		if ( ! $this->maybe_log_theme( Activity_Monitor_Base::ITEM_DELETED, $stylesheet ) ) {
			return;
		}

		$hash = md5( $stylesheet );

		$data = $this->get_theme_data( $stylesheet );

		set_transient( 'roxwp_theme_data_' . $hash, $data, 60 );
	}

	public function on_theme_deleted( $stylesheet, $deleted ) {

		if ( ! $deleted || ! $this->maybe_log_theme( Activity_Monitor_Base::ITEM_DELETED, $stylesheet ) ) {
			return;
		}

		$hash = md5( $stylesheet );

		$data = get_transient( 'roxwp_theme_data_' . $hash );
		if ( $data ) {
			$this->_theme[ $hash ] = $data;
		}

		delete_transient( 'roxwp_theme_data_' . $hash );

		$this->log_activity(
			Activity_Monitor_Base::ITEM_DELETED,
			0,
			$stylesheet,
			$this->get_name( $stylesheet ),
			[
				'version'    => $this->get_theme_data( $stylesheet, 'Version' ),
				'author'     => $this->get_theme_data( $stylesheet, 'Author' ),
				'theme_uri'  => $this->get_theme_data( $stylesheet, 'ThemeURI' ),
				'author_uri' => $this->get_theme_data( $stylesheet, 'AuthorURI' ),
			]
		);
	}

	/**
	 * Back compact.
	 * action hooks are introduced in WP 5.8
	 * @see delete_theme()
	 */
	public function trace_on_theme_deleted() {
		$backtrace_history = debug_backtrace();

		$delete_theme_call = false;
		foreach ( $backtrace_history as $call ) {
			if ( isset( $call['function'] ) && 'delete_theme' === $call['function'] ) {
				$delete_theme_call = $call;
				break;
			}
		}

		if ( ! $delete_theme_call ) {
			return;
		}

		$stylesheet = $delete_theme_call['args'][0];

		if ( ! $this->maybe_log_theme( Activity_Monitor_Base::ITEM_DELETED, $stylesheet ) ) {
			return;
		}

		$this->log_activity(
			Activity_Monitor_Base::ITEM_DELETED,
			0,
			$stylesheet,
			$stylesheet
		);
	}

	public function trace_on_disable_theme() {

		$backtrace_history = debug_backtrace();

		$delete_theme_call = false;
		foreach ( $backtrace_history as $call ) {
			if ( isset( $call['function'] ) && false !== strpos( $call['function'], 'network_disable_theme' ) ) {
				$delete_theme_call = $call;
				break;
			}
		}

		if ( ! $delete_theme_call ) {
			return;
		}

		$stylesheet = $delete_theme_call['args'][0];

		if ( ! $this->maybe_log_theme( Activity_Monitor_Base::ITEM_DEACTIVATED, $stylesheet ) ) {
			return;
		}

		$this->log_activity(
			Activity_Monitor_Base::ITEM_DEACTIVATED,
			0,
			$stylesheet,
			$this->get_name( $stylesheet ),
			[
				'version'    => $this->get_theme_data( $stylesheet, 'Version' ),
				'author'     => $this->get_theme_data( $stylesheet, 'Author' ),
				'theme_uri'  => $this->get_theme_data( $stylesheet, 'ThemeURI' ),
				'author_uri' => $this->get_theme_data( $stylesheet, 'AuthorURI' ),
			]
		);
	}

	/**
	 * @param Theme_Upgrader $upgrader
	 * @param array $extra
	 */
	public function on_theme_install_or_update( $upgrader, $extra ) {

		if ( ! isset( $extra['type'] ) || 'theme' !== $extra['type'] ) {
			return;
		}

		if ( isset( $extra['action'] ) && 'install' === $extra['action'] ) {
			$slug = $upgrader->theme_info();
			if ( $slug ) {
				wp_clean_themes_cache();

				if ( ! $this->maybe_log_theme( Activity_Monitor_Base::ITEM_INSTALLED, $slug ) ) {
					return;
				}

				$this->log_activity(
					Activity_Monitor_Base::ITEM_INSTALLED,
					0,
					$slug,
					$this->get_name( $slug ),
					[
						'version'    => $this->get_theme_data( $slug, 'Version' ),
						'author'     => $this->get_theme_data( $slug, 'Author' ),
						'theme_uri'  => $this->get_theme_data( $slug, 'ThemeURI' ),
						'author_uri' => $this->get_theme_data( $slug, 'AuthorURI' ),
					]
				);
			}
		}

		if ( isset( $extra['action'] ) && 'update' === $extra['action'] ) {
			if ( isset( $extra['bulk'] ) && true == $extra['bulk'] ) {
				$slugs = $extra['themes'];
			} else {
				$slugs = array( $upgrader->skin->theme );
			}

			foreach ( $slugs as $slug ) {

				if ( ! $this->maybe_log_theme( Activity_Monitor_Base::ITEM_UPDATED, $slug ) ) {
					return;
				}

				$this->log_activity(
					Activity_Monitor_Base::ITEM_UPDATED,
					0,
					$slug,
					$this->get_name( $slug ),
					[
						'version'    => $this->get_theme_data( $slug, 'Version' ),
						'author'     => $this->get_theme_data( $slug, 'Author' ),
						'theme_uri'  => $this->get_theme_data( $slug, 'ThemeURI' ),
						'author_uri' => $this->get_theme_data( $slug, 'AuthorURI' ),
					]
				);
			}
		}
	}

	public function on_theme_customized(  WP_Customize_Manager $customize_manager ) {

		$slug = $customize_manager->get_stylesheet();

		if ( ! $this->maybe_log_theme( Activity_Monitor_Base::ITEM_UPDATED, $slug . '/customizer' ) ) {
			return;
		}

		$this->log_activity(
			Activity_Monitor_Base::ITEM_UPDATED,
			0,
			$slug,
			$customize_manager->theme()->get( 'Name' ),
			[ 'customizer' => true ]
		);
	}

	/**
	 * Hooked into plugin file edit ajax action
	 *
	 * @see wp_edit_theme_plugin_file()
	 */
	public function on_theme_file_modify( $location = null ) {
		if ( ! empty( $_POST ) && isset( $_POST['action'], $_POST['theme'], $_POST['file'] ) && ! empty( $_POST['theme'] ) ) {

			if (
				'edit-theme-plugin-file' === $_POST['action'] ||
				(
					'wp_redirect' === current_filter() &&
					false !== strpos( $location, 'plugin-editor.php' ) &&
					'update' === $_REQUEST['action']
				)
			) {


				$_POST = wp_unslash( $_POST );
				$theme = sanitize_text_field( $_POST['theme'] );
				$file  = sanitize_text_field( $_POST['file'] );
				$_file = WP_PLUGIN_DIR . $theme;

				if ( $this->maybe_log_theme( Activity_Monitor_Base::ITEM_UPDATED, $theme, $file ) && file_exists( $_file ) ) {

					roxwp_switch_to_site_locale();
					/* translators: 1. Theme Name, 2. File path. */
					$name = __( 'Modified file (%2$s) of “%1%s” theme' );
					roxwp_restore_locale();

					try {
						$this->log_activity(
							Activity_Monitor_Base::ITEM_UPDATED,
							0,
							$theme,
							sprintf( $name, $this->get_name( $theme ), $file ),
							[ 'slug' => $theme, 'file' => $file ]
						);
					} catch ( Exception $e ) {}

				}

			}
		}

		// return the location so wp_redirect can complete.
		return $location;
	}

	protected function get_name( $theme ) {
		$data = $this->get_theme_data( $theme );

		return $data ? $data['Name'] : $theme;
	}

	protected function get_theme_data( $theme, $header = null ) {
		$hash = md5( $theme );
		if ( ! isset( $this->_theme[ $hash ] ) ) {

			if ( ! function_exists( 'wp_get_theme' ) ) {
				require_once ABSPATH . 'wp-includes/theme.php';
			}

			$this->_theme[ $hash ] = wp_get_theme( $theme );

		}

		if ( $header ) {
			return isset( $this->_theme[ $hash ][ $header ] ) ? $this->_theme[ $hash ][ $header ] : null;
		}

		return $this->_theme[ $hash ];
	}
}

// End of file Monitor_Themes_Activity.php.
