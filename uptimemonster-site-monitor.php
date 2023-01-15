<?php
/**
 * Plugin Name: UptimeMonster Site Monitor
 * Plugin URI: https://uptimemonster.com/
 * Description: WordPress Site Activity Monitor For UptimeMonster
 * Author: Pixelaar
 * Author URI: https://pixelaar.com/
 * Text Domain: uptime
 * Domain Path: /languages
 * Version: 1.0.0
 *
 * RequiresPHP: 5.6
 * RequiresWP: 5.5.0
 * Tested Upto: 6.1
 *
 * @package UptimeMonster\SiteMonitor
 */

use UptimeMonster\SiteMonitor\UptimeMonster_Site_Monitor;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

if ( ! defined( 'UMSM_PLUGIN_VERSION' ) ) {
	define( 'UMSM_PLUGIN_VERSION', '1.0.0' );
}

if ( ! defined( 'UMSM_PLUGIN_FILE' ) ) {
	define( 'UMSM_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'UMSM_PLUGIN_BASENAME' ) ) {
	define( 'UMSM_PLUGIN_BASENAME', plugin_basename( UMSM_PLUGIN_FILE ) );
}

if ( ! defined( 'UMSM_PLUGIN_PATH' ) ) {
	/** @define "UMSM_PLUGIN_PATH" "./" */
	define( 'UMSM_PLUGIN_PATH', plugin_dir_path( UMSM_PLUGIN_FILE ) );
}

if ( ! defined( 'UMSM_PLUGIN_URL' ) ) {
	define( 'UMSM_PLUGIN_URL', plugin_dir_url( UMSM_PLUGIN_FILE ) );
}

if ( ! file_exists( UMSM_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
	/**
	 * Get Dependencies Notice.
	 *
	 * @return void
	 */
	function umsm_dependency_notice() {
		$install_dir = str_replace( ABSPATH, '', dirname( UMSM_PLUGIN_FILE ) );
		?>
		<div class="notice notice-warning notice-alt">
			<p>
				<?php
				printf(
				/* translators: 1. Download link for production build, 2. composer install command, 3. Plugin installation path for running composer install.. */
					esc_html__( 'It seems that you have downloaded the development version of this plugin from github or other sources. Please download it from %1$s or run %2$s command within %3$s directory.', 'uptime' ),
					'<a href="https://absoluteplugins.com/wordpress-plugins/uptimemonster-site-monitor/" target="_blank" rel="noopener">UptimeMonster.com</a>',
					'<code>composer install</code>',
					'<code>' . esc_html( $install_dir ) . '</code>'
				);
				?>
			</p>
		</div>
		<?php
	}
	add_action( 'admin_notices', 'umsm_dependency_notice' );
	return;
}

require_once UMSM_PLUGIN_PATH . 'vendor/autoload.php';
require_once UMSM_PLUGIN_PATH . 'includes/helpers.php';

/**
 * @return UptimeMonster_Site_Monitor
 */
function umsm_site_monitor() {
	return UptimeMonster_Site_Monitor::get_instance();
}

// Start Monitoring
umsm_site_monitor();

// @TODO do-translation-upgrade

// End of file uptimemonster-site-monitor.php.
