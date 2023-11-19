<?php
/**
 * Plugin Name: UptimeMonster Site Monitor
 * Plugin URI: https://github.com/Pixelaar-LLC/uptimemonster-site-monitor
 * Description: WordPress Site Activity Monitor For UptimeMonster
 * Author: UptimeMonster
 * Author URI: https://uptimemonster.com/
 * Text Domain: uptimemonster-site-monitor
 * Domain Path: /languages
 * Version: 1.0.0
 *
 * RequiresPHP: 5.6
 * RequiresWP: 5.5.0
 * Tested Upto: 6.4
 *
 * @package UptimeMonster\SiteMonitor
 */

use UptimeMonster\SiteMonitor\UptimeMonster_Site_Monitor;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

if ( ! defined( 'UPTIMEMONSTER_PLUGIN_VERSION' ) ) {
	define( 'UPTIMEMONSTER_PLUGIN_VERSION', '1.0.0' );
}

if ( ! defined( 'UPTIMEMONSTER_PLUGIN_FILE' ) ) {
	define( 'UPTIMEMONSTER_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'UPTIMEMONSTER_PLUGIN_BASENAME' ) ) {
	define( 'UPTIMEMONSTER_PLUGIN_BASENAME', plugin_basename( UPTIMEMONSTER_PLUGIN_FILE ) );
}

if ( ! defined( 'UPTIMEMONSTER_PLUGIN_PATH' ) ) {
	/** @define "UPTIMEMONSTER_PLUGIN_PATH" "./" */
	define( 'UPTIMEMONSTER_PLUGIN_PATH', plugin_dir_path( UPTIMEMONSTER_PLUGIN_FILE ) );
}

if ( ! defined( 'UPTIMEMONSTER_PLUGIN_URL' ) ) {
	define( 'UPTIMEMONSTER_PLUGIN_URL', plugin_dir_url( UPTIMEMONSTER_PLUGIN_FILE ) );
}

if ( ! file_exists( UPTIMEMONSTER_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
	/**
	 * Get Dependencies Notice.
	 *
	 * @return void
	 */
	function uptimemonster_dependency_notice() {
		$install_dir = str_replace( ABSPATH, '', dirname( UPTIMEMONSTER_PLUGIN_FILE ) );
		?>
		<div class="notice notice-warning notice-alt">
			<p>
				<?php
				printf(
				/* translators: 1. Download link for production build, 2. composer install command, 3. Plugin installation path for running composer install. */
					esc_html__( 'It seems that you have downloaded the development version of this plugin from github or other sources. Please download it from %1$s or run %2$s command within %3$s directory.', 'uptimemonster-site-monitor' ),
					'<a href="https://uptimemonster.com/downloads/uptimemonster-site-monitor.1.0.0.zip" target="_blank" rel="noopener">UptimeMonster.com</a>',
					'<code>composer install</code>',
					'<code>' . esc_html( $install_dir ) . '</code>'
				);
				?>
			</p>
		</div>
		<?php
	}
	add_action( 'admin_notices', 'uptimemonster_dependency_notice' );
	return;
}

/**
 * Declare HPOS (COT) compatibility for WooCommerce.
 *
 * @return void
 * @since 1.2.0
 */
function uptimemonster_declare_WC_HPOS_compatibility() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}

require_once UPTIMEMONSTER_PLUGIN_PATH . 'vendor/autoload.php';
require_once UPTIMEMONSTER_PLUGIN_PATH . 'includes/helpers.php';

add_action( 'before_woocommerce_init', 'uptimemonster_declare_WC_HPOS_compatibility' );

/**
 * Plugin Initializer.
 * @return UptimeMonster_Site_Monitor
 */
function uptimemonster_site_monitor() {
	return UptimeMonster_Site_Monitor::get_instance();
}

// Initialize Monitoring
uptimemonster_site_monitor();

// End of file uptimemonster-site-monitor.php.
