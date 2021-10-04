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

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

/**
 * Switch Plugin to site language.
 */
function roxwp_switch_to_site_locale() {
	if ( function_exists( 'switch_to_locale' ) ) {
		switch_to_locale( get_locale() );

		// Filter on plugin_locale so other plugin/theme can load the correct locale.
		add_filter( 'plugin_locale', 'get_locale' );

		// We don't need to reload plugin local.
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
 * Get current actor data.
 *
 * @return array|string[]
 */
function roxwp_get_current_actor() {
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		$actor = [
			'type' => 'cron',
			'ip'   => roxwp_get_ip_address(), // maybe cron triggered by visitor.
		];
	} elseif ( class_exists( '\WP_CLI', false ) ) {
		$actor = [
			'type' => 'wp-cli',
			'ip'   => roxwp_get_ip_address(), // maybe cron triggered by visitor.
		];
	} else {
		if ( ! function_exists( 'is_user_logged_in' ) ) {
			//
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
				'ip'   => roxwp_get_ip_address(),
			];
		}
	}

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


	return get_user_by( $field, $identity );
}

function roxwp_get_user_display_name( $user ) {
	$name = trim( implode( ' ', [ $user->first_name, $user->last_name ] ) );
	if ( empty( $name ) ) {
		$name = $user->display_name;
	}

	if ( $user->user_login !== $name ) {
		roxwp_switch_to_site_locale();
		$name = sprintf(
			_x( '%1$s (%2$s)', 'User display name with username', 'rwp-site-mon' ),
			$name,
			$user->user_login
		);
		roxwp_restore_locale();
	}

	return $name;
}

function roxwp_get_user_role( $user ) {
	return strtolower( key( $user->caps ) );
}

/**
 * Get current visitor ip.
 *
 * @return string
 */
function roxwp_get_ip_address() {
	$server_ip_keys = array(
		'HTTP_CF_CONNECTING_IP', // CloudFlare
		'HTTP_TRUE_CLIENT_IP', // CloudFlare Enterprise header
		'HTTP_CLIENT_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'REMOTE_ADDR',
	);

	foreach ( $server_ip_keys as $key ) {
		if ( isset( $_SERVER[ $key ] ) && filter_var( $_SERVER[ $key ], FILTER_VALIDATE_IP ) ) {
			return sanitize_text_field( $_SERVER[ $key ] );
		}
	}

	// Fallback local ip.
	return '127.0.0.1';
}

// End of file helpers.php.
