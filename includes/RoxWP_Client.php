<?php
/**
 *
 *
 * @package Package
 * @author Name <email>
 * @version
 * @since
 * @license
 */

namespace AbsolutePlugins\RoxwpSiteMonitor;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class RoxWP_Client {

	protected static $instance;

//	private $api = 'https://rox-wp.test/api/v1';
	private $api = 'https://staging.roxwp.com/api/v1';

	private $api_key;

	private $api_secret;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function __construct() {
		$this->reload_api_keys();
	}

	public function reload_api_keys() {
		$api_keys = get_option( 'roxwp_site_monitor_api_keys', [] );

		if ( isset( $api_keys['api_key'], $api_keys['api_secret'] ) ) {
			$this->api_key    = $api_keys['api_key'];
			$this->api_secret = $api_keys['api_secret'];
		}
	}

	public function set_api_key( $api_key ) {
		$this->api_key = $api_key;
	}

	public function set_api_secret( $api_secret ) {
		$this->api_secret = $api_secret;
	}

	public function hasKeys() {
		return $this->api_key && $this->api_secret;
	}

	public function ping() {
		return $this->request( 'ping' );
	}

	public function send_log( $log ) {
		return $this->request( 'site/activity/log', $log, 'post', [ 'blocking' => false, 'timeout' => 5 ] );
	}

	public function request( $route, $data = [], $method = 'get', $args = [] ) {
		if ( ! $this->hasKeys() ) {
			return new WP_Error( 'missing-api-keys', __( 'Missing API Keys.', 'rwp-site-mon' ) );
		}

		list( $algo, $timestamp, $signature ) = $this->signature( $data, $method );

		$defaults = [
			'sslverify' => true,
			'headers'   => [],
			'method'    => strtoupper( $method ),
			'body'      => [],
			'blocking'  => true,
			'timeout'   => 15,
		];

		$args = wp_parse_args( $args, $defaults );

		$args = array_merge( $args['headers'], [
			'X-Api-Key'        => $this->api_key,
			'X-Signature-Algo' => $algo,
			'X-Api-Signature'  => $signature,
			'X-Api-Timestamp'  => $timestamp,
			'Content-Type'     => 'application/json',
			'Accept'           => 'application/json',
		] );

		if ( ! empty( $data ) ) {
			$args['body'] = 'get' === $method ? $data : json_encode( $data );
		}

		$route = ltrim( $route, '\\/' );

		if ( false !== strpos( $this->api, '.test/' ) ) {
			$response = wp_remote_request( $this->api . '/' . $route, $args );
		} else {
			$response = wp_safe_remote_request( $this->api . '/' . $route, $args );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$_body  = trim( wp_remote_retrieve_body( $response ) );
		$body   = json_decode( $_body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$body = $_body;
			unset( $_body );
		}

		if ( 200 !== $status ) {
			$code = isset( $body['code'] ) ? $body['code'] : $status;
			if ( ! is_array( $body ) ) {
				$message = $body;
			} elseif( isset( $body['message'] ) ) {
				$message = $body['message'];
			} else {
				$message = wp_remote_retrieve_response_message( $response );
			}

			if ( ! $message ) {
				$message = __( 'Something went wrong', 'rwp-site-mon' );
			}

			return new WP_Error( $code, $message, $body );
		}

		return $body;
	}

	protected function signature( $data, $method ) {

		$method = strtolower( $method );

		if ( empty( $data ) ) {
			$data = '';
		} else {
			if ( ! is_string( $data ) ) {
				$data = json_encode( $data );
			}
		}

		// Signature Timestamp.
		$timestamp = current_time( 'timestamp', 1 );

		// Signature Hash
		$hash = hash_hmac( 'sha256', "{$this->api_key}{$method}{$data}{$timestamp}", $this->api_secret );

		return [ 'sha256', $timestamp, $hash ];

	}
}

// End of file RoxWP_Client.php.
