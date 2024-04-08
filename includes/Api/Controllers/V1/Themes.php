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
	exit; // Exit if accessed directly
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

		$data = json_decode( $request->get_body() );

		if ( empty( $data->slugs ) ) {
			return new WP_Error( 'no-theme-specified', esc_html__( 'No theme specified.', 'uptimemonster-site-monitor' ) );
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$response = [];
		$changed  = false;

		foreach ( $data->slugs as $slug ) {
			$slug  = sanitize_key( wp_unslash( $slug ) );
			$theme = wp_get_theme( $slug );

			if ( $theme->exists() ) {
				$changed           = true;
				$response[ $slug ] = [
					'status'  => false,
					/* translators: Theme Name */
					'message' => sprintf( esc_html__( 'Theme %s already installed.', 'uptimemonster-site-monitor' ), (string) $theme ),
				];

				continue;
			}

			$api = themes_api( 'theme_information', [
				'slug'   => $slug,
				'fields' => [
					'sections' => false,
				],
			]);

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
				return new WP_Error( 'fs-readonly', esc_html__( 'Unable to connect to the filesystem. Filesystem seems readonly or credentials are not provided in wp-config.php.', 'uptimemonster-site-monitor' ) );
			}

			$status = [ 'status' => false ];

			/* translators: 1. Theme/plugin name, 2. Error details */
			$failed = esc_html__( '%1$s installation failed. Error: %2$s', 'uptimemonster-site-monitor' );

			if ( is_wp_error( $result ) ) {
				$status['message'] = sprintf( $failed, $api->name, $result->get_error_message() );
			} elseif ( is_wp_error( $skin->result ) ) {
				$status['message'] = sprintf( $failed, $api->name, $skin->result->get_error_message() );
			} elseif ( $skin->get_errors()->has_errors() ) {
				$status['message'] = sprintf( $failed, $api->name, $skin->get_error_messages() );
			} else {
				$theme            = wp_get_theme( $slug );
				$status['status'] = true;

				/* translators: Theme/Plugin name */
				$status['message'] = sprintf( esc_html__( '%s successfully installed.', 'uptimemonster-site-monitor' ), (string) $theme );
				$changed           = true;
			}

			$response[ $slug ] = $status;
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
	public function activate_theme( $request ) {

		$data = json_decode( $request->get_body() );
		$slug = $data->slugs[0] ?? ( $data->slug ?? false );
		$slug = sanitize_key( wp_unslash( $slug ) );

		if ( ! $slug ) {
			return new WP_Error( 'no-theme-specified', esc_html__( 'No theme specified.', 'uptimemonster-site-monitor' ) );
		}

		if ( get_stylesheet() === $slug ) {
			$response = [
				'status'  => false,
				'message' => esc_html__( 'Requested theme already been activated.', 'uptimemonster-site-monitor' ),
			];
		} else {
			$theme = wp_get_theme( $slug );

			if ( ! $theme->exists() ) {
				$response = [
					'status'  => false,
					'message' => esc_html__( 'Requested theme is not installed.', 'uptimemonster-site-monitor' ),
				];
			}

			$requirements = validate_theme_requirements( $slug );
			if ( is_wp_error( $requirements ) ) {
				return $requirements;
			}

			switch_theme( $slug );

			if ( get_stylesheet() === $slug ) {
				$response = [
					'status'  => true,
					/* translators: Theme Name */
					'message' => sprintf( esc_html__( '%s successfully activated.', 'uptimemonster-site-monitor' ), (string) $theme ),
				];
			} else {
				$response = [
					'status'  => false,
					/* translators: Theme Name */
					'message' => sprintf( esc_html__( 'Unable to activate requested theme (%s).', 'uptimemonster-site-monitor' ), (string) $theme ),
				];
			}
		}

		$this->add_extra_data( $response );

		return rest_ensure_response( $response );
	}

	/**
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function delete_themes( WP_REST_Request $request ) {

		$data = json_decode( $request->get_body() );

		if ( empty( $data->slugs ) ) {
			return new WP_Error( 'no-theme-specified', esc_html__( 'No theme specified.', 'uptimemonster-site-monitor' ) );
		}

		$response = [];
		$changed  = false;

		foreach ( $data->slugs as $slug ) {
			$slug  = sanitize_key( wp_unslash( $slug ) );
			$theme = wp_get_theme( $slug );

			if ( ! $theme->exists() ) {
				$changed           = true;
				$response[ $slug ] = [
					'status'  => false,
					/* translators: Theme Name */
					'message' => sprintf( esc_html__( 'Theme %s does not exists or already deleted.', 'uptimemonster-site-monitor' ), $slug ),
				];

				continue;
			}

			// Check filesystem credentials. `delete_theme()` will bail otherwise.
			$url = wp_nonce_url( 'themes.php?action=delete&stylesheet=' . urlencode( $slug ), 'delete-theme_' . $slug );

			$need_credentials = uptimemonster_need_filesystem_credentials( $url );
			if ( $need_credentials ) {
				// File system is not writable, we can't delete anything, no need to loop.
				return $need_credentials;
			}

			if ( get_stylesheet() === $slug ) {
				$response[ $slug ] = [
					'status'  => false,
					/* translators: Theme Name */
					'message' => sprintf( esc_html__( '%s is the active theme. You cannot delete an active theme.', 'uptimemonster-site-monitor' ), (string) $theme ),
				];
				continue;
			}

			if ( get_template() === $slug ) {
				$response[ $slug ] = [
					'status'  => false,
					/* translators: parent Theme Name */
					'message' => sprintf( esc_html__( '%s is the parent theme of active theme. You cannot delete parent theme of active theme.', 'uptimemonster-site-monitor' ), (string) $theme ),
				];
				continue;
			}

			$result = delete_theme( $slug );
			$status = [ 'status' => false ];

			if ( is_wp_error( $result ) ) {
				$status['message'] = $result->get_error_message();
			} elseif ( false === $result ) {
				/* translators: Theme Name */
				$status['message'] = sprintf( esc_html__( 'Unable to delete requested theme (%s).', 'uptimemonster-site-monitor' ), (string) $theme );
			} else {
				$status['status'] = true;

				/* translators: Theme/Plugin name */
				$status['message'] = sprintf( esc_html__( '%s successfully deleted.', 'uptimemonster-site-monitor' ), (string) $theme );
				$changed           = true;
			}

			$response[ $slug ] = $status;
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
	public function update_themes( WP_REST_Request $request ) {

		$data = json_decode( $request->get_body() );

		if ( empty( $data->slugs ) ) {
			return new WP_Error( 'no-theme-specified', esc_html__( 'No theme specified.', 'uptimemonster-site-monitor' ) );
		}

		$response = [];
		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$changed  = false;

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
					/* translators: Theme Name */
					'message' => sprintf( esc_html__( 'Theme %s does not exists.', 'uptimemonster-site-monitor' ), $slug ),
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
				return new WP_Error( 'fs-readonly', esc_html__( 'Unable to connect to the filesystem. Filesystem seems readonly or credentials are not provided in wp-config.php.', 'uptimemonster-site-monitor' ) );
			}


			$status = [ 'status' => false ];

			/* translators: 1. Theme/plugin name, 2. Error details */
			$failed = esc_html__( 'Failed to update %1$s. Error: %2$s', 'uptimemonster-site-monitor' );

			if ( is_wp_error( $skin->result ) ) {
				$status['message'] = sprintf( $failed, (string) $theme, $skin->result->get_error_message() );
			} elseif ( $skin->get_errors()->has_errors() ) {
				$status['message'] = sprintf( $failed, (string) $theme, $skin->get_error_messages() );
			} elseif ( true === $result ) {
				// Theme is already at the latest version.
				$status['message'] = $upgrader->strings['up_to_date'];
			} else {
				$status['status'] = true;

				/* translators: Theme Name */
				$status['message'] = sprintf( esc_html__( '%s  updated.', 'uptimemonster-site-monitor' ), (string) $theme );
				$changed           = true;
			}

			$response[ $slug ] = $status;
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
}

// End of file Themes.php.
