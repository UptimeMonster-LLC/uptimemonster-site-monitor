<?php
/**
 * Data Monitor Base
 *
 * @package UptimeMonster\SiteMonitor\Monitors
 * @version 1.0.0
 * @since SiteMonitor 1.0.0
 */

namespace UptimeMonster\SiteMonitor\Monitors;

use UptimeMonster\SiteMonitor\UptimeMonster_Site_Monitor;
use Exception;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Monitor_Self_Activation_Activity extends Activity_Monitor_Base {

	use Singleton;

	protected $check_maybe_log = false;

	public function init() {
		add_action( 'umsm_site_monitor_api_updated', [ $this, 'on_activation' ] );
		add_action( 'umsm_site_monitor_activation', [ $this, 'on_activation' ] );

		add_action( 'umsm_site_monitor_api_deactivating', [ $this, 'on_deactivation' ] );
		add_action( 'umsm_site_monitor_api_deactivated', [ $this, 'on_deactivation' ] );

		add_action( 'umsm_error_logger_installed', [ $this, 'on_error_logger_installed' ], 10, 2 );
	}

	protected function maybe_log_activity( $action = '', $object_id = '' ) {
		return get_option( 'umsm_site_monitor_api_keys' );
	}

	public function on_activation() {
		if ( ! $this->maybe_log_activity() ) {
			return;
		}

		umsm_switch_to_english();
		$this->log_activity(
			Activity_Monitor_Base::ITEM_ACTIVATED,
			0,
			'monitor',
			__( 'Site Monitor Activated', 'uptimemonster-site-monitor' ),
			[ 'include_installed' => true ]
		);
		umsm_restore_locale();
	}

	public function on_deactivation() {
		if ( ! $this->maybe_log_activity() ) {
			return;
		}

		umsm_switch_to_english();
		$this->log_activity(
			Activity_Monitor_Base::ITEM_DEACTIVATED,
			0,
			'monitor',
			__( 'Site Monitor Deactivated', 'uptimemonster-site-monitor' )
		);
		umsm_restore_locale();
	}

	public function on_error_logger_installed( $old = null, $installed = false ) {
		if ( ! $this->maybe_log_activity() ) {
			return;
		}

		umsm_switch_to_english();

		if ( $installed ) {
			$name = $old ? __( '“Error Logger” Drop-In Updated', 'uptimemonster-site-monitor' ) : __( '“Error Logger” Drop-In Installed', 'uptimemonster-site-monitor' );
		} else {
			$name = __( 'Failed To Install “Error Logger” Drop-In.', 'uptimemonster-site-monitor' );
		}

		umsm_restore_locale();

		$data = [ 'version' => UptimeMonster_Site_Monitor::drop_in_version() ];

		if ( $old ) {
			$data['previous'] = $old;
		}

		$this->log_activity( $old ? Activity_Monitor_Base::ITEM_UPDATED : Activity_Monitor_Base::ITEM_INSTALLED, 0, 'monitor', $name, $data );
	}
}

// End of file Monitor_Self_Activation_Activity.php.
