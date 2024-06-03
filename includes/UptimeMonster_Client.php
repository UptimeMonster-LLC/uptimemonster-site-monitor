<?php
/**
 * API Client
 *
 * @package UptimeMonster\SiteMonitor
 */

namespace UptimeMonster\SiteMonitor;

use UptimeMonster\SiteMonitor\Traits\Singleton;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

class UptimeMonster_Client {

	use Singleton;

	/**
	 * API server host.
	 *
	 * @var string
	 */
	private $host = 'https://app.uptimemonster.com/';

	/**
	 * API version.
	 *
	 * @var string
	 */
	private $version = 'v1';

	/**
	 * API Key (Public Key).
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * API Secret (Private Key).
	 *
	 * @var string
	 */
	private $api_secret;

	/**
	 * UptimeMonster Client Constructor.
	 *
	 * @return void
	 */
	protected function __construct() {
		$this->reload_api_keys();
	}

	/**
	 * Reload API keys from database.
	 *
	 * @return $this
	 */
	public function reload_api_keys(): UptimeMonster_Client {
		$api_keys = (array) get_option( 'uptimemonster_site_monitor_api_keys', [] );

		if ( isset( $api_keys['api_key'], $api_keys['api_secret'] ) ) {
			$this->api_key    = $api_keys['api_key'];
			$this->api_secret = $api_keys['api_secret'];
		}

		return $this;
	}

	/**
	 * Get API server host with network protocol & trailing slash.
	 *
	 * @return string
	 */
	public function get_host(): string {
		return trailingslashit( apply_filters( 'uptimemonster_client_app_host', $this->host ) );
	}

	/**
	 * Sets API Key (Public Key).
	 *
	 * @param string $api_key API Key
	 *
	 * @return $this
	 */
	public function set_api_key( string $api_key ): UptimeMonster_Client {
		$this->api_key = $api_key;

		return $this;
	}

	/**
	 * Sets API Secret key.
	 *
	 * @param string $api_secret API secret.
	 *
	 * @return $this
	 */
	public function set_api_secret( string $api_secret ): UptimeMonster_Client {
		$this->api_secret = $api_secret;

		return $this;
	}

	/**
	 * Checks and confirm client has api keys.
	 *
	 * @return bool
	 */
	public function has_keys(): bool {
		return $this->api_key && $this->api_secret;
	}

	/**
	 * Ping.
	 * Tests api connectivity.
	 *
	 * @return array|mixed|string|WP_Error
	 */
	public function ping() {
		return $this->request( 'ping' );
	}

	/**
	 * Send Log Data to UptimeMonster.
	 *
	 * @param array $log Log Data.
	 *
	 * @return array|mixed|string|WP_Error
	 */
	public function send_log( array $log ) {
		return $this->request(
			'site/activity/log',
			$log,
			'post',
			[
				'blocking' => false,
				'timeout'  => 5, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			]
		);
	}

	/**
	 * Sends api request.
	 *
	 * @param string $route Endpoint route to send request to.
	 * @param array $data Request payload.
	 * @param string $method Request method.
	 * @param array $args Optional. wp_remote_request args.
	 *
	 * @return array|mixed|string|WP_Error
	 */
	public function request( string $route, array $data = [], string $method = 'get', array $args = [] ) {
		if ( ! $this->has_keys() ) {
			return new WP_Error( 'missing-api-keys', esc_html__( 'Missing API Keys.', 'uptimemonster-site-monitor' ) );
		}

		list( $algo, $timestamp, $signature ) = $this->signature( $data, $method );

		$defaults = [
			'sslverify' => apply_filters( 'uptimemonster_client_ssl_verify', true ), // https_local_ssl_verify local
			'headers'   => [],
			'method'    => strtoupper( $method ),
			'body'      => [],
			'blocking'  => true,
			'timeout'   => 15, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
		];

		$args = wp_parse_args( $args, $defaults );

		$args['headers'] = array_merge(
			$args['headers'],
			[
				'X-Api-Key'        => $this->api_key,
				'X-Signature-Algo' => $algo,
				'X-Api-Signature'  => $signature,
				'X-Api-Timestamp'  => $timestamp,
				'Content-Type'     => 'application/json',
				'Accept'           => 'application/json',
			]
		);

		if ( ! empty( $data ) ) {
			$args['body'] = 'get' === $method ? $data : wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
		}

		$route       = ltrim( $route, '\\/' );
		$request_url = $this->get_host() . 'api/' . $this->version . '/' . $route;

		$args = apply_filters( 'uptimemonster_site_monitor_client_args', $args, $route );

		if ( false !== strpos( $this->get_host(), '.test/' ) ) {
			$response = wp_remote_request( $request_url, $args );
		} elseif ( function_exists( 'vip_safe_wp_remote_request' ) ) {
			/** @noinspection PhpUndefinedFunctionInspection */
			$response = vip_safe_wp_remote_request( $request_url, '', 3, 5, 20, $args );
		} else {
			$response = wp_safe_remote_request( $request_url, $args );
		}

		/**
		 * Action after request being sent.
		 *
		 * @param array|WP_Error $response raw response returned from wp_remote_request.
		 * @param array $args request args.
		 * @param string $request_url
		 */
		do_action( 'uptimemonster_site_monitor_client_response', $response, $args, $request_url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status   = wp_remote_retrieve_response_code( $response );
		$res_body = trim( wp_remote_retrieve_body( $response ) );
		$body     = json_decode( $res_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$body = $res_body;
			unset( $res_body );
		}

		if ( 200 !== $status ) {
			$code = $body['code'] ?? $status;
			if ( ! is_array( $body ) ) {
				$message = $body;
			} elseif ( isset( $body['message'] ) ) {
				$message = $body['message'];
			} else {
				$message = wp_remote_retrieve_response_message( $response );
			}

			if ( ! $message ) {
				$message = esc_html__( 'Something went wrong', 'uptimemonster-site-monitor' );
			}

			return new WP_Error( $code, $message, $body );
		}

		return $body;
	}

	/**
	 * Generate HMAC Signature for UptimeMonster Api auth header.
	 *
	 * @param string|array $data Request payload.
	 * @param string $method Request Method.
	 *
	 * @return array
	 */
	protected function signature( $data, string $method ): array {
		$method = strtolower( $method );

		if ( empty( $data ) ) {
			$data = '';
		}

		if ( ! is_string( $data ) ) {
			$data = wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
		}

		$timestamp = time();
		$payload   = implode( '', [ $this->api_key, $method, $data, $timestamp ] );

		return [ 'sha256', $timestamp, hash_hmac( 'sha256', $payload, $this->api_secret ) ];
	}
}

// End of file UptimeMonster_Client.php.
