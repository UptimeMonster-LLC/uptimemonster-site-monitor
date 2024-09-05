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
 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
 * @return WP_Filesystem_Base
 */
function uptimemonster_get_file_systeam() {
	/** @var WP_Filesystem_Base $wp_filesystem */
	global $wp_filesystem;
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	if ( ! $wp_filesystem instanceof WP_Filesystem_Direct ) {
		WP_Filesystem();
	}

	return $wp_filesystem;
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
 * Get GMT Time in mysql format.
 *
 * @return string
 */
function uptimemonster_get_current_time(): string {
	return (string) current_time( 'mysql', 1 );
}

/**
 * Get current actor data.
 *
 * @return array<string, array<string, string>
 */
function uptimemonster_get_current_actor(): array {
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
		$extra     = [
			'method'    => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : null,
			'namespace' => $namespace,
			'route'     => $route,
		];
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$extra['ua'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ); // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
		}
		if ( ! empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			$extra['ul'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) );
		}

		$actor = [
			'type'  => 'rest-api',
			'extra' => $extra,
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
 * @param WP_User $user
 *
 * @return string
 */
function uptimemonster_get_user_display_name( WP_User $user ): string {
	if ( $user->first_name || $user->last_name ) {
		/* translators: 1. Firstname, 2. Lastname */
		$name = sprintf( '%s %s', $user->display_name, $user->user_login );
		$name = trim( $name );
		if ( ! empty( $name ) ) {
			return $name;
		}
	}

	if ( ! empty( $user->display_name ) ) {
		return $user->display_name;
	}

	return $user->user_login;
}

/**
 * @param WP_User $user
 *
 * @return string
 */
function uptimemonster_get_user_role( WP_User $user ): string {
	return strtolower( (string) key( $user->caps ) );
}

/**
 * Get current visitor ip.
 *
 * @return string
 */
function uptimemonster_get_ip_address(): string {
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
		if ( ! empty( $_SERVER[ $item ] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER[ $item ] ) );

			if ( strpos( $ip, ',' ) ) {
				/** @noinspection PhpPregSplitWithoutRegExpInspection */
				$ip = preg_split( '/,/', $ip );
				if ( empty( $ip ) || ! is_array( $ip ) ) {
					break;
				}
				$ip = (string) rest_is_ip_address( trim( current( $ip ) ) );
			}

			break;
		}
	}

	return (string) filter_var( $ip, FILTER_VALIDATE_IP );
}

/**
 * @param string $plugin_file
 *
 * @return array<string, mixed>|false
 */
function uptimemonster_get_plugin_data( string $plugin_file ) {
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
 * @return array<array<string,bool|null|string|int>>
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
		$data['fatal-error-handler.php']['isUptimeMonster'] = true;
	}

	return $data;
}

/**
 * Get All theme with data.
 *
 * @return array<array<string,bool|null|string>>
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
 * @return array<string,bool|string|null>
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

/**
 * @param string $since
 * @param string $operator
 *
 * @return bool
 */
function uptimemonster_wp_version_compare( $since, $operator ) {
	$wp_version = str_replace( '-src', '', $GLOBALS['wp_version'] ); // @phpstan-ignore-line
	$since      = str_replace( '-src', '', $since );
	return version_compare( $wp_version, $since, $operator );
}

/**
 * @param string|bool|numeric|null $maybe_bool
 *
 * @return bool
 */
function uptimemonster_parse_boolval( $maybe_bool ): bool {
	if ( null === $maybe_bool ) {
		return false;
	}
	if ( is_bool( $maybe_bool ) ) {
		return $maybe_bool;
	}

	if ( is_numeric( $maybe_bool ) ) {
		return (bool) $maybe_bool;
	}

	$maybe_bool = strtolower( $maybe_bool );

	return 'true' === $maybe_bool || 'yes' === $maybe_bool || 'on' === $maybe_bool;
}

/**
 * @param array<string,string>  $raw_data
 *
 * @return array<string,bool|string|null>
 */
function uptimemonster_prepare_plugin_data( array $raw_data ): array {
	$data = [
		'author'      => ! empty( $raw_data['Author'] ) ? $raw_data['Author'] : ( ! empty( $raw_data['AuthorName'] ) ? $raw_data['AuthorName'] : 'unavailable' ),
		'version'     => ! empty( $raw_data['Version'] ) ? $raw_data['Version'] : 'unavailable',
		'plugin_uri'  => ! empty( $raw_data['PluginURI'] ) ? $raw_data['PluginURI'] : '',
		'author_uri'  => ! empty( $raw_data['AuthorURI'] ) ? $raw_data['AuthorURI'] : '',
		'network'     => ! empty( $raw_data['Network'] ) ? $raw_data['Network'] : '',
		'description' => ! empty( $raw_data['Description'] ) ? $raw_data['Description'] : '',
		'new_version' => null,
		'need_update' => false,
		'auto_update' => 'disabled',
	];

	unset(
		$raw_data['Author'], $raw_data['AuthorName'], $raw_data['Version'],
		$raw_data['PluginURI'], $raw_data['AuthorURI'], $raw_data['Network'],
		$raw_data['Description'], $raw_data['Name'], $raw_data['Title'],
		$raw_data['Woo']
	);

	return array_merge( $data, $raw_data );
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

// End of file helpers.php.
