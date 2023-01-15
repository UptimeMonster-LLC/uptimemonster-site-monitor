<?php
/**
 * Data Monitor Base
 *
 * @package UptimeMonster\SiteMonitor\Monitors
 * @version 1.0.0
 * @since SiteMonitor 1.0.0
 */

namespace UptimeMonster\SiteMonitor\Monitors;

use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

interface Activity_Monitor_Interface {

	public static function get_instance();

	public function init();

}

// End of file Activity_Monitor_Interface.php.
