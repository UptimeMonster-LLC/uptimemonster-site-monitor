<?php

namespace AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\V1;

use AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\Controller_Base;

/**
 * Class Plugins
 */
class Plugins extends Controller_Base {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = '/plugin';

	/**
	 * constructor.
	 */
	public function __construct() {

	}

	/**
	 * Register routes.
	 * @return void
	 */
	public function register_routes() {

		// Register install plugin.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/install',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'install_plugin' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),

			)
		);

		// Register activate plugin.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/activate',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'activate_plugins' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),

			)
		);

		// Register deactivate plugin.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/deactivate',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'deactivate_plugins' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),

			)
		);

		// Register uninstall plugin.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/uninstall',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'uninstall_plugin' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),

			)
		);

		// Register delete plugin.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/delete',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'delete_plugins' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),

			)
		);

		// Register update plugin.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/update',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_plugins' ),
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
	public function install_plugin( \WP_REST_Request $request ) {

		$data = json_decode( $request->get_body() );

		if ( ! isset( $data->slug ) || empty( $data->slug ) ) {
			return rest_ensure_response( [
					'status' => false,
					'slug'   => '',
					'error'  => [
						'errorCode'    => 'no_plugin_specified',
						'errorMessage' => __( 'No plugin specified.' ),
					]
				]
			);
		}

		$response = array(
			'action' => 'install',
			'error'  => [],
			'extra'  => ''
		);

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => sanitize_key( wp_unslash( $data->slug ) ),
				'fields' => array(
					'sections' => false,
				),
			)
		);


		if ( is_wp_error( $api ) ) {
			$response['status'] = false;
			$response['error']  = [
				'errorCode'    => $api->get_error_code(),
				'errorMessage' => $api->get_error_message(),
			];

			return rest_ensure_response( $response );
		}


		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$response['extra'][] = $skin->get_upgrade_messages();
		}

		$error = [];
		if ( is_wp_error( $result ) ) {
			$error['errorCode']    = $result->get_error_code();
			$error['errorMessage'] = $result->get_error_message();

		} elseif ( is_wp_error( $skin->result ) ) {
			$error['errorCode']    = $skin->result->get_error_code();
			$error['errorMessage'] = $skin->result->get_error_message();
		} elseif ( $skin->get_errors()->has_errors() ) {
			$error['errorMessage'] = $skin->get_error_messages();

		} elseif ( is_null( $result ) ) {
			global $wp_filesystem;

			$error['errorCode']    = 'unable_to_connect_to_filesystem';
			$error['errorMessage'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.' );

			// Pass through the error from WP_Filesystem if one was raised.
			if ( $wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
				$error['errorMessage'] = esc_html( $wp_filesystem->errors->get_error_message() );
			}
		}

		if ( ! empty( $error ) ) {
			$response['status'] = false;
			$response['error']  = $error;

			return rest_ensure_response( $response );
		}

		$install_status = install_plugin_install_status( $api );

		$response['status'] = true;
		$response['extra']  = $install_status;

		return rest_ensure_response( $response );
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function activate_plugins( $request ) {

		$data = json_decode( $request->get_body() );

		$response = [
			'action' => 'activate',
			'data'   => [],
			'extra'  => [],
		];

		if ( ! isset( $data->slugs ) || empty( $data->slugs ) ) {
			return rest_ensure_response( [
					'action' => 'activate',
					'status' => false,
					'data'   => [
						'message' => __( 'No plugin specified.', 'roxwp-site-mon' ),
					],
					'extra'  => []
				]
			);
		}

		$statuses = [];
		foreach ( $data->slugs as $slug ) {
			$status = array();
			$plugin = ( isset( $slug ) ) ? esc_attr( $slug ) : false;
			if ( $this->is_plugin_exists( $plugin ) ) {

				if ( is_plugin_active( $plugin ) ) {
					$status['status']  = false;
					$status['message'] = __( 'Plugin already active', 'roxwp-site-mon' );
					$statuses[ $slug ] = $status;
					continue;
				}
				$activate = activate_plugin( $plugin, '', false, false );
				if ( is_wp_error( $activate ) ) {
					$status['status']  = false;
					$status['message'] = $activate->get_error_message();
				} else {
					$status['status']  = true;
					$status['message'] = __( 'Plugin activated', 'roxwp-site-mon' );
				}

			} else {
				$status['status']  = false;
				$status['message'] = __( 'Plugin does not exist', 'roxwp-site-mon' );
			}

			$statuses[ $slug ] = $status;
		}

		$response['data'] = $statuses;

		return rest_ensure_response( $response );
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function deactivate_plugins( \WP_REST_Request $request ) {

		$response = [
			'action' => 'deactivate',
			'error'  => [],
			'extra'  => [],
		];

		$data = json_decode( $request->get_body() );

		if ( ! isset( $data->slugs ) || empty( $data->slugs ) ) {
			return rest_ensure_response( [
					'status'       => false,
					'slug'         => '',
					'errorCode'    => 'no_plugin_specified',
					'errorMessage' => __( 'No plugin specified.' ),
				]
			);
		}

		deactivate_plugins( $data->slugs );

		$statuses = [];
		foreach ( $data->slugs as $plugin ) {
			$statuses[ $plugin ]['success'] = is_plugin_inactive( $plugin );
		}

		$response['status'] = true;
		$response['extra']  = $statuses;

		return rest_ensure_response( $response );
	}

	/**
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function uninstall_plugin( \WP_REST_Request $request ) {

		$data = json_decode( $request->get_body() );

		$response = [];

		if ( ! isset( $data->slugs ) || empty( $data->slugs ) ) {
			return rest_ensure_response( [
					'status'       => false,
					'slug'         => '',
					'errorCode'    => 'no_plugin_specified',
					'errorMessage' => __( 'No plugin specified.' ),
				]
			);
		}


		foreach ( $data->slugs as $slug ) {
			$slug   = sanitize_text_field( $slug );
			$status = [ 'action' => 'uninstall', ];

			if ( ! $slug ) {
				$status['message'] = __( 'Invalid Plugin Slug', 'roxwp-site-mon' );
				$status['success'] = false;
				$response[ $slug ] = $status;

				continue;
			}

			if ( is_uninstallable_plugin( $slug ) ) {
				/*uninstall_plugin( $slug );*/
			}
			if ( is_plugin_active( $slug ) ) {
				$status['message'] = __( 'Uninstallation failed', 'roxwp-site-mon' );
				$status['success'] = false;
			} else {
				$status['success'] = true;
				$status['message'] = __( 'Plugin uninstalled', 'roxwp-site-mon' );
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
	public function delete_plugins( \WP_REST_Request $request ) {

		$data = json_decode( $request->get_body() );

		if ( ! isset( $data->slugs ) || empty( $data->slugs ) ) {
			return rest_ensure_response( [
					'status' => false,
					'data'   => [],
					'extra'  => []
				]
			);
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$existed_plugin = $this->existed_plugins( $data->slugs );

		$status = delete_plugins( $existed_plugin );
		$count  = count( $existed_plugin );

		if ( null === $status ) {
			$status = new \WP_Error( 'filesystem-not-writable', _n( 'Unable to delete plugin. Filesystem is readonly.', 'Unable to delete plugins. Filesystem is readonly.', $count, 'roxwp-site-mon' ) );
		} else if ( ! is_wp_error( $status ) ) {
			$status = [
				'status'  => true,
				'message' => _n( 'Specified plugin deleted', 'Specified plugins deleted', $count, 'roxwp-site-mon' ),
				'plugins' => $existed_plugin,
			];
		}

		return rest_ensure_response( $status );
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function update_plugins( \WP_REST_Request $request ) {

	}

	/**
	 * @param $plugin
	 *
	 * @return bool
	 */
	private function is_plugin_exists( $plugin ) {
		return file_exists( WP_PLUGIN_DIR . '/' . $plugin );
	}

	/**
	 * @param $plugins
	 *
	 * @return array
	 */
	private function existed_plugins( $plugins ) {
		$existed_plugins = [];
		foreach ( $plugins as $plugin ) {
			if ( $this->is_plugin_exists( $plugin ) ) {
				$existed_plugins[] = $plugin;
			}
		}

		return $existed_plugins;
	}

}
