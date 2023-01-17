<?php
/**
 * REST API Controller Base.
 *
 * @package UptimeMonster\SiteMonitor\API
 * @version 1.0.0
 */

namespace UptimeMonster\SiteMonitor\Api\Controllers;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

abstract class Controller_Base extends WP_REST_Controller {

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
	 */
	public function get_route_access( $request ) {
		$api_keys = get_option( 'umsm_site_monitor_api_keys', [] );

		if ( empty( $api_keys['api_key'] ) || empty( $api_keys['api_secret'] ) ) {
			return new WP_Error( 'invalid_api_keys', __( 'Invalid API Keys, Update Plugin Settings.', 'uptime' ) );
		}

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
			hash_hmac( 'sha256', $api_keys['api_key'] . $method . $data . $timestamp, $api_keys['api_secret'] )
		);

		if ( $isValid && $request_api_key === $api_keys['api_key'] ) {
			return true;
		}

		return new WP_Error( 'invalid_signature', __( 'Invalid Signature', 'uptime' ) );
	}
}
