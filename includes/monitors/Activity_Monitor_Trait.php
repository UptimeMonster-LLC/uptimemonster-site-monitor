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

trait Activity_Monitor_Trait {

	/**
	 * Singleton ref.
	 * @var static
	 */
	protected static $instance;

	/**
	 * @return static
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Enforce singleton instance.
	 *
	 * @return void
	 */
	final protected function __construct() {}
}

// End of file Monitor_Base.php.
