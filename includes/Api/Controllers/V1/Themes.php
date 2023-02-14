<?php
/**
 * Theme Updater API
 *
 * @package UptimeMonster\SiteMonitor\API
 * @version 1.0.0
 */

namespace UptimeMonster\SiteMonitor\Api\Controllers\V1;

use Theme_Upgrader;
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
	die();
}

if ( ! class_exists( '\WP_Upgrader' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
}

if ( ! function_exists( 'delete_theme' ) ) {
	require_once ABSPATH . 'wp-admin/includes/theme.php';
}

if ( ! class_exists( '\WP_Ajax_Upgrader_Skin' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
}

if ( ! function_exists( 'request_filesystem_credentials' ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
}

if ( ! class_exists( '\WP_Filesystem_Base' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
}


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

	public function register_routes() {
		// Register install theme.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/install',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'install_themes' ],
					'permission_callback' => [ $this, 'get_route_access' ],
					'args'                => [],
				],
			]
		);
		// Register activate theme.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/activate',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'activate_theme' ],
					'permission_callback' => [ $this, 'get_route_access' ],
					'args'                => [],
				],
			]
		);

		// Register delete theme.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
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
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_themes' ),
					'permission_callback' => array( $this, 'get_route_access' ),
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response|
	 */
	public function install_themes( WP_REST_Request $request ) {
		set_time_limit( 0 );

		$data = json_decode( $request->get_body() );

		if ( empty( $data->slugs ) ) {
			return new WP_Error( 'no-theme-specified', __( 'No theme specified.', 'uptime' ) );
		}

		$skin         = new WP_Ajax_Upgrader_Skin();
		$upgrader     = new Theme_Upgrader( $skin );
		$response     = [];
		$is_installed = false;

		foreach ( $data->slugs as $slug ) {
			$slug  = sanitize_key( wp_unslash( $slug ) );
			$theme = wp_get_theme( $slug );

			if ( $theme->exists() ) {
				$response[ $slug ] = [
					'status'  => false,
					'message' => sprintf( __( 'Theme %s already installed.', 'uptime' ), (string) $theme ),
				];

				continue;
			}

			$api = themes_api( 'theme_information', [ 'slug' => $slug, 'fields' => [ 'sections' => false ] ] );

			if ( is_wp_error( $api ) ) {
				$response[ $slug ] = [
					'status'  => false,
					'message' => $api->get_error_message(),
				];

				continue;
			}

			$result = $upgrader->install( $api->download_link );

			if ( false === $result ) {
				// File system is not writable, we can't update anything, no need to loop.
				return new WP_Error( 'fs-readonly', __( 'Unable to connect to the filesystem. Filesystem seems readonly or credentials are not provided in wp-config.php.', 'uptime' ) );
			}

			$status = [ 'status' => false ];

			if ( is_wp_error( $result ) ) {
				$status['message'] = $result->get_error_message();
			} elseif ( is_wp_error( $skin->result ) ) {
				$status['message'] = $skin->result->get_error_message();
			} elseif ( $skin->get_errors()->has_errors() ) {
				$status['message'] = $skin->get_error_messages();
			} else {
				$status['status']  = true;
				$status['message'] = sprintf( __( 'Theme %s  installed.', 'uptime' ), $slug );
				$is_installed      = true;
			}

			$response[ $slug ] = $status;
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
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function activate_theme( $request ) {
		set_time_limit( 0 );

		$data = json_decode( $request->get_body() );
		$slug = $data->slugs[0] ?? ( $data->slug ?? false );
		$slug = sanitize_key( wp_unslash( $slug ) );

		if ( ! $slug ) {
			return new WP_Error( 'no-theme-specified', __( 'No theme specified.', 'uptime' ) );
		}

		if ( get_stylesheet() === $slug ) {
			return new WP_Error( 'active-theme', __( 'Requested theme already been activated.', 'uptime' ) );
		}

		$theme = wp_get_theme( $slug );

		if ( ! $theme->exists() ) {
			return new WP_Error( 'theme-not-installed', __( 'Requested theme is not installed.', 'uptime' ) );
		}

		$requirements = validate_theme_requirements( $slug );
		if ( is_wp_error( $requirements ) ) {
			return $requirements;
		}

		switch_theme( $slug );

		if ( get_stylesheet() === $slug ) {
			$response = [
				'status'  => true,
				'message' => sprintf( __( 'Theme %s successfully activated.', 'uptime' ), (string) $theme ),
			];
		} else {
			$response = [
				'status'  => false,
				'message' => sprintf( __( 'Unable to activate requested theme (%s).', 'uptime' ), (string) $theme ),
			];
		}

		if ( $response['status'] ) {
			$this->add_extra_data( $response );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function delete_themes( WP_REST_Request $request ) {

		set_time_limit( 0 );

		$data = json_decode( $request->get_body() );

		if ( empty( $data->slugs ) ) {
			return new WP_Error( 'no-theme-specified', __( 'No theme specified.', 'uptime' ) );
		}

		$response = [];

		$is_deleted = false;

		foreach ( $data->slugs as $slug ) {
			$slug  = sanitize_key( wp_unslash( $slug ) );
			$theme = wp_get_theme( $slug );

			if ( ! $theme->exists() ) {
				$response[ $slug ] = [
					'status'  => false,
					'message' => sprintf( __( 'Theme (%s) does not exists.', 'uptime' ), $slug ),
				];

				continue;
			}

			// Check filesystem credentials. `delete_theme()` will bail otherwise.
			$url = wp_nonce_url( 'themes.php?action=delete&stylesheet=' . urlencode( $slug ), 'delete-theme_' . $slug );

			$need_credentials = umsm_need_filesystem_credentials( $url );
			if ( $need_credentials ) {
				// File system is not writable, we can't delete anything, no need to loop.
				return $need_credentials;
			}

			/*$currentTheme = wp_get_theme();
			$currentTheme->get_stylesheet();
			$currentTheme->get_template();*/

			if ( get_stylesheet() === $slug ) {
				$response[ $slug ] = [
					'status'  => false,
					'message' => sprintf( __( '%s is the active theme. You cannot delete an active theme.', 'uptime' ), (string) $theme ),
				];
				continue;
			}

			if ( get_template() === $slug ) {
				$response[ $slug ] = [
					'status'  => false,
					'message' => sprintf( __( '%s is the parent theme of active theme. You cannot delete parent theme of active theme.', 'uptime' ), (string) $theme ),
				];
				continue;
			}

			$result = delete_theme( $slug );
			$status = [ 'status' => false ];

			if ( is_wp_error( $result ) ) {
				$status['message'] = $result->get_error_message();
			} elseif ( false === $result ) {
				$status['message'] = sprintf( __( 'Unable to delete requested theme (%s).', 'uptime' ), (string) $theme );
			} else {
				$status['status']  = true;
				$status['message'] = sprintf( __( 'Theme %s has been deleted.', 'uptime' ), (string) $theme );
				$is_deleted        = true;
			}

			$response[ $slug ] = $status;
		}

		$response = [ 'status' => true, 'data' => $response ];

		if ( $is_deleted ) {
			$this->add_extra_data( $response );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response|
	 */
	public function update_themes( WP_REST_Request $request ) {
		set_time_limit( 0 );

		$data = json_decode( $request->get_body() );

		if ( empty( $data->slugs ) ) {
			return new WP_Error( 'no-theme-specified', __( 'No theme specified.', 'uptime' ) );
		}

		$response   = [];
		$skin       = new WP_Ajax_Upgrader_Skin();
		$upgrader   = new Theme_Upgrader( $skin );
		$is_updated = false;

		// checking update can output error if system failed to connect wp.org api.
		ob_start();
		wp_update_themes();
		ob_get_clean();

		foreach ( $data->slugs as $slug ) {
			$slug  = sanitize_key( wp_unslash( $slug ) );
			$theme = wp_get_theme( $slug );

			if ( ! $theme->exists() ) {
				$response[ $slug ] = [
					'status'  => false,
					'message' => sprintf( __( 'Theme %s does not exists.', 'uptime' ), $slug ),
				];
				continue;
			}

			/**
			 * @var false|true[]|WP_Error[]|array[] $results
			 */
			$results = $upgrader->bulk_upgrade( [ $slug ] );
			$result  = $results[ $slug ] ?? false;

			if ( false === $results || false === $result ) {
				// File system is not writable, we can't update anything, no need to loop.
				return new WP_Error( 'fs-readonly', __( 'Unable to connect to the filesystem. Filesystem seems readonly or credentials are not provided in wp-config.php.', 'uptime' ) );
			}


			$status = [ 'status' => false ];

			if ( is_wp_error( $skin->result ) ) {
				$status['message'] = $skin->result->get_error_message();
			} elseif ( $skin->get_errors()->has_errors() ) {
				$status['message'] = $skin->get_error_messages();
			} elseif ( true === $result ) {
				// Theme is already at the latest version.
				$status['message'] = $upgrader->strings['up_to_date'];
			} else {
				$status['status']  = true;
				$status['message'] = sprintf( __( '%s  updated.', 'uptime' ), (string) $theme );
				$is_updated        = true;
			}

			$response[ $slug ] = $status;
		}

		$response = [ 'status' => true, 'data' => $response ];

		if ( $is_updated ) {
			$this->add_extra_data( $response );
		}

		return rest_ensure_response( $response );
	}
}
