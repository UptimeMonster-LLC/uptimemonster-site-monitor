<?php
/**
 * Plugin Updater API
 *
 * @package UptimeMonster\SiteMonitor\API
 * @version 1.0.0
 */

namespace UptimeMonster\SiteMonitor\Api\Controllers\V1;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

use Plugin_Upgrader;
use UptimeMonster\SiteMonitor\Api\Controllers\Controller_Base;
use UptimeMonster\SiteMonitor\Api\Controllers\V1\Site_Health\UptimeMonster_Debug_Data;
use UptimeMonster\SiteMonitor\Api\Controllers\V1\Site_Health\UptimeMonster_Update_Check;
use WP_Ajax_Upgrader_Skin;
use WP_Error;
use WP_Filesystem_Base;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

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
					'methods'             => WP_REST_Server::CREATABLE,
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
					'methods'             => WP_REST_Server::CREATABLE,
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
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'deactivate_plugins' ],
					'permission_callback' => [ $this, 'get_route_access' ],
					'args'                => [],
				],
			]
		);

		// Register uninstall plugin.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/uninstall',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'uninstall_plugins' ],
					'permission_callback' => [ $this, 'get_route_access' ],
					'args'                => [],
				],
			]
		);

		// Register delete plugin.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/delete',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'delete_plugins' ],
					'permission_callback' => [ $this, 'get_route_access' ],
					'args'                => [],
				],
			]
		);

		// Register update plugin.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/update',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_plugins' ],
					'permission_callback' => [ $this, 'get_route_access' ],
					'args'                => [],
				],
			]
		);
	}

	/**
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response|
	 */
	public function install_plugins( WP_REST_Request $request ) {
		set_time_limit( 0 );

		$response = [];

		$data = json_decode( $request->get_body() );

		$check = $this->check_slug( $data );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

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

		$skin         = new WP_Ajax_Upgrader_Skin();
		$upgrader     = new Plugin_Upgrader( $skin );
		$is_installed = false;
		$response     = [];
		$plugins      = $data->slugs;

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
					if ( $wp_filesystem instanceof WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
						$status['message'] = esc_html( $wp_filesystem->errors->get_error_message() );
					} else {
						$status['message'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.' );
					}
				}

				if ( ! isset( $status['message'] ) ) {
					$status['status']  = true;
					$status['message'] = sprintf( __( '%s installed.', 'uptime' ), $slug );
					$is_installed      = true;
				}

			} else {
				$status['status']  = false;
				$status['message'] = sprintf( __( '%s already exists.', 'uptime' ), $slug );
			}
			$response[ $plugin ] = $status;

		}

		$response = [ 'status' => true, 'data' => $response ];
		if ( $is_installed ) {
			$this->add_extra_data( $response );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response|
	 */
	public function activate_plugins( $request ) {
		set_time_limit( 0 );
		$data = json_decode( $request->get_body() );

		$check = $this->check_slug( $data );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$response     = [];
		$changed      = false;
		$plugins_data = $this->get_plugins();

		foreach ( $data->slugs as $plugin ) {
			$plugin_data = $plugins_data[ $plugin ] ?? false;

			if ( ! $plugin_data ) {
				$response[ $plugin ] = [
					'status'  => false,
					'message' => sprintf( __( 'Plugin (%s) does not exists.', 'uptime' ), $plugin ),
				];

				$changed = true;

				continue;
			}

			$status = [];

			$plugin = esc_attr( $plugin );

			if ( is_plugin_active( $plugin ) ) {
				$status = [
					'status'  => false,
					'message' => sprintf( __( '“%s” already active', 'uptime' ), $plugin_data['Name'] ),
				];
			} else {
				$activate = activate_plugin( $plugin, '', false, false );

				if ( is_wp_error( $activate ) ) {
					$status['status']  = false;
					$status['message'] = sprintf( __( 'Error activating “%1$s”. Error: %2$s', 'uptime' ), $plugin_data['Name'], $activate->get_error_message() );
				} else {
					$status['status']  = true;
					$status['message'] = sprintf( __( '“%s” activated', 'uptime' ), $plugin_data['Name'] );
					$changed           = true;
				}
			}

			$response[ $plugin ] = $status;
		}

		$response = [ 'status' => true, 'data' => $response ];

		if ( $changed ) {
			$this->add_extra_data( $response );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response|
	 */
	public function deactivate_plugins( WP_REST_Request $request ) {
		set_time_limit( 0 );

		$data = json_decode( $request->get_body() );

		$check = $this->check_slug( $data );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		if ( ! function_exists( 'deactivate_plugins' ) || ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$response     = [];
		$plugins      = $data->slugs;
		$changed      = false;
		$plugins_data = $this->get_plugins();

		$this->self_check( $plugins, $response, $plugins_data );

		foreach ( $plugins as $plugin ) {
			$plugin_data = $plugins_data[ $plugin ] ?? false;

			if ( ! $plugin_data ) {
				$response[ $plugin ] = [
					'status'  => false,
					'message' => sprintf( __( 'Plugin (%s) does not exists.', 'uptime' ), $plugin ),
				];

				$changed = true;

				continue;
			}

			if ( is_plugin_inactive( $plugin ) ) {
				$response[ $plugin ] = [
					'status'  => false,
					'message' => sprintf( __( '“%s” already inactive.', 'uptime' ), $plugin_data['Name'] ),
				];

				continue;
			}

			$status = [];
			if ( is_plugin_inactive( $plugin ) ) {
				$status['status']  = false;
				$status['message'] = sprintf( __( 'Unable to deactivated “%s”.', 'uptime' ), $plugin_data['Name'] );
			} else {
				deactivate_plugins( $plugin );
				$status['status']  = true;
				$status['message'] = sprintf( __( '“%s” deactivated.', 'uptime' ), $plugin_data['Name'] );

				if ( ! $changed ) {
					$changed = true;
				}
			}

			$response[ $plugin ] = $status;
		}

		$response = [ 'status' => true, 'data' => $response ];

		if ( $changed ) {
			$this->add_extra_data( $response );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function uninstall_plugins( WP_REST_Request $request ) {
		set_time_limit( 0 );

		$data = json_decode( $request->get_body() );

		$check = $this->check_slug( $data );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$response     = [];
		$plugins      = $data->slugs;
		$changed      = false;
		$plugins_data = $this->get_plugins();

		// Prevent from uninstall self.
		$this->self_check( $plugins, $response, $plugins_data );

		foreach ( $plugins as $plugin ) {
			$plugin_data = $plugins_data[ $plugin ] ?? false;

			if ( ! $plugin_data ) {
				$response[ $plugin ] = [
					'status'  => false,
					'message' => sprintf( __( 'Plugin (%s) does not exists.', 'uptime' ), $plugin ),
				];

				$changed = true;

				continue;
			}

			$status = [];

			if ( is_uninstallable_plugin( $plugin ) ) {
				uninstall_plugin( $plugin );

				if ( is_plugin_active( $plugin ) ) {
					$status['status']  = false;
					$status['message'] = __( 'Failed to uninstallation “%s”. This Plugin is currently Active.', 'uptime' );
				} else {
					$status  = [
						'status'  => true,
						'message' => sprintf( __( '“%s” uninstalled successfully.', 'uptime' ), $plugin_data['Name'] ),
					];
					$changed = true;
				}
			} else {
				$status['status']  = false;
				$status['message'] = sprintf( __( "“%s” can't be uninstalled.", 'uptime' ), $plugin_data['Name'] );
			}

			$response[ $plugin ] = $status;
		}

		$response = [ 'status' => true, 'data' => $response ];

		if ( $changed ) {
			$this->add_extra_data( $response );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response|
	 */
	public function delete_plugins( WP_REST_Request $request ) {
		set_time_limit( 0 );
		$data = json_decode( $request->get_body() );

		$check = $this->check_slug( $data );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'delete_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$response     = [];
		$plugins      = $data->slugs;
		$changed      = false;
		$plugins_data = $this->get_plugins();

		// Prevent from delete self.
		$this->self_check( $plugins, $response, $plugins_data );

		foreach ( $plugins as $plugin ) {
			$plugin_data = $plugins_data[ $plugin ] ?? false;

			if ( ! $plugin_data ) {
				$response[ $plugin ] = [
					'status'  => false,
					'message' => sprintf( __( 'Plugin (%s) does not exists.', 'uptime' ), $plugin ),
				];

				$changed = true;

				continue;
			}

			$deleted = delete_plugins( $plugin );

			if ( null === $deleted ) {
				$count    = count( $plugins );
				$response = [
					'status'  => false,
					'message' => _n( 'Unable to delete plugin. Filesystem is readonly.', 'Unable to delete plugins. Filesystem is readonly.', $count, 'uptime' ),
				];
				// file system is not writable, we can't delete anything, no need to loop.
				// break;
				return rest_ensure_response( $response );
			} else if ( is_wp_error( $deleted ) ) {
				$status = [
					'status'  => false,
					'message' => sprintf( __( 'Failed to delete “%1$s”. Error: %2$s', 'uptime' ), $plugin_data['Name'], $deleted->get_error_message() ),
				];
			} else {
				$status  = [
					'status'  => true,
					'message' => sprintf( __( '“%s” deleted successfully.', 'uptime' ), $plugin_data['Name'] ),
				];
				$changed = true;
			}

			$response[ $plugin ] = $status;
		}

		$response = [ 'status' => true, 'data' => $response ];

		if ( $changed ) {
			$this->add_extra_data( $response );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response|
	 */
	public function update_plugins( WP_REST_Request $request ) {
		set_time_limit( 0 );

		$data = json_decode( $request->get_body() );

		$check = $this->check_slug( $data );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$response     = [];
		$plugins      = $data->slugs;
		$changed      = false;
		$plugins_data = $this->get_plugins();

		// Prevent from uninstall self.
		$this->self_check( $plugins, $response, $plugins_data );

		wp_update_plugins();

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );

		foreach ( $plugins as $plugin ) {
			$plugin_data = $plugins_data[ $plugin ] ?? false;

			if ( ! $plugin_data ) {
				$response[ $plugin ] = [
					'status'  => false,
					'message' => sprintf( __( 'Plugin (%s) does not exists.', 'uptime' ), $plugin ),
				];

				$changed = true;

				continue;
			}

			$status = [];

			$plugin = plugin_basename( sanitize_text_field( wp_unslash( $plugin ) ) );
			$result = $upgrader->bulk_upgrade( [ $plugin ] );

			$status['status'] = true;
			if ( is_wp_error( $skin->result ) ) {
				$status['status']  = false;
				$status['message'] = sprintf( __( 'Failed to update “%1$s”. Error: %2$s', 'uptime' ), $plugin_data['Name'], $skin->result->get_error_message() );
			} elseif ( $skin->get_errors()->has_errors() ) {
				$status['status']  = false;
				$status['message'] = sprintf( __( 'Failed to update “%1$s”. Error: %2$s', 'uptime' ), $plugin_data['Name'], $skin->get_error_messages() );
			} elseif ( is_array( $result ) && ! empty( $result[ $plugin ] ) ) {
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
					$status['message'] = sprintf( __( '“%s” is at the latest version.', 'uptime' ), $plugin_data['Name'] );
				} else {
					$plugin_data_new   = get_plugins( '/' . $result[ $plugin ]['destination_name'] );
					$plugin_data_new   = reset( $plugin_data_new );
					$version           = ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : __( 'Unknown', 'uptime' );
					$version_new       = ! empty( $plugin_data_new['Version'] ) ? $plugin_data_new['Version'] : '';
					$status['message'] = sprintf( __( '“%1$s” Updated from version %2$s to %3$s.', 'uptime' ), $plugin_data['Name'], $version, $version_new );
					$changed           = true;
				}
			} elseif ( false === $result ) {
				$count    = count( $plugins );
				$response = [
					'status'  => false,
					'message' => _n( 'Unable to delete plugin. Filesystem is readonly.', 'Unable to delete plugins. Filesystem is readonly.', $count, 'uptime' ),
				];
				// file system is not writable, we can't delete anything, no need to loop.
				// break;
				return rest_ensure_response( $response );

				/*global $wp_filesystem;
				// Pass through the error from WP_Filesystem if one was raised.
				if ( $wp_filesystem instanceof WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
					$status['message'] = esc_html( $wp_filesystem->errors->get_error_message() );
				} else {
					$status['message'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.', 'uptimemonster-site-monitor' );
				}*/
			}

			$response[ $plugin ] = $status;
		}

		$response = [ 'status' => true, 'data' => $response ];

		if ( $changed ) {
			$this->add_extra_data( $response );
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

	protected function self_check( &$plugins, &$response, $plugins_data ) {
		$plugins = array_filter( $plugins ); // clean up.

		if ( in_array( UMSM_PLUGIN_BASENAME, $plugins, true ) ) {
			$plugins = array_flip( $plugins );

			unset( $plugins[ UMSM_PLUGIN_BASENAME ] );

			$response[ UMSM_PLUGIN_BASENAME ] = [
				'status'  => false,
				'message' => sprintf( __( 'Self (%s) destruction is prohibited', 'uptime' ), $plugins_data[ UMSM_PLUGIN_BASENAME ]['Name'] ?? UMSM_PLUGIN_BASENAME ),
			];

			$plugins = array_flip( $plugins );
		}
	}

	/**
	 * @param $plugins
	 *
	 * @return array|WP_Error|WP_HTTP_Response|WP_REST_Response
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
	 * @return bool|WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	private function check_slug( $data ) {
		if ( empty( $data->slugs ) || ! is_array( $data->slugs ) ) {
			return new WP_Error( 'plugin-not-specified', __( 'No plugin specified.', 'uptime' ) );
		}

		return true;
	}
}
