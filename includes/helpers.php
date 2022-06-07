<?php
/**
 * Helper Functions
 *
 * @package Package
 * @author Name <email>
 * @version
 * @since
 * @license
 */

use AbsolutePlugins\RoxwpSiteMonitor\RoxWP_Site_Monitor;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

/**
 * Switch to english language (en_US) so we can understand and analyse the data.
 *
 * Must restore language after uses.
 *
 * @see roxwp_restore_locale()
 */
function roxwp_switch_to_english() {
	if ( function_exists( 'switch_to_locale' ) ) {
		switch_to_locale( 'en_US' );

		// Filter on plugin_locale so other plugin/theme can load the correct locale.
		add_filter( 'plugin_locale', 'get_locale' );

		RoxWP_Site_Monitor::get_instance()->load_plugin_textdomain();
	}
}

/**
 * Switch Plugin language to original.
 */
function roxwp_restore_locale() {
	if ( function_exists( 'restore_previous_locale' ) ) {
		restore_previous_locale();

		// Remove filter.
		remove_filter( 'plugin_locale', 'get_locale' );
	}
}

/**
 * Get Gmt Time in mysql format.
 *
 * @return string
 */
function roxwp_get_current_time() {
	return (string) current_time( 'mysql', 1 );
}

/**
 * Get current actor data.
 *
 * @return array|string[]
 */
function roxwp_get_current_actor() {
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		$actor = [
			'type' => 'cron',
			'name' => 'WP Cron',
			'ip'   => roxwp_get_ip_address(), // maybe cron triggered by visitor.
		];
	} elseif ( class_exists( '\WP_CLI', false ) ) {
		$actor = [
			'type' => 'wp-cli',
			'name' => 'WP CLI',
			'ip'   => roxwp_get_ip_address(), // maybe cron triggered by visitor.
		];
	} else {
		if ( ! function_exists( 'is_user_logged_in' ) ) {
			require_once ABSPATH . 'wp-includes/pluggable.php';
		}
		if ( is_user_logged_in() ) {
			$actor = wp_get_current_user();
			$actor = [
				'type'  => 'user',
				'id'    => $actor->ID,
				'ip'    => roxwp_get_ip_address(),
				'name'  => roxwp_get_user_display_name( $actor ),
				'email' => $actor->user_email,
				'role'  => roxwp_get_user_role( $actor ),
			];
		} else {
			$actor = [
				'type' => 'visitor',
				'name' => 'Unknown Visitor',
				'ip'   => roxwp_get_ip_address(),
			];
		}
	}

	return $actor; // @phpstan-ignore-line
}

/**
 * Get User by identity.
 *
 * @param WP_User|int|string $identity User's identity (username, email or id)
 * @param string|int $field Optional. Field
 *
 * @return false|WP_User
 */
function roxwp_get_user( $identity, $field = null ) {
	if ( $identity instanceof WP_User ) {
		return $identity;
	}

	if ( ! $field ) {
		$field = 'id';
		if ( is_string( $identity ) ) {
			if ( is_email( $identity ) ) {
				$field = 'email';
			} else {
				$field = 'login';
			}
		}
	}

	return get_user_by( (string) $field, $identity );
}

/**
 * @param WP_User|false $user
 *
 * @return string
 */
function roxwp_get_user_display_name( $user ) {
	$name = trim( implode( ' ', [ $user->first_name, $user->last_name ] ) ); // @phpstan-ignore-line

	if ( empty( $name ) ) {
		$name = $user->display_name; // @phpstan-ignore-line
	}

	if ( empty( $name ) ) {
		$name = $user->user_nicename; // @phpstan-ignore-line
	}

	return $name;
}

/**
 * @param WP_User $user
 *
 * @return string
 */
function roxwp_get_user_role( $user ) {
	return strtolower( (string) key( $user->caps ) );
}

/**
 * Get current visitor ip.
 *
 * @return string
 */
function roxwp_get_ip_address() {
	$server_ip_keys = [
		'HTTP_CF_CONNECTING_IP', // CloudFlare
		'HTTP_TRUE_CLIENT_IP', // CloudFlare Enterprise header
		'HTTP_CLIENT_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'REMOTE_ADDR',
	];

	foreach ( $server_ip_keys as $key ) {
		if ( isset( $_SERVER[ $key ] ) && filter_var( $_SERVER[ $key ], FILTER_VALIDATE_IP ) ) {
			return sanitize_text_field( $_SERVER[ $key ] );
		}
	}

	// Fallback local ip.
	return '127.0.0.1';
}

/**
 * @param string $plugin_file
 *
 * @return array|false
 */
function roxwp_get_plugin_data( $plugin_file ) {
	if ( ! is_readable( $plugin_file ) ) {
		return false;
	}

	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	// Get Data.
	$plugin_data = get_plugin_data( $plugin_file, false, false );
	// Set Installation status
	$plugin_data['Status'] = (int) is_plugin_active( $plugin_file );

	return $plugin_data;
}

