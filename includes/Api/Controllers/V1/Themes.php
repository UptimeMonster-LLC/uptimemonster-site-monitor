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
//	public function install_theme( \WP_REST_Request $request ) {
//
//		$response = array(
//			'action' => 'install',
//			'data'   => [],
//			'extra'  => []
//		);
//
//		$data = json_decode( $request->get_body() );
//
//		if ( ! isset( $data->slugs ) || empty( $data->slugs ) ) {
//			return rest_ensure_response( [
//					'status' => false,
//					'action' => 'install',
//					'data'   => [
//						'message' => __( 'No theme specified.', 'roxwp-site-mon' ),
//					],
//					'extra'  => []
//				]
//			);
//		}
//
//		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
//		include_once ABSPATH . 'wp-admin/includes/theme.php';
//		if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
//			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
//		}
//		if ( ! class_exists( 'WP_Ajax_Upgrader_Skin' ) ) {
//			require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
//		}
//		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
//			require_once ABSPATH . 'wp-admin/includes/file.php';
//		}
//
//		$skin     = new \WP_Ajax_Upgrader_Skin();
//		$upgrader = new \Theme_Upgrader( $skin );
//
//		$status = array( 'status' => false );
//		$slug   = sanitize_key( wp_unslash( $data->slugs[0] ) );
//
//		if ( ! $this->is_theme_exists( $slug ) ) {
//			$status['sluggg'] = $data->slugs[0];
//			$api            = themes_api(
//				'theme_information',
//				array(
//					'slug'   => $slug,
//					'fields' => array( 'sections' => false ),
//				)
//			);
//
//
//			if ( is_wp_error( $api ) ) {
//				$status['message']  = $api->get_error_message();
//				$response['status'] = false;
//				$response['data']   = $status;
//
//				return rest_ensure_response( $response );
//			}
//			$result = $upgrader->install( $api->download_link );
//
//			if ( is_wp_error( $result ) ) {
//				$status['message'] = $result->get_error_message();
//			} elseif ( is_wp_error( $skin->result ) ) {
//				$status['message'] = $skin->result->get_error_message();
//			} elseif ( $skin->get_errors()->has_errors() ) {
//				$status['message'] = $skin->get_error_messages();
//			} elseif ( is_null( $result ) ) {
//				global $wp_filesystem;
//
//				// Pass through the error from WP_Filesystem if one was raised.
//				if ( $wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
//					$status['message'] = esc_html( $wp_filesystem->errors->get_error_message() );
//				} else {
//					$status['message'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.', 'roxwp-site-mon' );
//				}
//			}
//
//			if ( ! isset( $status['message'] ) ) {
//				$status['status']  = true;
//				$status['message'] = sprintf( __( '%s  installed.', 'roxwp-site-mon' ), $slug );
//			}
//		} else {
//			$status['status']  = false;
//			$status['message'] = sprintf( __( '%s already installed.', 'roxwp-site-mon' ), $slug );
//		}
//
//		$response['status'] = true;
//		$response['data']   = $status;
//
//		return rest_ensure_response( $response );
//	}

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
		if ( ! class_exists( 'WP_Ajax_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
		}
		if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		}
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );

		$statuses = [];
		foreach ( $data->slugs as $slug ) {
			$status = array( 'status' => false );
			$slug   = sanitize_key( wp_unslash( $slug ) );

			if ( ! $this->is_theme_exists( $slug ) ) {
				$status['slug'] = $slug;
				$api            = themes_api(
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
						$status['message'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.', 'roxwp-site-mon' );
					}
				}

				if ( ! isset( $status['message'] ) ) {
					$status['status']  = true;
					$status['message'] = sprintf( __( '%s  installed.', 'roxwp-site-mon' ), $slug );
				}
			} else {
				$status['status']  = false;
				$status['message'] = sprintf( __( '%s already installed.', 'roxwp-site-mon' ), $slug );
			}

			$statuses [ $slug ] = $status;
		}


		$response['status'] = true;
		$response['data']   = $statuses;

		return rest_ensure_response( $response );
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function activate_theme( $request ) {

		$data     = json_decode( $request->get_body() );
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
			if ( wp_get_theme()->get_stylesheet() === $data->slugs[0] ) {
				$response['status']          = false;
				$response['data']['message'] = __( 'Theme already active.', 'roxwp-site-mon' );
			} else {
				switch_theme( $data->slugs[0] );
				$response['data']['message'] = __( 'Theme activated.', 'roxwp-site-mon' );
			}
		} else {
			$response['status']          = false;
			$response['data']['message'] = __( 'Theme does\'nt exists.', 'roxwp-site-mon' );
		}

		return rest_ensure_response( $response );

	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function delete_themes( \WP_REST_Request $request ) {

		$data     = json_decode( $request->get_body() );
		$response = array(
			'status' => true,
			'action' => 'delete',
			'data'   => [],
			'extra'  => [],
		);

		if ( ! isset( $data->slugs ) || empty( $data->slugs ) ) {
			return rest_ensure_response( [
					'status' => false,
					'action' => 'delete',
					'data'   => [
						'message' => __( 'No theme specified.', 'roxwp-site-mon' ),
					],
					'extra'  => []
				]
			);
		}

		include_once ABSPATH . 'wp-admin/includes/theme.php';
		if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		}
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$statuses = [];
		foreach ( $data->slugs as $slug ) {
			$status = array( 'status' => false );
			$slug   = sanitize_key( wp_unslash( $slug ) );
			if ( wp_get_theme( $slug )->exists() ) {

				// Check filesystem credentials. `delete_theme()` will bail otherwise.
				$url = wp_nonce_url( 'themes.php?action=delete&stylesheet=' . urlencode( $slug ), 'delete-theme_' . $slug );

				ob_start();
				$credentials = request_filesystem_credentials( $url );
				ob_end_clean();

				if ( false === $credentials || ! \WP_Filesystem( $credentials ) ) {
					global $wp_filesystem;

					// Pass through the error from WP_Filesystem if one was raised.
					if ( $wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
						$status['message'] = esc_html( $wp_filesystem->errors->get_error_message() );
					} else {
						$status['message'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.', 'roxwp-site-mon' );
					}

					$statuses [ $slug ] = $status;
					continue;
				}

				if ( wp_get_theme()->get_stylesheet() === $slug ) {

					$status['status']   = false;
					$status['message']  = sprintf( __( '%s is active theme. You cannot delete an active theme.', 'roxwp-site-mon' ), $slug );
					$statuses [ $slug ] = $status;
					continue;
				}

				$result = delete_theme( $slug );

				if ( is_wp_error( $result ) ) {
					$status['message'] = $result->get_error_message();
				} elseif ( false === $result ) {
					$status['message'] = __( 'Theme could not be deleted.', 'roxwp-site-mon' );
				}

				if ( ! isset( $status['message'] ) ) {
					$status['status']  = true;
					$status['message'] = sprintf( __( '%s deleted.', 'roxwp-site-mon' ), $slug );
				}

			} else {
				$status['status']  = false;
				$status['message'] = sprintf( __( '%s does\'nt exists.', 'roxwp-site-mon' ), $slug );
			}

			$statuses [ $slug ] = $status;
		}

		$response['status'] = true;
		$response['data']   = $statuses;

		return rest_ensure_response( $response );
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function update_themes( \WP_REST_Request $request ) {

		$response = array(
			'action' => 'update',
			'data'   => [],
			'extra'  => []
		);

		$data = json_decode( $request->get_body() );

		if ( ! isset( $data->slugs ) || empty( $data->slugs ) ) {
			return rest_ensure_response( [
					'status' => false,
					'action' => 'update',
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
		$upgrader = new \Theme_Upgrader( $skin );
		$statuses = [];
		foreach ( $data->slugs as $slug ) {
			$status = array( 'status' => false );
			$slug   = sanitize_key( wp_unslash( $slug ) );

			if ( $this->is_theme_exists( $slug ) ) {
				$status['slug'] = $slug;
				$current = get_site_transient( 'update_themes' );
				if ( empty( $current ) ) {
					wp_update_themes();
				}

				$result   = $upgrader->bulk_upgrade( array( $slug ) );

				if ( is_wp_error( $skin->result ) ) {
					$status['message'] = $skin->result->get_error_message();
				} elseif ( $skin->get_errors()->has_errors() ) {
					$status['message'] = $skin->get_error_messages();
				} elseif ( is_array( $result ) && ! empty( $result[ $slug ] ) ) {
					// Theme is already at the latest version.
					if ( true === $result[ $slug ] ) {
						$status['message'] = $upgrader->strings['up_to_date'];
					}
				}  elseif ( is_null( $result ) ) {
					global $wp_filesystem;

					// Pass through the error from WP_Filesystem if one was raised.
					if ( $wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
						$status['message'] = esc_html( $wp_filesystem->errors->get_error_message() );
					} else {
						$status['message'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.', 'roxwp-site-mon' );
					}
				}

				if ( ! isset( $status['message'] ) ) {
					$status['status']  = true;
					$status['message'] = sprintf( __( '%s  updated.', 'roxwp-site-mon' ), $slug );
				}
			} else {
				$status['message'] = sprintf( __( '%s does\'nt exists.', 'roxwp-site-mon' ), $slug );
			}

			$statuses [ $slug ] = $status;
		}


		$response['status'] = true;
		$response['data']   = $statuses;


		return rest_ensure_response( $response );
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
