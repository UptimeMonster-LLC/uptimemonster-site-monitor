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
	exit; // Exit if accessed directly
}

/**
 * Drop-in fatal-error-handler class for UptimeMonster Site Monitor
 */
#[\AllowDynamicProperties]
class UptimeMonster_Monitor_Errors extends WP_Fatal_Error_Handler {

	/**
	 * Runs the shutdown handler.
	 *
	 * This method is registered via `register_shutdown_function()`.
	 *
	 * @return void
	 */
	public function handle() {
		if ( ! defined( 'UPTIMEMONSTER_SITE_PLUGIN_VERSION' ) ) {
			parent::handle();
			return;
		}

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

			$this->send_log( $error );

			// Handle WP default Fatal Error Handler Behavior,
			// And Let WP trigger recovery mode if necessary.
			$error = $this->parent_detect_error( $error );

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
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Catch exceptions and remain silent.
		}
	}

	/**
	 * @param $error
	 *
	 * @return void
	 */
	protected function send_log( $error ) {
		if ( ! file_exists( WP_CONTENT_DIR . '/plugins/uptimemonster-site-monitor/includes/helpers.php' ) ) {
			return;
		}

		if ( ! file_exists( WP_CONTENT_DIR . '/plugins/uptimemonster-site-monitor/vendor/autoload.php' ) ) {
			return;
		}

		require_once WP_CONTENT_DIR . '/plugins/uptimemonster-site-monitor/vendor/autoload.php';
		require_once WP_CONTENT_DIR . '/plugins/uptimemonster-site-monitor/includes/helpers.php';

		$client = UptimeMonster\SiteMonitor\UptimeMonster_Client::get_instance();
		$client->send_log( [
			'action'    => 'error_log',
			'activity'  => 'WP_Error_Handler',
			'subtype'   => 'error',
			'object_id' => 0,
			'name'      => '',
			'timestamp' => uptimemonster_get_current_time(),
			'actor'     => uptimemonster_get_current_actor(),
			'error'     => $error,
			'extra'     => [
				'wp_version' => get_bloginfo( 'version' ),
				'locale'     => get_locale(),
				'timezone'   => wp_timezone()->getName(),
			],
		] );
	}

	/**
	 * Detects the error causing the crash if it should be handled.
	 *
	 * @return array|null
	 */
	protected function detect_error() {
		$error = error_get_last();

		// No error, just skip the error handling code.
		if ( null === $error ) {
			return null;
		}

		// we will detect all error.

		return $error;
	}

	/**
	 * This original WP_Fatal_Error_Handler::detect_error method as we override it in
	 * this class and also need to call it from the parent.
	 *
	 * @param $error
	 *
	 * @return array|null
	 * @see WP_Fatal_Error_Handler::detect_error
	 */
	protected function parent_detect_error( $error ) {
		// No error, just skip the error handling code.
		if ( null === $error ) {
			return null;
		}

		// Bail if this error should not be handled.
		if ( ! $this->should_handle_error( $error ) ) {
			return null;
		}

		return $error;
	}
}

return new UptimeMonster_Monitor_Errors();

// End of file fatal-error-handler.php.
