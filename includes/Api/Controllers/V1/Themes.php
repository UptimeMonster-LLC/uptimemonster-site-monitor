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

		// Register install theme.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/install',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'install_themes' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),
			)
		);
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
	public function install_themes( \WP_REST_Request $request ) {

		$response = array(
			'action' => 'install',
			'data'   => [],
			'extra'  => []
		);

		$data = json_decode( $request->get_body() );

		if ( ! isset( $data->slugs ) || empty( $data->slugs ) ) {
			return rest_ensure_response( [
					'status' => false,
					'action' => 'install',
					'data'   => [
						'message' => __( 'No theme specified.', 'roxwp-site-mon' ),
					],
					'extra'  => []
				]
			);
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/theme.php';
		if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$statuses = [];
		foreach ( $data->slugs as $slug ) {
			$status = array( 'status' => false );

			$slug = sanitize_key( wp_unslash( $slug ) );

			$api = themes_api(
				'theme_information',
				array(
					'slug'   => $slug,
					'fields' => array( 'sections' => false ),
				)
			);

			if ( is_wp_error( $api ) ) {
				$status['message']  = $api->get_error_message();
				$statuses [ $slug ] = $status;
				continue;
			}
			$result   = $upgrader->install( $api->download_link );
			if ( is_wp_error( $result ) ) {
				$status['message'] = $result->get_error_message();
			} elseif ( is_wp_error( $skin->result ) ) {
				$status['message'] = $skin->result->get_error_message();
			} elseif ( $skin->get_errors()->has_errors() ) {
				$status['message'] = $skin->get_error_messages();
			} elseif ( is_null( $result ) ) {
				global $wp_filesystem;

				// Pass through the error from WP_Filesystem if one was raised.
				if ( $wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
					$status['message'] = esc_html( $wp_filesystem->errors->get_error_message() );
				}else{
					$status['message'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.' );
				}
			}

			$status['status'] = true;

			$statuses [ $slug ] = $status;
		}


		$response['status']  = true;
		$response['data']    = $statuses;

		return rest_ensure_response( $response );
	}


	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function activate_theme( $request ) {

		$data = json_decode( $request->get_body() );


		$response = array(
			'status' => true,
			'action' => 'activate',
			'data'   => [],
			'extra'  => [],
		);

		if ( ! isset( $data->slugs ) || empty( $data->slugs ) ) {
			return rest_ensure_response( [
					'status' => false,
					'action' => 'activate',
					'data'   => [
						'message' => __( 'No theme specified.', 'roxwp-site-mon' ),
					],
					'extra'  => []
				]
			);
		}

		if ( isset( $data->slugs[0] ) && $this->is_theme_exists( $data->slugs[0] ) ) {
			if( wp_get_theme()->get_stylesheet() === $data->slugs[0] ) {
				$response['status'] = false;
				$response['data']['message'] = __( 'Theme already active.', 'roxwp-site-mon' );
			} else {
				switch_theme( $data->slugs[0] );
				$response['data']['message'] = __( 'Theme activated.', 'roxwp-site-mon' );
			}
		} else {
			$response['status'] = false;
			$response['data']['message'] = __( 'Theme does\'nt exists.', 'roxwp-site-mon' );
		}

		return rest_ensure_response( $response );

	}


	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function delete_theme( \WP_REST_Request $request ) {

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


	/**
	 * @param $path
	 *
	 * @return bool
	 */
	private function is_theme_exists( $path ) {
		return file_exists( WP_CONTENT_DIR . '/themes/' . $path );
	}

}
