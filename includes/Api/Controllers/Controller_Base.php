<?php
/**
 * REST API Controller Base.
 *
 * @package UptimeMonster\SiteMonitor\API
 * @version 1.0.0
 */

namespace UptimeMonster\SiteMonitor\Api\Controllers;

use UptimeMonster\SiteMonitor\Site_Health\Site_Health;
use UptimeMonster\SiteMonitor\Site_Health\Debug_Data;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

#[\AllowDynamicProperties]
abstract class Controller_Base extends WP_REST_Controller {

	/**
	 * Extra data.
	 *
	 * @var array
	 */
	protected static $extra_data;

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'uptimemonster/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = '';

	/**
	 * Get route access if request is valid.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|boolean
	 * @see WP_REST_Server::respond_to_request()
	 */
	public function get_route_access( WP_REST_Request $request ) {
		/** @var string[] $api_keys */
		$api_keys = get_option( 'uptimemonster_site_monitor_api_keys', [] );
		if ( empty( $api_keys['api_key'] ) || empty( $api_keys['api_secret'] ) ) {
			// returning false or null results in default "Sorry, you are not allowed to do that." message.
			return new WP_Error(
				'empty_api_keys',
				esc_html__( 'Update API Keys In Plugin Settings.', 'uptimemonster-site-monitor' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		$request_api_key = $request->get_header( 'X-Api-Key' ) ? $request->get_header( 'X-Api-Key' ) : '';
		$signature       = $request->get_header( 'X-Api-Signature' ) ? $request->get_header( 'X-Api-Signature' ) : '';
		$timestamp       = $request->get_header( 'X-Api-Timestamp' ) ? $request->get_header( 'X-Api-Timestamp' ) : '';
		$method          = strtolower( $request->get_method() );
		$data            = $request->get_body();

		if ( $data && ! is_string( $data ) ) {
			$data = wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
		}

		if ( empty( $data ) ) {
			$data = '';
		}

		$is_valid = hash_equals(
			$signature,
			hash_hmac( 'sha256', $api_keys['api_key'] . $method . $data . $timestamp, $api_keys['api_secret'] )
		);

		if ( $is_valid && $request_api_key === $api_keys['api_key'] ) {
			return true;
		}

		return new WP_Error( 'invalid_signature', esc_html__( 'Invalid Signature', 'uptimemonster-site-monitor' ) );
	}

	/**
	 * @param array $response
	 *
	 * @return void
	 */
	protected function add_extra_data( &$response ) {
		// Check for update.
		Debug_Data::check_for_updates();

		if ( null === self::$extra_data ) {
			self::$extra_data = [
				'site_health' => Site_Health::get_reports(),
				'site_info'   => Debug_Data::get_report(),
			];
		}

		$response['extra'] = self::$extra_data;
	}
}

// End of file Controller_Base.php.
