<?php
/**
 * Plugin Name: RoxWP Site Monitor
 * Plugin URI: https://absoluteplugins.com/wordpress-plugins/roxwp-site-monitor/
 * Description: WordPress Site Activity Monitor For RoxWP
 * Author: AbsolutePlugins
 * Author URI: https://absoluteplugins.com/
 * Text Domain: roxwp-site-mon
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

if ( ! defined( 'ROXWP_SM_PLUGIN_VERSION' ) ) {
	define( 'ROXWP_SM_PLUGIN_VERSION', '1.0.0' );
}

if ( ! defined( 'ROXWP_SM_PLUGIN_FILE' ) ) {
	define( 'ROXWP_SM_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'ROXWP_SM_PLUGIN_BASENAME' ) ) {
	define( 'ROXWP_SM_PLUGIN_BASENAME', plugin_basename( ROXWP_SM_PLUGIN_FILE ) );
}

if ( ! defined( 'ROXWP_SM_PLUGIN_PATH' ) ) {
	/** @define "ROXWP_SM_PLUGIN_PATH" "./" */
	define( 'ROXWP_SM_PLUGIN_PATH', plugin_dir_path( ROXWP_SM_PLUGIN_FILE ) );
}

if ( ! defined( 'ROXWP_SM_PLUGIN_URL' ) ) {
	define( 'ROXWP_SM_PLUGIN_URL', plugin_dir_url( ROXWP_SM_PLUGIN_FILE ) );
}

if ( ! file_exists( ROXWP_SM_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
	/**
	 * Get Dependencies Notice.
	 *
	 * @return void
	 */
	function roxwp_dependency_notice() {
		$install_dir = str_replace( ABSPATH, '', dirname( ROXWP_SM_PLUGIN_FILE ) );
		?>
		<div class="notice notice-warning notice-alt">
			<p>
				<?php
				printf(
				/* translators: 1. Download link for production build, 2. composer install command, 3. Plugin installation path for running composer install.. */
					esc_html__( 'It seems that you have downloaded the development version of this plugin from github or other sources. Please download it from %1$s or run %2$s command within %3$s directory.', 'roxwp-site-mon' ),
					'<a href="https://absoluteplugins.com/wordpress-plugins/roxwp-site-monitor/" target="_blank" rel="noopener">AbsolutePlugins.com</a>',
					'<code>composer install</code>',
					'<code>' . esc_html( $install_dir ) . '</code>'
				);
				?>
			</p>
		</div>
		<?php
	}
	add_action( 'admin_notices', 'roxwp_dependency_notice' );
	return;
}

require_once ROXWP_SM_PLUGIN_PATH . 'vendor/autoload.php';
require_once ROXWP_SM_PLUGIN_PATH . 'includes/helpers.php';

/**
 * @return RoxWP_Site_Monitor
 */
function roxwp_site_monitor() {
	return RoxWP_Site_Monitor::get_instance();
}

// Start Monitoring
roxwp_site_monitor();

// @TODO do-translation-upgrade

// End of file roxwp-site-monitor.php.
