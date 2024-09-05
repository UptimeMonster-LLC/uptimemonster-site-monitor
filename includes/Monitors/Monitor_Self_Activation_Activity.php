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
use UptimeMonster\SiteMonitor\UptimeMonster_Site_Monitor;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

class Monitor_Self_Activation_Activity extends Activity_Monitor_Base {

	use Singleton;

	protected $check_maybe_log = false;

	public function init() {
		add_action( 'uptimemonster_site_monitor_api_updated', [ $this, 'on_activation' ] );
		add_action( 'uptimemonster_site_monitor_activation', [ $this, 'on_activation' ] );

		add_action( 'uptimemonster_error_logger_installed', [ $this, 'on_error_logger_installed' ], 10, 2 );
		add_action( 'uptimemonster_error_logger_uninstalled', [ $this, 'on_error_logger_uninstalled' ], 10, 2 );
	}

	protected function maybe_log_activity( string $action = null, $object_id = null ): bool {
		return ! empty( get_option( 'uptimemonster_site_monitor_api_keys' ) );
	}

	public function on_activation() {
		if ( ! $this->maybe_log_activity() ) {
			return;
		}

		uptimemonster_switch_to_english();
		$this->log_activity(
			Activity_Monitor_Base::ITEM_ACTIVATED,
			0,
			'monitor',
			esc_html__( 'Site Monitor Activated', 'uptimemonster-site-monitor' ),
			[ 'include_installed' => true ]
		);
		uptimemonster_restore_locale();
	}

	public function on_error_logger_installed( $installed, $old_version = null ) {
		if ( ! $this->maybe_log_activity() ) {
			return;
		}

		uptimemonster_switch_to_english();

		if ( $installed ) {
			$name = $old_version ? esc_html__( '“Error Logger” Drop-In Updated', 'uptimemonster-site-monitor' ) : esc_html__( '“Error Logger” Drop-In Installed', 'uptimemonster-site-monitor' );
		} else {
			$name = esc_html__( 'Failed To Install “Error Logger” Drop-In.', 'uptimemonster-site-monitor' );
		}

		uptimemonster_restore_locale();

		$data = [ 'version' => UptimeMonster_Site_Monitor::drop_in_version() ];

		if ( $old_version ) {
			$data['previous'] = $old_version;
		}

		$this->log_activity( $old_version ? Activity_Monitor_Base::ITEM_UPDATED : Activity_Monitor_Base::ITEM_INSTALLED, 0, 'monitor', $name, $data );
	}
	public function on_error_logger_uninstalled( $removed, $version = null ) {
		if ( ! $this->maybe_log_activity() ) {
			return;
		}

		uptimemonster_switch_to_english();

		if ( $removed ) {
			$name = esc_html__( '“Error Logger” Drop-In Uninstalled', 'uptimemonster-site-monitor' );
		} else {
			$name = esc_html__( 'Failed To Uninstall “Error Logger” Drop-In.', 'uptimemonster-site-monitor' );
		}

		uptimemonster_restore_locale();

		$data = [ 'version' => $version ];

		$this->log_activity( Activity_Monitor_Base::ITEM_UNINSTALLED, 0, 'monitor', $name, $data );
	}
}

// End of file Monitor_Self_Activation_Activity.php.
