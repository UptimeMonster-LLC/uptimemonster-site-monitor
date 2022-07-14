<?php


/**
 * REST Controller Base.
 *
 * @package AbsolutePlugins\RoxwpSiteMonitor
 * @author Azizul Hasan <azizulhasan.cr@gmail.com>
 * @version 1.0.0
 * @since 1.0.0
 */

namespace AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers;



if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

abstract class Controller_Base extends \WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'roxwp/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = '/site-health';

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

		return true;
		return new \WP_Error( 'invalid_signature', __( 'Invalid Signature', 'roxwp-site-mon' ), [] );

	}


}
