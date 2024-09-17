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
use WP_Widget;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

class Monitor_Widgets_Activity extends Activity_Monitor_Base {

	use Singleton;

	protected $check_maybe_log = false;

	public function init() {
		/**
		 * When sidebar get deleted from new widget editor it fires "rest_save_sidebar"
		 * ultimately wp_set_sidebars_widgets get called before the action which just updates the ids of remaining
		 * widgets on that sidebar and then calls update_option to save the state, the option key is already set for
		 * monitoring by the Option Activity monitor.
		 *
		 * @see WP_REST_Sidebars_Controller::update_item()
		 * @see wp_set_sidebars_widgets
		 */

		add_filter( 'widget_update_callback', [ $this, 'on_update' ], 99999, 4 );
		add_action( 'sidebar_admin_setup', [ $this, 'on_delete' ] ); // Widget delete.
	}

	protected function maybe_log_widget( $action, $sidebar, $widget ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed

		/**
		 * Should report activity?
		 *
		 * @param bool $status
		 * @param string $option
		 * @param string $action
		 */
		return (bool) apply_filters( 'uptimemonster_should_log_widgets_activity', true, $sidebar, $widget );
	}

	/**
	 * @param array $instance
	 * @param array $new_instance
	 * @param array $old_instance
	 * @param WP_Widget $widget
	 *
	 * @return array
	 * @throws Exception
	 */
	public function on_update( $instance, $new_instance, $old_instance, WP_Widget $widget ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed
		if ( ! empty( $_REQUEST['sidebar'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$sidebar = sanitize_text_field( wp_unslash( $_REQUEST['sidebar'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( $this->maybe_log_widget( Activity_Monitor_Base::ITEM_UPDATED, $sidebar, $widget ) ) {
				try {
					$this->log_activity(
						Activity_Monitor_Base::ITEM_UPDATED,
						0,
						'widget',
						$widget->name,
						[
							'widget_base'  => $widget->id_base,
							'sidebar_name' => $this->get_sidebar_name( $sidebar ),
							'old_instance' => $old_instance,
						]
					);
				} catch ( Exception $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						throw $e;
					}
				}
			}
		}

		return $instance;
	}

	public function on_delete() {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.NonceVerification.Recommended
		if ( 'post' == strtolower( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) && ! empty( $_REQUEST['widget-id'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( isset( $_REQUEST['sidebar'], $_REQUEST['delete_widget'] ) && 1 === (int) $_REQUEST['delete_widget'] ) {
				$sidebar = sanitize_text_field( wp_unslash( $_REQUEST['sidebar'] ) );
				$widget  = sanitize_text_field( wp_unslash( $_REQUEST['id_base'] ) );
				if ( $this->maybe_log_widget( Activity_Monitor_Base::ITEM_DELETED, $sidebar, '' ) ) {
					$this->log_activity(
						Activity_Monitor_Base::ITEM_DELETED,
						0,
						'widget',
						$widget,
						[
							'widget_base'  => $widget,
							'sidebar_name' => $this->get_sidebar_name( $sidebar ),
						]
					);
				}
			}
		}
		// phpcs:enable
	}

	protected function get_sidebar_name( $sidebar ) {
		global $wp_registered_sidebars;

		return isset( $wp_registered_sidebars[ $sidebar ] ) ? $wp_registered_sidebars[ $sidebar ] : $sidebar;
	}
}

// End of file Monitor_Widgets_Activity.php.
