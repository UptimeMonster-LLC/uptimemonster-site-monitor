<?php
/**
 * Data Monitor Base
 *
 * @package RoxwpSiteMonitor\Monitors
 * @version 1.0.0
 * @since RoxwpSiteMonitor 1.0.0
 */

namespace AbsolutePlugins\RoxwpSiteMonitor\Monitors;

use Exception;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Monitor_Users_Activity extends Activity_Monitor_Base {

	use Singleton;

	protected $check_maybe_log = false;

	public function init() {
		add_action( 'user_register', [ $this, 'on_registered' ] );
		add_action( 'profile_update', [ $this, 'on_updated' ], 10, 2 );
		add_action( 'deleted_user', [ $this, 'on_deleted' ], 10, 3 );

		add_action( 'make_spam_user', [ $this, 'on_spammed' ] );
		add_action( 'make_ham_user', [ $this, 'on_unspammed' ] );
	}

	protected function maybe_log_activity( $action, $object_id ) {
		$user = roxwp_get_user( $object_id );

		/**
		 * Should report activity?
		 *
		 * @param bool $status
		 * @param string $option
		 * @param string $action
		 * @param string $value
		 * @param string $new_value
		 */
		return (bool) apply_filters( 'roxwp_should_log_users_activity', false !== $user, $user, $action );
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
		$user = roxwp_get_user( $user );

		if ( ! $this->maybe_log_activity( $action, $user ) ) {
			return;
		}

		if ( ! empty( $user ) ) {
			$this->log_activity(
				$action,
				$user->ID,
				'user',
				roxwp_get_user_display_name( $user ),
				array_merge( [
					'username' => $user->user_login,
					'role'     => roxwp_get_user_role( $user ),
					'email'    => $user->user_email,
				], $extra )
			);
		}
	}

	public function on_registered( $user ) {
		try {
			$this->log_user( Activity_Monitor_Base::ITEM_REGISTERED, $user );
		} catch ( Exception $e ) {
		}
	}

	/**
	 * @param WP_User $user
	 * @param WP_User $old_user_data
	 *
	 * @throws Exception
	 */
	public function on_updated( $user, $old_user_data ) {
		$this->log_user( Activity_Monitor_Base::ITEM_REGISTERED, $user, [ 'old' => $old_user_data->to_array() ] );
	}

	public function on_deleted( $id, $reassign, $user ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed
		$reassign = roxwp_get_user( $reassign );
		if ( $reassign ) {
			$this->log_user(
				Activity_Monitor_Base::ITEM_DELETED,
				$user,
				[
					'old'           => $user->to_array(),
					'reassigned_to' => [
						'id'       => $reassign->ID,
						'name'     => roxwp_get_user_display_name( $reassign ),
						'email'    => $reassign->user_email,
						'username' => $reassign->user_login,
					],
				]
			);
		}
	}

	public function on_spammed( $user ) {
		$this->log_user(
			Activity_Monitor_Base::ITEM_SPAMMED,
			$user
		);
	}

	public function on_unspammed( $user ) {
		$this->log_user(
			Activity_Monitor_Base::ITEM_UNSPAMMED,
			$user
		);
	}
}

// End of file Monitor_Users_Activity.php.
