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

/**
 * @deprecated error handler logs & reports deprecated function/method calls.
 */
class Monitor_Deprecated_Activity extends Activity_Monitor_Base {

	use Singleton;

	protected $check_maybe_log = false;

	public function init() {

		// sometime gettext function might unavailable, as deprecation function can trigger early stage of wp loading.
		// no gettext used in this monitor.
		add_action( 'deprecated_function_run', [ $this, 'log_deprecated_function' ], 10, 3 );
		add_action( 'deprecated_constructor_run', [ $this, 'log_deprecated_class' ], 10, 3 );
		add_action( 'deprecated_argument_run', [ $this, 'log_deprecated_argument' ], 10, 3 );
		add_action( 'deprecated_hook_run', [ $this, 'log_deprecated_hook' ], 10, 4 );
	}

	protected function maybe_log_deprecated( $which ) {

		/**
		 * Should report activity?
		 *
		 * @param bool $status
		 * @param string $which
		 */
		return (bool) apply_filters( 'uptimemonster_should_log_deprecated_activity', true, $which );
	}

	protected function log_deprecated_function( $function_name, $replacement, $version ) {
		if ( ! $this->maybe_log_deprecated( 'function' ) ) {
			return;
		}

		if ( $replacement ) {
			$message = sprintf( '%1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.', $function_name, $version, $replacement );
		} else {
			$message = sprintf( '%1$s is <strong>deprecated</strong> since version %2$s with no alternative available.', $function_name, $version );
		}

		$this->log_activity( Activity_Monitor_Base::ITEM_DEPRECATED, 0, 'function', $message, compact( $function_name, $replacement, $version ) );
	}

	protected function log_deprecated_class( $class_name, $version, $parent_class ) {
		if ( ! $this->maybe_log_deprecated( 'class' ) ) {
			return;
		}

		if ( $parent_class ) {
			$message = sprintf( 'The called constructor method for %1$s in %2$s is <strong>deprecated</strong> since version %3$s! Use %4$s instead.', $class_name, $parent_class, $version, '<code>__construct()</code>' );
		} else {
			$message = sprintf( 'The called constructor method for %1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.', $class_name, $version, '<code>__construct()</code>' );
		}

		$this->log_activity( Activity_Monitor_Base::ITEM_DEPRECATED, 0, 'class', $message, compact( $class_name, $version, $parent_class ) );
	}

	protected function log_deprecated_argument( $function_name, $message, $version ) {
		if ( ! $this->maybe_log_deprecated( 'argument' ) ) {
			return;
		}

		if ( $message ) {
			$message = sprintf( '%1$s was called with an argument that is <strong>deprecated</strong> since version %2$s! %3$s', $function_name, $version, $message );
		} else {
			$message = sprintf( '%1$s was called with an argument that is <strong>deprecated</strong> since version %2$s with no alternative available.', $function_name, $version );
		}

		$this->log_activity( Activity_Monitor_Base::ITEM_DEPRECATED, 0, 'argument', $message, compact( $function_name, $version ) );
	}

	protected function log_deprecated_hook( $hook, $replacement, $version, $message ) {
		if ( ! $this->maybe_log_deprecated( 'hook' ) ) {
			return;
		}

		if ( $replacement ) {
			$message = sprintf( '%1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.', $hook, $version, $replacement ) . $message;
		} else {
			$message = sprintf( '%1$s is <strong>deprecated</strong> since version %2$s with no alternative available.', $hook, $version ) . $message;
		}

		$this->log_activity( Activity_Monitor_Base::ITEM_DEPRECATED, 0, 'hook', $message, compact( $hook, $replacement, $version ) );
	}
}

// End of file Monitor_Deprecated_Activity.php.
