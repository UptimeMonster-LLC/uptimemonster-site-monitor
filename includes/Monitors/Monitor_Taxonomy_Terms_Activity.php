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
use WP_Error;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

class Monitor_Taxonomy_Terms_Activity extends Activity_Monitor_Base {

	use Singleton;

	protected $check_maybe_log = false;

	public function init() {
		add_action( 'created_term', [ $this, 'log_on_change' ], 10, 3 );
		add_action( 'edited_term', [ $this, 'log_on_change' ], 10, 3 );
		add_action( 'delete_term', [ $this, 'log_on_change' ], 10, 5 );
	}

	protected function maybe_log_activity( string $action = null, $object_id = null ): bool {
		$term   = get_term( $object_id );
		$status = ! is_wp_error( $term ) && isset( $term->taxonomy ) && 'nav_menu' !== $term->taxonomy;

		/**
		 * Should report activity?
		 *
		 * @param bool $status
		 * @param WP_Term|WP_Error $term
		 * @param string $action
		 */
		return (bool) apply_filters( 'uptimemonster_should_log_wp_export_activity', $status, $term, $action );
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

	/**
	 * @throws Exception
	 */
	public function log_on_change( $term_id, $tt_id, $taxonomy, $deleted_term = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed
		if ( 'delete_term' === current_filter() ) {
			$term = $deleted_term;
		} else {
			$term = get_term( $term_id, $taxonomy );
		}

		$action = $this->detect_action();

		if ( ! $this->maybe_log_activity( $action, $term_id ) && 'nav_menu' !== $term->taxonomy ) {
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
