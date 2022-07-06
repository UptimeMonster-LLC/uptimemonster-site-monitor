<?php

namespace AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\V1;

use AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\Controller_Base;

/**
 * Class Theme
 */
class Themes extends Controller_Base {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = '/theme';

	public function __construct() {

	}

	public function register_routes() {
		// Register activate theme.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/activate',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'activate_theme' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),

			)
		);

		// Register deactivate theme.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/deactivate',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'deactivate_themes' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),

			)
		);

		// Register uninstall theme.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/uninstall',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'uninstall_theme' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),

			)
		);

		// Register delete theme.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/delete',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'delete_themes' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),

			)
		);

		// Register update theme.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/update',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_themes' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),

			)
		);

	}


	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function activate_theme( $request ) {

		$data = json_decode( $request->get_body() );

		$response = [];

		if ( ! isset( $data->slug ) || empty( $data->slug ) ) {
			return rest_ensure_response( [
					'status'       => false,
					'slug'         => '',
					'errorCode'    => 'no_theme_specified',
					'errorMessage' => __( 'No theme specified.' ),
				]
			);
		}



		switch_theme( $data->slug );

		$response['message']  = get_option( 'current_theme' );

		return rest_ensure_response( [
			'status' => true,
			'data'   => wp_get_themes(),
		] );
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function deactivate_themes( \WP_REST_Request $request ) {

		$status   = array( 'action' => 'deactivate' );
		$response = [];
		$data     = json_decode( $request->get_body() );

		if ( ! isset( $data->slugs ) || empty( $data->slugs ) ) {
			return rest_ensure_response( [
					'status'       => false,
					'slug'         => '',
					'errorCode'    => 'no_theme_specified',
					'errorMessage' => __( 'No theme specified.' ),
				]
			);
		}

		deactivate_themes( $data->slugs );

		foreach ( $data->slugs as $theme ) {
			$status['success']   = is_theme_inactive( $theme );
			$response[ $theme ] = $status;
		}

		return rest_ensure_response( [
			'status' => true,
			'data'   => $response,
		] );
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function uninstall_theme( \WP_REST_Request $request ) {

		$status = array(
			'action' => 'deactivate',
		);

		$data = json_decode( $request->get_body() );

		$response = [];

		if ( ! isset( $data->slugs ) || empty( $data->slugs ) ) {
			return rest_ensure_response( [
					'status'       => false,
					'slug'         => '',
					'errorCode'    => 'no_theme_specified',
					'errorMessage' => __( 'No theme specified.' ),
				]
			);
		}


		foreach ( $data->slugs as $slug ) {
			$status = array(
				'action' => 'uninstall',
			);

			$theme = ( isset( $slug ) ) ? esc_attr( $slug ) : false;

			if ( $theme ) {
				if ( is_uninstallable_theme( $theme ) ) {
					$uninstall = uninstall_theme( $theme );
				}
				if ( ! $uninstall ) {
					$status['message'] = __( 'Something went wrong', 'roxwp-site-mon' );
					$status['success'] = false;
				} else {
					$status['success'] = true;
					$status['message'] = __( 'theme uninstalled', 'roxwp-site-mon' );
				}
			} else {
				$status['message'] = __( 'Invalid theme Slug', 'roxwp-site-mon' );
				$status['success'] = false;
			}

			$response[ $slug ] = $status;
		}

		return rest_ensure_response( [
			'status' => true,
			'data'   => $response,
		] );
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function delete_themes( \WP_REST_Request $request ) {

		$status   = array( 'action' => 'delete' );
		$response = [];
		$data     = json_decode( $request->get_body() );

		if ( ! isset( $data->slugs ) || empty( $data->slugs ) ) {
			return rest_ensure_response( [
					'status'       => false,
					'slug'         => '',
					'errorCode'    => 'no_theme_specified',
					'errorMessage' => __( 'No theme specified.' ),
				]
			);
		}


//		delete_themes( $data->slugs );

//		foreach ( $data->slugs as $theme ) {
//
//			$response[ $theme ] = $status;
//		}

		return rest_ensure_response( [
			'status' => true,
			'data'   => $response,
			'error'  => error_get_last(),
		] );
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function update_themes( \WP_REST_Request $request ) {

	}


}
