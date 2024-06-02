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
		self::$error_handler_dist = UPTIMEMONSTER_SITE_PLUGIN_PATH . 'includes/fatal-error-handler.php.tpl';
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
		// @TODO move installation to another file.
		self::maybe_install_drop_in();

		$api_keys = get_option( 'uptimemonster_api_keys', array() );
		if ( empty( $api_keys ) ) {
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

	public static function uninstall() {
		self::remove_drop_in();
		do_action( 'uptimemonster_site_monitor_deactivation' );
	}

	public static function get_drop_in_data( $installed = true ) {
		$which = $installed ? 'installed' : 'dist';
		if ( ! isset( self::$error_handler_data[ $which ] ) ) {
			self::$error_handler_data[ $which ] = uptimemonster_get_plugin_data( $installed ? self::$error_handler : self::$error_handler_dist );
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

		return isset( $data['Name'] ) && 'UptimeMonster WordPress Error Monitor' === trim( $data['Name'] );
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
		$fs = uptimemonster_get_file_systeam();
		return $fs->is_writable( WP_CONTENT_DIR );
	}

	public static function is_drop_in_writable() {
		$fs = uptimemonster_get_file_systeam();
		return $fs->is_writable( self::$error_handler );
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
			$fs = uptimemonster_get_file_systeam();
			// reset cache.
			self::$error_handler_data = array();

			self::remove_drop_in();

			$fs->put_contents( self::$error_handler, $fs->get_contents( self::$error_handler_dist ) );

			do_action( 'uptimemonster_error_logger_installed', $old_version, self::is_drop_in_installed() );
		}
	}

	protected static function remove_drop_in() {
		$fs = uptimemonster_get_file_systeam();
		if ( $fs->exists( self::$error_handler ) ) {
			$fs->delete( self::$error_handler, false, 'f' );
		}
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
