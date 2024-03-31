<?php
/**
 * Core Health Check API
 *
 * @package UptimeMonster\SiteMonitor\API
 * @version 1.0.0
 */

namespace UptimeMonster\SiteMonitor\Api\Controllers\V1;

use UptimeMonster\SiteMonitor\Api\Controllers\V1\Site_Health\UptimeMonster_Debug_Data;
use UptimeMonster\SiteMonitor\Api\Controllers\V1\Site_Health\UptimeMonster_Update_Check;
use UptimeMonster\SiteMonitor\Api\Controllers\Controller_Base;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

/**
 * Class Health_Check
 */
class Health_Check extends Controller_Base {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = '/site-health';

	public function register_routes() {
		// Register site health route.
		register_rest_route(
			$this->namespace,
			$this->rest_base,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'send_site_health_info' ],
					'permission_callback' => [ $this, 'get_route_access' ],
					'args'                => [],
				],

			]
		);

		// Register ping route.
		register_rest_route(
			$this->namespace,
			'/ping',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_ping' ],
					'permission_callback' => [ $this, 'get_route_access' ],
					'args'                => [],
				],

			]
		);
	}

	/**
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function get_ping( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		return rest_ensure_response( 'ok' );
	}

	/**
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function send_site_health_info( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		$response = [];

		$this->add_extra_data( $response );

		return rest_ensure_response( [
			'status'      => true,
			'site_health' => $response['extra']['site_health'] ?? [],
			'site_info'   => $response['extra']['site_info'] ?? [],
		] );
	}

}
