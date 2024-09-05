<?php
/**
 * Update Checker
 *
 * @package UptimeMonster\SiteMonitor\API
 * @version 1.0.0
 */

namespace UptimeMonster\SiteMonitor\Site_Health;

use WP_Site_Health;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

if ( ! class_exists( '\WP_Site_Health' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
}

class Site_Health {

	/**
	 * @return array<string, array>
	 */
	public static function get_reports(): array {
		$site_health = WP_Site_Health::get_instance();
		$tests       = WP_Site_Health::get_tests();
		$results     = [];

		// Don't run https test on development environments.
		if ( $site_health->is_development_environment() ) {
			unset( $tests['async']['https_status'] );
		}

		foreach ( $tests['direct'] as $key => $test ) {
			$result = false;
			if ( is_string( $test['test'] ) ) {
				$test_function = sprintf( 'get_test_%s', $test['test'] );

				if ( method_exists( $site_health, $test_function ) && is_callable( [ $site_health, $test_function ] ) ) {
					$results[ $key ] = self::perform_test( [ $site_health, $test_function ] );
					continue;
				}
			}

			if ( is_callable( $test['test'] ) ) {
				$results[ $key ] = self::perform_test( $test['test'] );
			}
		}

		foreach ( $tests['async'] as $key => $test ) {
			// Local endpoints may require authentication, so asynchronous tests can pass a direct test runner as well.
			if ( ! empty( $test['async_direct_test'] ) && is_callable( $test['async_direct_test'] ) ) {
				// This test is callable, do so and continue to the next asynchronous check.
				$results[ $key ] = self::perform_test( $test['async_direct_test'] );
				continue;
			}

			if ( is_string( $test['test'] ) ) {
				// Check if this test has a REST API endpoint.
				if ( isset( $test['has_rest'] ) && $test['has_rest'] ) {
					if ( function_exists( 'vip_safe_wp_remote_get' ) ) {
						$result_fetch = vip_safe_wp_remote_get( $test['test'],
							[
								'body' => [
									'_wpnonce' => wp_create_nonce( 'wp_rest' ),
								],
							] );
					} else {
						$result_fetch = wp_remote_get( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
							$test['test'],
							[
								'body' => [
									'_wpnonce' => wp_create_nonce( 'wp_rest' ),
								],
							]
						);
					}
				} else {
					$result_fetch = wp_remote_post(
						admin_url( 'admin-ajax.php' ),
						[
							'body' => [
								'action'   => $test['test'],
								'_wpnonce' => wp_create_nonce( 'health-check-site-status' ),
							],
						]
					);
				}

				if ( ! is_wp_error( $result_fetch ) && 200 === wp_remote_retrieve_response_code( $result_fetch ) ) {
					$result = json_decode( wp_remote_retrieve_body( $result_fetch ), true );
				} else {
					$result = false;
				}

				if ( is_array( $result ) ) {
					$results[ $key ] = $result;
				} else {
					$results[ $key ] = array(
						'status' => 'recommended',
						'label'  => sprintf(
							/* translators: %s WP-Health test item label. */
							esc_html__( 'Unable to perform “%s” test', 'uptimemonster-site-monitor' ),
							esc_html( $test['label'] )
						),
					);
				}
			}
		}

		return [
			'version' => '1.0.0',
			'data'    => $results,
		];
	}

	/**
	 * @param $callback
	 *
	 * @return mixed
	 */
	private static function perform_test( $callback ) {
		// Core triggers site_status_test_result filter hook on output of test callback.
		return call_user_func( $callback );
	}
}

// End of file Site_Health.php.
