<?php
/**
 * Data Monitor Base
 *
 * @package UptimeMonster\SiteMonitor\Monitors
 * @version 1.0.0
 * @since 1.0.0
 */

namespace UptimeMonster\SiteMonitor\Monitors;

use UptimeMonster\SiteMonitor\Traits\Singleton;
use Exception;
use Theme_Upgrader;
use WP_Customize_Manager;
use WP_Theme;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

class Monitor_Themes_Activity extends Activity_Monitor_Base {

	use Singleton;

	protected $check_maybe_log = false;

	/**
	 * Theme temp. data.
	 *
	 * @var array
	 */
	protected $theme = [];

	public function init() {
		add_action( 'switch_theme', [ $this, 'on_theme_change' ], 10, 3 );

		if ( version_compare( get_bloginfo( 'version' ), '5.8', '>=' ) ) {
			add_action( 'delete_theme', [ $this, 'on_before_delete' ] );
			add_action( 'deleted_theme', [ $this, 'on_theme_deleted' ], 10, 2 );
		} else {
			add_action( 'delete_site_transient_update_themes', [ $this, 'trace_on_theme_deleted' ] );
		}

		add_action( 'update_site_option_allowedthemes', [ $this, 'trace_on_disable_theme' ] );

		add_action( 'upgrader_process_complete', [ $this, 'on_theme_install_or_update' ], 10, 2 );

		// Theme customizer
		add_action( 'customize_save', [ $this, 'on_theme_customized' ] );

		// Theme Editor Actions.
		add_action( 'wp_ajax_edit-theme-plugin-file', [ $this, 'on_theme_file_modify' ], - 1 );
		add_filter( 'wp_redirect', [ $this, 'on_theme_file_modify' ], - 1 );
	}

	protected function maybe_log_theme( $action, $theme, $file = null ) {

		/**
		 * Should report activity?
		 *
		 * @param bool $status
		 * @param string $plugin
		 * @param string $action
		 * @param string $file file path if one is being modified.
		 */
		return (bool) apply_filters( 'uptimemonster_should_log_plugins_activity', true, $theme, $action, $file );
	}

