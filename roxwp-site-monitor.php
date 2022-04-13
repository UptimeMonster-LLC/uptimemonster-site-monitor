<?php
/**
 * Plugin Name: Roxwp Site Monitor
 * Plugin URI: https://absoluteplugins.com/wordpress-plugins/roxwp-site-monitor/
 * Description: WordPress Site Activity Monitor For RoxWP
 * Author: AbsolutePlugins
 * Author URI: https://absoluteplugins.com/
 * Text Domain: rwp-site-mon
 * Domain Path: /languages
 * Version: 1.0.0
 *
 * RequiresPHP: 5.6
 * RequiresWP: 5.5.0
 * Tested Upto: 5.8
 *
 * @package Roxwp_Site_Monitor
 */

use AbsolutePlugins\RoxwpSiteMonitor\RoxWP_Site_Monitor;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

if ( ! defined( 'RWP_SM_PLUGIN_VERSION' ) ) {
	define( 'RWP_SM_PLUGIN_VERSION', '1.0.0' );
}

if ( ! defined( 'RWP_SM_PLUGIN_FILE' ) ) {
	define( 'RWP_SM_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'RWP_SM_PLUGIN_BASENAME' ) ) {
	define( 'RWP_SM_PLUGIN_BASENAME', plugin_basename( RWP_SM_PLUGIN_FILE ) );
}

if ( ! defined( 'RWP_SM_PLUGIN_PATH' ) ) {
	/** @define "RWP_SM_PLUGIN_PATH" "./" */
	define( 'RWP_SM_PLUGIN_PATH', plugin_dir_path( RWP_SM_PLUGIN_FILE ) );
}

if ( ! defined( 'RWP_SM_PLUGIN_URL' ) ) {
	define( 'RWP_SM_PLUGIN_URL', plugin_dir_url( RWP_SM_PLUGIN_FILE ) );
}

if ( ! file_exists( RWP_SM_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
	function roxWP_dependency_notice() {

		$install_dir = str_replace( ABSPATH, '', dirname( RWP_SM_PLUGIN_FILE ) );
		?>
		<div class="notice notice-warning notice-alt">
			<p>
				<?php
				printf(
				/* translators: 1. Download link for production build, 2. composer install command, 3. Plugin installation path for running composer install.. */
					esc_html__( 'It seems that you have downloaded the development version of this plugin from github or other sources. Please download it from %1$s or run %2$s command within %3$s directory.', 'rwp-site-mon' ),
					'<a href="https://absoluteplugins.com/wordpress-plugins/roxwp-site-monitor/" target="_blank" rel="noopener">AbsolutePlugins.com</a>',
					'<code>composer install</code>',
					'<code>' . esc_html( $install_dir ) . '</code>'
				);
				?>
			</p>
		</div>
		<?php
	}
	add_action( 'admin_notices', 'roxWP_dependency_notice' );
	return;
}

require_once RWP_SM_PLUGIN_PATH . 'vendor/autoload.php';



require_once RWP_SM_PLUGIN_PATH . 'includes/helpers.php';

/**
 * @return RoxWP_Site_Monitor
 */
function roxWP_site_monitor() {
	return RoxWP_Site_Monitor::get_instance();
}

// Start Monitoring
roxWP_site_monitor();

// @TODO do-translation-upgrade

// End of file roxwp-site-monitor.php.
