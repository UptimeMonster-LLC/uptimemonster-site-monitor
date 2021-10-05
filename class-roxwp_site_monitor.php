<?php
/**
 * Initialize Monitoring.
 * @package RoxwpSiteMonitor
 * @version 1.0.0
 */

namespace AbsolutePlugins\RoxwpSiteMonitor;



use function Sodium\add;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

/** @define "RWP_SM_PLUGIN_PATH" "./../" */

/**
 * Site Monitor.
 */
final class RoxWP_Site_Monitor {

	/**
	 * Singleton instance ref.
	 *
	 * @var RoxWP_Site_Monitor
	 */
	protected static $instance;

	protected static $errorHandlerDist;

	protected static $errorHandler;

	protected static $errorHandlerData = [];

	/**
	 * Create one instance of this class, stores and return that.
	 *
	 * @return RoxWP_Site_Monitor
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Construct
	 */
	protected function __construct() {

		// Check if autoloader exists, include it or show error with admin notice ui.

		// DropIns
		self::$errorHandlerDist    = RWP_SM_PLUGIN_PATH . 'includes/fatal-error-handler.php.tpl';
		self::$errorHandler        = WP_CONTENT_DIR . '/fatal-error-handler.php';

		register_activation_hook( RWP_SM_PLUGIN_FILE, [ __CLASS__, 'install' ] );
		register_deactivation_hook( RWP_SM_PLUGIN_FILE, [ __CLASS__, 'uninstall' ] );

		add_action( 'plugins_loaded', [ $this, 'load_plugin_textdomain' ] );

		if ( file_exists( RWP_SM_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
			require_once RWP_SM_PLUGIN_PATH . 'vendor/autoload.php';

			// Start monitoring activities.
			MonitorActivities::get_instance();

			// Plugin Dashboard.
			Dashboard::get_instance();

		} else {
			add_action( 'admin_notices', [ __CLASS__, 'dependency_notice' ] );
		}
	}

	public static function install() {
		// @TODO move installation to another file.
		self::maybe_install_drop_in();

		$api_keys = get_option( 'roxwp_site_monitor_api_keys', [] );
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
		RoxWP_Site_Monitor::installDropIn();
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
		$data = RoxWP_Site_Monitor::getDropInData();

		return false !== $data && $data['Name'] === 'Roxwp Site Error Logger Drop-in';
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

			return RoxWP_Site_Monitor::getDropInData()['Version'];
		}

		return RoxWP_Site_Monitor::getDropInData( false )['Version'];
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

	public static function dependency_notice() {
		// @TODO change the download url & anchor text to wp.org after public release.

		$install_dir = str_replace( ABSPATH, '', dirname( RWP_SM_PLUGIN_FILE ) );
		?>
		<div class="notice notice-warning notice-alt">
			<p><?php printf(
					/* translators: 1. Download link for production build, 2. composer install command, 3. Plugin installation path for running composer install.. */
					esc_html__( "It seems that you have downloaded the development version of this plugin from github or other sources. Please download it from %1\$s or run %2\$s command within %3\$s directory.", 'rwp-site-mon' ),
					'<a href="https://absoluteplugins.com/wordpress-plugins/roxwp-site-monitor/" target="_blank" rel="noopener">AbsolutePlugins.com</a>',
					'<code>composer install</code>',
					'<code>' . esc_html( $install_dir ) . '</code>'
				); ?></p>
		</div>
		<?php
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'rwp-site-mon' ), '1.0.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'rwp-site-mon' ), '1.0.0' );
	}
}

// End of file class-roxwp_site_monitor.php.
