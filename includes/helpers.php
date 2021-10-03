<?php
/**
 * Helper Functions
 *
 * @package Package
 * @author Name <email>
 * @version
 * @since
 * @license
 */

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

/**
 * Switch Plugin to site language.
 */
function roxwp_switch_to_site_locale() {
	if ( function_exists( 'switch_to_locale' ) ) {
		switch_to_locale( get_locale() );

		// Filter on plugin_locale so other plugin/theme can load the correct locale.
		add_filter( 'plugin_locale', 'get_locale' );

		// We don't need to reload plugin local.
	}
}

/**
 * Switch Plugin language to original.
 */
function roxwp_restore_locale() {
	if ( function_exists( 'restore_previous_locale' ) ) {
		restore_previous_locale();

		// Remove filter.
		remove_filter( 'plugin_locale', 'get_locale' );
	}
}

// End of file helpers.php.
