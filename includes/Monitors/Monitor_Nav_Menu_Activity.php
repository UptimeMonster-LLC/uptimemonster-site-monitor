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

class Monitor_Nav_Menu_Activity extends Activity_Monitor_Base {

	use Singleton;

	protected $check_maybe_log = false;

	public function init() {
		add_action( 'wp_update_nav_menu', [ $this, 'log_on_change' ] );
		add_action( 'wp_create_nav_menu', [ $this, 'log_on_change' ] );
		//"delete_{$taxonomy}"
		add_action( 'wp_delete_nav_menu', [ $this, 'log_on_delete' ] );
	}

	protected function maybe_log_activity( string $action = null, $object_id = null ): bool {
		$menu = wp_get_nav_menu_object( $object_id );

		/**
		 * Should report activity?
		 *
		 * @param bool $status
		 * @param null $menu
		 * @param string $action
		 */
		return (bool) apply_filters( 'uptimemonster_should_log_wp_export_activity', false !== $menu, $menu, $action );
	}

	protected function detect_action() {
		return 'wp_create_nav_menu' === current_filter() ? Activity_Monitor_Base::ITEM_CREATED : Activity_Monitor_Base::ITEM_UPDATED;
	}

	public function log_on_delete( $menu_id ) {
		$menu = wp_get_nav_menu_object( $menu_id );
		if ( $menu && $this->maybe_log_activity( Activity_Monitor_Base::ITEM_DELETED, $menu_id ) ) {
			$this->log_activity(
				Activity_Monitor_Base::ITEM_DELETED,
				$menu_id,
				'menu',
				$menu->name
			);
		}
	}

	public function log_on_change( $menu_id ) {
		$menu   = wp_get_nav_menu_object( $menu_id );
		$action = $this->detect_action();

		if ( $menu && $this->maybe_log_activity( $action, $menu_id ) ) {
			$this->log_activity(
				$this->detect_action(),
				$menu_id,
				'menu',
				$menu->name
			);
		}
	}
}

// End of file Monitor_Nav_Menu_Activity.php.