function roxwp_get_all_plugins() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	roxwp_switch_to_english();

	$plugins    = get_plugins();
	$mu_plugins = get_mu_plugins();
	$dropins    = get_dropins();

	foreach ( $plugins as $slug => &$plugin ) {
		$plugin['Type']   = 'plugin';
		$plugin['Status'] = (int) is_plugin_active( $slug );
		if ( isset( $plugin['Tags'] ) ) {
			unset( $plugin['Tags'] );
		}
	}

	foreach ( $mu_plugins as &$plugin ) {
		$plugin['Type']   = 'mu';
		$plugin['Status'] = 1;
		if ( isset( $plugin['Tags'] ) ) {
			unset( $plugin['Tags'] );
		}
	}

	foreach ( $dropins as &$plugin ) {
		$plugin['Type']   = 'dropin';
		$plugin['Status'] = 1;
		if ( isset( $plugin['Tags'] ) ) {
			unset( $plugin['Tags'] );
		}
	}

	$data = array_merge( $plugins, $mu_plugins, $dropins );

	roxwp_restore_locale();

	if ( isset( $data['fatal-error-handler.php'] ) || isset( $data['roxwp-site-monitor/roxwp-site-monitor.php'] ) ) {
		$data['fatal-error-handler.php']['isRoxMon'] = true;
	}

	return $data;
}

/**
 * Get All theme with data.
 *
 * @return array|array[]
 */
function roxwp_get_all_themes() {
	if ( ! function_exists( 'wp_get_themes' ) ) {
		require_once ABSPATH . 'wp-includes/theme.php';
	}

	$themes = wp_get_themes();

	if ( empty( $themes ) ) {
		return [];
	}

	return array_map(
		function ( $theme ) {
			return roxwp_get_theme_data_headers( $theme );
		},
		$themes
	);
}

/**
 * @param WP_Theme $theme
 *
 * @return array
 */
function roxwp_get_theme_data_headers( $theme ) {
	$headers = [
		'Name',
		'Parent Theme',
		'ThemeURI',
		'Description',
		'Author',
		'AuthorURI',
		'Version',
		'Template',
		'Status',
		'RequiresWP',
		'RequiresPHP',
	];

	$data = [];

	foreach ( $headers as $header ) {
		if ( is_a( $theme, WP_Theme::class ) ) {
			$data[ $header ] = $theme->get( $header );
			if ( 'Status' === $header ) {
				$data['Status'] = $theme->get_stylesheet() === get_stylesheet();
			}
		} else {
			$data[ $header ] = '';
		}
	}

	return $data;
}

function get_site_health_tests() {
	$tests = array(
		'direct' => array(
			'wordpress_version'         => array(
				'label' => __( 'WordPress Version' ),
				'test'  => 'wordpress_version',
			),
			'plugin_version'            => array(
				'label' => __( 'Plugin Versions' ),
				'test'  => 'plugin_version',
			),
			'theme_version'             => array(
				'label' => __( 'Theme Versions' ),
				'test'  => 'theme_version',
			),
			'php_version'               => array(
				'label' => __( 'PHP Version' ),
				'test'  => 'php_version',
			),
			'php_extensions'            => array(
				'label' => __( 'PHP Extensions' ),
				'test'  => 'php_extensions',
			),
			'php_default_timezone'      => array(
				'label' => __( 'PHP Default Timezone' ),
				'test'  => 'php_default_timezone',
			),
			'php_sessions'              => array(
				'label' => __( 'PHP Sessions' ),
				'test'  => 'php_sessions',
			),
			'sql_server'                => array(
				'label' => __( 'Database Server version' ),
				'test'  => 'sql_server',
			),
			'utf8mb4_support'           => array(
				'label' => __( 'MySQL utf8mb4 support' ),
				'test'  => 'utf8mb4_support',
			),
			'ssl_support'               => array(
				'label' => __( 'Secure communication' ),
				'test'  => 'ssl_support',
			),
			'scheduled_events'          => array(
				'label' => __( 'Scheduled events' ),
				'test'  => 'scheduled_events',
			),
			'http_requests'             => array(
				'label' => __( 'HTTP Requests' ),
				'test'  => 'http_requests',
			),
			'rest_availability'         => array(
				'label'     => __( 'REST API availability' ),
				'test'      => 'rest_availability',
				'skip_cron' => true,
			),
			'debug_enabled'             => array(
				'label' => __( 'Debugging enabled' ),
				'test'  => 'is_in_debug_mode',
			),
			'file_uploads'              => array(
				'label' => __( 'File uploads' ),
				'test'  => 'file_uploads',
			),
			'plugin_theme_auto_updates' => array(
				'label' => __( 'Plugin and theme auto-updates' ),
				'test'  => 'plugin_theme_auto_updates',
			),
		),
		'async'  => array(
			'dotorg_communication' => array(
				'label'             => __( 'Communication with WordPress.org' ),
				'test'              => rest_url( 'wp-site-health/v1/tests/dotorg-communication' ),
				'has_rest'          => true,
				'async_direct_test' => array( WP_Site_Health::get_instance(), 'get_test_dotorg_communication' ),
			),
			'background_updates'   => array(
				'label'             => __( 'Background updates' ),
				'test'              => rest_url( 'wp-site-health/v1/tests/background-updates' ),
				'has_rest'          => true,
				'async_direct_test' => array( WP_Site_Health::get_instance(), 'get_test_background_updates' ),
			),
			'loopback_requests'    => array(
				'label'             => __( 'Loopback request' ),
				'test'              => rest_url( 'wp-site-health/v1/tests/loopback-requests' ),
				'has_rest'          => true,
				'async_direct_test' => array( WP_Site_Health::get_instance(), 'get_test_loopback_requests' ),
			),
			'https_status'         => array(
				'label'             => __( 'HTTPS status' ),
				'test'              => rest_url( 'wp-site-health/v1/tests/https-status' ),
				'has_rest'          => true,
				'async_direct_test' => array( WP_Site_Health::get_instance(), 'get_test_https_status' ),
			),
		),
	);

	return $tests;
}

// End of file helpers.php.
