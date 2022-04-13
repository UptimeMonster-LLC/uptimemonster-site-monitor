<?php
/**
 * Initialize Monitoring.
 *
 * @package RoxwpSiteMonitor
 * @version 1.0.0
 */

namespace AbsolutePlugins\RoxwpSiteMonitor;

use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

/** @define "RWP_SM_PLUGIN_PATH" "./" */

/**
 * Site Monitor.
 */
final class RoxWP_Site_Monitor {

	use Singleton;

	protected static $errorHandlerDist;

	protected static $errorHandler;

	protected static $errorHandlerData = [];

	/**
	 * Main Constructor
	 */
	protected function __construct() {

		// Check if autoloader exists, include it or show error with admin notice ui.

		// DropIns
		self::$errorHandlerDist = RWP_SM_PLUGIN_PATH . 'includes/fatal-error-handler.php.tpl';
		self::$errorHandler     = WP_CONTENT_DIR . '/fatal-error-handler.php';

		register_activation_hook( RWP_SM_PLUGIN_FILE, array( __CLASS__, 'install' ) );
		register_deactivation_hook( RWP_SM_PLUGIN_FILE, array( __CLASS__, 'uninstall' ) );

		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

		// Start monitoring activities.
		MonitorActivities::get_instance();

		// Plugin Dashboard.
		Dashboard::get_instance();
	}

	public static function install() {
		// @TODO move installation to another file.
		self::maybe_install_drop_in();

		$api_keys = get_option( 'roxwp_site_monitor_api_keys', array() );
		if ( empty( $api_keys ) ) {
			update_option( 'roxwp_need_setup', 'yes' );
		}

		if ( empty( get_option( 'roxwp_first_installed' ) ) ) {
			update_option( 'roxwp_first_installed', roxwp_get_current_time() );
		}

		update_option( 'roxwp_site_monitor_version', RWP_SM_PLUGIN_VERSION );

		do_action( 'roxwp_site_monitor_activation' );
	}

	public static function maybe_install_drop_in() {
		self::installDropIn();
	}

	public static function uninstall() {
		self::removeDropIn();
		do_action( 'roxwp_site_monitor_deactivation' );
	}

	public static function getDropInData( $installed = true ) {
		$which = $installed ? 'installed' : 'dist';
		if ( ! isset( self::$errorHandlerData[ $which ] ) ) {
			self::$errorHandlerData[ $which ] = roxwp_get_plugin_data( $installed ? self::$errorHandler : self::$errorHandlerDist );
		}

		return self::$errorHandlerData[ $which ];
	}

	public static function getDropInFile() {
		return self::$errorHandler;
	}

	public static function getDropInDistFile() {
		return self::$errorHandlerDist;
	}

	public static function isDropInInstalled() {
		$data = self::getDropInData();

		return isset( $data['Name'] ) && $data['Name'] === 'Roxwp Site Error Logger Drop-in';
	}

	/**
	 * @param bool $installed
	 *
	 * @return string
	 */
	public static function dropInVersion( $installed = true ) {
		if ( $installed ) {
			if ( ! self::isDropInInstalled() ) {
				return null;
			}

			return self::getDropInData()['Version'];
		}

		return self::getDropInData( false )['Version'];
	}

	public static function isWPContentWritable() {
		return is_writable( WP_CONTENT_DIR );
	}

	public static function isDropInWritable() {
		return is_writable( self::$errorHandler );
	}

	public static function dropInNeedUpdate() {
		if ( ! self::isDropInInstalled() ) {
			return true;
		}

		return version_compare( self::dropInVersion(), self::dropInVersion( false ), '<' );
	}

	protected static function installDropIn() {
		$oldVersion = self::dropInVersion();

		if ( self::dropInNeedUpdate() ) {
			// reset cache.
			self::$errorHandlerData = array();

			self::removeDropIn();

			$file = file_get_contents( self::$errorHandlerDist );
			$fp   = @fopen( self::$errorHandler, 'w' );
			if ( $fp ) {
				fputs( $fp, $file );
				fclose( $fp );
			}

			do_action( 'roxwp_error_logger_installed', $oldVersion, self::isDropInInstalled() );
		}
	}

	protected static function removeDropIn() {
		if ( file_exists( self::$errorHandler ) ) {
			@unlink( self::$errorHandler );
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
		$locale = determine_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'rwp-site-mon' );

		unload_textdomain( 'rwp-site-mon' );

		load_textdomain( 'rwp-site-mon', WP_LANG_DIR . '/roxwp-site-monitor/roxwp-site-monitor-' . $locale . '.mo' );
		load_plugin_textdomain( 'rwp-site-mon', false, plugin_basename( dirname( RWP_SM_PLUGIN_FILE ) ) . '/languages' );
	}
}

// End of file class-roxwp_site_monitor.php.
