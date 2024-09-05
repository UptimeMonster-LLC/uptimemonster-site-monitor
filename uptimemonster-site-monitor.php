<?php
/**
 * Plugin Name: UptimeMonster Site Monitor
 * Plugin URI: https://github.com/Pixelaar-LLC/uptimemonster-site-monitor
 * Description: WordPress Site Activity Monitor For UptimeMonster
 * Author: UptimeMonster
 * Author URI: https://uptimemonster.com/
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html#license-text
 * Text Domain: uptimemonster-site-monitor
 * Domain Path: /languages
 * Version: 1.0.0
 *
 * Requires PHP: 7.0
 *
 * Requires at least: 5.6
 * Tested Upto: 6.6
 *
 * WC requires at least: 5.6
 * WC tested up to: 9.1
 *
 * @package UptimeMonster\SiteMonitor
 *
 * Copyright (C) 2024 UptimeMonster
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 */

use UptimeMonster\SiteMonitor\UptimeMonster_Site_Monitor;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

if ( ! defined( 'UPTIMEMONSTER_SITE_PLUGIN_VERSION' ) ) {
	define( 'UPTIMEMONSTER_SITE_PLUGIN_VERSION', '1.0.0' );
}

if ( ! defined( 'UPTIMEMONSTER_SITE_PLUGIN_FILE' ) ) {
	define( 'UPTIMEMONSTER_SITE_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'UPTIMEMONSTER_SITE_PLUGIN_BASENAME' ) ) {
	define( 'UPTIMEMONSTER_SITE_PLUGIN_BASENAME', plugin_basename( UPTIMEMONSTER_SITE_PLUGIN_FILE ) );
}

if ( ! defined( 'UPTIMEMONSTER_SITE_PLUGIN_PATH' ) ) {
	/** @define "UPTIMEMONSTER_SITE_PLUGIN_PATH" "./" */
	define( 'UPTIMEMONSTER_SITE_PLUGIN_PATH', plugin_dir_path( UPTIMEMONSTER_SITE_PLUGIN_FILE ) );
}

if ( ! defined( 'UPTIMEMONSTER_SITE_PLUGIN_URL' ) ) {
	define( 'UPTIMEMONSTER_SITE_PLUGIN_URL', plugin_dir_url( UPTIMEMONSTER_SITE_PLUGIN_FILE ) );
}

if ( ! file_exists( UPTIMEMONSTER_SITE_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
	/**
	 * Get Dependencies Notice.
	 *
	 * @return void
	 */
	function uptimemonster_dependency_notice() {
		$install_dir = str_replace( ABSPATH, '', dirname( UPTIMEMONSTER_SITE_PLUGIN_FILE ) );
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
 * @since 1.0.0
 */
function uptimemonster_declare_wc_hpos_compatibility() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}

require_once UPTIMEMONSTER_SITE_PLUGIN_PATH . 'vendor/autoload.php';
require_once UPTIMEMONSTER_SITE_PLUGIN_PATH . 'includes/helpers.php';

add_action( 'before_woocommerce_init', 'uptimemonster_declare_wc_hpos_compatibility' );

/**
 * Plugin Initializer.
 *
 * @return UptimeMonster_Site_Monitor
 */
function uptimemonster_site_monitor(): UptimeMonster_Site_Monitor {
	return UptimeMonster_Site_Monitor::get_instance();
}

// Initialize Monitoring
uptimemonster_site_monitor();

// End of file uptimemonster-site-monitor.php.
