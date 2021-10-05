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

class Monitor_WP_Export_Content_Activity extends Activity_Monitor_Base {

	use Activity_Monitor_Trait;

	public function init() {
		add_action( 'export_wp', [ $this, 'log_on_export' ] );
	}

	protected function maybe_log_activity( $action, $objectId ) {

		/**
		 * Should report activity?
		 *
		 * @param bool $status
		 * @param null $object
		 * @param string $action
		 */
		return (bool) apply_filters( 'roxwp_should_log_wp_export_activity', true, null, $action );
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
