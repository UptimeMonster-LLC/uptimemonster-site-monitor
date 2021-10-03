<?php
/**
 * Initialize Monitoring.
 * @package RoxwpSiteMonitor
 * @version 1.0.0
 */

namespace AbsolutePlugins\RoxwpSiteMonitor;



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

		register_activation_hook( RWP_SM_PLUGIN_FILE, [ __CLASS__, 'install' ] );
		register_deactivation_hook( RWP_SM_PLUGIN_FILE, [ __CLASS__, 'uninstall' ] );

		add_action( 'plugins_loaded', [ $this, 'load_plugin_textdomain' ] );

		if ( file_exists( RWP_SM_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
			require_once RWP_SM_PLUGIN_PATH . 'vendor/autoload.php';

			MonitorActivities::get_instance();
			Dashboard::get_instance();

		} else {
			add_action( 'admin_notices', [ __CLASS__, 'dependency_notice' ] );
		}
	}

	public static function install() {
		do_action( 'roxwp_site_monitor_activation' );

		wp_safe_redirect( Dashboard::get_instance()->get_page_url() );
		die();
	}

	public static function uninstall() {
		do_action( 'roxwp_site_monitor_deactivation' );
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
