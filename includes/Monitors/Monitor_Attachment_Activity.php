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

class Monitor_Attachment_Activity extends Monitor_Posts_Activity {

	public function init() {
		add_action( 'add_attachment', [ $this, 'log_on_created' ], 10, 1 );
		add_action( 'edit_attachment', [ $this, 'log_on_updated' ], 10, 1 );
		add_action( 'delete_attachment', [ $this, 'log_on_deleted' ], 10, 1 );
	}

	protected function maybe_log_activity( $action, $objectId ) {
		/**
		 * Should report activity for attachment?
		 *
		 * @param bool $status
		 * @param int|object $objectId
		 */
		return (bool) apply_filters( 'roxwp_should_log_attachment_activity', true, $action, $objectId );
	}

	protected function log_data( $action, $attachment_id ) {
		if ( $this->maybe_log_activity( $action, $attachment_id ) ) {
			parent::log_activity( $action, $attachment_id, 'attachment', $this->get_name( $attachment_id ) );
		}
	}

	public function log_on_created( $attachment_id ) {
		$this->log_data( Activity_Monitor_Base::ITEM_CREATED, $attachment_id );
	}

	public function log_on_updated( $attachment_id ) {
		$this->log_data( Activity_Monitor_Base::ITEM_UPDATED, $attachment_id );
	}

	public function log_on_deleted( $attachment_id ) {
		$this->log_data( Activity_Monitor_Base::ITEM_DELETED, $attachment_id );
	}
}

// End of file Activity_Monitor_Base.php.
