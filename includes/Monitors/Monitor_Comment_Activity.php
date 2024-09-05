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
use WP_Comment;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

class Monitor_Comment_Activity extends Monitor_Posts_Activity {

	public function init() {
		add_action( 'wp_insert_comment', [ $this, 'log_on_change' ], 10, 2 );
		add_action( 'edit_comment', [ $this, 'log_on_change' ], 10, 2 );
		add_action( 'trash_comment', [ $this, 'log_on_change' ], 10, 2 );
		add_action( 'untrash_comment', [ $this, 'log_on_change' ], 10, 2 );
		add_action( 'spam_comment', [ $this, 'log_on_change' ], 10, 2 );
		add_action( 'unspam_comment', [ $this, 'log_on_change' ], 10, 2 );
		add_action( 'delete_comment', [ $this, 'log_on_change' ], 10, 2 );
		add_action( 'transition_comment_status', [ $this, 'log_on_status_change' ], 10, 3 );
	}

	protected function maybe_log_activity( string $action = null, $object_id = null ): bool {
		/**
		 * Should report activity for comment?
		 *
		 * @param bool $status
		 * @param int|object $object
		 * @param string $action
		 */
		return (bool) apply_filters( 'uptimemonster_should_log_comment_activity', true, get_comment( $object_id ), $action );
	}

	protected function detect_action( $comment ) {
		$action = Activity_Monitor_Base::ITEM_CREATED;

		switch ( current_filter() ) {
			case 'wp_insert_comment':
				$action = 1 === (int) $comment->comment_approved ? Activity_Monitor_Base::ITEM_APPROVED : Activity_Monitor_Base::ITEM_PENDING;
				break;

			case 'edit_comment':
				$action = Activity_Monitor_Base::ITEM_UPDATED;
				break;

			case 'delete_comment':
				$action = Activity_Monitor_Base::ITEM_DELETED;
				break;

			case 'trash_comment':
				$action = Activity_Monitor_Base::ITEM_TRASHED;
				break;

			case 'untrash_comment':
				$action = Activity_Monitor_Base::ITEM_RESTORED;
				break;

			case 'spam_comment':
				$action = Activity_Monitor_Base::ITEM_SPAMMED;
				break;

			case 'unspam_comment':
				$action = Activity_Monitor_Base::ITEM_UNSPAMMED;
				break;
		}

		return $action;
	}

	/**
	 * @param string|int $status
	 *
	 * @return string
	 */
	protected function translate_comment_status( $status ) {
		$action = Activity_Monitor_Base::ITEM_PENDING;

		switch ( $status ) {
			case '0':
			case 'hold':
				$action = Activity_Monitor_Base::ITEM_UNAPPROVED;
				break;
			case '1':
			case 'approve':
				$action = Activity_Monitor_Base::ITEM_APPROVED;
				break;
			case 'spam':
				$action = Activity_Monitor_Base::ITEM_SPAMMED;
				break;
			case 'trash':
				$action = Activity_Monitor_Base::ITEM_TRASHED;
				break;
		}

		return $action;
	}

	/**
	 * @param string $action
	 * @param WP_Comment $comment
	 *
	 * @throws \Exception
	 */
	protected function log_data( $action, $comment ) {
		if ( $this->maybe_log_activity( $action, $comment ) ) {
			parent::log_activity( $action, $comment->comment_ID, 'comment', $this->get_name( $comment->comment_post_ID ) );
		}
	}

	public function log_on_change( $comment_id, $comment = null ) {
		if ( null === $comment ) {
			$comment = get_comment( $comment_id );
		}

		$this->log_data( $this->detect_action( $comment ), $comment );
	}

	/**
	 * @param int|string $new_status The new comment status.
	 * @param int|string $old_status The old comment status.
	 * @param WP_Comment $comment Comment object.
	 */
	public function log_on_status_change( $new_status, $old_status, $comment ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed @phpstan-ignore-line,Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed
		// @TODO this method might cause duplicate data, test needed.
		$this->log_data( $this->translate_comment_status( $new_status ), $comment );
	}
}

// End of file Monitor_Comment_Activity.php.