	public function on_theme_change( $new_name, WP_Theme $new_theme, WP_Theme $old_theme ) {
		if ( ! $this->maybe_log_theme( Activity_Monitor_Base::ITEM_ACTIVATED, $new_theme->get_stylesheet() ) ) {
			return;
		}

		uptimemonster_switch_to_english();
		/* translators: %1$s New Theme Name, %2$s: Old Theme Name */
		$name = sprintf( esc_html__( 'Switched to %1$s theme from %2$s', 'uptimemonster-site-monitor' ),
			$new_theme->get( 'Name' ),
			$old_theme->get( 'Name' )
		);
		uptimemonster_restore_locale();

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
				],
			]
		);
	}

	public function on_before_delete( $stylesheet ) {
		if ( ! $this->maybe_log_theme( Activity_Monitor_Base::ITEM_DELETED, $stylesheet ) ) {
			return;
		}

		$hash = md5( $stylesheet );

		$data = $this->get_theme_data( $stylesheet );

		set_transient( 'uptimemonster_theme_data_' . $hash, $data, 5 * MINUTE_IN_SECONDS );
	}

	public function on_theme_deleted( $stylesheet, $deleted ) {
		if ( ! $deleted || ! $this->maybe_log_theme( Activity_Monitor_Base::ITEM_DELETED, $stylesheet ) ) {
			return;
		}

		$hash = md5( $stylesheet );

		$data = get_transient( 'uptimemonster_theme_data_' . $hash );

		if ( $data ) {
			$this->theme[ $hash ] = $data;
		}

		delete_transient( 'uptimemonster_theme_data_' . $hash );

		$data = $this->get_theme_data( $stylesheet );
		$data = empty( $data ) ? [] : $data;

		$this->log_activity(
			Activity_Monitor_Base::ITEM_DELETED,
			0,
			$stylesheet,
			$this->get_name( $stylesheet ),
			$data
		);
	}

	/**
	 * Back compact.
	 * Action hooks are introduced in WP 5.8
	 *
	 * @see delete_theme()
	 */
	public function trace_on_theme_deleted() {
		$backtrace_history = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace

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
		$backtrace_history = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace

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
			$this->get_theme_data( $stylesheet )
		);
	}

	/**
	 * @param Theme_Upgrader $upgrader
	 * @param array $extra
	 *
	 * @throws Exception
	 */
	public function on_theme_install_or_update( $upgrader, $extra ) {
		if ( ! isset( $extra['type'] ) || 'theme' !== $extra['type'] ) {
			return;
		}

		if ( isset( $extra['action'] ) && 'install' === $extra['action'] ) {
			$theme_info = $upgrader->theme_info();
			if ( $theme_info ) {
				$slug = $theme_info->get_stylesheet();
				wp_clean_themes_cache();

				if ( ! $this->maybe_log_theme( Activity_Monitor_Base::ITEM_INSTALLED, $slug ) ) {
					return;
				}

				$this->log_activity(
					Activity_Monitor_Base::ITEM_INSTALLED,
					0,
					$slug,
					$this->get_name( $slug ),
					$this->get_theme_data( $slug )
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
					$this->get_name( (string) $slug ),
					$this->get_theme_data( $slug )
				);
			}
		}
	}

	public function on_theme_customized( WP_Customize_Manager $customize_manager ) {
		$slug = $customize_manager->get_stylesheet();

		if ( ! $this->maybe_log_theme( Activity_Monitor_Base::ITEM_UPDATED, $slug . '/customizer' ) ) {
			return;
		}

		$data = uptimemonster_get_theme_data_headers( $customize_manager->theme() );

		$data['customizer'] = true;

		$this->log_activity( Activity_Monitor_Base::ITEM_UPDATED, 0, $slug, $customize_manager->theme()->get( 'Name' ), $data );
	}

	/**
	 * Hooked into plugin file edit ajax action
	 *
	 * @throws Exception
	 * @see wp_edit_theme_plugin_file()
	 */
	public function on_theme_file_modify( $location = null ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		// phpcs:enable
		if (
			! empty( $_POST['action'] ) &&
			! empty( $_POST['nonce'] ) &&
			(
				( 'wp_redirect' === current_filter() && $location && false !== strpos( $location, 'theme-editor.php' ) && 'update' === $_REQUEST['action'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
				||
				( 'edit-theme-plugin-file' === $_POST['action'] && ! empty( $_POST['theme'] ) && ! empty( $_POST['file'] ) )
			)
		) {
			$stylesheet = sanitize_text_field( wp_unslash( $_POST['theme'] ) );
			$file       = sanitize_text_field( wp_unslash( $_POST['file'] ) );
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'edit-theme_' . $stylesheet . '_' . $file ) ) {
				$_file = WP_PLUGIN_DIR . $stylesheet;
				if ( $this->maybe_log_theme( Activity_Monitor_Base::ITEM_UPDATED, $stylesheet, $file ) && file_exists( $_file ) ) {
					uptimemonster_switch_to_english();
					/* translators: %1$s. Theme Name, %2$s. File path. */
					$name = esc_html__( 'Modified file (%2$s) of “%1$s” theme', 'uptimemonster-site-monitor' );
					uptimemonster_restore_locale();

					$this->log_activity(
						Activity_Monitor_Base::ITEM_UPDATED,
						0,
						$stylesheet,
						sprintf( $name, $this->get_name( $stylesheet ), $file ),
						[
							'slug' => $stylesheet,
							'file' => $file,
						]
					);
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

		if ( ! isset( $this->theme[ $hash ] ) ) {
			if ( ! function_exists( 'wp_get_theme' ) ) {
				require_once ABSPATH . 'wp-includes/theme.php';
			}

			$this->theme[ $hash ] = uptimemonster_get_theme_data_headers( wp_get_theme( $theme ) );
		}

		if ( $header ) {
			return isset( $this->theme[ $hash ][ $header ] ) ? $this->theme[ $hash ][ $header ] : null;
		}

		return $this->theme[ $hash ];
	}
}

// End of file Monitor_Themes_Activity.php.
