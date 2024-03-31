<?php
/**
 * Helper Functions
 *
 * @package UptimeMonster\SiteMonitor
 */

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use UptimeMonster\SiteMonitor\UptimeMonster_Site_Monitor;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

/**
 * Switch to english language (en_US) so we can understand and analyse the data.
 *
 * Must restore language after uses.
 *
 * @see uptimemonster_restore_locale()
 */
function uptimemonster_switch_to_english() {
	if ( function_exists( 'switch_to_locale' ) ) {
		switch_to_locale( 'en_US' );

		// Filter on plugin_locale so other plugin/theme can load the correct locale.
		add_filter( 'plugin_locale', 'get_locale' );

		UptimeMonster_Site_Monitor::get_instance()->load_plugin_textdomain();
	}
}

/**
 * Switch Plugin language to original.
 */
function uptimemonster_restore_locale() {
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
function uptimemonster_get_current_time() {
	return (string) current_time( 'mysql', 1 );
}

/**
 * Get current actor data.
 *
 * @return array|string[]
 */
function uptimemonster_get_current_actor() {
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		$actor = [
			'type' => 'cron',
			'name' => 'WP Cron',
		];
	} elseif ( class_exists( '\WP_CLI', false ) ) {
		$actor = [
			'type' => 'wp-cli',
			'name' => 'WP CLI',
		];
	} elseif ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		if ( ! function_exists( 'is_user_logged_in' ) ) {
			require_once ABSPATH . 'wp-includes/pluggable.php';
		}

		$route     = trim( $GLOBALS['wp']->query_vars['rest_route'], '/' );
		$parts     = explode( '/', $route );
		$namespace = reset( $parts );
		$actor     = [
			'type'  => 'rest-api',
			'extra' => [
				'method'    => isset( $_SERVER['REQUEST_METHOD'] )? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD']  ) ) : 'N/A', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'namespace' => $namespace,
				'route'     => $route,
			],
		];

		if ( is_user_logged_in() ) {
			$user           = wp_get_current_user();
			$actor['id']    = $user->ID;
			$actor['name']  = uptimemonster_get_user_display_name( $user );
			$actor['email'] = $user->user_email;
			$actor['role']  = uptimemonster_get_user_role( $user );
		} else {
			$actor['name'] = $namespace;
		}
	} else {
		if ( ! function_exists( 'is_user_logged_in' ) ) {
			require_once ABSPATH . 'wp-includes/pluggable.php';
		}
		if ( is_user_logged_in() ) {
			$user  = wp_get_current_user();
			$actor = [
				'type'  => 'user',
				'id'    => $user->ID,
				'name'  => uptimemonster_get_user_display_name( $user ),
				'email' => $user->user_email,
				'role'  => uptimemonster_get_user_role( $user ),
			];
		} else {
			$actor = [
				'type' => 'visitor',
				'name' => esc_html__( 'Unknown Visitor', 'uptimemonster-site-monitor' ),
			];
		}
	}

	$actor['ip'] = uptimemonster_get_ip_address();

	return $actor;
}

/**
 * Get User by identity.
 *
 * @param WP_User|int|string $identity User's identity (username, email or id)
 * @param string|int $field Optional. Field
 *
 * @return false|WP_User
 */
