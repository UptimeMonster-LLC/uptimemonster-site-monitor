<?php

namespace AbsolutePlugins\RoxwpSiteMonitor\Api;

use AbsolutePlugins\RoxwpSiteMonitor\RoxWP_Client;
use AbsolutePlugins\RoxwpSiteMonitor\Api\RoxWP_Debug_Data;
use AbsolutePlugins\RoxwpSiteMonitor\Api\RoxWP_Update_Check;

/**
 * Class RoxWP_Health_Check_Api
 */
class RoxWP_Health_Check_Api {


	protected $namespace;
	protected $rest_base;
	protected $woocommerce;
	protected $version;
	public $current_user;


	public function __construct( $current_user = null)
	{
		$this->version = 'v1';
		$this->namespace = 'roxwp/' . $this->version;
		$this->rest_base = '/site-health';
		$this->current_user = $current_user;

		add_action('rest_api_init', [ $this , 'roxwp_register_routes']);
	}

	public  function roxwp_register_routes(){
		// Register debug_data route.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/debug_data',
			array(
				array(
					'methods' => \WP_REST_Server::READABLE,
					'callback' => array( $this, 'send_debug_info' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args' => array(),
				),

			)
		);

		// Register site health route.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/site_health',
			array(
				array(
					'methods' => \WP_REST_Server::READABLE,
					'callback' => array( $this, 'send_site_health_info' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args' => array(),
				),

			)
		);


	}


	/**
	 * @param $request
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function send_debug_info( $request ){

		$debug_data =   new RoxWP_Debug_Data();
		$debug_info = $debug_data->debug_data();

		$response['status'] = true;
		$response['data']   = $debug_info;

		return rest_ensure_response( $response );

	}

	/**
	 * @param $request
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function send_site_health_info( $request ) {

		$update_check = new RoxWP_Update_Check();
		$this->site_healths = $update_check->get_site_health();

		$response['status'] = true;
		$response['data']   = $this->site_healths;

		return rest_ensure_response( $response );

	}


	/*
     * Get route access if request is valid.
     */
	public function get_route_access() {

		$api_keys = get_option( 'roxwp_site_monitor_api_keys', []);

		$api_key = $_SERVER['HTTP_API_KEY'];
		$secret_key = $_SERVER['HTTP_SECRET_KEY'];

		if(  $api_keys['api_key'] === $api_key && $api_keys['api_secret'] === $secret_key) {

			return true;
		}
	}


}
