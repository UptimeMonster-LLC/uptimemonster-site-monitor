<?php
/**
 * Plugin Loader.
 *
 * @package Ayyash_Studio_Server\RestApi
 * @author Kudratullah <mhamudul.hk@gmail.com>
 * @version 1.0.0
 * @since 1.0.0
 */

namespace AbsolutePlugins\RoxwpSiteMonitor\Api;

use AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\V1\Health_Check;
use AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\V1\Plugins;


if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

final class Server {

	/**
	 * @var Server
	 */
	protected static $instance;

	public static function get_instance(): Server {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Ayyash_Studio_Server constructor.
	 *
	 * @return void
	 */
	protected function __construct() {
	}

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 1000 );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {


		$routes = [
			Health_Check::class,
			Plugins::class
		];

		foreach ( $routes as $route ) {
			$route = new $route();
			$route->register_routes();
		}
	}

	/**
	 * Prevent cloning.
	 */
	final public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'ayyash-studio' ), '1.0.0' );
	}

	/**
	 * Prevent unserializing.
	 */
	final public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'ayyash-studio' ), '1.0.0' );
	}
}

// End of file class-server.php.
