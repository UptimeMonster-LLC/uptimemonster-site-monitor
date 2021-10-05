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
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Monitor_WP_Core_Update_Activity extends Activity_Monitor_Base {

	use Activity_Monitor_Trait;

	public function init() {
		add_action( 'admin_head', [ $this, 'log_on_update_start' ] );
		add_action( 'wp_maybe_auto_update', [ $this, 'log_on_update_start' ] );
		add_action( '_core_updated_successfully', [ $this, 'log_on_successful_update' ] );
		// @TODO find way to log update failed.
	}

	protected function maybe_log_activity( $action, $objectId ) {

		/**
		 * Should report activity?
		 *
		 * @param bool $status
		 * @param null $object
		 * @param string $action
		 */
		return (bool) apply_filters( 'roxwp_should_log_wp_core_update_activity', true, null, $action );
	}

	public function log_on_update_start() {
		global $pagenow;

		if ( 'wp_maybe_auto_update' === current_filter() ) {

			roxwp_switch_to_english();
			/* translators: 1. WordPress Version. */
			$name = __( 'WordPress Auto Upgrading From %s', 'roxwp-site-mon' );
			roxwp_restore_locale();

			$version = get_bloginfo( 'version' );

			$this->log_activity(
				Activity_Monitor_Base::ITEM_UPGRADING,
				0,
				'WordPressCore',
				sprintf( $name, $version ),
				[ 'new_version' => $version ]
			);
		}

		if ( 'update-core.php' !== $pagenow ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'upgrade-core';

		if ( 'do-core-upgrade' === $action || 'do-core-reinstall' === $action ) {
			if ( isset( $_POST['upgrade'] ) ) {
				$action = 'do-core-upgrade' === $action ? Activity_Monitor_Base::ITEM_UPGRADING : Activity_Monitor_Base::ITEM_REINSTALLING;
				roxwp_switch_to_english();
				/* translators: 1. WordPress Version. */
				$name = 'do-core-upgrade' === $action ? __( 'WordPress Upgrading From %s', 'roxwp-site-mon' ) : __( 'WordPress Reinstalling %s', 'roxwp-site-mon' );
				roxwp_restore_locale();

				$version = get_bloginfo( 'version' );

				$this->log_activity(
					$action,
					0,
					'WordPressCore',
					sprintf( $name, $version ),
					[ 'new_version' => $version ]
				);
			}
		}
	}

	public function log_on_successful_update( $version ) {
		global $pagenow;

		roxwp_switch_to_english();
		/* translators: 1. WordPress Updated Version. */
		$name = 'update-core.php' !== $pagenow ? __( 'WordPress Auto Updated to %s', 'roxwp-site-mon' ) : __( 'WordPress Updated to %s', 'roxwp-site-mon' );
		roxwp_restore_locale();

		$this->log_activity(
			Activity_Monitor_Base::ITEM_UPDATED,
			0,
			'WordPressCore',
			sprintf( $name, $version ),
			[ 'new_version' => $version ]
		);
	}
}

// End of file Monitor_WP_Core_Update_Activity.php.
