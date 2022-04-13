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

class Monitor_Posts_Activity extends Activity_Monitor_Base {

	use Singleton;

	protected $check_maybe_log = false;

	public function init() {
		add_action( 'transition_post_status', [ $this, 'log_on_status_change' ], 10, 3 );
		add_action( 'post_updated', [ $this, 'log_on_change' ] );
		add_action( 'delete_post', [ $this, 'log_delete' ], 10, 2 );
	}

	protected function maybe_log_activity( $action, $object_id ) {
		$post = get_post( $object_id );

		if ( ! $post ) {
			return false; // Maybe invalid post.
		}

		$status = true;

		// Attachment & Menu item should be logged separately.
		// @TODO log WC Product & variation in separate monitor.

		$excluded_post_types = self::get_excluded_post_types();
		array_push( $excluded_post_types, [ 'nav_menu_item', 'attachment' ] );

		if (
			in_array( $post->post_type, $excluded_post_types ) ||
			in_array( $post->post_status, [ 'auto-draft', 'draft', 'inherit' ] ) ||
			wp_is_post_revision( $post ) ||
			wp_is_post_autosave( $post )
		) {
			$status = false;
		}

		/**
		 * Should report activity for post?
		 *
		 * @param bool $status
		 * @param WP_Post $post
		 * @param string $action
		 */
		return (bool) apply_filters( 'roxwp_should_log_post_activity', $status, $post, $action );
	}

	/**
	 * Log post create & update.
	 *
	 * @param string $new_status New post status.
	 * @param string $old_status Old post status.
	 * @param WP_Post $post Post object.
	 *
	 * @throws Exception
	 */
	public function log_on_status_change( $new_status, $old_status, $post ) {
		$action = $this->get_action_from_status_change( $new_status, $old_status );
		if ( ! $this->maybe_log_activity( $action, $post->ID ) ) {
			return;
		}
		$this->log_activity(
			$action,
			$post->ID,
			$post->post_type,
			$this->get_name( $post )
		);
	}

	public function log_on_change( $post_ID ) {
		if ( ! $this->maybe_log_activity( Activity_Monitor_Base::ITEM_UPDATED, $post_ID ) ) {
			return;
		}

		$post = get_post( $post_ID );

		$this->log_activity(
			Activity_Monitor_Base::ITEM_UPDATED, // @phpstan-ignore-line
			$post_ID,
			get_post_type( $post_ID ), // @phpstan-ignore-line
			$this->get_name( $post ) // @phpstan-ignore-line
		);
	}

	/**
	 * Log Post Delete.
	 *
	 * @param int $post_id Post id.
	 *
	 * @throws Exception
	 */
	public function log_delete( $post_id ) {
		if ( ! $this->maybe_log_activity( Activity_Monitor_Base::ITEM_DELETED, $post_id ) ) {
			return;
		}

		$this->log_activity(
			Activity_Monitor_Base::ITEM_DELETED,
			$post_id,
			get_post_type( $post_id ), // @phpstan-ignore-line
			$this->get_name( $post_id )
		);
	}

	protected function get_action_from_status_change( $new_status, $old_status ) {
		if ( 'auto-draft' === $old_status && ( 'auto-draft' !== $new_status && 'inherit' !== $new_status ) ) {
			return Activity_Monitor_Base::ITEM_CREATED;
		} elseif ( 'trash' === $new_status ) {
			return Activity_Monitor_Base::ITEM_TRASHED;
		} elseif ( 'trash' === $old_status ) {
			return Activity_Monitor_Base::ITEM_RESTORED;
		} elseif ( 'auto-draft' === $new_status || ( 'new' === $old_status && 'inherit' === $new_status ) ) {
			// @XXX we dont need to log auto-draft or drafts. Keep it for the record.
			return Activity_Monitor_Base::ITEM_CREATED;
		} else {
			return Activity_Monitor_Base::ITEM_UPDATED;
		}
	}

	/**
	 * Get excluded post types.
	 *
	 * @return array
	 */
	public static function get_excluded_post_types() {
		/**
		 * Exclude post type from the monitor.
		 *
		 * @param array $post_types
		 */
		return (array) apply_filters( 'roxwp_exclude_pos_type', [] );
	}

	/**
	 * Get Post Name
	 *
	 * @param WP_Post|int|string $post
	 *
	 * @return string
	 */
	protected function get_name( $post ) {
		$post = get_post( $post ); // @phpstan-ignore-line

		/** @noinspection PhpTernaryExpressionCanBeReducedToShortVersionInspection */
		return $post->post_title ? $post->post_title : 'no_title'; // @phpstan-ignore-line
	}
}

// End of file Monitor_Posts_Activity.php.
