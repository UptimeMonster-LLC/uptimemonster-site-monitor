<?php
/**
 * Data Monitor Base
 *
 * @package RoxwpSiteMonitor\Monitors
 * @version 1.0.0
 * @since RoxwpSiteMonitor 1.0.0
 */

namespace AbsolutePlugins\RoxwpSiteMonitor\Monitors;

use AbsolutePlugins\RoxwpSiteMonitor\RoxWP_Client;
use Exception;
use InvalidArgumentException;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

abstract class Activity_Monitor_Base implements Activity_Monitor_Interface {

	/**
	 * Log Actions.
	 */
	const ITEM_INSTALLING = 'installing';
	const ITEM_INSTALLED = 'installed';
	const ITEM_UNINSTALLED = 'uninstalled';
	const ITEM_REINSTALLING = 'reinstalling';
	const ITEM_ACTIVATED = 'activated';
	const ITEM_DEACTIVATED = 'deactivated';
	const ITEM_UPGRADING = 'upgrading';
	const ITEM_CREATED = 'created';
	const ITEM_UPDATED = 'updated';
	const ITEM_TRASHED = 'trashed';
	const ITEM_RESTORED = 'restored';
	const ITEM_DELETED = 'deleted';
	const ITEM_APPROVED = 'approved';
	const ITEM_UNAPPROVED = 'unapproved';
	const ITEM_PENDING = 'pending';
	const ITEM_SPAMMED = 'spammed';
	const ITEM_UNSPAMMED = 'unspammed';
	const ITEM_EXPORTED = 'exported';
	const ITEM_REGISTERED = 'registered';
	const ITEM_LOGGED_IN = 'logged_in';
	const ITEM_LOGGED_OUT = 'logged_out';
	const ITEM_LOGIN_FAILED = 'login_failed';

	protected $activity;

	/**
	 * Flag if base class should execute the maybe_log_activity method.
	 * This will help prevent execute same logics twice.
	 *
	 * @var bool
	 */
	protected $check_maybe_log = true;

	protected function strip_activity_name( $name ) {
		return wp_strip_all_tags( $name, true );
	}

	/**
	 * Log Activity.
	 *
	 * @param string $action Log Type
	 * @param int $objectId Object Id
	 * @param string $subtype Object Type
	 * @param string $name Object Name.
	 * @param array $data [Optional] Extra data.
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function log_activity( $action, $objectId, $subtype, $name, $data = null ) {

		if ( ! $this->activity ) {
			$this->activity = str_replace( [ __NAMESPACE__, 'Monitor_', '_Activity' ], '', get_called_class() );
		}

		if ( 'string' !== gettype( $subtype ) ) {
			throw new InvalidArgumentException(
				sprintf(
				/* translators: 1. PHP Argument Type. */
					esc_html__( '$subtype expected to be a string, got %s.', 'rwp-site-mon' ),
					gettype( $subtype )
				)
			);
		}

		if ( 'string' !== gettype( $name ) ) {
			throw new InvalidArgumentException(
				sprintf(
				/* translators: 1. PHP Argument Type. */
					esc_html__( '$name expected to be a string, got %s.', 'rwp-site-mon' ),
					gettype( $name )
				)
			);
		}

		$_objectId = absint( $objectId );

		if ( strlen( $_objectId ) !== strlen( $objectId ) ) {
			throw new InvalidArgumentException(
				sprintf(
				/* translators: 1. PHP Argument Type. */
					esc_html__( '$id expected to be an integer, got %s.', 'rwp-site-mon' ),
					gettype( $objectId )
				)
			);
		}

		$_objectId = $_objectId > 0 ? $_objectId : null;

		if ( $this->check_maybe_log && ! $this->maybe_log_activity( $action, $objectId ) ) {
			return;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			$actor = [
				'type' => 'cron',
				'ip'   => $this->get_ip_address(), // maybe cron triggered by visitor.
			];
		} else {
			if ( is_user_logged_in() ) {
				$actor = wp_get_current_user();
				$actor = [
					'type'  => 'user',
					'id'    => $actor->ID,
					'ip'    => $this->get_ip_address(),
					'name'  => $this->get_user_display_name( $actor ),
					'email' => $actor->user_email,
					'role'  => $this->get_user_role( $actor ),
				];
			} else {
				$actor = [
					'type' => 'visitor',
					'ip'   => $this->get_ip_address(),
				];
			}
		}

		$log = [
			'action'    => $action,
			'activity'  => $this->activity,
			'subtype'   => $subtype,
			'id'        => $_objectId > 0 ? $_objectId : null,
			'name'      => $this->strip_activity_name( $name ),
			'timestamp' => current_time( 'mysql' ),
			'actor'     => $actor,
		];

		if ( $data ) {
			if ( is_object( $data ) ) {
				$data = get_object_vars( $data );
			}

			if ( ! is_array( $data ) ) {
				throw new InvalidArgumentException(
					sprintf(
					/* translators: 1. PHP Argument Type. */
						esc_html__( '$data expected to be an array or object, got %s.', 'rwp-site-mon' ),
						gettype( $data )
					)
				);
			}

			$log['extra'] = $data;
		}

		RoxWP_Client::get_instance()->send_log( $log );

		do_action( 'roxwp_log_sent', $log );
	}

	/**
	 * this should be overridden by the subClass.
	 *
	 * @param string $action
	 * @param mixed $objectId
	 *
	 * @return bool
	 *
	 * @throws Exception
	 */
	protected function maybe_log_activity( $action, $objectId ) {
		throw new Exception(
			sprintf(
			/* translators: 1. Method Name. */
				esc_html__( '%s must be overridden by the subClass', 'rwp-site-mon' ),
				__METHOD__
			)
		);
	}

	/**
	 * @param WP_User|int| string $identity User's identity (username, email or id)
	 *
	 * @return false|WP_User
	 */
	protected function get_user( $identity ) {

		if ( $identity instanceof WP_User ) {
			return $identity;
		}

		$by = 'id';

		if ( is_string( $identity ) ) {
			if ( is_email( $identity ) ) {
				$by = 'email';
			} else {
				$by = 'login';
			}
		}

		return get_user_by( $by, $identity );
	}

	/**
	 * @param WP_User $user
	 *
	 * @return string
	 */
	protected function get_user_role( $user ) {
		return strtolower( key( $user->caps ) );
	}

	/**
	 * Get real address
	 *
	 * @since 2.1.4
	 *
	 * @return string real address IP
	 */
	protected function get_ip_address() {
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

	/**
	 * @param WP_User $user
	 */
	protected function get_user_display_name( $user ) {
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

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong(
			__FUNCTION__,
			__( 'Cloning is forbidden.', 'rwp-site-mon' ),
			'1.0.0'
		);
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong(
			__FUNCTION__,
			__( 'Unserializing instances of this class is forbidden.', 'rwp-site-mon' ),
			'1.0.0'
		);
	}
}

// End of file Activity_Monitor_Base.php.
