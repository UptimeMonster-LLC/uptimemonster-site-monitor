<?php
/**
 * WP Debug Data.
 *
 * @package UptimeMonster\SiteMonitor\API
 * @version 1.0.0
 */

namespace UptimeMonster\SiteMonitor\Api\Controllers\V1\Site_Health;

use Imagick;
use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class UptimeMonster_Debug_Data {
	public $update_check;

	public function __construct() {
		$this->update_check = new UptimeMonster_Update_Check();
	}

	/**
	 * Calls all core functions to check for updates.
	 */
	public static function check_for_updates() {
		wp_version_check();
		wp_update_plugins();
		wp_update_themes();
	}

	/**
	 * Static function for generating site debug data when required.
	 *
	 * @return array The debug data for the site.
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public function debug_data() {
		global $wpdb;

		// Save few function calls.
		$upload_dir             = wp_upload_dir();
		$permalink_structure    = get_option( 'permalink_structure' );
		$is_ssl                 = is_ssl();
		$is_multisite           = is_multisite();
		$users_can_register     = get_option( 'users_can_register' );
		$blog_public            = get_option( 'blog_public' );
		$default_comment_status = get_option( 'default_comment_status' );
		$environment_type       = wp_get_environment_type();
		$core_version           = get_bloginfo( 'version' );
		$core_updates           = $this->update_check->get_core_updates();
		$core_update_needed     = $core_version;

		if ( is_array( $core_updates ) ) {
			foreach ( $core_updates as $update ) {
				if ( 'upgrade' === $update->response ) {
					$core_update_needed = $update->version;
				} else {
					$core_update_needed = $core_version;
				}
			}
		}

		// Set up the array that holds all debug information.
		$info = [];

		$info['wp-core'] = [
			'label'          => __( 'WordPress', 'uptimemonster-site-monitor' ),
			'version'        => $core_version,
			'latest_version' => $core_update_needed,
			'update'         => version_compare( $core_version, $core_update_needed, '<' ),
			'fields'         => [
				'site_language'          => [
					'label' => __( 'Site Language', 'uptimemonster-site-monitor' ),
					'value' => get_locale(),
				],
				'user_language'          => [
					'label' => __( 'User Language', 'uptimemonster-site-monitor' ),
					'value' => get_user_locale(),
				],
				'timezone'               => [
					'label' => __( 'Timezone', 'uptimemonster-site-monitor' ),
					'value' => wp_timezone_string(),
				],
				'home_url'               => [
					'label'   => __( 'Home URL', 'uptimemonster-site-monitor' ),
					'value'   => get_bloginfo( 'url' ),
					'private' => true,
				],
				'site_url'               => [
					'label'   => __( 'Site URL', 'uptimemonster-site-monitor' ),
					'value'   => get_bloginfo( 'wpurl' ),
					'private' => true,
				],
				'permalink'              => [
					'label' => __( 'Permalink structure', 'uptimemonster-site-monitor' ),
					'value' => $permalink_structure ? $permalink_structure : __( 'No permalink structure set', 'uptimemonster-site-monitor' ),
					'debug' => $permalink_structure,
				],
				'https_status'           => [
					'label' => __( 'Is this site using HTTPS?', 'uptimemonster-site-monitor' ),
					'value' => $is_ssl ? __( 'Yes', 'uptimemonster-site-monitor' ) : __( 'No', 'uptimemonster-site-monitor' ),
					'debug' => $is_ssl,
				],
				'multisite'              => [
					'label' => __( 'Is this a multisite?', 'uptimemonster-site-monitor' ),
					'value' => $is_multisite ? __( 'Yes', 'uptimemonster-site-monitor' ) : __( 'No', 'uptimemonster-site-monitor' ),
					'debug' => $is_multisite,
				],
				'user_registration'      => [
					'label' => __( 'Can anyone register on this site?', 'uptimemonster-site-monitor' ),
					'value' => $users_can_register ? __( 'Yes', 'uptimemonster-site-monitor' ) : __( 'No', 'uptimemonster-site-monitor' ),
					'debug' => $users_can_register,
				],
				'blog_public'            => [
					'label' => __( 'Is this site discouraging search engines?', 'uptimemonster-site-monitor' ),
					'value' => $blog_public ? __( 'No', 'uptimemonster-site-monitor' ) : __( 'Yes', 'uptimemonster-site-monitor' ),
					'debug' => $blog_public,
				],
				'default_comment_status' => [
					'label' => __( 'Default comment status', 'uptimemonster-site-monitor' ),
					'value' => 'open' === $default_comment_status ? _x( 'Open', 'comment status', 'uptimemonster-site-monitor' ) : _x( 'Closed', 'comment status', 'uptimemonster-site-monitor' ),
					'debug' => $default_comment_status,
				],
				'environment_type'       => [
					'label' => __( 'Environment type', 'uptimemonster-site-monitor' ),
					'value' => $environment_type,
					'debug' => $environment_type,
				],
			],
		];

		if ( ! $is_multisite ) {
			$info['wp-paths-sizes'] = array(
				'label'  => __( 'Directories and Sizes', 'uptimemonster-site-monitor' ),
				'fields' => array(),
			);
		}

		$info['wp-dropins'] = array(
			'label'       => __( 'Drop-ins', 'uptimemonster-site-monitor' ),
			'show_count'  => true,
			'description' => sprintf(
			/* translators: %s: wp-content directory name. */
				__( 'Drop-ins are single files, found in the %s directory, that replace or enhance WordPress features in ways that are not possible for traditional plugins.', 'uptimemonster-site-monitor' ),
				'<code>' . str_replace( ABSPATH, '', WP_CONTENT_DIR ) . '</code>'
			),
			'fields'      => array(),
		);

		$info['wp-active-theme'] = [];

		$info['wp-parent-theme'] = [];

		$info['wp-themes-inactive'] = [];

		$info['wp-mu-plugins'] = array(
			'label'      => __( 'Must Use Plugins', 'uptimemonster-site-monitor' ),
			'show_count' => true,
			'fields'     => array(),
		);

		$info['wp-plugins-active'] = array(
			'label'      => __( 'Active Plugins', 'uptimemonster-site-monitor' ),
			'show_count' => true,
			'fields'     => array(),
		);

		$info['wp-plugins-inactive'] = array(
			'label'      => __( 'Inactive Plugins', 'uptimemonster-site-monitor' ),
			'show_count' => true,
			'fields'     => array(),
		);

		$info['wp-media'] = array(
			'label'  => __( 'Media Handling', 'uptimemonster-site-monitor' ),
			'fields' => array(),
		);

		$info['wp-server'] = array(
			'label'       => __( 'Server', 'uptimemonster-site-monitor' ),
			'description' => __( 'The options shown below relate to your server setup. If changes are required, you may need your web host&#8217;s assistance.', 'uptimemonster-site-monitor' ),
			'fields'      => array(),
		);

		$info['wp-database'] = array(
			'label'  => __( 'Database', 'uptimemonster-site-monitor' ),
			'fields' => array(),
		);

		// Check if WP_DEBUG_LOG is set.
		$wp_debug_log_value = __( 'Disabled', 'uptimemonster-site-monitor' );

		if ( is_string( WP_DEBUG_LOG ) ) {
			$wp_debug_log_value = WP_DEBUG_LOG;
		} elseif ( WP_DEBUG_LOG ) {
			$wp_debug_log_value = __( 'Enabled', 'uptimemonster-site-monitor' );
		}

		// Check CONCATENATE_SCRIPTS.
		if ( defined( 'CONCATENATE_SCRIPTS' ) ) {
			$concatenate_scripts       = CONCATENATE_SCRIPTS ? __( 'Enabled', 'uptimemonster-site-monitor' ) : __( 'Disabled', 'uptimemonster-site-monitor' );
			$concatenate_scripts_debug = CONCATENATE_SCRIPTS ? 'true' : 'false';
		} else {
			$concatenate_scripts       = __( 'Undefined', 'uptimemonster-site-monitor' );
			$concatenate_scripts_debug = 'undefined';
		}

		// Check COMPRESS_SCRIPTS.
		if ( defined( 'COMPRESS_SCRIPTS' ) ) {
			$compress_scripts       = COMPRESS_SCRIPTS ? __( 'Enabled', 'uptimemonster-site-monitor' ) : __( 'Disabled', 'uptimemonster-site-monitor' );
			$compress_scripts_debug = COMPRESS_SCRIPTS ? 'true' : 'false';
		} else {
			$compress_scripts       = __( 'Undefined', 'uptimemonster-site-monitor' );
			$compress_scripts_debug = 'undefined';
		}

		// Check COMPRESS_CSS.
		if ( defined( 'COMPRESS_CSS' ) ) {
			$compress_css       = COMPRESS_CSS ? __( 'Enabled', 'uptimemonster-site-monitor' ) : __( 'Disabled', 'uptimemonster-site-monitor' );
			$compress_css_debug = COMPRESS_CSS ? 'true' : 'false';
		} else {
			$compress_css       = __( 'Undefined', 'uptimemonster-site-monitor' );
			$compress_css_debug = 'undefined';
		}

		// Check WP_ENVIRONMENT_TYPE.
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
			$wp_environment_type = WP_ENVIRONMENT_TYPE;
		} else {
			$wp_environment_type = __( 'Undefined', 'uptimemonster-site-monitor' );
		}

		$info['wp-constants'] = array(
			'label'       => __( 'WordPress Constants', 'uptimemonster-site-monitor' ),
			'description' => __( 'These settings alter where and how parts of WordPress are loaded.', 'uptimemonster-site-monitor' ),
			'fields'      => array(
				'ABSPATH'             => array(
					'label'   => 'ABSPATH',
					'value'   => ABSPATH,
					'private' => true,
				),
				'WP_HOME'             => array(
					'label' => 'WP_HOME',
					'value' => ( defined( 'WP_HOME' ) ? WP_HOME : __( 'Undefined', 'uptimemonster-site-monitor' ) ),
					'debug' => ( defined( 'WP_HOME' ) ? WP_HOME : 'undefined' ),
				),
				'WP_SITEURL'          => array(
					'label' => 'WP_SITEURL',
					'value' => ( defined( 'WP_SITEURL' ) ? WP_SITEURL : __( 'Undefined', 'uptimemonster-site-monitor' ) ),
					'debug' => ( defined( 'WP_SITEURL' ) ? WP_SITEURL : 'undefined' ),
				),
				'WP_CONTENT_DIR'      => array(
					'label' => 'WP_CONTENT_DIR',
					'value' => WP_CONTENT_DIR,
				),
				'WP_PLUGIN_DIR'       => array(
					'label' => 'WP_PLUGIN_DIR',
					'value' => WP_PLUGIN_DIR,
				),
				'WP_MEMORY_LIMIT'     => array(
					'label' => 'WP_MEMORY_LIMIT',
					'value' => WP_MEMORY_LIMIT,
				),
				'WP_MAX_MEMORY_LIMIT' => array(
					'label' => 'WP_MAX_MEMORY_LIMIT',
					'value' => WP_MAX_MEMORY_LIMIT,
				),
				'WP_DEBUG'            => array(
					'label' => 'WP_DEBUG',
					'value' => WP_DEBUG ? __( 'Enabled', 'uptimemonster-site-monitor' ) : __( 'Disabled', 'uptimemonster-site-monitor' ),
					'debug' => WP_DEBUG,
				),
				'WP_DEBUG_DISPLAY'    => array(
					'label' => 'WP_DEBUG_DISPLAY',
					'value' => WP_DEBUG_DISPLAY ? __( 'Enabled', 'uptimemonster-site-monitor' ) : __( 'Disabled', 'uptimemonster-site-monitor' ),
					'debug' => WP_DEBUG_DISPLAY,
				),
				'WP_DEBUG_LOG'        => array(
					'label' => 'WP_DEBUG_LOG',
					'value' => $wp_debug_log_value,
					'debug' => WP_DEBUG_LOG,
				),
				'SCRIPT_DEBUG'        => array(
					'label' => 'SCRIPT_DEBUG',
					'value' => SCRIPT_DEBUG ? __( 'Enabled', 'uptimemonster-site-monitor' ) : __( 'Disabled', 'uptimemonster-site-monitor' ),
					'debug' => SCRIPT_DEBUG,
				),
				'WP_CACHE'            => array(
					'label' => 'WP_CACHE',
					'value' => WP_CACHE ? __( 'Enabled', 'uptimemonster-site-monitor' ) : __( 'Disabled', 'uptimemonster-site-monitor' ),
					'debug' => WP_CACHE,
				),
				'CONCATENATE_SCRIPTS' => array(
					'label' => 'CONCATENATE_SCRIPTS',
					'value' => $concatenate_scripts,
					'debug' => $concatenate_scripts_debug,
				),
				'COMPRESS_SCRIPTS'    => array(
					'label' => 'COMPRESS_SCRIPTS',
					'value' => $compress_scripts,
					'debug' => $compress_scripts_debug,
				),
				'COMPRESS_CSS'        => array(
					'label' => 'COMPRESS_CSS',
					'value' => $compress_css,
					'debug' => $compress_css_debug,
				),
				'WP_ENVIRONMENT_TYPE' => array(
					'label' => 'WP_ENVIRONMENT_TYPE',
					'value' => $wp_environment_type,
					'debug' => $wp_environment_type,
				),
				'DB_CHARSET'          => array(
					'label' => 'DB_CHARSET',
					'value' => ( defined( 'DB_CHARSET' ) ? DB_CHARSET : __( 'Undefined', 'uptimemonster-site-monitor' ) ),
					'debug' => ( defined( 'DB_CHARSET' ) ? DB_CHARSET : 'undefined' ),
				),
				'DB_COLLATE'          => array(
					'label' => 'DB_COLLATE',
					'value' => ( defined( 'DB_COLLATE' ) ? DB_COLLATE : __( 'Undefined', 'uptimemonster-site-monitor' ) ),
					'debug' => ( defined( 'DB_COLLATE' ) ? DB_COLLATE : 'undefined' ),
				),
			),
		);

		$is_writable_abspath            = wp_is_writable( ABSPATH );
		$is_writable_wp_content_dir     = wp_is_writable( WP_CONTENT_DIR );
		$is_writable_upload_dir         = wp_is_writable( $upload_dir['basedir'] );
		$is_writable_wp_plugin_dir      = wp_is_writable( WP_PLUGIN_DIR );
		$is_writable_template_directory = wp_is_writable( get_theme_root( get_template() ) );

		$info['wp-filesystem'] = array(
			'label'       => __( 'Filesystem Permissions', 'uptimemonster-site-monitor' ),
			'description' => __( 'Shows whether WordPress is able to write to the directories it needs access to.', 'uptimemonster-site-monitor' ),
			'fields'      => array(
				'wordpress'  => array(
					'label' => __( 'The main WordPress directory', 'uptimemonster-site-monitor' ),
					'value' => ( $is_writable_abspath ? __( 'Writable', 'uptimemonster-site-monitor' ) : __( 'Not writable', 'uptimemonster-site-monitor' ) ),
					'debug' => ( $is_writable_abspath ? 'writable' : 'not writable' ),
				),
				'wp-content' => array(
					'label' => __( 'The wp-content directory', 'uptimemonster-site-monitor' ),
					'value' => ( $is_writable_wp_content_dir ? __( 'Writable', 'uptimemonster-site-monitor' ) : __( 'Not writable', 'uptimemonster-site-monitor' ) ),
					'debug' => ( $is_writable_wp_content_dir ? 'writable' : 'not writable' ),
				),
				'uploads'    => array(
					'label' => __( 'The uploads directory', 'uptimemonster-site-monitor' ),
					'value' => ( $is_writable_upload_dir ? __( 'Writable', 'uptimemonster-site-monitor' ) : __( 'Not writable', 'uptimemonster-site-monitor' ) ),
					'debug' => ( $is_writable_upload_dir ? 'writable' : 'not writable' ),
				),
				'plugins'    => array(
					'label' => __( 'The plugins directory', 'uptimemonster-site-monitor' ),
					'value' => ( $is_writable_wp_plugin_dir ? __( 'Writable', 'uptimemonster-site-monitor' ) : __( 'Not writable', 'uptimemonster-site-monitor' ) ),
					'debug' => ( $is_writable_wp_plugin_dir ? 'writable' : 'not writable' ),
				),
				'themes'     => array(
					'label' => __( 'The themes directory', 'uptimemonster-site-monitor' ),
					'value' => ( $is_writable_template_directory ? __( 'Writable', 'uptimemonster-site-monitor' ) : __( 'Not writable', 'uptimemonster-site-monitor' ) ),
					'debug' => ( $is_writable_template_directory ? 'writable' : 'not writable' ),
				),
			),
		);

		// Conditionally add debug information for multisite setups.
		if ( is_multisite() ) {
			$network_query = new \WP_Network_Query();
			$network_ids   = $network_query->query(
				array(
					'fields'        => 'ids',
					'number'        => 100,
					'no_found_rows' => false,
				)
			);

			$site_count = 0;
			foreach ( $network_ids as $network_id ) {
				$site_count += get_blog_count( $network_id );
			}

			$info['wp-core']['fields']['site_count'] = array(
				'label' => __( 'Site count', 'uptimemonster-site-monitor' ),
				'value' => $site_count,
			);

			$info['wp-core']['fields']['network_count'] = array(
				'label' => __( 'Network count', 'uptimemonster-site-monitor' ),
				'value' => $network_query->found_networks,
			);
		}

		if ( function_exists( 'get_user_count' ) ) {
			$info['wp-core']['fields']['user_count'] = array(
				'label' => __( 'User count', 'uptimemonster-site-monitor' ),
				'value' => get_user_count(),
			);
		}

		// WordPress features requiring processing.
		$wp_dotorg = wp_remote_get( 'https://wordpress.org', array( 'timeout' => 10 ) ); // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout, WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get

		if ( ! is_wp_error( $wp_dotorg ) ) {
			$info['wp-core']['fields']['dotorg_communication'] = array(
				'label' => __( 'Communication with WordPress.org', 'uptimemonster-site-monitor' ),
				'value' => __( 'WordPress.org is reachable', 'uptimemonster-site-monitor' ),
				'debug' => 'true',
			);
		} else {
			$info['wp-core']['fields']['dotorg_communication'] = array(
				'label' => __( 'Communication with WordPress.org', 'uptimemonster-site-monitor' ),
				'value' => sprintf(
				/* translators: 1: The IP address WordPress.org resolves to. 2: The error returned by the lookup. */
					__( 'Unable to reach WordPress.org at %1$s: %2$s', 'uptimemonster-site-monitor' ),
					gethostbyname( 'wordpress.org' ),
					$wp_dotorg->get_error_message()
				),
				'debug' => $wp_dotorg->get_error_message(),
			);
		}

		// Remove accordion for Directories and Sizes if in Multisite.
		if ( ! $is_multisite ) {
			$loading = __( 'Loading&hellip;', 'uptimemonster-site-monitor' );

			$info['wp-paths-sizes']['fields'] = array(
				'wordpress_path' => array(
					'label' => __( 'WordPress directory location', 'uptimemonster-site-monitor' ),
					'value' => untrailingslashit( ABSPATH ),
				),
				'wordpress_size' => array(
					'label' => __( 'WordPress directory size', 'uptimemonster-site-monitor' ),
					'value' => $loading,
					'debug' => 'loading...',
				),
				'uploads_path'   => array(
					'label' => __( 'Uploads directory location', 'uptimemonster-site-monitor' ),
					'value' => $upload_dir['basedir'],
				),
				'uploads_size'   => array(
					'label' => __( 'Uploads directory size', 'uptimemonster-site-monitor' ),
					'value' => $loading,
					'debug' => 'loading...',
				),
				'themes_path'    => array(
					'label' => __( 'Themes directory location', 'uptimemonster-site-monitor' ),
					'value' => get_theme_root(),
				),
				'themes_size'    => array(
					'label' => __( 'Themes directory size', 'uptimemonster-site-monitor' ),
					'value' => $loading,
					'debug' => 'loading...',
				),
				'plugins_path'   => array(
					'label' => __( 'Plugins directory location', 'uptimemonster-site-monitor' ),
					'value' => WP_PLUGIN_DIR,
				),
				'plugins_size'   => array(
					'label' => __( 'Plugins directory size', 'uptimemonster-site-monitor' ),
					'value' => $loading,
					'debug' => 'loading...',
				),
				'database_size'  => array(
					'label' => __( 'Database size', 'uptimemonster-site-monitor' ),
					'value' => $loading,
					'debug' => 'loading...',
				),
				'total_size'     => array(
					'label' => __( 'Total installation size', 'uptimemonster-site-monitor' ),
					'value' => $loading,
					'debug' => 'loading...',
				),
			);
		}

		// Get a list of all drop-in replacements.
		$dropins = get_dropins();

		// Get dropins descriptions.
		//$dropin_descriptions = _get_dropins();

		// Spare few function calls.
		$not_available = __( 'Not available', 'uptimemonster-site-monitor' );
		$common_data   = [
			'new_version' => null,
			'need_update' => null,
			'auto_update' => null,
		];

		foreach ( $dropins as $dropin_key => $dropin ) {
			$info['wp-dropins']['fields'][] = [
				'label' => $dropin['Name'],
				'slug'  => $dropin_key,
				'value' => array_merge( uptimemonster_prepare_plugin_data( $dropin ), $common_data ),
			];
		}

		// Populate the media fields.
		$info['wp-media']['fields']['image_editor'] = array(
			'label' => __( 'Active editor', 'uptimemonster-site-monitor' ),
			'value' => _wp_image_editor_choose(),
		);

		// Get ImageMagic information, if available.
		if ( class_exists( 'Imagick' ) ) {
			// Save the Imagick instance for later use.
			$imagick             = new \Imagick();
			$imagemagick_version = $imagick->getVersion();
		} else {
			$imagemagick_version = __( 'Not available', 'uptimemonster-site-monitor' );
		}

		$info['wp-media']['fields']['imagick_module_version'] = array(
			'label' => __( 'ImageMagick version number', 'uptimemonster-site-monitor' ),
			'value' => ( is_array( $imagemagick_version ) ? $imagemagick_version['versionNumber'] : $imagemagick_version ),
		);

		$info['wp-media']['fields']['imagemagick_version'] = array(
			'label' => __( 'ImageMagick version string', 'uptimemonster-site-monitor' ),
			'value' => ( is_array( $imagemagick_version ) ? $imagemagick_version['versionString'] : $imagemagick_version ),
		);

		$imagick_version = phpversion( 'imagick' );

		$info['wp-media']['fields']['imagick_version'] = array(
			'label' => __( 'Imagick version', 'uptimemonster-site-monitor' ),
			'value' => ( $imagick_version ) ? $imagick_version : __( 'Not available', 'uptimemonster-site-monitor' ),
		);

		if ( ! function_exists( 'ini_get' ) ) {
			$info['wp-media']['fields']['ini_get'] = array(
				'label' => __( 'File upload settings', 'uptimemonster-site-monitor' ),
				'value' => sprintf(
				/* translators: %s: ini_get() */
					__( 'Unable to determine some settings, as the %s function has been disabled.', 'uptimemonster-site-monitor' ),
					'ini_get()'
				),
				'debug' => 'ini_get() is disabled',
			);
		} else {
			// Get the PHP ini directive values.
			$post_max_size       = ini_get( 'post_max_size' );
			$upload_max_filesize = ini_get( 'upload_max_filesize' );
			$max_file_uploads    = ini_get( 'max_file_uploads' );
			$effective           = min( wp_convert_hr_to_bytes( $post_max_size ), wp_convert_hr_to_bytes( $upload_max_filesize ) );

			// Add info in Media section.
			$info['wp-media']['fields']['file_uploads']        = array(
				'label' => __( 'File uploads', 'uptimemonster-site-monitor' ),
				'value' => empty( ini_get( 'file_uploads' ) ) ? __( 'Disabled', 'uptimemonster-site-monitor' ) : __( 'Enabled', 'uptimemonster-site-monitor' ),
				'debug' => 'File uploads is turned off',
			);
			$info['wp-media']['fields']['post_max_size']       = array(
				'label' => __( 'Max size of post data allowed', 'uptimemonster-site-monitor' ),
				'value' => $post_max_size,
			);
			$info['wp-media']['fields']['upload_max_filesize'] = array(
				'label' => __( 'Max size of an uploaded file', 'uptimemonster-site-monitor' ),
				'value' => $upload_max_filesize,
			);
			$info['wp-media']['fields']['max_effective_size']  = array(
				'label' => __( 'Max effective file size', 'uptimemonster-site-monitor' ),
				'value' => size_format( $effective ),
			);
			$info['wp-media']['fields']['max_file_uploads']    = array(
				'label' => __( 'Max number of files allowed', 'uptimemonster-site-monitor' ),
				'value' => number_format( $max_file_uploads ),
			);
		}

		// If Imagick is used as our editor, provide some more information about its limitations.
		if ( 'WP_Image_Editor_Imagick' === _wp_image_editor_choose() && isset( $imagick ) && $imagick instanceof Imagick ) {
			$limits = array(
				'area'   => ( defined( 'imagick::RESOURCETYPE_AREA' ) ? size_format( $imagick->getResourceLimit( imagick::RESOURCETYPE_AREA ) ) : $not_available ),
				'disk'   => ( defined( 'imagick::RESOURCETYPE_DISK' ) ? $imagick->getResourceLimit( imagick::RESOURCETYPE_DISK ) : $not_available ),
				'file'   => ( defined( 'imagick::RESOURCETYPE_FILE' ) ? $imagick->getResourceLimit( imagick::RESOURCETYPE_FILE ) : $not_available ),
				'map'    => ( defined( 'imagick::RESOURCETYPE_MAP' ) ? size_format( $imagick->getResourceLimit( imagick::RESOURCETYPE_MAP ) ) : $not_available ),
				'memory' => ( defined( 'imagick::RESOURCETYPE_MEMORY' ) ? size_format( $imagick->getResourceLimit( imagick::RESOURCETYPE_MEMORY ) ) : $not_available ),
				'thread' => ( defined( 'imagick::RESOURCETYPE_THREAD' ) ? $imagick->getResourceLimit( imagick::RESOURCETYPE_THREAD ) : $not_available ),
			);

			$limits_debug = array(
				'imagick::RESOURCETYPE_AREA'   => ( defined( 'imagick::RESOURCETYPE_AREA' ) ? size_format( $imagick->getResourceLimit( imagick::RESOURCETYPE_AREA ) ) : 'not available' ),
				'imagick::RESOURCETYPE_DISK'   => ( defined( 'imagick::RESOURCETYPE_DISK' ) ? $imagick->getResourceLimit( imagick::RESOURCETYPE_DISK ) : 'not available' ),
				'imagick::RESOURCETYPE_FILE'   => ( defined( 'imagick::RESOURCETYPE_FILE' ) ? $imagick->getResourceLimit( imagick::RESOURCETYPE_FILE ) : 'not available' ),
				'imagick::RESOURCETYPE_MAP'    => ( defined( 'imagick::RESOURCETYPE_MAP' ) ? size_format( $imagick->getResourceLimit( imagick::RESOURCETYPE_MAP ) ) : 'not available' ),
				'imagick::RESOURCETYPE_MEMORY' => ( defined( 'imagick::RESOURCETYPE_MEMORY' ) ? size_format( $imagick->getResourceLimit( imagick::RESOURCETYPE_MEMORY ) ) : 'not available' ),
				'imagick::RESOURCETYPE_THREAD' => ( defined( 'imagick::RESOURCETYPE_THREAD' ) ? $imagick->getResourceLimit( imagick::RESOURCETYPE_THREAD ) : 'not available' ),
			);

			$info['wp-media']['fields']['imagick_limits'] = array(
				'label' => __( 'Imagick Resource Limits', 'uptimemonster-site-monitor' ),
				'value' => $limits,
				'debug' => $limits_debug,
			);

			try {
				$formats = \Imagick::queryFormats( '*' );
			} catch ( \Exception $e ) {
				$formats = array();
			}

			$info['wp-media']['fields']['imagemagick_file_formats'] = array(
				'label' => __( 'ImageMagick supported file formats', 'uptimemonster-site-monitor' ),
				'value' => ( empty( $formats ) ) ? __( 'Unable to determine', 'uptimemonster-site-monitor' ) : implode( ', ', $formats ),
				'debug' => ( empty( $formats ) ) ? 'Unable to determine' : implode( ', ', $formats ),
			);
		}

		// Get GD information, if available.
		if ( function_exists( 'gd_info' ) ) {
			$gd = gd_info();
		} else {
			$gd = false;
		}

		$info['wp-media']['fields']['gd_version'] = array(
			'label' => __( 'GD version', 'uptimemonster-site-monitor' ),
			'value' => ( is_array( $gd ) ? $gd['GD Version'] : $not_available ),
			'debug' => ( is_array( $gd ) ? $gd['GD Version'] : 'not available' ),
		);

		$gd_image_formats     = array();
		$gd_supported_formats = array(
			'GIF Create' => 'GIF',
			'JPEG'       => 'JPEG',
			'PNG'        => 'PNG',
			'WebP'       => 'WebP',
			'BMP'        => 'BMP',
			'AVIF'       => 'AVIF',
			'HEIF'       => 'HEIF',
			'TIFF'       => 'TIFF',
			'XPM'        => 'XPM',
		);

		foreach ( $gd_supported_formats as $format_key => $format ) {
			$index = $format_key . ' Support';
			if ( isset( $gd[ $index ] ) && $gd[ $index ] ) {
				array_push( $gd_image_formats, $format );
			}
		}

		if ( ! empty( $gd_image_formats ) ) {
			$info['wp-media']['fields']['gd_formats'] = array(
				'label' => __( 'GD supported file formats', 'uptimemonster-site-monitor' ),
				'value' => implode( ', ', $gd_image_formats ),
			);
		}

		// Get Ghostscript information, if available.
		if ( function_exists( 'exec' ) ) {
			$gs = exec( 'gs --version' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec

			if ( empty( $gs ) ) {
				$gs       = $not_available;
				$gs_debug = 'not available';
			} else {
				$gs_debug = $gs;
			}
		} else {
			$gs       = __( 'Unable to determine if Ghostscript is installed', 'uptimemonster-site-monitor' );
			$gs_debug = 'unknown';
		}

		$info['wp-media']['fields']['ghostscript_version'] = array(
			'label' => __( 'Ghostscript version', 'uptimemonster-site-monitor' ),
			'value' => $gs,
			'debug' => $gs_debug,
		);

		// Populate the server debug fields.
		if ( function_exists( 'php_uname' ) ) {
			$server_architecture = sprintf( '%s %s %s', php_uname( 's' ), php_uname( 'r' ), php_uname( 'm' ) );
		} else {
			$server_architecture = 'unknown';
		}

		if ( function_exists( 'phpversion' ) ) {
			$php_version_debug = phpversion();
			// Whether PHP supports 64-bit.
			$php64bit = ( PHP_INT_SIZE * 8 === 64 );

			$php_version = sprintf(
				'%s %s',
				$php_version_debug,
				( $php64bit ? __( '(Supports 64bit values)', 'uptimemonster-site-monitor' ) : __( '(Does not support 64bit values)', 'uptimemonster-site-monitor' ) )
			);

			if ( $php64bit ) {
				$php_version_debug .= ' 64bit';
			}
		} else {
			$php_version       = __( 'Unable to determine PHP version', 'uptimemonster-site-monitor' );
			$php_version_debug = 'unknown';
		}

		if ( function_exists( 'php_sapi_name' ) ) {
			$php_sapi = php_sapi_name();
		} else {
			$php_sapi = 'unknown';
		}

		$info['wp-server']['fields']['server_architecture'] = array(
			'label' => __( 'Server architecture', 'uptimemonster-site-monitor' ),
			'value' => ( 'unknown' !== $server_architecture ? $server_architecture : __( 'Unable to determine server architecture', 'uptimemonster-site-monitor' ) ),
			'debug' => $server_architecture,
		);
		$info['wp-server']['fields']['httpd_software']      = array(
			'label' => __( 'Web server', 'uptimemonster-site-monitor' ),
			'value' => ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_slash( $_SERVER['SERVER_SOFTWARE'] ) ) : __( 'Unable to determine what web server software is used', 'uptimemonster-site-monitor' ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'debug' => ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_slash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'unknown' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		);
		$info['wp-server']['fields']['php_version']         = array(
			'label' => __( 'PHP version', 'uptimemonster-site-monitor' ),
			'value' => $php_version,
			'debug' => $php_version_debug,
		);
		$info['wp-server']['fields']['php_sapi']            = array(
			'label' => __( 'PHP SAPI', 'uptimemonster-site-monitor' ),
			'value' => ( 'unknown' !== $php_sapi ? $php_sapi : __( 'Unable to determine PHP SAPI', 'uptimemonster-site-monitor' ) ),
			'debug' => $php_sapi,
		);

		// Some servers disable `ini_set()` and `ini_get()`, we check this before trying to get configuration values.
		if ( ! function_exists( 'ini_get' ) ) {
			$info['wp-server']['fields']['ini_get'] = array(
				'label' => __( 'Server settings', 'uptimemonster-site-monitor' ),
				'value' => sprintf(
				/* translators: %s: ini_get() */
					__( 'Unable to determine some settings, as the %s function has been disabled.', 'uptimemonster-site-monitor' ),
					'ini_get()'
				),
				'debug' => 'ini_get() is disabled',
			);
		} else {
			$info['wp-server']['fields']['max_input_variables'] = array(
				'label' => __( 'PHP max input variables', 'uptimemonster-site-monitor' ),
				'value' => ini_get( 'max_input_vars' ),
			);
			$info['wp-server']['fields']['time_limit']          = array(
				'label' => __( 'PHP time limit', 'uptimemonster-site-monitor' ),
				'value' => ini_get( 'max_execution_time' ),
			);

			if ( \WP_Site_Health::get_instance()->php_memory_limit !== ini_get( 'memory_limit' ) ) {
				$info['wp-server']['fields']['memory_limit']       = array(
					'label' => __( 'PHP memory limit', 'uptimemonster-site-monitor' ),
					'value' => \WP_Site_Health::get_instance()->php_memory_limit,
				);
				$info['wp-server']['fields']['admin_memory_limit'] = array(
					'label' => __( 'PHP memory limit (only for admin screens)', 'uptimemonster-site-monitor' ),
					'value' => ini_get( 'memory_limit' ),
				);
			} else {
				$info['wp-server']['fields']['memory_limit'] = array(
					'label' => __( 'PHP memory limit', 'uptimemonster-site-monitor' ),
					'value' => ini_get( 'memory_limit' ),
				);
			}

			$info['wp-server']['fields']['max_input_time']      = array(
				'label' => __( 'Max input time', 'uptimemonster-site-monitor' ),
				'value' => ini_get( 'max_input_time' ),
			);
			$info['wp-server']['fields']['upload_max_filesize'] = array(
				'label' => __( 'Upload max filesize', 'uptimemonster-site-monitor' ),
				'value' => ini_get( 'upload_max_filesize' ),
			);
			$info['wp-server']['fields']['php_post_max_size']   = array(
				'label' => __( 'PHP post max size', 'uptimemonster-site-monitor' ),
				'value' => ini_get( 'post_max_size' ),
			);
		}

		if ( function_exists( 'curl_version' ) ) {
			$curl = curl_version();

			$info['wp-server']['fields']['curl_version'] = array(
				'label' => __( 'cURL version', 'uptimemonster-site-monitor' ),
				'value' => sprintf( '%s %s', $curl['version'], $curl['ssl_version'] ),
			);
		} else {
			$info['wp-server']['fields']['curl_version'] = array(
				'label' => __( 'cURL version', 'uptimemonster-site-monitor' ),
				'value' => $not_available,
			);
		}

		// SUHOSIN.
		$suhosin_loaded = ( extension_loaded( 'suhosin' ) || ( defined( 'SUHOSIN_PATCH' ) && constant( 'SUHOSIN_PATCH' ) ) );

		$info['wp-server']['fields']['suhosin'] = array(
			'label' => __( 'Is SUHOSIN installed?', 'uptimemonster-site-monitor' ),
			'value' => ( $suhosin_loaded ? __( 'Yes', 'uptimemonster-site-monitor' ) : __( 'No', 'uptimemonster-site-monitor' ) ),
		);

		// Imagick.
		$imagick_loaded = extension_loaded( 'imagick' );

		$info['wp-server']['fields']['imagick_availability'] = array(
			'label' => __( 'Is the Imagick library available?', 'uptimemonster-site-monitor' ),
			'value' => ( $imagick_loaded ? __( 'Yes', 'uptimemonster-site-monitor' ) : __( 'No', 'uptimemonster-site-monitor' ) ),
		);

		// Pretty permalinks.
		$pretty_permalinks_supported = $this->update_check->got_url_rewrite();

		$info['wp-server']['fields']['pretty_permalinks'] = [
			'label' => __( 'Are pretty permalinks supported?', 'uptimemonster-site-monitor' ),
			'value' => ( $pretty_permalinks_supported ? __( 'Yes', 'uptimemonster-site-monitor' ) : __( 'No', 'uptimemonster-site-monitor' ) ),
		];

		// Check if a .htaccess file exists.
		if ( is_file( ABSPATH . '.htaccess' ) ) {
			// If the file exists, grab the content of it.
			$htaccess_content = file_get_contents( ABSPATH . '.htaccess' );

			// Filter away the core WordPress rules.
			$filtered_htaccess_content = trim( preg_replace( '/\# BEGIN WordPress[\s\S]+?# END WordPress/si', '', $htaccess_content ) );
			$filtered_htaccess_content = ! empty( $filtered_htaccess_content );

			if ( $filtered_htaccess_content ) {
				/* translators: %s: .htaccess */
				$htaccess_rules_string = sprintf( __( 'Custom rules have been added to your %s file.', 'uptimemonster-site-monitor' ), '.htaccess' );
			} else {
				/* translators: %s: .htaccess */
				$htaccess_rules_string = sprintf( __( 'Your %s file contains only core WordPress features.', 'uptimemonster-site-monitor' ), '.htaccess' );
			}

			$info['wp-server']['fields']['htaccess_extra_rules'] = array(
				'label' => __( '.htaccess rules', 'uptimemonster-site-monitor' ),
				'value' => $htaccess_rules_string,
				'debug' => $filtered_htaccess_content,
			);
		}

		// Populate the database debug fields.
		if ( is_resource( $wpdb->dbh ) ) {
			// Old mysql extension.
			$extension = 'mysql';
		} elseif ( is_object( $wpdb->dbh ) ) {
			// mysqli or PDO.
			$extension = get_class( $wpdb->dbh );
		} else {
			// Unknown sql extension.
			$extension = null;
		}

		$server = $wpdb->get_var( 'SELECT VERSION()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( isset( $wpdb->use_mysqli ) && $wpdb->use_mysqli ) {
			$client_version = $wpdb->dbh->client_info;
		} else {
			// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysql_get_client_info,PHPCompatibility.Extensions.RemovedExtensions.mysql_DeprecatedRemoved
			/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
			if ( preg_match( '|[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,2}|', mysql_get_client_info(), $matches ) ) { // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysql_get_client_info, PHPCompatibility.Extensions.RemovedExtensions.mysql_DeprecatedRemoved
				$client_version = $matches[0];
			} else {
				$client_version = null;
			}
		}

		$info['wp-database']['fields']['extension'] = array(
			'label' => __( 'Extension', 'uptimemonster-site-monitor' ),
			'value' => $extension,
		);

		$info['wp-database']['fields']['server_version'] = array(
			'label' => __( 'Server version', 'uptimemonster-site-monitor' ),
			'value' => $server,
		);

		$info['wp-database']['fields']['client_version'] = array(
			'label' => __( 'Client version', 'uptimemonster-site-monitor' ),
			'value' => $client_version,
		);

		$info['wp-database']['fields']['database_user'] = array(
			'label'   => __( 'Database username', 'uptimemonster-site-monitor' ),
			'value'   => $wpdb->dbuser,
			'private' => true,
		);

		$info['wp-database']['fields']['database_host'] = array(
			'label'   => __( 'Database host', 'uptimemonster-site-monitor' ),
			'value'   => $wpdb->dbhost,
			'private' => true,
		);

		$info['wp-database']['fields']['database_name'] = array(
			'label'   => __( 'Database name', 'uptimemonster-site-monitor' ),
			'value'   => $wpdb->dbname,
			'private' => true,
		);

		$info['wp-database']['fields']['database_prefix'] = array(
			'label'   => __( 'Table prefix', 'uptimemonster-site-monitor' ),
			'value'   => $wpdb->prefix,
			'private' => true,
		);

		$info['wp-database']['fields']['database_charset'] = array(
			'label'   => __( 'Database charset', 'uptimemonster-site-monitor' ),
			'value'   => $wpdb->charset,
			'private' => true,
		);

		$info['wp-database']['fields']['database_collate'] = array(
			'label'   => __( 'Database collation', 'uptimemonster-site-monitor' ),
			'value'   => $wpdb->collate,
			'private' => true,
		);

		$info['wp-database']['fields']['max_allowed_packet'] = array(
			'label' => __( 'Max allowed packet size', 'uptimemonster-site-monitor' ),
			'value' => self::get_mysql_var( 'max_allowed_packet' ),
		);

		$info['wp-database']['fields']['max_connections'] = array(
			'label' => __( 'Max connections number', 'uptimemonster-site-monitor' ),
			'value' => self::get_mysql_var( 'max_connections' ),
		);

		// List must use plugins if there are any.
		$mu_plugins = get_mu_plugins();

		foreach ( $mu_plugins as $plugin_path => $plugin ) {
			$info['wp-mu-plugins']['fields'][] = [
				'label' => $plugin['Name'],
				'slug'  => $plugin_path,
				'value' => array_merge( uptimemonster_prepare_plugin_data( $plugin ), $common_data ),
			];
		}

		// List all available plugins.
		$plugins        = get_plugins();
		$plugin_updates = $this->update_check->get_plugin_updates();
		$transient      = get_site_transient( 'update_plugins' );

		$auto_updates = [];

		$auto_updates_enabled = $this->update_check->wp_is_auto_update_enabled_for_type( 'plugin' );

		if ( $auto_updates_enabled ) {
			$auto_updates = (array) get_site_option( 'auto_update_plugins', [] );
		}


		foreach ( $plugins as $plugin_path => $plugin ) {
			$plugin_part = ( is_plugin_active( $plugin_path ) ) ? 'wp-plugins-active' : 'wp-plugins-inactive';
			$plugin_data = uptimemonster_prepare_plugin_data( $plugin );

			if ( array_key_exists( $plugin_path, $plugin_updates ) ) {
				/* translators: %s: Latest plugin version number. */
				$plugin_data['new_version'] = $plugin_updates[ $plugin_path ]->update->new_version;

				if ( version_compare( $plugin['Version'], $plugin_updates[ $plugin_path ]->update->new_version, '<' ) ) {
					$plugin_data['need_update'] = true;
				}
			}

			if ( $auto_updates_enabled ) {
				if ( isset( $transient->response[ $plugin_path ] ) ) {
					$item = $transient->response[ $plugin_path ];
				} elseif ( isset( $transient->no_update[ $plugin_path ] ) ) {
					$item = $transient->no_update[ $plugin_path ];
				} else {
					$item = [
						'id'            => $plugin_path,
						'slug'          => '',
						'plugin'        => $plugin_path,
						'new_version'   => '',
						'url'           => '',
						'package'       => '',
						'icons'         => [],
						'banners'       => [],
						'banners_rtl'   => [],
						'tested'        => '',
						'requires_php'  => '',
						'compatibility' => new \stdClass(),
					];
					$item = wp_parse_args( $plugin, $item );
				}

				$auto_update_forced = $this->update_check->wp_is_auto_update_forced_for_item( 'plugin', null, (object) $item );

				if ( ! is_null( $auto_update_forced ) ) {
					$enabled = $auto_update_forced;
				} else {
					$enabled = in_array( $plugin_path, $auto_updates, true );
				}

				if ( $enabled ) {
					$plugin_data['auto_update'] = 'enabled';
				}
			}

			$plugin_data['is_uninstallable'] = is_uninstallable_plugin( $plugin_path );

			$info[ $plugin_part ]['fields'][] = [
				'label' => $plugin['Name'],
				'slug'  => $plugin_path,
				'value' => $plugin_data,
			];
		}

		// Populate the section for the currently active theme.
		global $_wp_theme_features;
		$theme_features = [];

		if ( ! empty( $_wp_theme_features ) ) {
			foreach ( $_wp_theme_features as $feature => $options ) {
				$theme_features[] = $feature;
			}
		}

		$active_theme  = wp_get_theme();
		$theme_updates = $this->get_theme_updates();
		$transient     = get_site_transient( 'update_themes' );

		$auto_updates         = [];
		$auto_updates_enabled = $this->update_check->wp_is_auto_update_enabled_for_type( 'theme' );
		if ( $auto_updates_enabled ) {
			$auto_updates = (array) get_site_option( 'auto_update_themes', [] );
		}

		$need_to_update_theme     = false;
		$theme_update_new_version = null;
		if ( array_key_exists( $active_theme->stylesheet, $theme_updates ) ) {
			$theme_update_new_version = $theme_updates[ $active_theme->stylesheet ]->update['new_version'];
			if ( version_compare( $active_theme->version, $theme_update_new_version, '<' ) ) {
				$need_to_update_theme = true;
			}
		}

		$parent_theme = [
			'name' => __( 'None', 'uptimemonster-site-monitor' ),
			'slug' => 'none',
		];

		if ( $active_theme->parent_theme ) {
			$parent_theme = [
				'name' => $active_theme->parent_theme,
				'slug' => $active_theme->template,
			];
		}

		$info['wp-active-theme'] = [
			'name'           => $active_theme->name,
			'slug'           => $active_theme->stylesheet,
			'version'        => $active_theme->version,
			'latest_version' => $theme_update_new_version,
			'update'         => $need_to_update_theme,
			'author'         => wp_kses( $active_theme->author, [] ),
			'author_website' => $active_theme->display( 'AuthorURI' ),
			'parent_theme'   => $parent_theme,
			'theme_features' => $theme_features,
			'theme_path'     => get_stylesheet_directory(),
			'auto_update'    => 'disabled',
		];

		if ( $auto_updates_enabled ) {
			if ( isset( $transient->response[ $active_theme->stylesheet ] ) ) {
				$item = $transient->response[ $active_theme->stylesheet ];
			} elseif ( isset( $transient->no_update[ $active_theme->stylesheet ] ) ) {
				$item = $transient->no_update[ $active_theme->stylesheet ];
			} else {
				$item = [
					'theme'        => $active_theme->stylesheet,
					'new_version'  => $active_theme->version,
					'url'          => '',
					'package'      => '',
					'requires'     => '',
					'requires_php' => '',
				];
			}

			$auto_update_forced = $this->update_check->wp_is_auto_update_forced_for_item( 'theme', null, (object) $item );

			if ( ! is_null( $auto_update_forced ) ) {
				$enabled = $auto_update_forced;
			} else {
				$enabled = in_array( $active_theme->stylesheet, $auto_updates, true );
			}

			if ( $enabled ) {
				$info['wp-active-theme']['auto_update'] = 'enabled';
			}
		}

		$parent_theme = $active_theme->parent();

		if ( $parent_theme ) {
			$need_to_update_parent_theme     = false;
			$parent_theme_update_new_version = null;
			if ( array_key_exists( $parent_theme->stylesheet, $theme_updates ) ) {
				$parent_theme_update_new_version = $theme_updates[ $parent_theme->stylesheet ]->update['new_version'];
				if ( version_compare( $parent_theme->version, $parent_theme_update_new_version, '<' ) ) {
					$need_to_update_parent_theme = true;
				}
			}


			$info['wp-parent-theme'] = [
				'slug'           => $parent_theme->stylesheet,
				'name'           => $parent_theme->name,
				'version'        => $parent_theme->version,
				'latest_version' => $parent_theme_update_new_version,
				'update'         => $need_to_update_parent_theme,
				'author'         => wp_kses( $parent_theme->author, [] ),
				'author_website' => $parent_theme->display( 'AuthorURI' ),
				'theme_features' => null,
				'theme_path'     => get_template_directory(),
				'auto_update'    => 'disabled',
			];

			if ( $auto_updates_enabled ) {
				if ( isset( $transient->response[ $parent_theme->stylesheet ] ) ) {
					$item = $transient->response[ $parent_theme->stylesheet ];
				} elseif ( isset( $transient->no_update[ $parent_theme->stylesheet ] ) ) {
					$item = $transient->no_update[ $parent_theme->stylesheet ];
				} else {
					$item = array(
						'theme'        => $parent_theme->stylesheet,
						'new_version'  => $parent_theme->version,
						'url'          => '',
						'package'      => '',
						'requires'     => '',
						'requires_php' => '',
					);
				}

				$auto_update_forced = $this->update_check->wp_is_auto_update_forced_for_item( 'theme', null, (object) $item );

				if ( ! is_null( $auto_update_forced ) ) {
					$enabled = $auto_update_forced;
				} else {
					$enabled = in_array( $parent_theme->stylesheet, $auto_updates, true );
				}

				if ( $enabled ) {
					$info['wp-parent-theme']['auto_update'] = 'enabled';
				}
			}
		}

		// Populate a list of all themes available in the `wp-content/themes` directory.
		$all_themes = wp_get_themes();

		foreach ( $all_themes as $theme_slug => $theme ) {
			// Exclude the currently active theme from the list of all themes.
			if ( $active_theme->stylesheet === $theme_slug ) {
				continue;
			}

			// Exclude the currently active parent theme from the list of all themes.
			if ( ! empty( $parent_theme ) && $parent_theme->stylesheet === $theme_slug ) {
				continue;
			}

			$data = [
				'slug'           => $theme->stylesheet,
				'name'           => $theme->name,
				'version'        => $theme->version,
				'latest_version' => null,
				'update'         => false,
				'author'         => wp_kses( $theme->author, [] ),
				'author_website' => $theme->display( 'AuthorURI' ),
				'theme_features' => null,
				'theme_path'     => get_template_directory(),
				'auto_update'    => 'disabled',
			];

			if ( array_key_exists( $theme_slug, $theme_updates ) ) {
				$data['latest_version'] = $theme_updates[ $theme_slug ]->update['new_version'];
				if ( version_compare( $theme->version, $theme_updates[ $theme_slug ]->update['new_version'], '<' ) ) {
					$data['update'] = true;
				}
			}

			if ( $auto_updates_enabled ) {
				if ( isset( $transient->response[ $theme_slug ] ) ) {
					$item = $transient->response[ $theme_slug ];
				} elseif ( isset( $transient->no_update[ $theme_slug ] ) ) {
					$item = $transient->no_update[ $theme_slug ];
				} else {
					$item = [
						'theme'        => $theme_slug,
						'new_version'  => $theme->version,
						'url'          => '',
						'package'      => '',
						'requires'     => '',
						'requires_php' => '',
					];
				}

				$auto_update_forced = $this->update_check->wp_is_auto_update_forced_for_item( 'theme', null, (object) $item );

				if ( ! is_null( $auto_update_forced ) ) {
					$enabled = $auto_update_forced;
				} else {
					$enabled = in_array( $theme_slug, $auto_updates, true );
				}

				if ( $enabled ) {
					$data['auto_update'] = 'enabled';
				}
			}

			$info['wp-themes-inactive'][] = $data;
		}

		// Add more filesystem checks.
		if ( defined( 'WPMU_PLUGIN_DIR' ) && is_dir( WPMU_PLUGIN_DIR ) ) {
			$is_writable_wpmu_plugin_dir = wp_is_writable( WPMU_PLUGIN_DIR );

			$info['wp-filesystem']['fields']['mu-plugins'] = [
				'label' => __( 'The must use plugins directory', 'uptimemonster-site-monitor' ),
				'value' => ( $is_writable_wpmu_plugin_dir ? 'writable' : 'not-writable' ),
			];
		}

		return [
			'version' => '1.0.0',
			'data'    => $info,
		];
	}

	/**
	 * @return array
	 */
	public function get_plugin_updates() {
		$all_plugins     = get_plugins();
		$upgrade_plugins = array();
		$current         = get_site_transient( 'update_plugins' );
		foreach ( (array) $all_plugins as $plugin_file => $plugin_data ) {
			if ( isset( $current->response[ $plugin_file ] ) ) {
				$upgrade_plugins[ $plugin_file ]         = (object) $plugin_data;
				$upgrade_plugins[ $plugin_file ]->update = $current->response[ $plugin_file ];
			}
		}

		return $upgrade_plugins;
	}

	/**
	 * Returns the value of a MySQL system variable.
	 *
	 * @param string $mysql_var Name of the MySQL system variable.
	 *
	 * @return string|null The variable value on success. Null if the variable does not exist.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public static function get_mysql_var( $mysql_var ) {
		global $wpdb;

		$result = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare( 'SHOW VARIABLES LIKE %s', $mysql_var ),
			ARRAY_A
		);

		if ( ! empty( $result ) && array_key_exists( 'Value', $result ) ) {
			return $result['Value'];
		}

		return null;
	}

	/**
	 * Format the information gathered for debugging, in a manner suitable for copying to a forum or support ticket.
	 *
	 * @param array $info_array Information gathered from the `WP_Debug_Data::debug_data()` function.
	 * @param string $data_type The data type to return, either 'info' or 'debug'.
	 *
	 * @return string The formatted data.
	 */
	public static function format( $info_array, $data_type ) {
		$return = "`\n";

		foreach ( $info_array as $section => $details ) {
			// Skip this section if there are no fields, or the section has been declared as private.
			if ( empty( $details['fields'] ) || ( isset( $details['private'] ) && $details['private'] ) ) {
				continue;
			}

			$section_label = 'debug' === $data_type ? $section : $details['label'];

			$return .= sprintf(
				"### %s%s ###\n\n",
				$section_label,
				( isset( $details['show_count'] ) && $details['show_count'] ? sprintf( ' (%d)', count( $details['fields'] ) ) : '' )
			);

			foreach ( $details['fields'] as $field_name => $field ) {
				if ( isset( $field['private'] ) && true === $field['private'] ) {
					continue;
				}

				if ( 'debug' === $data_type && isset( $field['debug'] ) ) {
					$debug_data = $field['debug'];
				} else {
					$debug_data = $field['value'];
				}

				// Can be array, one level deep only.
				if ( is_array( $debug_data ) ) {
					$value = '';

					foreach ( $debug_data as $sub_field_name => $sub_field_value ) {
						$value .= sprintf( "\n\t%s: %s", $sub_field_name, $sub_field_value );
					}
				} elseif ( is_bool( $debug_data ) ) {
					$value = $debug_data ? 'true' : 'false';
				} elseif ( empty( $debug_data ) && '0' !== $debug_data ) {
					$value = 'undefined';
				} else {
					$value = $debug_data;
				}

				if ( 'debug' === $data_type ) {
					$label = $field_name;
				} else {
					$label = $field['label'];
				}

				$return .= sprintf( "%s: %s\n", $label, $value );
			}

			$return .= "\n";
		}

		$return .= '`';

		return $return;
	}

	/**
	 * Fetch the total size of all the database tables for the active database user.
	 *
	 * @return int The size of the database, in bytes.
	 */
	public static function get_database_size() {
		global $wpdb;
		$size = 0;
		$rows = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( $wpdb->num_rows > 0 ) {
			foreach ( $rows as $row ) {
				$size += $row['Data_length'] + $row['Index_length'];
			}
		}

		return (int) $size;
	}

	/**
	 * Fetch the sizes of the WordPress directories: `wordpress` (ABSPATH), `plugins`, `themes`, and `uploads`.
	 * Intended to supplement the array returned by `WP_Debug_Data::debug_data()`.
	 *
	 * @return array The sizes of the directories, also the database size and total installation size.
	 */
	public static function get_sizes() {
		$size_db    = self::get_database_size();
		$upload_dir = wp_get_upload_dir();

		/*
		 * We will be using the PHP max execution time to prevent the size calculations
		 * from causing a timeout. The default value is 30 seconds, and some
		 * hosts do not allow you to read configuration values.
		 */
		if ( function_exists( 'ini_get' ) ) {
			$max_execution_time = ini_get( 'max_execution_time' );
		}

		// The max_execution_time defaults to 0 when PHP runs from cli.
		// We still want to limit it below.
		if ( empty( $max_execution_time ) ) {
			$max_execution_time = 30;
		}

		if ( $max_execution_time > 20 ) {
			// If the max_execution_time is set to lower than 20 seconds, reduce it a bit to prevent
			// edge-case timeouts that may happen after the size loop has finished running.
			$max_execution_time -= 2;
		}

		// Go through the various installation directories and calculate their sizes.
		// No trailing slashes.
		$paths = array(
			'wordpress_size' => untrailingslashit( ABSPATH ),
			'themes_size'    => get_theme_root(),
			'plugins_size'   => WP_PLUGIN_DIR,
			'uploads_size'   => $upload_dir['basedir'],
		);

		$exclude = $paths;
		unset( $exclude['wordpress_size'] );
		$exclude = array_values( $exclude );

		$size_total = 0;
		$all_sizes  = array();

		// Loop over all the directories we want to gather the sizes for.
		foreach ( $paths as $name => $path ) {
			$dir_size = null; // Default to timeout.
			$results  = array(
				'path' => $path,
				'raw'  => 0,
			);

			if ( microtime( true ) - WP_START_TIMESTAMP < $max_execution_time ) {
				if ( 'wordpress_size' === $name ) {
					$dir_size = recurse_dirsize( $path, $exclude, $max_execution_time );
				} else {
					$dir_size = recurse_dirsize( $path, null, $max_execution_time );
				}
			}

			if ( false === $dir_size ) {
				// Error reading.
				$results['size']  = __( 'The size cannot be calculated. The directory is not accessible. Usually caused by invalid permissions.', 'uptimemonster-site-monitor'  );
				$results['debug'] = 'not accessible';

				// Stop total size calculation.
				$size_total = null;
			} elseif ( null === $dir_size ) {
				// Timeout.
				$results['size']  = __( 'The directory size calculation has timed out. Usually caused by a very large number of sub-directories and files.', 'uptimemonster-site-monitor' );
				$results['debug'] = 'timeout while calculating size';

				// Stop total size calculation.
				$size_total = null;
			} else {
				if ( null !== $size_total ) {
					$size_total += $dir_size;
				}

				$results['raw']   = $dir_size;
				$results['size']  = size_format( $dir_size, 2 );
				$results['debug'] = $results['size'] . " ({$dir_size} bytes)";
			}

			$all_sizes[ $name ] = $results;
		}

		if ( $size_db > 0 ) {
			$database_size = size_format( $size_db, 2 );

			$all_sizes['database_size'] = array(
				'raw'   => $size_db,
				'size'  => $database_size,
				'debug' => $database_size . " ({$size_db} bytes)",
			);
		} else {
			$all_sizes['database_size'] = array(
				'size'  => __( 'Not available', 'uptimemonster-site-monitor' ),
				'debug' => 'not available',
			);
		}

		if ( null !== $size_total && $size_db > 0 ) {
			$total_size    = $size_total + $size_db;
			$total_size_mb = size_format( $total_size, 2 );

			$all_sizes['total_size'] = array(
				'raw'   => $total_size,
				'size'  => $total_size_mb,
				'debug' => $total_size_mb . " ({$total_size} bytes)",
			);
		} else {
			$all_sizes['total_size'] = array(
				'size'  => __( 'Total size is not available. Some errors were encountered when determining the size of your installation.', 'uptimemonster-site-monitor' ),
				'debug' => 'not available',
			);
		}

		return $all_sizes;
	}

	/**
	 * Gets and caches the checksums for the given version of WordPress.
	 *
	 * @param string $version Version string to query.
	 * @param string $locale Locale to query.
	 *
	 * @return array|false An array of checksums on success, false on failure.
	 */
	public function get_core_checksums( $version, $locale ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$http_url = 'http://api.wordpress.org/core/checksums/1.0/?' . http_build_query( compact( 'version', 'locale' ), '', '&' );
		$url      = $http_url;

		$ssl = wp_http_supports( array( 'ssl' ) );
		if ( $ssl ) {
			$url = set_url_scheme( $url, 'https' );
		}

		$options = array(
			'timeout' => wp_doing_cron() ? 30 : 3,
		);

		$response = wp_remote_get( $url, $options ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		if ( $ssl && is_wp_error( $response ) ) {
			trigger_error( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				sprintf(
				/* translators: %s: Support forums URL. */
					__( 'An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration. If you continue to have problems, please try the <a href="%s">support forums</a>.', 'uptimemonster-site-monitor' ),
					__( 'https://wordpress.org/support/forums/', 'uptimemonster-site-monitor' )
				) . ' UptimeMonster_Debug_Data.php' . __( '(WordPress could not establish a secure connection to WordPress.org. Please contact your server administrator.)', 'uptimemonster-site-monitor' ),
				headers_sent() || WP_DEBUG ? E_USER_WARNING : E_USER_NOTICE
			);
			$response = wp_remote_get( $http_url, $options ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		}

		if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = trim( wp_remote_retrieve_body( $response ) );
		$body = json_decode( $body, true );

		if ( ! is_array( $body ) || ! isset( $body['checksums'] ) || ! is_array( $body['checksums'] ) ) {
			return false;
		}

		return $body['checksums'];
	}

	/**
	 * @return array
	 */
	public function get_theme_updates() {
		$current = get_site_transient( 'update_themes' );

		if ( ! isset( $current->response ) ) {
			return array();
		}

		$update_themes = array();
		foreach ( $current->response as $stylesheet => $data ) {
			$update_themes[ $stylesheet ]         = wp_get_theme( $stylesheet );
			$update_themes[ $stylesheet ]->update = $data;
		}

		return $update_themes;
	}

	/**
	 * Prints the JavaScript templates for update admin notices.
	 *
	 * Template takes one argument with four values:
	 *
	 *     param {object} data {
	 *         Arguments for admin notice.
	 *
	 * @type string id        ID of the notice.
	 * @type string className Class names for the notice.
	 * @type string message   The notice's message.
	 * @type string type      The type of update the notice is for. Either 'plugin' or 'theme'.
	 *     }
	 */
	public function wp_print_admin_notice_templates() {
		?>
		<script id="tmpl-wp-updates-admin-notice" type="text/html">
			<div <# if ( data.id ) { #>id="{{ data.id }}"<# } #> class="notice {{ data.className }}"><p>{{{ data.message }}}</p></div> <?php // phpcs:ignore WordPressVIPMinimum.Security.Mustache.OutputNotation ?>
		</script>
		<script id="tmpl-wp-bulk-updates-admin-notice" type="text/html">
			<div id="{{ data.id }}" class="{{ data.className }} notice <# if ( data.errors ) { #>notice-error<# } else { #>notice-success<# } #>">
				<p>
					<# if ( data.successes ) { #>
					<# if ( 1 === data.successes ) { #>
					<# if ( 'plugin' === data.type ) { #>
					<?php
					/* translators: %s: Number of plugins. */
					printf( __( '%s plugin successfully updated.', 'uptimemonster-site-monitor' ), '{{ data.successes }}' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
					<# } else { #>
					<?php
					/* translators: %s: Number of themes. */
					printf( __( '%s theme successfully updated.', 'uptimemonster-site-monitor' ), '{{ data.successes }}' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
					<# } #>
					<# } else { #>
					<# if ( 'plugin' === data.type ) { #>
					<?php
					/* translators: %s: Number of plugins. */
					printf( __( '%s plugins successfully updated.', 'uptimemonster-site-monitor' ), '{{ data.successes }}' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
					<# } else { #>
					<?php
					/* translators: %s: Number of themes. */
					printf( __( '%s themes successfully updated.', 'uptimemonster-site-monitor' ), '{{ data.successes }}' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
					<# } #>
					<# } #>
					<# } #>
					<# if ( data.errors ) { #>
					<button class="button-link bulk-action-errors-collapsed" aria-expanded="false">
						<# if ( 1 === data.errors ) { #>
						<?php
						/* translators: %s: Number of failed updates. */
						printf( __( '%s update failed.', 'uptimemonster-site-monitor' ), '{{ data.errors }}' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
						<# } else { #>
						<?php
						/* translators: %s: Number of failed updates. */
						printf( __( '%s updates failed.', 'uptimemonster-site-monitor' ), '{{ data.errors }}' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
						<# } #>
						<span class="screen-reader-text"><?php _e( 'Show more details', 'uptimemonster-site-monitor' ); ?></span>
						<span class="toggle-indicator" aria-hidden="true"></span>
					</button>
					<# } #>
				</p>
				<# if ( data.errors ) { #>
				<ul class="bulk-action-errors hidden">
					<# _.each( data.errorMessages, function( errorMessage ) { #>
					<li>{{ errorMessage }}</li>
					<# } ); #>
				</ul>
				<# } #>
			</div>
		</script>
		<?php
	}

	/**
	 * Determines the appropriate auto-update message to be displayed.
	 *
	 * @return string The update message to be shown.
	 */
	public function wp_get_auto_update_message() {
		$next_update_time = wp_next_scheduled( 'wp_version_check' );

		// Check if the event exists.
		if ( false === $next_update_time ) {
			$message = __( 'Automatic update not scheduled. There may be a problem with WP-Cron.', 'uptimemonster-site-monitor' );
		} else {
			$time_to_next_update = human_time_diff( (int) $next_update_time );

			// See if cron is overdue.
			$overdue = ( time() - $next_update_time ) > 0;

			if ( $overdue ) {
				$message = sprintf(
				/* translators: %s: Duration that WP-Cron has been overdue. */
					__( 'Automatic update overdue by %s. There may be a problem with WP-Cron.', 'uptimemonster-site-monitor' ),
					$time_to_next_update
				);
			} else {
				$message = sprintf(
				/* translators: %s: Time until the next update. */
					__( 'Automatic update scheduled in %s.', 'uptimemonster-site-monitor' ),
					$time_to_next_update
				);
			}
		}

		return $message;
	}
}
