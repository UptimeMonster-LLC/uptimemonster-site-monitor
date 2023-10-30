<?php
/**
 * Initialize Monitoring.
 *
 * @package UptimeMonster\SiteMonitor
 * @version 1.0.0
 */

namespace UptimeMonster\SiteMonitor;

use UptimeMonster\SiteMonitor\Monitors\Singleton;
use UptimeMonster\SiteMonitor\Api\Server;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

/** @define "UMSM_PLUGIN_PATH" "./" */

/**
 * Site Monitor.
 */
final class UptimeMonster_Site_Monitor {

	use Singleton;

	protected static $error_handler_dist;

	protected static $error_handler;

	protected static $error_handler_data = [];

	/**
	 * Main Constructor
	 */
	protected function __construct() {

		// Check if autoloader exists, include it or show error with admin notice ui.

		// DropIns
		self::$error_handler_dist = UMSM_PLUGIN_PATH . 'includes/fatal-error-handler.php.tpl'; // @phpstan-ignore-line
		self::$error_handler      = WP_CONTENT_DIR . '/fatal-error-handler.php'; // @phpstan-ignore-line

		register_activation_hook( UMSM_PLUGIN_FILE, array( __CLASS__, 'install' ) );
		register_deactivation_hook( UMSM_PLUGIN_FILE, array( __CLASS__, 'uninstall' ) );

		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

		// Start monitoring activities.
		MonitorActivities::get_instance();

		// Plugin Dashboard.
		Dashboard::get_instance();

		// Site monitor rest api.
		add_action('init', function () {
			$server = Server::get_instance();
			$server->init();
		} );
	}

	public static function install() {
		// @TODO move installation to another file.
		self::maybe_install_drop_in();

		$api_keys = get_option( 'umsm_site_monitor_api_keys', array() );
		if ( empty( $api_keys ) ) {
			update_option( 'umsm_need_setup', 'yes' );
		}

		if ( empty( get_option( 'umsm_first_installed' ) ) ) {
			update_option( 'umsm_first_installed', umsm_get_current_time() );
		}

		update_option( 'umsm_site_monitor_version', UMSM_PLUGIN_VERSION );

		do_action( 'umsm_site_monitor_activation' );
	}

	public static function maybe_install_drop_in() {
		self::install_drop_in();
	}

	public static function uninstall() {
		self::remove_drop_in();
		do_action( 'umsm_site_monitor_deactivation' );
	}

	public static function get_drop_in_data( $installed = true ) {
		$which = $installed ? 'installed' : 'dist';
		if ( ! isset( self::$error_handler_data[ $which ] ) ) {
			self::$error_handler_data[ $which ] = umsm_get_plugin_data( $installed ? self::$error_handler : self::$error_handler_dist );
		}

		return self::$error_handler_data[ $which ];
	}

	public static function get_drop_in_file() {
		return self::$error_handler;
	}

	public static function get_drop_in_dist_file() {
		return self::$error_handler_dist;
	}

	public static function is_drop_in_installed() {
		$data = self::get_drop_in_data();

		return isset( $data['Name'] ) && 'UptimeMonster WordPress Error Monitor ' === $data['Name'];
	}

	/**
	 * @param bool $installed
	 *
	 * @return string
	 */
	public static function drop_in_version( $installed = true ) {
		if ( $installed && self::is_drop_in_installed() ) {
			return self::get_drop_in_data()['Version'];
		}

		return self::get_drop_in_data( false )['Version'];
	}

	public static function is_wp_content_writable() {
		return is_writable( WP_CONTENT_DIR ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_is_writable @phpstan-ignore-line,WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_is_writable
	}

	public static function is_drop_in_writable() {
		return is_writable( self::$error_handler ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_is_writable
	}

	public static function drop_in_need_update() {
		if ( ! self::is_drop_in_installed() ) {
			return true;
		}

		return version_compare( self::drop_in_version(), self::drop_in_version( false ), '<' );
	}

	protected static function install_drop_in() {
		$old_version = self::drop_in_version();

		if ( self::drop_in_need_update() ) {
			// reset cache.
			self::$error_handler_data = array();

			self::remove_drop_in();

			$file = file_get_contents( self::$error_handler_dist ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
			$fp   = @fopen( self::$error_handler, 'w' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_read_fopen
			if ( $fp ) {
				fputs( $fp, (string) $file ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputs
				fclose( $fp );
			}

			do_action( 'umsm_error_logger_installed', $old_version, self::is_drop_in_installed() );
		}
	}

	protected static function remove_drop_in() {
		if ( file_exists( self::$error_handler ) ) {
			@unlink( self::$error_handler ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink,WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
	 *
	 * Locales found in:
	 *      - WP_LANG_DIR/woocommerce/woocommerce-LOCALE.mo
	 *      - WP_LANG_DIR/plugins/woocommerce-LOCALE.mo
	 */
	public function load_plugin_textdomain() {

		if ( ! function_exists('determine_locale') ) {
			require_once ABSPATH . 'wp-includes/l10n.php';
		}

		$locale = determine_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'uptimemonster-site-monitor' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		unload_textdomain( 'uptimemonster-site-monitor' );

		load_textdomain( 'uptimemonster-site-monitor', WP_LANG_DIR . '/uptimemonster-site-monitor/uptimemonster-site-monitor-' . $locale . '.mo' ); // @phpstan-ignore-line
		load_plugin_textdomain( 'uptimemonster-site-monitor', false, plugin_basename( dirname( UMSM_PLUGIN_FILE ) ) . '/languages' );
	}
}

// End of file class-umsm_site_monitor.php.
