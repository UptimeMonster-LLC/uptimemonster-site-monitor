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
use Exception;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

class Monitor_Users_Activity extends Activity_Monitor_Base {

	use Singleton;

	protected $check_maybe_log = false;

	public function init() {
		add_action( 'user_register', [ $this, 'on_registered' ] );
		add_action( 'profile_update', [ $this, 'on_updated' ], 10, 2 );
		add_action( 'deleted_user', [ $this, 'on_deleted' ], 10, 3 );

		add_action( 'make_spam_user', [ $this, 'on_spammed' ] );
		add_action( 'make_ham_user', [ $this, 'on_hammed' ] );
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
		return (bool) apply_filters( 'uptimemonster_should_log_users_activity', false !== $user, $user, $action );
	}

	/**
	 * @param string $action
	 * @param WP_User $user
	 * @param array $extra
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function log_user( $action, $user, $extra = [] ) {
		$user = uptimemonster_get_user( $user );

		if ( ! $this->maybe_log_activity( $action, $user ) ) {
			return;
		}

		if ( ! empty( $user ) ) {
			$this->log_activity(
				$action,
				$user->ID,
				'user',
				uptimemonster_get_user_display_name( $user ),
				array_merge( [
					'username' => $user->user_login,
					'role'     => uptimemonster_get_user_role( $user ),
					'email'    => $user->user_email,
				], $extra )
			);
		}
	}

	/**
	 * @throws Exception
	 */
	public function on_registered( $user ) {
		try {
			$this->log_user( Activity_Monitor_Base::ITEM_REGISTERED, $user );
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				throw $e;
			}
		}
	}

	/**
	 * @param WP_User $user
	 * @param WP_User $old_user_data
	 *
	 * @throws Exception
	 */
	public function on_updated( $user, $old_user_data ) {
		$this->log_user( Activity_Monitor_Base::ITEM_UPDATED, $user, [ 'old' => $old_user_data->to_array() ] );
	}

	/**
	 * @param int $id ID of the deleted user.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 *                           Default null, for no reassignment.
	 * @param WP_User $user WP_User object of the deleted user.
	 *
	 * @throws Exception
	 */
	public function on_deleted( $id, $reassign, $user ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed
		if ( $reassign ) {
			$reassign = uptimemonster_get_user( $reassign );
			$reassign = [
				'id'       => $reassign->ID,
				'name'     => uptimemonster_get_user_display_name( $reassign ),
				'email'    => $reassign->user_email,
				'username' => $reassign->user_login,
			];
		}
		$this->log_user(
			Activity_Monitor_Base::ITEM_DELETED,
			$user,
			[
				'old_user' => $user->to_array(),
				'reassign' => $reassign,
			]
		);
	}

	public function on_spammed( $user ) {
		$this->log_user(
			Activity_Monitor_Base::ITEM_SPAMMED,
			$user
		);
	}

	public function on_hammed( $user ) {
		$this->log_user(
			Activity_Monitor_Base::ITEM_UNSPAMMED,
			$user
		);
	}
}

// End of file Monitor_Users_Activity.php.