function uptimemonster_get_user( $identity, $field = null ) {
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
 * Get user full name for display purpose.
 *
 * @param WP_User|false $user
 *
 * @return string
 */
function uptimemonster_get_user_display_name( $user ) {
	$name = trim( implode( ' ', [ $user->first_name, $user->last_name ] ) ); // @phpstan-ignore-line

	if ( ! empty( $name ) ) {
		return $name;
	}

	if ( ! empty( $user->display_name ) ) {
		return $user->display_name;
	}

	if ( ! empty( $user->user_nicename ) ) {
		return $user->user_nicename;
	}

	return $user->user_login;
}

/**
 * @param WP_User $user
 *
 * @return string
 */
function uptimemonster_get_user_role( $user ) {
	return strtolower( (string) key( $user->caps ) );
}

/**
 * Get current visitor ip.
 *
 * @return string
 */
function uptimemonster_get_ip_address() {
	$ip     = '';
	$lookup = [
		'HTTP_X_REAL_IP',
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

	foreach ( $lookup as $item ) {
		if ( isset( $_SERVER[ $item ] ) && ! empty( $_SERVER[ $item ] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER[ $item ] ) );

			if ( strpos( $ip, ',' ) ) {
				/** @noinspection PhpPregSplitWithoutRegExpInspection */
				$ip = (string) rest_is_ip_address( trim( current( preg_split( '/,/', $ip ) ) ) );
			}

			break;
		}
	}

	return (string) filter_var( $ip, FILTER_VALIDATE_IP );
}

/**
 * @param string $plugin_file
 *
 * @return array|false
 */
function uptimemonster_get_plugin_data( $plugin_file ) {
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

/**
 * Get all plugins data
 *
 * @return array|array[]
 */
function uptimemonster_get_all_plugins() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	uptimemonster_switch_to_english();

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

	uptimemonster_restore_locale();

	if ( isset( $data['fatal-error-handler.php'] ) || isset( $data['uptimemonster-site-monitor/uptimemonster-site-monitor.php'] ) ) {
		$data['fatal-error-handler.php']['isRoxMon'] = true;
	}

	return $data;
}

/**
 * Get All theme with data.
 *
 * @return array|array[]
 */
function uptimemonster_get_all_themes() {
	if ( ! function_exists( 'wp_get_themes' ) ) {
		require_once ABSPATH . 'wp-includes/theme.php';
	}

	$themes = wp_get_themes();

	if ( empty( $themes ) ) {
		return [];
	}

	return array_map(
		function ( $theme ) {
			return uptimemonster_get_theme_data_headers( $theme );
		},
		$themes
	);
}

/**
 * @param WP_Theme $theme
 *
 * @return array
 */
function uptimemonster_get_theme_data_headers( $theme ) {
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

function uptimemonster_get_site_health_tests() {
	return [
		'direct' => [
			'wordpress_version'         => [
				'label' => esc_html__( 'WordPress Version', 'uptimemonster-site-monitor' ),
				'test'  => 'wordpress_version',
			],
			'plugin_version'            => [
				'label' => esc_html__( 'Plugin Versions', 'uptimemonster-site-monitor' ),
				'test'  => 'plugin_version',
			],
			'theme_version'             => [
				'label' => esc_html__( 'Theme Versions', 'uptimemonster-site-monitor' ),
				'test'  => 'theme_version',
			],
			'php_version'               => [
				'label' => esc_html__( 'PHP Version', 'uptimemonster-site-monitor' ),
				'test'  => 'php_version',
			],
			'php_extensions'            => [
				'label' => esc_html__( 'PHP Extensions', 'uptimemonster-site-monitor' ),
				'test'  => 'php_extensions',
			],
			'php_default_timezone'      => [
				'label' => esc_html__( 'PHP Default Timezone', 'uptimemonster-site-monitor' ),
				'test'  => 'php_default_timezone',
			],
			'php_sessions'              => [
				'label' => esc_html__( 'PHP Sessions', 'uptimemonster-site-monitor' ),
				'test'  => 'php_sessions',
			],
			'sql_server'                => [
				'label' => esc_html__( 'Database Server version', 'uptimemonster-site-monitor' ),
				'test'  => 'sql_server',
			],
			'utf8mb4_support'           => [
				'label' => esc_html__( 'MySQL utf8mb4 support', 'uptimemonster-site-monitor' ),
				'test'  => 'utf8mb4_support',
			],
			'ssl_support'               => [
				'label' => esc_html__( 'Secure communication', 'uptimemonster-site-monitor' ),
				'test'  => 'ssl_support',
			],
			'scheduled_events'          => [
				'label' => esc_html__( 'Scheduled events', 'uptimemonster-site-monitor' ),
				'test'  => 'scheduled_events',
			],
			'http_requests'             => [
				'label' => esc_html__( 'HTTP Requests', 'uptimemonster-site-monitor' ),
				'test'  => 'http_requests',
			],
			'rest_availability'         => [
				'label'     => esc_html__( 'REST API availability', 'uptimemonster-site-monitor' ),
				'test'      => 'rest_availability',
				'skip_cron' => true,
			],
			'debug_enabled'             => [
				'label' => esc_html__( 'Debugging enabled', 'uptimemonster-site-monitor' ),
				'test'  => 'is_in_debug_mode',
			],
			'file_uploads'              => [
				'label' => esc_html__( 'File uploads', 'uptimemonster-site-monitor' ),
				'test'  => 'file_uploads',
			],
			'plugin_theme_auto_updates' => [
				'label' => esc_html__( 'Plugin and theme auto-updates', 'uptimemonster-site-monitor' ),
				'test'  => 'plugin_theme_auto_updates',
			],
		],
		'async'  => [
			'dotorg_communication' => [
				'label'             => esc_html__( 'Communication with WordPress.org', 'uptimemonster-site-monitor' ),
				'test'              => rest_url( 'wp-site-health/v1/tests/dotorg-communication' ),
				'has_rest'          => true,
				'async_direct_test' => [ WP_Site_Health::get_instance(), 'get_test_dotorg_communication' ],
			],
			'background_updates'   => [
				'label'             => esc_html__( 'Background updates', 'uptimemonster-site-monitor' ),
				'test'              => rest_url( 'wp-site-health/v1/tests/background-updates' ),
				'has_rest'          => true,
				'async_direct_test' => [ WP_Site_Health::get_instance(), 'get_test_background_updates' ],
			],
			'loopback_requests'    => [
				'label'             => esc_html__( 'Loopback request', 'uptimemonster-site-monitor' ),
				'test'              => rest_url( 'wp-site-health/v1/tests/loopback-requests' ),
				'has_rest'          => true,
				'async_direct_test' => [ WP_Site_Health::get_instance(), 'get_test_loopback_requests' ],
			],
			'https_status'         => [
				'label'             => esc_html__( 'HTTPS status', 'uptimemonster-site-monitor' ),
				'test'              => rest_url( 'wp-site-health/v1/tests/https-status' ),
				'has_rest'          => true,
				'async_direct_test' => [ WP_Site_Health::get_instance(), 'get_test_https_status' ],
			],
		],
	];
}

function uptimemonster_wp_version_compare( $since, $operator ) {
	$wp_version = str_replace( '-src', '', $GLOBALS['wp_version'] );
	$since      = str_replace( '-src', '', $since );
	return version_compare( $wp_version, $since, $operator );
}

function uptimemonster_parse_boolval( $maybe_bool ) {
	if ( is_bool( $maybe_bool ) ) {
		return $maybe_bool;
	}

	if ( is_numeric( $maybe_bool ) ) {
		return (bool) $maybe_bool;
	}

	$maybe_bool = strtolower( $maybe_bool );

	return 'true' === $maybe_bool || 'yes' === $maybe_bool || 'on' === $maybe_bool;
}

function uptimemonster_prepare_plugin_data( $plugin ) {
	$data = [
		'author'      => ! empty( $plugin['Author'] ) ? $plugin['Author'] : ( ! empty( $plugin['AuthorName'] ) ? $plugin['AuthorName'] :  'unavailable' ),
		'version'     => ! empty( $plugin['Version'] ) ? $plugin['Version'] : 'unavailable',
		'plugin_uri'  => ! empty( $plugin['PluginURI'] ) ? $plugin['PluginURI'] : '',
		'author_uri'  => ! empty( $plugin['AuthorURI'] ) ? $plugin['AuthorURI'] : '',
		'network'     => ! empty( $plugin['Network'] ) ? $plugin['Network'] : '',
		'description' => ! empty( $plugin['Description'] ) ? $plugin['Description'] : '',
		'new_version' => null,
		'need_update' => false,
		'auto_update' => 'disabled',
	];

	// phpcs:disable
	unset(
		$plugin['Author'], $plugin['AuthorName'],
		$plugin['Version'], $plugin['PluginURI'],
		$plugin['AuthorURI'], $plugin['Network'],
		$plugin['Description'], $plugin['Name'],
		$plugin['Title'], $plugin['Woo'],
	);
	// phpcs:enable

	return array_merge( $data, $plugin );
}

/**
 * Compare two version strings to get the named semantic version.
 *
 * @access public
 *
 * @param string $new_version
 * @param string $original_version
 * @return string 'major', 'minor', 'patch'
 */
function uptimemonster_get_named_sem_ver( $new_version, $original_version ) {
	if ( ! Comparator::greaterThan( $new_version, $original_version ) ) {
		return '';
	}

	$parts = explode( '-', $original_version );
	$bits  = explode( '.', $parts[0] );
	$major = $bits[0];
	if ( isset( $bits[1] ) ) {
		$minor = $bits[1];
	}
	if ( isset( $bits[2] ) ) {
		$patch = $bits[2];
	}

	if ( isset( $minor ) && Semver::satisfies( $new_version, "{$major}.{$minor}.x" ) ) {
		return 'patch';
	}

	if ( Semver::satisfies( $new_version, "{$major}.x.x" ) ) {
		return 'minor';
	}

	return 'major';
}

function uptimemonster_need_filesystem_credentials( $redirect ) {
	if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	}

	if ( ! function_exists( 'request_filesystem_credentials' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	ob_start();
	$credentials = request_filesystem_credentials( $redirect );
	ob_end_clean();

	// Check if credentials aren't provided or wrong credentials.
	if ( false === $credentials || ! \WP_Filesystem( $credentials ) ) {
		return new WP_Error( 'fs-readonly', esc_html__( 'Unable to connect to the filesystem. Filesystem seems readonly or credentials are not provided in wp-config.php.', 'uptimemonster-site-monitor' ) );
	}

	return false;
}

// End of file helpers.php.
