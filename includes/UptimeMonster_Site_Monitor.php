<?php
/**
 * Initialize Monitoring.
 *
 * @package UptimeMonster\SiteMonitor
 * @version 1.0.0
 */

namespace UptimeMonster\SiteMonitor;

use UptimeMonster\SiteMonitor\Traits\Singleton;
use UptimeMonster\SiteMonitor\Api\Server;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

/** @define "UPTIMEMONSTER_SITE_PLUGIN_PATH" "./" */

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
		self::$error_handler_dist = UPTIMEMONSTER_SITE_PLUGIN_PATH . 'includes/fatal-error-handler.php';
		self::$error_handler      = WP_CONTENT_DIR . '/fatal-error-handler.php';

		register_activation_hook( UPTIMEMONSTER_SITE_PLUGIN_FILE, array( __CLASS__, 'install' ) );
		register_deactivation_hook( UPTIMEMONSTER_SITE_PLUGIN_FILE, array( __CLASS__, 'uninstall' ) );

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
		// @XXX move installation to another file.
		self::maybe_install_drop_in();

		// Update keys from old pre-release versions.
		$old_opt_check = get_option( 'umsm_site_monitor_api_keys' );
		if ( ! $old_opt_check ) {
			$old_opt_check = get_option( 'roxwp_site_monitor_api_keys' );
		}

		if ( $old_opt_check ) {
			delete_option( 'umsm_site_monitor_api_keys' );
			delete_option( 'roxwp_site_monitor_api_keys' );
			update_option( 'uptimemonster_site_monitor_api_keys', $old_opt_check );
		}

		$api_keys = get_option( 'uptimemonster_site_monitor_api_keys', array() );
		if ( empty( $api_keys ) || ! isset( $api_keys['api_key'], $api_keys['api_secret'] ) ) {
			update_option( 'uptimemonster_need_setup', 'yes' );
		}

		if ( empty( get_option( 'uptimemonster_first_installed' ) ) ) {
			update_option( 'uptimemonster_first_installed', uptimemonster_get_current_time() );
		}

		update_option( 'uptimemonster_site_monitor_version', UPTIMEMONSTER_SITE_PLUGIN_VERSION );

		do_action( 'uptimemonster_site_monitor_activation' );
	}

	public static function maybe_install_drop_in() {
		self::install_drop_in();
	}

	public static function uninstall(): bool {
		$old_version = self::drop_in_version();
		$removed     = self::remove_drop_in();
		do_action( 'uptimemonster_error_logger_uninstalled', $removed, $old_version );
		return $removed;
	}

	public static function get_drop_in_data( $installed = true ) {
		$which = $installed ? 'installed' : 'dist';
		if ( ! isset( self::$error_handler_data[ $which ] ) ) {
			self::$error_handler_data[ $which ] = uptimemonster_get_plugin_data( $installed ? self::$error_handler : self::$error_handler_dist );
		}

		return self::$error_handler_data[ $which ];
	}

	public static function get_drop_in_file(): string {
		return self::$error_handler;
	}

	public static function get_drop_in_dist_file(): string {
		return self::$error_handler_dist;
	}

	public static function is_drop_in_installed(): bool {
		$data = self::get_drop_in_data();

		return isset( $data['Name'] ) && 'UptimeMonster WordPress Error Monitor' === trim( $data['Name'] );
	}

	/**
	 * @param bool $installed
	 *
	 * @return string|false
	 */
	public static function drop_in_version( bool $installed = true ) {
		$data = self::get_drop_in_data( $installed );
		return ! empty( $data['Version'] ) ? $data['Version'] : false;
	}

	public static function is_wp_content_writable(): bool {
		$fs = uptimemonster_get_file_systeam();
		return $fs->is_writable( WP_CONTENT_DIR );
	}

	public static function is_drop_in_writable(): bool {
		$fs = uptimemonster_get_file_systeam();
		return $fs->is_writable( self::$error_handler );
	}

	public static function drop_in_need_update() {
		if ( ! self::is_drop_in_installed() ) {
			return true;
		}

		return version_compare( self::drop_in_version(), self::drop_in_version( false ), '<' );
	}

	protected static function install_drop_in(): bool {
		if ( self::drop_in_need_update() ) {
			$old_version = self::drop_in_version();
			$fs          = uptimemonster_get_file_systeam();
			// Reset cache.
			self::$error_handler_data = array();

			self::remove_drop_in();

			$status = $fs->put_contents( self::$error_handler, $fs->get_contents( self::$error_handler_dist ) );

			do_action( 'uptimemonster_error_logger_installed', $status, $old_version );

			return $status;
		}

		return self::is_drop_in_installed();
	}

	protected static function remove_drop_in(): bool {
		$fs = uptimemonster_get_file_systeam();
		if ( $fs->exists( self::$error_handler ) ) {
			return $fs->delete( self::$error_handler, false, 'f' );
		}

		return true;
	}

	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
	 *
	 * Locales found in:
	 *      - WP_LANG_DIR/uptimemonster-site-monitor/uptimemonster-site-monitor-LOCALE.mo
	 *      - WP_LANG_DIR/plugins/uptimemonster-site-monitor-LOCALE.mo
	 */
	public function load_plugin_textdomain() {
		if ( ! function_exists('determine_locale') ) {
			require_once ABSPATH . 'wp-includes/l10n.php';
		}

		$locale = determine_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'uptimemonster-site-monitor' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		unload_textdomain( 'uptimemonster-site-monitor' );

		load_textdomain( 'uptimemonster-site-monitor', WP_LANG_DIR . '/uptimemonster-site-monitor/uptimemonster-site-monitor-' . $locale . '.mo' );
		load_plugin_textdomain( 'uptimemonster-site-monitor', false, plugin_basename( dirname( UPTIMEMONSTER_SITE_PLUGIN_FILE ) ) . '/languages' );
	}
}

// End of file class-uptimemonster_site_monitor.php.
