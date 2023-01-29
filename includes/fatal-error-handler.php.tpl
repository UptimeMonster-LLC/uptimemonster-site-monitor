<?php
/**
 * Error handler drop-in for UptimeMonster WordPress Error Monitoring Support.
 *
 * Plugin Name: UptimeMonster WordPress Error Monitor
 * Plugin URI: https://uptimemonster.com/
 * Description: Monitor and report php (and related) errors to UptimeMonster.
 * Author: UptimeMonster
 * Author URI: https://uptimemonster.com/
 * Version: 1.0.0
 *
 * @package UptimeMonster\SiteMonitor
 * @version 1.0.0
 * @license GPL-3.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

$current_errors = [];

/**
 * Get all errors and push to $GLOBALS['ERRORS'].
 *
 * @param $type
 * @param $message
 * @param $file
 * @param $line
 *
 * @return false
 */
function umsm_error_catcher( $type, $message, $file, $line ) {
	global $current_errors;

	if ( E_USER_DEPRECATED !== $type && ! wp_is_maintenance_mode() ) {
		$error                   = [
			'type'    => $type,
			'message' => $message,
			'file'    => $file,
			'line'    => $line
		];
		$hash                    = md5( maybe_serialize( $error ) );
		$error['hash']           = $hash;
		$current_errors[ $hash ] = $error;
	}

	return false;
}

set_error_handler( 'umsm_error_catcher', E_ALL );

/**
 * Drop-in fatal-error-handler class for UptimeMonster Site Monitor
 */
#[AllowDynamicProperties]
class UptimeMonster_Monitor_Errors extends WP_Fatal_Error_Handler {

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

		//   errors.
		$this->send_log( [
			'errors' => isset( $GLOBALS['current_errors'] ) ? $GLOBALS['current_errors'] : [],
		] );

	}

	/**
	 * @param $data
	 *
	 * @return void
	 */
	protected function send_log( $data ) {

		if ( ! function_exists( 'umsm_get_current_actor' ) ) {
			require_once WP_CONTENT_DIR . '/plugins/uptimemonster-site-monitor/includes/helpers.php';
		}

		if ( ! class_exists( '\UptimeMonster\SiteMonitor\UptimeMonster_Client', false ) ) {
			require_once WP_CONTENT_DIR . '/plugins/uptimemonster-site-monitor/includes/UptimeMonster_Client.php';
		}

		$client = UptimeMonster\SiteMonitor\UptimeMonster_Client::get_instance();
		$client->send_log( [
			'action'    => 'error_log',
			'activity'  => 'WP_Error_Handler',
			'subtype'   => 'error',
			'object_id' => null,
			'name'      => null,
			'timestamp' => umsm_get_current_time(),
			'actor'     => umsm_get_current_actor(),
			'extra'     => $data,
		] );

		try {

			// Default WP Error handler.
			$error = $this->_detect_error( [] );

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
}

return new UptimeMonster_Monitor_Errors;

// End of file fatal-error-handler.php.
