<?php

namespace AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\V1;

use AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\Controller_Base;

/**
 * Class Plugins
 */
class Plugins extends Controller_Base {


	public function __construct() {

	}

	public function register_routes() {
		// Register activate route.
		register_rest_route(
			$this->namespace,
			'activate',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'activate' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),

			)
		);

	}


	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function activate( $request ) {

		return rest_ensure_response( [
			'status' => true,
			'data'   => $request->get_body(),
		] );
	}

}
