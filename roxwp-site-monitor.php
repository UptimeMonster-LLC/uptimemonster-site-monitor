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

if ( ! class_exists( 'AbsolutePlugins\RoxwpSiteMonitor\RoxWP_Site_Monitor', false ) ) {
	require_once RWP_SM_PLUGIN_PATH . 'class-roxwp_site_monitor.php';
}

/**
 * @return RoxWP_Site_Monitor
 */
function roxWP_site_monitor() {
	return RoxWP_Site_Monitor::get_instance();
}

// Start Monitoring
roxWP_site_monitor();

// End of file roxwp-site-monitor.php.
