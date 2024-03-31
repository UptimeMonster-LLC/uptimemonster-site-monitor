<?php
/**
 * Plugin Loader.
 *
 * @package UptimeMonster\SiteMonitor\Api
 * @version 1.0.0
 */

namespace UptimeMonster\SiteMonitor\Api;

use UptimeMonster\SiteMonitor\Api\Controllers\V1\Health_Check;
use UptimeMonster\SiteMonitor\Api\Controllers\V1\Plugins;
use UptimeMonster\SiteMonitor\Api\Controllers\V1\Themes;
use UptimeMonster\SiteMonitor\Api\Controllers\V1\Core_Update;


if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
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
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$routes = [
			Health_Check::class,
			Plugins::class,
			Themes::class,
			Core_Update::class,
		];

		foreach ( $routes as $route ) {
			$route = new $route();
			$route->register_routes();
		}
	}

	/**
	 * Prevent cloning.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'serializing instances of this class is forbidden.', 'uptimemonster-site-monitor' ), '1.0.0' );
	}

	/**
	 * Prevent unserializing.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances of this class is forbidden.', 'uptimemonster-site-monitor' ), '1.0.0' );
	}
}

// End of file Server.php.
