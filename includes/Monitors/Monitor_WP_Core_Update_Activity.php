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

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

class Monitor_WP_Core_Update_Activity extends Activity_Monitor_Base {

	use Singleton;

	public function init() {
		add_action( 'admin_head', [ $this, 'log_on_update_start' ] );
		add_action( 'wp_maybe_auto_update', [ $this, 'log_on_auto_update_start' ] );
		add_action( '_core_updated_successfully', [ $this, 'log_on_successful_update' ] );
		// @TODO find way to log update failed.
	}

	protected function maybe_log_activity( string $action = null, $object_id = null ): bool {

		/**
		 * Should report activity?
		 *
		 * @param bool $status
		 * @param null $object
		 * @param string $action
		 */
		return (bool) apply_filters( 'uptimemonster_should_log_wp_core_update_activity', true, null, $action );
	}

	public function log_on_auto_update_start() {
		uptimemonster_switch_to_english();
		/* translators: 1. WordPress Version. */
		$name = esc_html__( 'WordPress Auto Upgrading From %s', 'uptimemonster-site-monitor' );
		uptimemonster_restore_locale();

		$version = get_bloginfo( 'version' );

		$this->log_activity( Activity_Monitor_Base::ITEM_UPGRADING, 0, 'WordPressCore', sprintf( $name, $version ) );
	}

	/**
	 * @throws Exception
	 */
	public function log_on_update_start() {
		global $pagenow;
		if ( 'update-core.php' !== $pagenow ) {
			return;
		}

		if ( isset( $_POST['upgrade'], $_REQUEST['_wpnonce'] ) && ! empty( $_GET['action'] ) && in_array( sanitize_text_field( $_GET['action'] ), [ 'do-core-upgrade', 'do-core-reinstall' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'upgrade-core' ) ) {
			$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
			uptimemonster_switch_to_english();
			/* translators: 1. WordPress Version. */
			$name = 'do-core-upgrade' == $action ? esc_html__( 'WordPress Upgrading From %s', 'uptimemonster-site-monitor' ) : esc_html__( 'WordPress Reinstalling %s', 'uptimemonster-site-monitor' );
			uptimemonster_restore_locale();

			$version = get_bloginfo( 'version' );

			$this->log_activity(
				'do-core-upgrade' === $action ? Activity_Monitor_Base::ITEM_UPGRADING : Activity_Monitor_Base::ITEM_REINSTALLING,
				0,
				'WordPressCore',
				sprintf( $name, $version )
			);
		}
	}

	public function log_on_successful_update( $version ) {
		global $pagenow;

		uptimemonster_switch_to_english();
		/* translators: 1. WordPress Updated Version. */
		$name = 'update-core.php' !== $pagenow ? esc_html__( 'WordPress Auto Updated to %s', 'uptimemonster-site-monitor' ) : esc_html__( 'WordPress Updated to %s', 'uptimemonster-site-monitor' );
		uptimemonster_restore_locale();

		$this->log_activity( Activity_Monitor_Base::ITEM_UPDATED, 0, 'WordPressCore', sprintf( $name, $version ), [ 'wp_version' => $version ] );
	}
}

// End of file Monitor_WP_Core_Update_Activity.php.
