<?php

namespace AbsolutePlugins\RoxwpSiteMonitor\Api;

use AbsolutePlugins\RoxwpSiteMonitor\RoxWP_Client;
use AbsolutePlugins\RoxwpSiteMonitor\Api\RoxWP_Debug_Data;
use AbsolutePlugins\RoxwpSiteMonitor\Api\RoxWP_Update_Check;

/**
 * Class RoxWP_Health_Check_Api
 */
class RoxWP_Health_Check_Api {


	protected $namespace;
	protected $rest_base;
	protected $woocommerce;
	protected $version;
	public $current_user;


	public function __construct( $current_user = null ) {
		$this->version      = 'v1';
		$this->namespace    = 'roxwp/' . $this->version;
		$this->rest_base    = '/site-health';
		$this->current_user = $current_user;

		add_action( 'rest_api_init', [ $this, 'roxwp_register_routes' ] );
	}

	public function roxwp_register_routes() {
		// Register site health route.
		register_rest_route(
			$this->namespace,
			$this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'send_site_health_info' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),
			)
		);

		// Register site debug dta route.
		register_rest_route(
			$this->namespace,
			'site-debug-data',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'send_debug_info' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),

			)
		);

		// Register ping route.
		register_rest_route(
			$this->namespace,
			'ping',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_ping' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),

			)
		);
	}

	public function get_ping() {

		return rest_ensure_response( 'ok' );
	}


	/**
	 * @param $request
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function send_debug_info( $request ) {

		$debug_data = new RoxWP_Debug_Data();
		$debug_info = $debug_data->debug_data();

		$response['status'] = true;
		$response['data']   = $debug_info;

		return rest_ensure_response( $response );

	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function send_site_health_info( $request ) {

		$update_check = new RoxWP_Update_Check();

		return rest_ensure_response( [
			'status' => true,
			'data' => $update_check->get_site_health(),
		] );
	}


	/**
	 * Get route access if request is valid.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|boolean
	 */
	public function get_route_access( $request ) {

		$api_keys        = get_option( 'roxwp_site_monitor_api_keys', [] );
		$request_api_key = $request->get_header( 'X-Api-Key' ) ? $request->get_header( 'X-Api-Key' ) : '';
		$signature       = $request->get_header( 'X-Api-Signature' ) ? $request->get_header( 'X-Api-Signature' ) : '';
		$timestamp       = $request->get_header( 'X-Api-Timestamp' ) ? $request->get_header( 'X-Api-Timestamp' ) : '';
		$method          = strtolower( $request->get_method() );
		$data            = $request->get_body();

		if ( empty( $data ) ) {
			$data = '';
		} else {
			if ( ! is_string( $data ) ) {
				$data = json_encode( $data );
			}
		}

		$isValid = hash_equals(
			$signature,
			hash_hmac( 'sha256', $request_api_key . $method . $data . $timestamp, $api_keys['api_secret'] )
		);

		if ( $isValid ) {
			return true;
		}

		return new \WP_Error( 'invalid_signature', __( 'Invalid Signature', 'roxwp-site-mon' ) );

	}


}
