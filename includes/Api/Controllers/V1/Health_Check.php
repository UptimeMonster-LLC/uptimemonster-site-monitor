<?php

namespace AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\V1;


use AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\V1\Site_Health\RoxWP_Debug_Data;
use AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\V1\Site_Health\RoxWP_Update_Check;
use AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\Controller_Base;

/**
 * Class Health_Check
 */
class Health_Check extends Controller_Base {


	public function __construct() {

	}

	public function register_routes() {
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

		// Register ping route.
		register_rest_route(
			$this->namespace,
			'/ping',
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

	/**
	 * @param $request
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function get_ping( $request ) {

		return rest_ensure_response( 'ok' );
	}


	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function send_site_health_info( $request ) {

		// Health data
		$update_check = new RoxWP_Update_Check();
		$site_healths = $update_check->get_site_health() ? $update_check->get_site_health() : [];

		// Debug data.
		$debug_data = new RoxWP_Debug_Data();
		$debug_info = $debug_data->debug_data() ? $debug_data->debug_data() : [];

		return rest_ensure_response( [
			'status'      => true,
			'site_health' => $site_healths,
			'site_info'   => $debug_info,
		] );
	}

}
