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
	const ITEM_INSTALLING   = 'installing';
	const ITEM_INSTALLED    = 'installed';
	const ITEM_UNINSTALLED  = 'uninstalled';
	const ITEM_REINSTALLING = 'reinstalling';
	const ITEM_ACTIVATED    = 'activated';
	const ITEM_DEACTIVATED  = 'deactivated';
	const ITEM_UPGRADING    = 'upgrading';
	const ITEM_CREATED      = 'created';
	const ITEM_UPDATED      = 'updated';
	const ITEM_TRASHED      = 'trashed';
	const ITEM_RESTORED     = 'restored';
	const ITEM_DELETED      = 'deleted';
	const ITEM_APPROVED     = 'approved';
	const ITEM_UNAPPROVED   = 'unapproved';
	const ITEM_PENDING      = 'pending';
	const ITEM_SPAMMED      = 'spammed';
	const ITEM_UNSPAMMED    = 'unspammed';
	const ITEM_EXPORTED     = 'exported';
	const ITEM_REGISTERED   = 'registered';
	const ITEM_LOGGED_IN    = 'logged_in';
	const ITEM_LOGGED_OUT   = 'logged_out';
	const ITEM_LOGIN_FAILED = 'login_failed';
	const ITEM_DEPRECATED   = 'deprecated';
	const ITEM_ERROR_LOG    = 'error_log';

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
	 * @param int|string $object_id Object Id
	 * @param string $subtype Object Type
	 * @param array|string|false $name Object Name.
	 * @param array|object $data [Optional] Extra data.
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function log_activity( $action, $object_id, $subtype, $name, $data = null ) {
		if ( ! $this->activity ) {
			$this->activity = str_replace( [ __NAMESPACE__, 'Monitor_', '_Activity' ], '', get_called_class() );
			$this->activity = ltrim( $this->activity, '\\' );
		}

		if ( 'string' !== gettype( $subtype ) ) {
			throw new InvalidArgumentException(
				sprintf(
				/* translators: 1. PHP Argument Type. */
					esc_html__( '$subtype expected to be a string, got %s.', 'roxwp-site-mon' ),
					gettype( $subtype )
				)
			);
		}

		if ( 'string' !== gettype( $name ) ) {
			throw new InvalidArgumentException(
				sprintf(
				/* translators: 1. PHP Argument Type. */
					esc_html__( '$name expected to be a string, got %s.', 'roxwp-site-mon' ),
					gettype( $name )
				)
			);
		}

		$_object_id = absint( $object_id );

		if ( strlen( (string) $_object_id ) !== strlen( (string) $object_id ) ) {
			throw new InvalidArgumentException(
				sprintf(
				/* translators: 1. PHP Argument Type. */
					esc_html__( '$id expected to be an integer, got %s.', 'roxwp-site-mon' ),
					gettype( $object_id )
				)
			);
		}

		$_object_id = $_object_id ? $_object_id : null;

		if ( $this->check_maybe_log && ! $this->maybe_log_activity( $action, $object_id ) ) {
			return;
		}

		$log = [
			'action'    => $action,
			'activity'  => $this->activity,
			'subtype'   => $subtype,
			'object_id' => $_object_id,
			'name'      => $this->strip_activity_name( $name ),
			'timestamp' => roxwp_get_current_time(),
			'actor'     => roxwp_get_current_actor(),
			'extra'     => [
				'wp_version' => get_bloginfo( 'version' ),
			],
		];

		if ( $data ) {
			if ( is_object( $data ) ) {
				$data = get_object_vars( $data );
			}

			if ( ! is_array( $data ) ) {
				throw new InvalidArgumentException(
					sprintf(
					/* translators: 1. PHP Argument Type. */
						esc_html__( '$data expected to be an array or object, got %s.', 'roxwp-site-mon' ),
						gettype( $data )
					)
				);
			}

			if ( isset( $data['include_installed'] ) ) {
				unset( $data['include_installed'] );
				$data['installed'] = [
					'plugins' => roxwp_get_all_plugins(),
					'themes'  => roxwp_get_all_themes(),
				];
			}

			$log['extra'] = array_merge( $log['extra'], $data );
		}

		RoxWP_Client::get_instance()->send_log( $log );

		do_action( 'roxwp_log_sent', $log );
	}

	/**
	 * This should be overridden by the subClass.
	 *
	 * @param string $action
	 * @param mixed $object_id
	 *
	 * @return bool
	 *
	 * @throws Exception
	 */
	protected function maybe_log_activity( $action, $object_id ) {
		throw new Exception(
			sprintf(
			/* translators: 1. Method Name. */
				esc_html__( '%s must be overridden by the subClass', 'roxwp-site-mon' ),
				__METHOD__
			)
		);
	}
}

// End of file Activity_Monitor_Base.php.
