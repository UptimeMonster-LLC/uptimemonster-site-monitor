<?php
/**
 * Data Monitor Base
 *
 * @package RoxwpSiteMonitor\Monitors
 * @version 1.0.0
 * @since RoxwpSiteMonitor 1.0.0
 */

namespace AbsolutePlugins\RoxwpSiteMonitor\Monitors;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
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

	protected function maybe_log_activity( $action, $objectId ) {

		$menu = wp_get_nav_menu_object( $objectId );

		/**
		 * Should report activity?
		 *
		 * @param bool $status
		 * @param null $menu
		 * @param string $action
		 */
		return (bool) apply_filters( 'roxwp_should_log_wp_export_activity', false !== $menu, $menu, $action );
	}

	protected function detect_action() {
		return 'wp_create_nav_menu' === current_filter() ? Activity_Monitor_Base::ITEM_CREATED : Activity_Monitor_Base::ITEM_UPDATED;
	}

	public function log_on_delete( $menuId ) {
		$menu = wp_get_nav_menu_object( $menuId );
		if ( $this->maybe_log_activity( Activity_Monitor_Base::ITEM_DELETED, $menuId ) ) {
			$this->log_activity(
				Activity_Monitor_Base::ITEM_DELETED,
				$menuId,
				'menu',
				$menu->name
			);
		}
	}

	public function log_on_change( $menuId ) {
		$menu = wp_get_nav_menu_object( $menuId );
		$action = $this->detect_action();

		if ( $this->maybe_log_activity( $action, $menuId ) ) {
			$this->log_activity(
				$this->detect_action(),
				$menuId,
				'menu',
				$menu->name
			);
		}
	}
}

// End of file Monitor_Nav_Menu_Activity.php.
