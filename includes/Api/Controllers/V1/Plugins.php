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
	exit; // Exit if accessed directly
}

use Plugin_Upgrader;
use UptimeMonster\SiteMonitor\Api\Controllers\Controller_Base;
use WP_Ajax_Upgrader_Skin;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

if ( ! function_exists( 'deactivate_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if ( ! class_exists( '\WP_Upgrader' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
}

if ( ! function_exists( 'plugins_api' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
}

if ( ! class_exists( '\WP_Ajax_Upgrader_Skin' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
}

if ( ! class_exists( '\WP_Filesystem_Base' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
}

if ( ! function_exists( 'request_filesystem_credentials' ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
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
	 *
	 * @return void
	 */
	public function register_routes() {

		// Register install plugin.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/install',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'install_plugins' ],
					'permission_callback' => [ $this, 'get_route_access' ],
					'args'                => [],
				],
			]
		);

		// Register activate plugin.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/activate',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'activate_plugins' ],
					'permission_callback' => [ $this, 'get_route_access' ],
					'args'                => [],
				],
			]
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
	public function install_plugins( $request ) {

		$data  = json_decode( $request->get_body() );
		$check = $this->check_slug( $data );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$skin         = new WP_Ajax_Upgrader_Skin();
		$upgrader     = new Plugin_Upgrader( $skin );
		$is_installed = false;
		$response     = [];
		$plugins_data = $this->get_plugins();

		foreach ( $data->slugs as $plugin ) {
			$plugin      = sanitize_text_field( wp_unslash( $plugin ) );
			$slug        = $this->get_slug( $plugin );
			$plugin_data = $plugins_data[ $plugin ] ?? false;

			if ( $plugin_data ) {
				$response[ $plugin ] = [
					'status'  => false,
					// translators: Plugin name
					'message' => sprintf( esc_html__( '%s already installed.', 'uptimemonster-site-monitor' ), $plugin_data['Name'] ),
				];
				continue;
			}

			$api = plugins_api( 'plugin_information', [
				'slug'   => $slug,
				'fields' => [
					'sections' => false,
				],
			] );

			if ( is_wp_error( $api ) ) {
				$response[ $plugin ] = [
					'status'  => false,
					'message' => $api->get_error_message(),
				];
				continue;
			}

			$status = [ 'status' => false ];
			$result = $upgrader->install( $api->download_link );

			if ( false === $result ) {
				return new WP_Error( 'fs-readonly', esc_html__( 'Unable to connect to the filesystem. Filesystem seems readonly or credentials are not provided in wp-config.php.', 'uptimemonster-site-monitor' ) );
			}

			/* translators: 1. Theme/plugin name, 2. Error details */
			$failed = esc_html__( '%1$s installation failed. Error: %2$s', 'uptimemonster-site-monitor' );
			if ( is_wp_error( $result ) ) {
				$status['message'] = sprintf( $failed, $api->name, $result->get_error_message() );
			} elseif ( is_wp_error( $skin->result ) ) {
				$status['message'] = sprintf( $failed, $api->name, $skin->result->get_error_message() );
			} elseif ( $skin->get_errors()->has_errors() ) {
				$status['message'] = sprintf( $failed, $api->name, $skin->get_error_messages() );
			} else {
				$status['status'] = true;
				/* translators: Theme/Plugin name */
				$status['message'] = sprintf( esc_html__( '%s successfully installed.', 'uptimemonster-site-monitor' ), $api->name );
				$is_installed      = true;
			}

			$response[ $plugin ] = $status;
		}

		$response = [
			'status' => true,
			'data'   => $response,
		];

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

		$data  = json_decode( $request->get_body() );
		$check = $this->check_slug( $data );

		if ( is_wp_error( $check ) ) {
			return $check;
		}


		$response     = [];
		$changed      = false;
		$plugins_data = $this->get_plugins();

		foreach ( $data->slugs as $plugin ) {
			$plugin      = sanitize_text_field( wp_unslash( $plugin ) );
			$plugin_data = $plugins_data[ $plugin ] ?? false;

			if ( ! $plugin_data ) {
				$response[ $plugin ] = [
					'status'  => false,
					// translators: Plugin name
					'message' => sprintf( esc_html__( 'Plugin %s does not exists or already deleted.', 'uptimemonster-site-monitor' ), $plugin ),
				];

				$changed = true;

				continue;
			}

			if ( is_plugin_active( $plugin ) ) {
				$response[ $plugin ] = [
					'status'  => false,
					// translators: Plugin name
					'message' => sprintf( esc_html__( '“%s” already active', 'uptimemonster-site-monitor' ), $plugin_data['Name'] ),
				];

				continue;
			}

			$status   = [ 'status' => false ];
			$activate = activate_plugin( $plugin, '', false, false );

			if ( is_wp_error( $activate ) ) {
				// translators: Plugin name
				$status['message'] = sprintf( esc_html__( 'Error activating “%1$s”. Error: %2$s', 'uptimemonster-site-monitor' ), $plugin_data['Name'], $activate->get_error_message() );
			} else {
				$status['status'] = true;
				// translators: Plugin name
				$status['message'] = sprintf( esc_html__( '“%s” activated', 'uptimemonster-site-monitor' ), $plugin_data['Name'] );
				$changed           = true;
			}

			$response[ $plugin ] = $status;
		}

		$response = [
			'status' => true,
			'data'   => $response,
		];

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

		$data  = json_decode( $request->get_body() );
		$check = $this->check_slug( $data );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$response     = [];
		$changed      = false;
		$plugins_data = $this->get_plugins();

		$this->self_check( $data->slugs, $response );

		foreach ( $data->slugs as $plugin ) {
			$plugin      = sanitize_text_field( wp_unslash( $plugin ) );
			$plugin_data = $plugins_data[ $plugin ] ?? false;

			if ( ! $plugin_data ) {
				$response[ $plugin ] = [
					'status'  => false,
					// translators: Plugin name
					'message' => sprintf( esc_html__( 'Plugin %s does not exists or already deleted.', 'uptimemonster-site-monitor' ), $plugin ),
				];

				$changed = true;

				continue;
			}

			if ( is_plugin_inactive( $plugin ) ) {
				$response[ $plugin ] = [
					'status'  => false,
					// translators: Plugin name
					'message' => sprintf( esc_html__( '“%s” already inactive.', 'uptimemonster-site-monitor' ), $plugin_data['Name'] ),
				];

				continue;
			}

			$status = [];
			if ( is_plugin_inactive( $plugin ) ) {
				$status['status'] = false;
				// translators: Plugin name
				$status['message'] = sprintf( esc_html__( 'Unable to deactivated “%s”.', 'uptimemonster-site-monitor' ), $plugin_data['Name'] );
			} else {
				deactivate_plugins( $plugin );
				$status['status'] = true;
				// translators: Plugin name
				$status['message'] = sprintf( esc_html__( '“%s” deactivated.', 'uptimemonster-site-monitor' ), $plugin_data['Name'] );

				if ( ! $changed ) {
					$changed = true;
				}
			}

			$response[ $plugin ] = $status;
		}

		$response = [
			'status' => true,
			'data'   => $response,
		];

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

		$data  = json_decode( $request->get_body() );
		$check = $this->check_slug( $data );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$response     = [];
		$changed      = false;
		$plugins_data = $this->get_plugins();

		// Prevent from uninstall self.
		$this->self_check( $data->slugs, $response );

		foreach ( $data->slugs as $plugin ) {
			$plugin      = sanitize_text_field( wp_unslash( $plugin ) );
			$plugin_data = $plugins_data[ $plugin ] ?? false;

			if ( ! $plugin_data ) {
				$response[ $plugin ] = [
					'status'  => false,
					// translators: Plugin name
					'message' => sprintf( esc_html__( 'Plugin %s does not exists or already deleted.', 'uptimemonster-site-monitor' ), $plugin ),
				];

				$changed = true;

				continue;
			}

			if ( is_plugin_active( $plugin ) ) {
				$response[ $plugin ] = [
					'status'  => false,
					// translators: Plugin name
					'message' => esc_html__( 'Failed to uninstallation “%s”. This Plugin is currently Active.', 'uptimemonster-site-monitor' ),
				];

				continue;
			}

			if ( ! is_uninstallable_plugin( $plugin ) ) {
				$response[ $plugin ] = [
					'status'  => false,
					// translators: Plugin name
					'message' => sprintf( esc_html__( 'Plugin “%s” can not be uninstalled.', 'uptimemonster-site-monitor' ), $plugin_data['Name'] ),
				];

				continue;
			}

			if ( true === uninstall_plugin( $plugin ) ) {
				$response[ $plugin ] = [
					'status'  => true,
					// translators: Plugin name
					'message' => sprintf( esc_html__( 'Plugin “%s” uninstalled successfully.', 'uptimemonster-site-monitor' ), $plugin_data['Name'] ),
				];

				$changed = true;
			} else {
				$response[ $plugin ] = [
					'status'  => true,
					// translators: Plugin name
					'message' => sprintf( esc_html__( 'Plugin “%s” does not have uninstall.php included.', 'uptimemonster-site-monitor' ), $plugin_data['Name'] ),
				];
			}
		}

		$response = [
			'status' => true,
			'data'   => $response,
		];

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

		$data  = json_decode( $request->get_body() );
		$check = $this->check_slug( $data );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$response     = [];
		$changed      = false;
		$plugins_data = $this->get_plugins();

		// Prevent from delete self.
		$this->self_check( $data->slugs, $response );

		foreach ( $data->slugs as $plugin ) {
			$plugin      = sanitize_text_field( wp_unslash( $plugin ) );
			$plugin_data = $plugins_data[ $plugin ] ?? false;

			if ( ! $plugin_data ) {
				$response[ $plugin ] = [
					'status'  => false,
					// translators: Plugin name
					'message' => sprintf( esc_html__( 'Plugin %s does not exists or already deleted.', 'uptimemonster-site-monitor' ), $plugin ),
				];

				$changed = true;

				continue;
			}

			$plugin  = plugin_basename( $plugin );
			$deleted = delete_plugins( [ $plugin ] );

			if ( null === $deleted ) {
				return new WP_Error( 'fs-readonly', esc_html__( 'Unable to connect to the filesystem. Filesystem seems readonly or credentials are not provided in wp-config.php.', 'uptimemonster-site-monitor' ) );
			}

			if ( is_wp_error( $deleted ) ) {
				$status = [
					'status'  => false,
					// translators: Plugin name
					'message' => sprintf( esc_html__( 'Failed to delete “%1$s”. Error: %2$s', 'uptimemonster-site-monitor' ), $plugin_data['Name'], $deleted->get_error_message() ),
				];
			} else {
				$status  = [
					'status'  => true,
					/* translators: Theme/Plugin name */
					'message' => sprintf( esc_html__( '%s successfully deleted.', 'uptimemonster-site-monitor' ), $plugin_data['Name'] ),
				];
				$changed = true;
			}

			$response[ $plugin ] = $status;
		}

		$response = [
			'status' => true,
			'data'   => $response,
		];

		if ( $changed ) {
			wp_clean_plugins_cache();
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

		$data = json_decode( $request->get_body() );

		$check = $this->check_slug( $data );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$response     = [];
		$changed      = false;
		$plugins_data = $this->get_plugins();

		// Prevent from uninstall self.
		$this->self_check( $data->slugs, $response );

		// checking update can output error if system failed to connect wp.org api.
		ob_start();
		wp_update_plugins();
		ob_get_clean();

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );

		foreach ( $data->slugs as $plugin ) {
			$plugin      = sanitize_text_field( wp_unslash( $plugin ) );
			$plugin_data = $plugins_data[ $plugin ] ?? false;

			if ( ! $plugin_data ) {
				$response[ $plugin ] = [
					'status'  => false,
					// translators: Plugin name
					'message' => sprintf( esc_html__( 'Plugin %s does not exists or already deleted.', 'uptimemonster-site-monitor' ), $plugin ),
				];

				// Send plugin list to uptime.
				$changed = true;

				continue;
			}

			$plugin  = plugin_basename( sanitize_text_field( wp_unslash( $plugin ) ) );
			$results = $upgrader->bulk_upgrade( [ $plugin ] );
			$result  = isset( $results[ $plugin ] ) ? $results[ $plugin ] : false;

			if ( false === $results || false === $result ) {
				// File system is not writable, we can't update anything, no need to loop.
				return new WP_Error( 'fs-readonly', esc_html__( 'Unable to connect to the filesystem. Filesystem seems readonly or credentials are not provided in wp-config.php.', 'uptimemonster-site-monitor' ) );
			}

			$status = [ 'status' => false ];

			/* translators: 1. Theme/plugin name, 2. Error details */
			$failed = esc_html__( 'Failed to update %1$s. Error: %2$s', 'uptimemonster-site-monitor' );
			if ( is_wp_error( $skin->result ) ) {
				$status['message'] = sprintf( $failed, $plugin_data['Name'], $skin->result->get_error_message() );
			} elseif ( $skin->get_errors()->has_errors() ) {
				$status['message'] = sprintf( $failed, $plugin_data['Name'], $skin->get_error_messages() );
			} elseif ( true === $result ) {
				// translators: Plugin name
				$status['message'] = sprintf( esc_html__( '“%s” is at the latest version.', 'uptimemonster-site-monitor' ), $plugin_data['Name'] );
			} else {
				$status['status'] = true;
				$plugin_file      = '/' . $result[ $plugin ]['destination_name'];
				$plugin_data_new  = get_plugins( $plugin_file );
				$plugin_data_new  = $plugin_data_new[ plugin_basename( $plugin_file ) ];
				$version          = ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : esc_html__( 'Unknown', 'uptimemonster-site-monitor' );
				$version_new      = ! empty( $plugin_data_new['Version'] ) ? $plugin_data_new['Version'] : '';
				// translators: Plugin name
				$status['message'] = sprintf( esc_html__( '“%1$s” Updated from version %2$s to %3$s.', 'uptimemonster-site-monitor' ), $plugin_data['Name'], $version, $version_new );
				$changed           = true;
			}

			$response[ $plugin ] = $status;
		}

		$response = [
			'status' => true,
			'data'   => $response,
		];

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
			$temp = explode( '/', $plugin_path );

			if ( isset( $temp[0] ) ) {
				return $temp[0];
			}
		}
		return $plugin_path;
	}

	/**
	 * @return array
	 */
	protected function get_plugins() {
		if ( null === self::$installed_plugins ) {
			self::$installed_plugins = apply_filters( 'all_plugins', get_plugins() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}

		return self::$installed_plugins;
	}

	protected function self_check( &$plugins, &$response ) {
		$plugins_data = $this->get_plugins();
		$plugins      = array_filter( $plugins ); // clean up.

		if ( in_array( UPTIMEMONSTER_SITE_PLUGIN_BASENAME, $plugins, true ) ) {
			$plugins = array_flip( $plugins );

			unset( $plugins[ UPTIMEMONSTER_SITE_PLUGIN_BASENAME ] );

			$response[ UPTIMEMONSTER_SITE_PLUGIN_BASENAME ] = [
				'status'  => false,
				// translators: Plugin name
				'message' => sprintf( esc_html__( 'Self (%s) destruction is prohibited', 'uptimemonster-site-monitor' ), $plugins_data[ UPTIMEMONSTER_SITE_PLUGIN_BASENAME ]['Name'] ?? UPTIMEMONSTER_SITE_PLUGIN_BASENAME ),
			];

			$plugins = array_flip( $plugins );
		}
	}

	/**
	 * @param $data
	 *
	 * @return bool|WP_Error
	 */
	private function check_slug( $data ) {
		if ( empty( $data->slugs ) || ! is_array( $data->slugs ) ) {
			return new WP_Error( 'plugin-not-specified', esc_html__( 'No plugin specified.', 'uptimemonster-site-monitor' ) );
		}

		return true;
	}
}

// End of file Plugins.php.
