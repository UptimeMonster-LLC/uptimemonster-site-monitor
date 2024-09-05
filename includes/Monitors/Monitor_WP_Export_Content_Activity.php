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

class Monitor_WP_Export_Content_Activity extends Activity_Monitor_Base {

	use Singleton;

	public function init() {
		add_action( 'export_wp', [ $this, 'log_on_export' ] );
	}

	protected function maybe_log_activity( string $action = null, $object_id = null ): bool {

		/**
		 * Should report activity?
		 *
		 * @param bool $status
		 * @param null $object
		 * @param string $action
		 */
		return (bool) apply_filters( 'uptimemonster_should_log_wp_export_activity', true, null, $action );
	}

	public function log_on_export( $args ) {
		$this->log_activity(
			Activity_Monitor_Base::ITEM_EXPORTED,
			0,
			'export',
			isset( $args['content'] ) ? $args['content'] : 'all'
		);
	}
}

// End of file Monitor_WP_Export_Content_Activity.php.
