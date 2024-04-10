<?php
/**
 * Monitor Activity
 *
 * @package UptimeMonster\SiteMonitor
 */

namespace UptimeMonster\SiteMonitor;

use UptimeMonster\SiteMonitor\Traits\Singleton;
use UptimeMonster\SiteMonitor\Monitors\Activity_Monitor_Base;
use UptimeMonster\SiteMonitor\Monitors\Monitor_Self_Activation_Activity;
use UptimeMonster\SiteMonitor\Monitors\Monitor_Attachment_Activity;
use UptimeMonster\SiteMonitor\Monitors\Monitor_Comment_Activity;
use UptimeMonster\SiteMonitor\Monitors\Monitor_Nav_Menu_Activity;
use UptimeMonster\SiteMonitor\Monitors\Monitor_Options_Activity;
use UptimeMonster\SiteMonitor\Monitors\Monitor_Plugins_Activity;
use UptimeMonster\SiteMonitor\Monitors\Monitor_Posts_Activity;
use UptimeMonster\SiteMonitor\Monitors\Monitor_Session_Activity;
use UptimeMonster\SiteMonitor\Monitors\Monitor_Taxonomy_Terms_Activity;
use UptimeMonster\SiteMonitor\Monitors\Monitor_Themes_Activity;
use UptimeMonster\SiteMonitor\Monitors\Monitor_Users_Activity;
use UptimeMonster\SiteMonitor\Monitors\Monitor_Widgets_Activity;
use UptimeMonster\SiteMonitor\Monitors\Monitor_WP_Core_Update_Activity;
use UptimeMonster\SiteMonitor\Monitors\Monitor_WP_Export_Content_Activity;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

class MonitorActivities {

	use Singleton;

	/**
	 * List of activity monitor.
	 *
	 * @var string[]
	 */
	protected $monitors = [
		Monitor_Self_Activation_Activity::class,

		Monitor_WP_Core_Update_Activity::class,
		Monitor_Options_Activity::class,

		Monitor_Plugins_Activity::class,
		Monitor_Themes_Activity::class,

		Monitor_Users_Activity::class,
		Monitor_Session_Activity::class,

		Monitor_Posts_Activity::class,
		Monitor_Attachment_Activity::class,

		Monitor_Comment_Activity::class,

		Monitor_Taxonomy_Terms_Activity::class,
		Monitor_Nav_Menu_Activity::class,

		Monitor_Widgets_Activity::class,

		Monitor_WP_Export_Content_Activity::class,
	];

	/**
	 * Activity Constructor.
	 */
	protected function __construct() {
		foreach ( $this->monitors as $monitor ) {
			/**
			 * @var Activity_Monitor_Base $monitor
			 */
			$monitor = call_user_func( [ $monitor, 'get_instance' ] );
			$monitor->init();
		}
	}
}

// End of file MonitorActivities.php.
