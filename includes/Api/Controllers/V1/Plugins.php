<?php

namespace AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\V1;

use AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\Controller_Base;

/**
 * Class Plugins
 */
class Plugins extends Controller_Base {


	public function __construct() {

	}

	public function register_routes() {
		// Register activate route.
		register_rest_route(
			$this->namespace,
			'activate',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'activate' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),

			)
		);

	}


	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function activate( $request ) {


//		if ( $plugin ) {
//			$activate = $this->__activate_plugin( $plugin );
//
//			if ( is_wp_error( $activate ) ) {
//				if ( defined( 'WP_CLI' ) ) {
//					\WP_CLI::error( 'Plugin Activation Error: ' . $activate->get_error_message() );
//				} elseif ( wp_doing_ajax() ) {
//					wp_send_json_error( [ 'slug' => $plugin, 'errorMessage' => $activate->get_error_message() ] );
//				}
//			}
//
//			if ( defined( 'WP_CLI' ) ) {
//				\WP_CLI::line( 'Plugin Activated!' );
//			} elseif ( wp_doing_ajax() ) {
//				wp_send_json_success(
//					array(
//						'success' => true,
//						'message' => __( 'Plugin Activated', 'ayyash-studio' ),
//					)
//				);
//			}
//		} else {
//			wp_send_json_error( [ 'errorMessage' => __( 'Invalid Request', 'ayyash-studio' ) ] );
//		}
		return rest_ensure_response( [
			'status' => true,
			'data'   => [],
		] );
	}

}
