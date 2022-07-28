<?php

namespace AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\V1;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

use AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\Controller_Base;
use AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\V1\Site_Health\RoxWP_Debug_Data;
use AbsolutePlugins\RoxwpSiteMonitor\Api\Controllers\V1\Site_Health\RoxWP_Update_Check;


/**
 * Class Plugins
 */
class Plugins extends Controller_Base {

	protected static $installed_plugins;

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = '/plugin';

	/**
	 * Class For Debug data.
	 *
	 * @var object
	 */
	protected $debug_model;

	/**
	 * Update Check.
	 *
	 * @var object
	 */
	protected $update_check_model;

	/**
	 * constructor.
	 */
	public function __construct() {
		// Health data
		$this->update_check_model = new RoxWP_Update_Check();

		// Debug data.
		$this->debug_model = new RoxWP_Debug_Data();
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
					'callback'            => array( $this, 'install_plugins' ),
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
					'callback'            => array( $this, 'uninstall_plugins' ),
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
	public function install_plugins( \WP_REST_Request $request ) {
		set_time_limit( 0 );

		$response = [];

		$data = json_decode( $request->get_body() );
		$this->check_slug( $data );

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		if ( ! class_exists( 'WP_Ajax_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
		}

		if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$skin         = new \WP_Ajax_Upgrader_Skin();
		$upgrader     = new \Plugin_Upgrader( $skin );
		$is_installed = false;
		$response = [];
		$plugins = $data->slugs;

		foreach ( $plugins as $plugin ) {
			$status = array( 'status' => false );
			$slug   = $this->get_slug( $plugin );

			if ( ! $this->is_plugin_exists( $plugin ) ) {
				$api = plugins_api(
					'plugin_information',
					array(
						'slug'   => $slug,
						'fields' => array(
							'sections' => false,
						),
					)
				);

				if ( is_wp_error( $api ) ) {
					$status['message'] = $api->get_error_message();
					continue;
				}

				$result = $upgrader->install( $api->download_link );

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
					} else {
						$status['message'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.' );
					}
				}

				if ( ! isset( $status['message'] ) ) {
					$status['status']  = true;
					$status['message'] = sprintf( __( '%s installed.', 'roxwp-site-mon' ), $slug );
					$is_installed      = true;
				}

			} else {
				$status['status']  = false;
				$status['message'] = sprintf( __( '%s already exists.', 'roxwp-site-mon' ), $slug );
			}
			$response[ $plugin ] = $status;

		}

		$response = [ 'status' => true, 'data' => $response ];
		if ( $is_installed ) {
			$response['extra'] = [
				'site_health' => $this->update_check_model->get_site_health() ? $this->update_check_model->get_site_health() : [],
				'site_info'   => $this->debug_model->debug_data() ? $this->debug_model->debug_data() : [],
			];
		}

		return rest_ensure_response( $response );
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function activate_plugins( $request ) {
		set_time_limit( 0 );
		$data = json_decode( $request->get_body() );

		if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$this->check_slug( $data );
		$response = [];
		$plugins =  $data->slugs;

		$is_activated = false;


		foreach ( $plugins as $plugin ) {
			$status = array();

			$plugin = esc_attr( $plugin );

			if ( $this->is_plugin_exists( $plugin ) ) {

				if ( is_plugin_active( $plugin ) ) {
					$status = [
						'status'  => false,
						'message' => __( 'Plugin already active', 'roxwp-site-mon' ),
					];
				} else {
					$activate = activate_plugin( $plugin, '', false, false );

					if ( is_wp_error( $activate ) ) {
						$status['status']  = false;
						$status['message'] = $activate->get_error_message();
					} else {
						$status['status']  = true;
						$status['message'] = __( 'Plugin activated', 'roxwp-site-mon' );
						$is_activated      = true;
					}
				}
			} else {
				$status['status']  = false;
				$status['message'] = __( 'Plugin does not exist', 'roxwp-site-mon' );
			}
			$response[ $plugin ] = $status;
		}

		$response = ['status'=>true, 'data'=>$response ];

		if ( $is_activated ) {
			$response['extra'] = [
				'site_health' => $this->update_check_model->get_site_health() ? $this->update_check_model->get_site_health() : [],
				'site_info'   => $this->debug_model->debug_data() ? $this->debug_model->debug_data() : [],
			];
		}

		return rest_ensure_response( $response );
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function deactivate_plugins( \WP_REST_Request $request ) {
		set_time_limit( 0 );

		$data = json_decode( $request->get_body() );

		if ( ! function_exists( 'deactivate_plugins' ) || ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! isset( $data->slugs ) || empty( $data->slugs ) || ! is_array( $data->slugs ) ) {
			return rest_ensure_response( [
				'status'  => false,
				'message' => __( 'No plugin specified.', 'roxwp-site-mon' ),
			] );
		}

		$response = [];
		$plugins  = $data->slugs;

		$this->self_check( $plugins, $response );

		$is_deactivated = false;

		foreach ( $plugins as $plugin ) {
			$status = array();

			if ( $this->is_plugin_exists( $plugin ) ) {
				if ( is_plugin_inactive( $plugin ) ) {
					$status[] = [
						'status'  => false,
						'message' => __( 'Plugin already inactive', 'roxwp-site-mon' ),
					];
				} else {

					if ( is_plugin_inactive( $plugin ) ) {
						$status['status']  = false;
						$status['message'] = __( 'Unable to deactivated.', 'roxwp-site-mon' );
					} else {
						deactivate_plugins( $plugin );
						$status['status']  = true;
						$status['message'] = __( 'Plugin deactivated', 'roxwp-site-mon' );
						$is_deactivated    = true;
					}
				}
			} else {
				$status['status']  = false;
				$status['message'] = __( 'Plugin does not exist', 'roxwp-site-mon' );
			}
			$response[ $plugin ] = $status;
		}

		$response = [ 'status' => true, 'data' => $response ];

		if ( $is_deactivated ) {
			$response['extra'] = [
				'site_health' => $this->update_check_model->get_site_health() ? $this->update_check_model->get_site_health() : [],
				'site_info'   => $this->debug_model->debug_data() ? $this->debug_model->debug_data() : [],
			];
		}

		return rest_ensure_response( $response );
	}


	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function uninstall_plugins( \WP_REST_Request $request ) {
		set_time_limit( 0 );

		$data = json_decode( $request->get_body() );

		if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$this->check_slug( $data );
		$response = [];
		$plugins  = $data->slugs;


		// Prevent from uninstall self.
		$this->self_check( $plugins, $response );
		$is_uninstalled = false;

		foreach ( $plugins as $plugin ) {
			$status = array();

			if ( $this->is_plugin_exists( $plugin )){
				if ( is_uninstallable_plugin( $plugin ) ) {
					uninstall_plugin( $plugin );

					if ( is_plugin_active( $plugin ) ) {
						$status['status']  = false;
						$status['message'] = __( 'Uninstallation failed. This Plugin is currently Active.', 'roxwp-site-mon' );
					} else {
						$status         = [
							'status'  => true,
							'message' => __( 'Plugin uninstalled successfully.', 'roxwp-site-mon' ),
						];
						$is_uninstalled = true;
					}
				} else {
					$status['status']  = false;
					$status['message'] = __( "Plugin can't be uninstalled.", "roxwp-site-mon" );
				}
			}else{
				$status['status']  = false;
				$status['message'] = __( 'Plugin does not exist', 'roxwp-site-mon' );
			}
			$response[ $plugin ] = $status;
		}

		$response = [ 'status' => true, 'data' => $response ];

		if ( $is_uninstalled ) {
			$response['extra'] = [
				'site_health' => $this->update_check_model->get_site_health() ? $this->update_check_model->get_site_health() : [],
				'site_info'   => $this->debug_model->debug_data() ? $this->debug_model->debug_data() : [],
			];
		}

		return rest_ensure_response( $response );
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function delete_plugins( \WP_REST_Request $request ) {
		set_time_limit( 0 );
		$data     = json_decode( $request->get_body() );

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'delete_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$this->check_slug( $data );
		$response = [];
		$plugins = $data->slugs;

		// Prevent from delete self.
		$this->self_check( $plugins, $response );
		$is_deleted = false;
		$count = count($plugins);

		foreach ( $plugins as $plugin ){
			$status = array();

			if ( $this->is_plugin_exists( $plugin) ){

				$status = delete_plugins( $plugin );
				if ( null === $status ) {
					$response['status'] = false;
					$response['message']   = new \WP_Error( 'filesystem-not-writable', _n( 'Unable to delete plugin. Filesystem is readonly.', 'Unable to delete plugins. Filesystem is readonly.', $count, 'roxwp-site-mon' ) );
				} else if ( ! is_wp_error( $status ) ) {
					$status   = [
						'status'  => true,
						'message' => _n( 'Specified plugin deleted', 'Specified plugins deleted', $count, 'roxwp-site-mon' ),
					];
					$is_deleted = true;
				}
			} else{
				$status['status']  = false;
				$status['message'] = __( 'Plugin does not exist', 'roxwp-site-mon' );
			}
			$response[ $plugin ] = $status;
		}
		$response = [ 'status' => true, 'data' => $response ];

		if ( $is_deleted ) {
			$response['extra'] = [
				'site_health' => $this->update_check_model->get_site_health() ? $this->update_check_model->get_site_health() : [],
				'site_info'   => $this->debug_model->debug_data() ? $this->debug_model->debug_data() : [],
			];
		}
		return rest_ensure_response( $response );
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function update_plugins( \WP_REST_Request $request ) {
		set_time_limit( 0 );

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$data     = json_decode( $request->get_body() );

		$this->check_slug( $data );
		$response = [];
		$plugins  = $data->slugs;

		// Prevent from uninstall self.
		$this->self_check( $plugins, $response );

		wp_update_plugins();

		$skin       = new \WP_Ajax_Upgrader_Skin();
		$upgrader   = new \Plugin_Upgrader( $skin );
		$is_updated = false;

		foreach ( $plugins as $plugin ) {
			$status      = array();
			if ( $this->is_plugin_exists( $plugin ) ) {
				$plugin      = plugin_basename( sanitize_text_field( wp_unslash( $plugin ) ) );
				$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
				$result      = $upgrader->bulk_upgrade( array( $plugin ) );

				$status['status']  = true;
				if ( is_wp_error( $skin->result ) ) {
					$status['message'] = $skin->result->get_error_message();
				}
				elseif ( $skin->get_errors()->has_errors() ) {
					$status['message'] = $skin->get_error_messages();
				}
				elseif ( is_array( $result ) && ! empty( $result[ $plugin ] ) ) {
					/*
					 * Plugin is already at the latest version.
					 *
					 * This may also be the return value if the `update_plugins` site transient is empty,
					 * e.g. when you update two plugins in quick succession before the transient repopulates.
					 *
					 * Preferably something can be done to ensure `update_plugins` isn't empty.
					 * For now, surface some sort of error here.
					 */
					if ( true === $result[ $plugin ] ) {
						$status['message'] = $upgrader->strings['up_to_date'];
					} else {
						$plugin_data       = get_plugins( '/' . $result[ $plugin ]['destination_name'] );
						$plugin_data       = reset( $plugin_data );
						$version           = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '';
						$status['message'] = sprintf( __( '%s Updated. New version %s.', 'roxwp-site-mon' ), $plugin, $version );
						$is_updated        = true;
					}
				}
				elseif ( false === $result ) {
					global $wp_filesystem;
					// Pass through the error from WP_Filesystem if one was raised.
					if ( $wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
						$status['message'] = esc_html( $wp_filesystem->errors->get_error_message() );
					} else {
						$status['message'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.', 'roxwp-site-monitor' );
					}
				}

			} else{
				$status['status']  = false;
				$status['message'] = __( 'Plugin does not exist', 'roxwp-site-mon' );
			}
			$response[ $plugin ] = $status;
		}

		$response = [ 'status' => true, 'data' => $response ];

		if ( $is_updated ) {
			$response['extra'] = [
				'site_health' => $this->update_check_model->get_site_health() ? $this->update_check_model->get_site_health() : [],
				'site_info'   => $this->debug_model->debug_data() ? $this->debug_model->debug_data() : [],
			];
		}

		return rest_ensure_response( $response );
	}

	/**
	 * @param $plugin_path
	 *
	 * @return string
	 */
	private function get_slug( $plugin_path ) {

		if ( strpos( $plugin_path, '/' ) > 0 ) {
			$temparr = explode( '/', $plugin_path );

			if ( isset( $temparr[0] ) ) {
				return $temparr[0];
			}

		}

		return $plugin_path;
	}

	/**
	 * @return array
	 */
	protected function get_plugins() {
		if ( null === self::$installed_plugins ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			self::$installed_plugins = apply_filters( 'all_plugins', get_plugins() );
		}

		return self::$installed_plugins;
	}

	/**
	 * @param $plugin
	 *
	 * @return bool
	 */
	private function is_plugin_exists( $plugin ): bool {
		$plugins = $this->get_plugins();
		return isset( $plugins[ $plugin ] );
	}

	protected function self_check( &$plugins, &$response ) {

		if ( in_array( ROXWP_SM_PLUGIN_BASENAME, $plugins, true ) ) {
			$plugins = array_flip( $plugins );

			unset( $plugins[ ROXWP_SM_PLUGIN_BASENAME ] );

			$response[ ROXWP_SM_PLUGIN_BASENAME ] = [
				'status'  => false,
				'message' => __( 'Self destruction is prohibited', 'roxwp-site-mon' ),
			];

			$plugins = array_flip( $plugins );
		}
	}

	/**
	 * @param $plugins
	 *
	 * @return array|\WP_Error|\WP_HTTP_Response|\WP_REST_Response
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

	/**
	 * @param $data
	 *
	 * @return void|\WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	private function check_slug( $data ){
		if ( ! isset( $data->slugs ) || empty( $data->slugs ) || ! is_array( $data->slugs ) ) {
			return rest_ensure_response( [
				'status'  => false,
				'message' => __( 'No plugin specified.', 'roxwp-site-mon' ),
			] );
		}
	}
}
