<?php
/**
 * Theme Updater API
 *
 * @package UptimeMonster\SiteMonitor\API
 * @version 1.0.0
 */

namespace UptimeMonster\SiteMonitor\Api\Controllers\V1;

use UptimeMonster\SiteMonitor\Api\Controllers\Controller_Base;
use UptimeMonster\SiteMonitor\Api\Controllers\V1\Site_Health\UptimeMonster_Debug_Data;
use UptimeMonster\SiteMonitor\Api\Controllers\V1\Site_Health\UptimeMonster_Update_Check;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

/**
 * Class Theme
 */
class Themes extends Controller_Base {

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
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = '/theme';

	public function __construct()
	{
		// Health data
		$this->update_check_model = new UptimeMonster_Update_Check();

		// Debug data.
		$this->debug_model = new UptimeMonster_Debug_Data();
	}

	public function register_routes()
	{
		// Register install theme.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/install',
			array(
				array(
					'methods'				=> \WP_REST_Server::CREATABLE,
					'callback'				=> array($this, 'install_themes'),
					'permission_callback' 	=> array($this, 'get_route_access'),
					'args'					=> array(),
				),
			)
		);
		// Register activate theme.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/activate',
			array(
				array(
					'methods'				=> \WP_REST_Server::CREATABLE,
					'callback' 				=> array($this, 'activate_theme'),
					'permission_callback' 	=> array($this, 'get_route_access'),
					'args' 					=> array(),
				),
			)
		);

		// Register delete theme.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/delete',
			array(
				array(
					'methods'		 		=> \WP_REST_Server::CREATABLE,
					'callback' 				=> array($this, 'delete_themes'),
					'permission_callback' 	=> array($this, 'get_route_access'),
					'args' 					=> array(),
				),
			)
		);

		// Register update theme.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/update',
			array(
				array(
					'methods' 				=> \WP_REST_Server::CREATABLE,
					'callback' 				=> array($this, 'update_themes'),
					'permission_callback' 	=> array($this, 'get_route_access'),
					'args' 					=> array(),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function install_themes(\WP_REST_Request $request) {

		set_time_limit( 0 );

		$response = array(
			'data' => [],
			'extra'=> []
		);
		$data = json_decode($request->get_body());

		if (!isset($data->slugs) || empty($data->slugs)) {
			return rest_ensure_response([
					'status'=> false,
					'data' 	=> [
						'message' => __('No theme specified.', 'uptime'),
					],
					'extra' => []
				]
			);
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		if (!class_exists('WP_Ajax_Upgrader_Skin')) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
		}

		if (!class_exists('WP_Filesystem_Base')) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		}

		if (!function_exists('request_filesystem_credentials')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$skin = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader($skin);
		$statuses = [];
		$is_installed = false;

		foreach ( $data->slugs as $slug ) {
			$status = array( 'status' => false );
			$slug = sanitize_key( wp_unslash($slug) );

			if (!$this->is_theme_exists($slug)) {
				$api = themes_api(
					'theme_information',
					array(
						'slug' => $slug,
						'fields' => array('sections' => false),
					)
				);

				if (is_wp_error($api)) {
					$status['message'] = $api->get_error_message();
					$statuses[$slug] = $status;
					continue;
				}
				$result = $upgrader->install($api->download_link);

				if (is_wp_error($result)) {
					$status['message'] = $result->get_error_message();
				} elseif (is_wp_error($skin->result)) {
					$status['message'] = $skin->result->get_error_message();
				} elseif ($skin->get_errors()->has_errors()) {
					$status['message'] = $skin->get_error_messages();
				} elseif (is_null($result)) {
					global $wp_filesystem;
					// Pass through the error from WP_Filesystem if one was raised.
					if ($wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->has_errors()) {
						$status['message'] = esc_html($wp_filesystem->errors->get_error_message());
					} else {
						$status['message'] = __('Unable to connect to the filesystem. Please confirm your credentials.', 'uptime');
					}

				}

				if (!isset($status['message'])) {
					$status['status'] = true;
					$status['message'] = sprintf(__('%s  installed.', 'uptime'), $slug);
					$is_installed = true;
				}

			} else {
				$status['status'] = false;
				$status['message'] = sprintf(__('%s already installed.', 'uptime'), $slug);
			}

			$statuses[] = $status;
		}

		$response['status'] = true;
		$response['data'] = $statuses;
		if ( $is_installed ) {
			$response = array_merge($response, ['extra' => [
				'site_health' 	=> $this->update_check_model->get_site_health() ? $this->update_check_model->get_site_health() : [],
				'site_info' 	=> $this->debug_model->debug_data() ? $this->debug_model->debug_data() : [],
			]]);
		}


		return rest_ensure_response($response);
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function activate_theme($request) {

		set_time_limit( 0 );

		$data = json_decode($request->get_body());
		$response = array(
			'status' => true,
			'data' => [],
			'extra' => [],
		);

		if ( !isset($data->slugs) || empty($data->slugs) ) {
			return rest_ensure_response([
					'status'=> false,
					'data' 	=> [
						'message' => __('No theme specified.', 'uptime'),
					],
					'extra' => []
				]
			);
		}

		$status = ['status' => false];
		$is_activated = false;

		if ( isset($data->slugs[0]) && $this->is_theme_exists($data->slugs[0]) ) {

			if (wp_get_theme()->get_stylesheet() === $data->slugs[0]) {
				$status['message'] = __('Theme already active.', 'uptime');
			} else {
				switch_theme($data->slugs[0]);
				$status['status'] = true;
				$status['message'] = __('Theme activated.', 'uptime');
				$is_activated = true;
			}

		} else {
			$status['message'] = __('Theme does\'nt exists.', 'uptime');
		}

		$response ['data'][] = $status;
		if ($is_activated) {
			$response['extra'] = [
				'site_health' 	=> $this->update_check_model->get_site_health() ? $this->update_check_model->get_site_health() : [],
				'site_info' 	=> $this->debug_model->debug_data() ? $this->debug_model->debug_data() : [],
			];
		}

		return rest_ensure_response($response);
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function delete_themes(\WP_REST_Request $request) {

		set_time_limit( 0 );

		$data = json_decode($request->get_body());
		$response = array(
			'status' => true,
			'data' => [],
			'extra' => [],
		);
		$statuses = [];

		if (!isset($data->slugs) || empty($data->slugs)) {
			return rest_ensure_response([
					'status' => false,
					'data' => [
						'message' => __('No theme specified.', 'uptime'),
					],
					'extra' => []
				]
			);
		}

		include_once ABSPATH . 'wp-admin/includes/theme.php';

		if (!class_exists('WP_Filesystem_Base')) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		}
		if (!function_exists('request_filesystem_credentials')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$is_deleted = false;

		foreach ($data->slugs as $slug) {
			$status = array('status' => false);
			$slug = sanitize_key(wp_unslash($slug));

			if (wp_get_theme($slug)->exists()) {
				// Check filesystem credentials. `delete_theme()` will bail otherwise.
				$url = wp_nonce_url('themes.php?action=delete&stylesheet=' . urlencode($slug), 'delete-theme_' . $slug);

				ob_start();
				$credentials = request_filesystem_credentials($url);
				ob_end_clean();

				if (false === $credentials || !\WP_Filesystem($credentials)) {
					global $wp_filesystem;

					// Pass through the error from WP_Filesystem if one was raised.
					if ($wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->has_errors()) {
						$status['message'] = esc_html($wp_filesystem->errors->get_error_message());
					} else {
						$status['message'] = __('Unable to connect to the filesystem. Please confirm your credentials.', 'uptime');
					}

					$statuses[] = $status;
					continue;
				}

				if (wp_get_theme()->get_stylesheet() === $slug) {
					$status['status'] = false;
					$status['message'] = sprintf(__('%s is active theme. You cannot delete an active theme.', 'uptime'), $slug);
					$statuses[] = $status;
					continue;
				}

				$result = delete_theme($slug);

				if (is_wp_error($result)) {
					$status['message'] = $result->get_error_message();
				} elseif (false === $result) {
					$status['message'] = __('Theme could not be deleted.', 'uptime');
				}

				if (!isset($status['message'])) {
					$status['status'] = true;
					$status['message'] = sprintf(__('%s deleted.', 'uptime'), $slug);
					$is_deleted = true;
				}

			} else {
				$status['status'] = false;
				$status['message'] = sprintf(__('%s does\'nt exists.', 'uptime'), $slug);
			}

			$statuses[] = $status;
		}

		$response['status'] = true;
		$response['data'] = $statuses;
		if ( $is_deleted ) {
			$response = array_merge($response, ['extra' => [
				'site_health' 	=> $this->update_check_model->get_site_health() ? $this->update_check_model->get_site_health() : [],
				'site_info' 	=> $this->debug_model->debug_data() ? $this->debug_model->debug_data() : [],
			]]);
		}


		return rest_ensure_response($response);
	}

	/**
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response|
	 */
	public function update_themes(\WP_REST_Request $request) {

		set_time_limit( 0 );
		$response = array(
			'action' => 'update',
			'data' => [],
			'extra' => []
		);

		$data = json_decode($request->get_body());

		if (!isset($data->slugs) || empty($data->slugs)) {
			return rest_ensure_response([
					'status' => false,
					'data' => [
						'message' => __('No theme specified.', 'uptime'),
					],
					'extra' => []
				]
			);
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		if (!class_exists('WP_Filesystem_Base')) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		}

		if (!function_exists('request_filesystem_credentials')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$skin = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader($skin);
		$statuses = [];
		$is_updated = false;

		foreach ($data->slugs as $slug) {
			$status = array('status' => false);
			$slug = sanitize_key(wp_unslash($slug));

			if ($this->is_theme_exists($slug)) {
				$status['slug'] = $slug;
				$current = get_site_transient('update_themes');
				if (empty($current)) {
					wp_update_themes();
				}

				$result = $upgrader->bulk_upgrade(array($slug));

				if (is_wp_error($skin->result)) {
					$status['message'] = $skin->result->get_error_message();
				} elseif ($skin->get_errors()->has_errors()) {
					$status['message'] = $skin->get_error_messages();
				} elseif (is_array($result) && !empty($result[$slug])) {
					// Theme is already at the latest version.
					if (true === $result[$slug]) {
						$status['message'] = $upgrader->strings['up_to_date'];
					}

				} elseif (is_null($result)) {
					global $wp_filesystem;
					// Pass through the error from WP_Filesystem if one was raised.
					if ($wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->has_errors()) {
						$status['message'] = esc_html($wp_filesystem->errors->get_error_message());
					} else {
						$status['message'] = __('Unable to connect to the filesystem. Please confirm your credentials.', 'uptime');
					}

				}

				if (!isset($status['message'])) {
					$status['status'] = true;
					$status['message'] = sprintf(__('%s  updated.', 'uptime'), $slug);
					$is_updated = true;
				}

			} else {
				$status['message'] = sprintf(__('%s does\'nt exists.', 'uptime'), $slug);
			}

			$statuses[] = $status;
		}

		$response = [
			'status' => true,
			'data' => $statuses
		];

		if ($is_updated) {
			$response = array_merge($response, ['extra' => [
				'site_health' 	=> $this->update_check_model->get_site_health() ? $this->update_check_model->get_site_health() : [],
				'site_info' 	=> $this->debug_model->debug_data() ? $this->debug_model->debug_data() : [],
			]]);
		}

		return rest_ensure_response($response);
	}


	/**
	 * @param $path
	 *
	 * @return bool
	 */
	private function is_theme_exists($path)
	{
		return file_exists(WP_CONTENT_DIR . '/themes/' . $path);
	}

}
