<?php

namespace AbsolutePlugins\RoxwpSiteMonitor\Api;

use AbsolutePlugins\RoxwpSiteMonitor\RoxWP_Client;
use AbsolutePlugins\RoxwpSiteMonitor\Api\RoxWP_Debug_Data;

/**
 * Class RoxWP_Health_Check
 */
class RoxWP_Health_Check {


	protected $namespace;
	protected $rest_base;
	protected $woocommerce;
	protected $version;
	public $current_user;


	public function __construct( $current_user = null)
	{
		$this->version = 'v1';
		$this->namespace = 'roxwp/' . $this->version;
		$this->rest_base = '/site-health';
		$this->current_user = $current_user;

		add_action('rest_api_init', [ $this , 'roxwp_register_routes']);
	}

	public  function roxwp_register_routes(){
		// Register debug_data route.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/debug_data',
			array(
				array(
					'methods' => \WP_REST_Server::READABLE,
					'callback' => array( $this, 'send_debug_info' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args' => array(),
				),

			)
		);

		// Register site health route.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/site_health',
			array(
				array(
					'methods' => \WP_REST_Server::READABLE,
					'callback' => array( $this, 'send_site_health_info' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args' => array(),
				),

			)
		);
	}

	/**
	 * @param $request
	 *
	 * @return void
	 */
	public function send_debug_info( $request ){

		$debug_data =   RoxWP_Debug_Data::debug_data();

		$log = [
			'action'    => 'debug_data',
			'activity'  => null,
			'subtype'   => null,
			'object_id' => null,
			'name'      => null,
			'timestamp' => roxwp_get_current_time(),
			'actor'     => roxwp_get_current_actor(),
			'extra'     => $debug_data,
		];

		RoxWP_Client::get_instance()->send_log( $log );

		do_action( 'roxwp_debug_data', $log );
	}

	/**
	 * @param $request
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function send_site_health_info( $request ){

		if ( ! class_exists( 'WP_Debug_Data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
		}
		if ( ! class_exists( 'WP_Site_Health' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		}

		$site_health =  \WP_Site_Health::get_instance();

		$tests = $site_health::get_tests();

		$results = [];

		foreach ( $tests['direct'] as $test ) {
			if ( ! empty( $test['skip_cron'] ) ) {
				continue;
			}

			if ( is_string( $test['test'] ) ) {
				$test_function = sprintf(
					'get_test_%s',
					$test['test']
				);
				$exclude_tests = [
					'get_test_wordpress_version',
					'get_test_plugin_version',
					'get_test_theme_version',
					'get_test_php_version',
					'get_test_plugin_theme_auto_updates',
					'detect_plugin_theme_auto_update_issues',
				];
				if( in_array( $test_function, $exclude_tests  ) ) {
					continue;
				}

				if ( method_exists( $site_health, $test_function ) && is_callable( array( $site_health, $test_function ) ) ) {
					$results[] = $this->perform_test( array( $site_health, $test_function ) );
					continue;
				}
			}

			if ( is_callable( $test['test'] ) ) {
				$results[] = $this->perform_test( $test['test'] );
			}
		}

		foreach ( $tests['async'] as $test ) {
			if ( ! empty( $test['skip_cron'] ) ) {
				continue;
			}

			// Local endpoints may require authentication, so asynchronous tests can pass a direct test runner as well.
			if ( ! empty( $test['async_direct_test'] ) && is_callable( $test['async_direct_test'] ) ) {
				// This test is callable, do so and continue to the next asynchronous check.
				$results[] = $this->perform_test( $test['async_direct_test'] );
				continue;
			}

			if ( is_string( $test['test'] ) ) {
				// Check if this test has a REST API endpoint.
				if ( isset( $test['has_rest'] ) && $test['has_rest'] ) {
					$result_fetch = wp_remote_get(
						$test['test'],
						array(
							'body' => array(
								'_wpnonce' => wp_create_nonce( 'wp_rest' ),
							),
						)
					);
				} else {
					$result_fetch = wp_remote_post(
						admin_url( 'admin-ajax.php' ),
						array(
							'body' => array(
								'action'   => $test['test'],
								'_wpnonce' => wp_create_nonce( 'Api-site-status' ),
							),
						)
					);
				}

				if ( ! is_wp_error( $result_fetch ) && 200 === wp_remote_retrieve_response_code( $result_fetch ) ) {
					$result = json_decode( wp_remote_retrieve_body( $result_fetch ), true );
				} else {
					$result = false;
				}

				if ( is_array( $result ) ) {
					$results[] = $result;
				} else {
					$results[] = array(
						'status' => 'recommended',
						'label'  => __( 'A test is unavailable' ),
					);
				}
			}
		}


		$log = [
			'action'    => 'debug_data',
			'activity'  => null,
			'subtype'   => null,
			'object_id' => null,
			'name'      => null,
			'timestamp' => roxwp_get_current_time(),
			'actor'     => roxwp_get_current_actor(),
			'extra'     => $results,
		];

		RoxWP_Client::get_instance()->send_log( $log );

		do_action( 'roxwp_site_health_info', $log );

	}

	/**
	 * @param $callback
	 *
	 * @return mixed|void
	 */
	private function perform_test( $callback ){

		return apply_filters( 'site_status_test_result', call_user_func( $callback ) );
	}



	/*
     * Get route access if request is valid.
     */
	public function get_route_access()
	{
		$has_access = RoxWP_Client::get_instance()->has_keys();

		if(  $has_access ){

			return true;
		}
	}


}
