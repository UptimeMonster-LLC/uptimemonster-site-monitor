<?php
/**
 * Error handler drop-in for RoxWP Site Monitor Error Monitoring Support.
 *
 * Plugin Name: Roxwp Site Error Logger Drop-in
 * Plugin URI: https://absoluteplugins.com/wordpress-plugins/roxwp-site-monitor/
 * Description: Error Logger RoxWP WordPress Site Activity Monitor Plugin.
 * Author: AbsolutePlugins
 * Author URI: https://absoluteplugins.com/
 * Version: 1.0.0
 *
 * @package RoxwpSiteMonitor
 * @version 1.0.0
 * @license GPL-3.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class RoxWP_Monitor_Errors extends WP_Fatal_Error_Handler {

	/**
	 * Runs the shutdown handler.
	 *
	 * This method is registered via `register_shutdown_function()`.
	 *
	 * @since 5.2.0
	 */
	public function handle() {
		if ( defined( 'WP_SANDBOX_SCRAPING' ) && WP_SANDBOX_SCRAPING ) {
			return;
		}

		// Do not trigger the fatal error handler while updates are being installed.
		if ( wp_is_maintenance_mode() ) {
			return;
		}

		try {
			// Bail if no error found.
			$error = $this->detect_error();

			if ( ! $error ) {
				return;
			}

			$this->send_error_log( $error );

			// Default WP Error Bail
			$error = $this->_detect_error( $error );

			if ( ! $error ) {
				return;
			}

			if ( ! isset( $GLOBALS['wp_locale'] ) && function_exists( 'load_default_textdomain' ) ) {
				load_default_textdomain();
			}

			$handled = false;

			if ( ! is_multisite() && wp_recovery_mode()->is_initialized() ) {
				$handled = wp_recovery_mode()->handle_error( $error );
			}

			// Display the PHP error template if headers not sent.
			if ( is_admin() || ! headers_sent() ) {
				$this->display_error_template( $error, $handled );
			}
		} catch ( Exception $e ) {
			// Catch exceptions and remain silent.
		}
	}

	/**
	 * Detects the error causing the crash if it should be handled.
	 *
	 * @since 5.2.0
	 *
	 * @return array|null Error that was triggered, or null if no error received or if the error should not be handled.
	 */
	protected function detect_error() {
		$error = error_get_last();

		// No error, just skip the error handling code.
		if ( null === $error ) {
			return null;
		}

		// Don't handle deprecated, it has its own monitor.
		if ( isset( $error['type'] ) && E_USER_DEPRECATED === $error['type'] ) {
			return null;
		}

		// we will detect all error.

		return $error;
	}

	protected function _detect_error( $error ) {
		if ( ! $error ) {
			return null;
		}
		// Bail if this error should not be handled.
		if ( ! $this->should_handle_error( $error ) ) {
			return null;
		}

		return $error;
	}

	protected function send_error_log( $error ) {
		if ( ! $error ) {
			return;
		}

		if ( ! function_exists( 'roxwp_get_current_actor' ) ) {
			require_once WP_CONTENT_DIR . '/plugins/roxwp-site-monitor/includes/helpers.php';
		}

		if ( ! class_exists( '\AbsolutePlugins\RoxwpSiteMonitor\RoxWP_Client', false ) ) {
			require_once WP_CONTENT_DIR . '/plugins/roxwp-site-monitor/includes/RoxWP_Client.php';
		}

		$client = \AbsolutePlugins\RoxwpSiteMonitor\RoxWP_Client::get_instance();
		$client->send_log( [
			'action'    => 'error_log',
			'activity'  => 'wp-error-handler',
			'subtype'   => 'error',
			'object_id' => null,
			'name'      => null,
			'timestamp' => roxwp_get_current_time(),
			'actor'     => roxwp_get_current_actor(),
			'extra'     => $error,
		] );
	}
}

return new RoxWP_Monitor_Errors;

// End of file fatal-error-handler.php.
