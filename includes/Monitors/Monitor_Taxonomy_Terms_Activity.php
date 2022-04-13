<?php
/**
 * Data Monitor Base
 *
 * @package RoxwpSiteMonitor\Monitors
 * @version 1.0.0
 * @since RoxwpSiteMonitor 1.0.0
 */

namespace AbsolutePlugins\RoxwpSiteMonitor\Monitors;

use WP_Error;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Monitor_Taxonomy_Terms_Activity extends Activity_Monitor_Base {

	use Singleton;

	protected $check_maybe_log = false;

	public function init() {
		add_action( 'created_term', [ $this, 'log_on_change' ], 10, 3 );
		add_action( 'edited_term', [ $this, 'log_on_change' ], 10, 3 );
		add_action( 'delete_term', [ $this, 'log_on_change' ], 10, 5 );
	}

	protected function maybe_log_activity( $action, $object_id ) {
		$term   = get_term( $object_id );
		$status = ! is_wp_error( $term ) && 'nav_menu' !== $term->taxonomy;

		/**
		 * Should report activity?
		 *
		 * @param bool $status
		 * @param WP_Term|WP_Error $term
		 * @param string $action
		 */
		return (bool) apply_filters( 'roxwp_should_log_wp_export_activity', $status, $term, $action );
	}

	protected function detect_action() {
		if ( 'edited_term' === current_filter() ) {
			$action = Activity_Monitor_Base::ITEM_UPDATED;
		} elseif ( 'delete_term' === current_filter() ) {
			$action = Activity_Monitor_Base::ITEM_DELETED;
		} else {
			$action = Activity_Monitor_Base::ITEM_CREATED;
		}

		return $action;
	}

	public function log_on_change( $term_id, $tt_id, $taxonomy, $deleted_term = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed
		if ( 'delete_term' === current_filter() ) {
			$term = $deleted_term;
		} else {
			$term = get_term( $term_id, $taxonomy );
		}

		$action = $this->detect_action();

		if ( ! $this->maybe_log_activity( $action, $term_id ) || 'nav_menu' !== $term->taxonomy ) {
			return;
		}

		if ( $this->maybe_log_activity( $action, $term_id ) ) {
			$this->log_activity(
				$action,
				$term->term_id,
				$term->taxonomy,
				$term->name
			);
		}
	}
}

// End of file Monitor_Taxonomy_Terms_Activity.php.
