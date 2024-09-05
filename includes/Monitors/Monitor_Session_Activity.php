<?php
/**
 * Data Monitor Base
 *
 * @package UptimeMonster\SiteMonitor\Monitors
 * @version 1.0.0
 * @since 1.0.0
 */

namespace UptimeMonster\SiteMonitor\Monitors;

use UptimeMonster\SiteMonitor\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

class Monitor_Session_Activity extends Activity_Monitor_Base {

	use Singleton;

	protected $check_maybe_log = false;

	public function init() {
		add_action( 'wp_login', [ $this, 'on_login' ] );
		add_action( 'wp_login_failed', [ $this, 'on_login_failed' ] );
		add_action( 'clear_auth_cookie', [ $this, 'on_logout' ] );
	}

	protected function maybe_log_activity( string $action = null, $object_id = null ): bool {
		$user = uptimemonster_get_user( $object_id );

		/**
		 * Should report activity?
		 *
		 * @param bool $status
		 * @param string $option
		 * @param string $action
		 * @param string $value
		 * @param string $new_value
		 */
		return (bool) apply_filters( 'uptimemonster_should_log_session_activity', false !== $user, $user, $action );
	}

	protected function log_user( $action, $user, $extra = [] ) {
		$user = uptimemonster_get_user( $user );

		if ( ! $user || ! $this->maybe_log_activity( $action, $user ) ) {
			return;
		}

		$this->log_activity(
			$action,
			$user->ID,
			'session',
			uptimemonster_get_user_display_name( $user ),
			array_merge( [
				'username' => $user->user_login,
				'role'     => uptimemonster_get_user_role( $user ),
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

		if ( $user && $user->exists() ) {
			$this->log_user(
				Activity_Monitor_Base::ITEM_LOGGED_OUT,
				$user
			);
		}
	}
}

// End of file Monitor_Session_Activity.php.
