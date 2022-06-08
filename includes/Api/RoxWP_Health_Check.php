<?php

namespace AbsolutePlugins\RoxwpSiteMonitor\Api;

use AbsolutePlugins\RoxwpSiteMonitor\RoxWP_Client;
use AbsolutePlugins\RoxwpSiteMonitor\Api\RoxWP_Debug_Data;
use AbsolutePlugins\RoxwpSiteMonitor\Api\RoxWP_Update_Check;

/**
 * Class RoxWP_Health_Check
 */
class RoxWP_Health_Check {


	protected $namespace;
	protected $rest_base;
	protected $woocommerce;
	protected $version;
	public $current_user;
	protected $site_healths = [];


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

		// Register site analytics route.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/site_analytics',
			array(
				array(
					'methods' => \WP_REST_Server::READABLE,
					'callback' => array( $this, 'site_analytics' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args' => array(),
				),

			)
		);
	}

	public  function site_analytics( $request ){

		$response['status'] = true;
		$analytics = get_transient( 'Api-site-status-result' );



		$response['data'] = $analytics;



		return rest_ensure_response($response);
	}


	/**
	 * @param $request
	 *
	 * @return void
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
	public function send_site_health_info( $request ){

		$update_check = new RoxWP_Update_Check();
		$this->site_healths = $update_check->get_site_health();

		$response['status'] = true;
		$site_status = [];
		foreach ( $this->site_healths as $result ) {
			if ( 'critical' === $result['status'] ) {
				$site_status['critical']++;
			} elseif ( 'recommended' === $result['status'] ) {
				$site_status['recommended']++;
			} else {
				$site_status['good']++;
			}
		}
//		$response['anali']

		$response['data']   = $this->site_healths;

		return rest_ensure_response( $response );

	}


	/*
     * Get route access if request is valid.
     */
	public function get_route_access()
	{
		$has_access = RoxWP_Client::get_instance()->has_keys();

		if(  $has_access ) {

			return true;
		}
	}


}
