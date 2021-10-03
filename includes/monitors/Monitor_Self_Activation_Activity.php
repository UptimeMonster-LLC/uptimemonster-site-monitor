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

class Monitor_Self_Activation_Activity extends Activity_Monitor_Base {

	use Activity_Monitor_Trait;

	protected $check_maybe_log = false;

	public function init() {

		add_action( 'roxwp_site_monitor_api_updated', [ $this, 'on_activation' ] );
		add_action( 'roxwp_site_monitor_activation', [ $this, 'on_activation' ] );

		add_action( 'roxwp_site_monitor_api_deactivated', [ $this, 'on_deactivation' ] );
		add_action( 'roxwp_site_monitor_deactivation', [ $this, 'on_deactivation' ] );
	}

	protected function maybe_log_activity( $action = '', $objectId = '' ) {
		return get_option( 'roxwp_site_monitor_api_keys' );
	}

	public function on_activation() {
		if ( ! $this->maybe_log_activity() ) {
			return;
		}

		roxwp_switch_to_site_locale();
		$this->log_activity(
			Activity_Monitor_Base::ITEM_ACTIVATED,
			0,
			'monitor',
			__( 'Site Monitor Activated', 'rwp-site-mon' )
		);
		roxwp_restore_locale();
	}

	public function on_deactivation() {
		if ( ! $this->maybe_log_activity() ) {
			return;
		}

		roxwp_switch_to_site_locale();
		$this->log_activity(
			Activity_Monitor_Base::ITEM_DEACTIVATED,
			0,
			'monitor',
			__( 'Site Monitor Deactivated', 'rwp-site-mon' )
		);
		roxwp_restore_locale();
	}
}

// End of file Monitor_Self_Activation_Activity.php.
