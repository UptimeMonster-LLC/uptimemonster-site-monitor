<?php
/**
 * Data Monitor Base
 *
 * @package RoxwpSiteMonitor\Monitors
 * @version 1.0.0
 * @since RoxwpSiteMonitor 1.0.0
 */

namespace AbsolutePlugins\RoxwpSiteMonitor\Monitors;

use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Monitor_Session_Activity extends Activity_Monitor_Base {

	use Activity_Monitor_Trait;

	protected $check_maybe_log = false;

	public function init() {

		add_action( 'wp_login', [ $this, 'on_login' ] );
		add_action( 'wp_login_failed', [ $this, 'on_login_failed' ] );
		add_action( 'clear_auth_cookie', [ $this, 'on_logout' ] );
	}

	protected function maybe_log_activity( $action, $objectId ) {

		$user = roxwp_get_user( $objectId );

		/**
		 * Should report activity?
		 *
		 * @param bool $status
		 * @param string $option
		 * @param string $action
		 * @param string $value
		 * @param string $new_value
		 */
		return (bool) apply_filters( 'roxwp_should_log_session_activity', false !== $user, $user, $action );
	}

	protected function log_user( $action, $user, $extra = [] ) {
		$user = roxwp_get_user( $user );

		if ( ! $this->maybe_log_activity( $action, $user ) ) {
			return;
		}

		$this->log_activity(
			$action,
			$user->ID,
			'session',
			roxwp_get_user_display_name( $user ),
			array_merge( [
				'username' => $user->user_login,
				'role'     => roxwp_get_user_role( $user ),
				'email'    => $user->user_email,
			], $extra )
		);
	}

	public function on_login( $user_login ) {
		$this->log_user(
			Activity_Monitor_Base::ITEM_LOGGED_IN,
			$user_login
		);
	}

	public function on_login_failed( $username ) {

		$this->log_user(
			Activity_Monitor_Base::ITEM_LOGIN_FAILED,
			$username
		);
	}

	public function on_logout() {
		$user = wp_get_current_user();

		if ( empty( $user ) || ! $user->exists() ) {
			return;
		}

		$this->log_user(
			Activity_Monitor_Base::ITEM_LOGGED_OUT,
			$user
		);
	}
}

// End of file Monitor_Session_Activity.php.
