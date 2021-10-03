<?php
/**
 * Monitor Activity
 *
 * @package RoxwpSiteMonitor
 * @author Name <email>
 * @version
 * @since
 * @license
 */

namespace AbsolutePlugins\RoxwpSiteMonitor;

use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Activity_Monitor_Base;
use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Monitor_Self_Activation_Activity;
use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Monitor_Attachment_Activity;
use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Monitor_Comment_Activity;
use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Monitor_Nav_Menu_Activity;
use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Monitor_Options_Activity;
use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Monitor_Plugins_Activity;
use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Monitor_Posts_Activity;
use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Monitor_Session_Activity;
use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Monitor_Taxonomy_Terms_Activity;
use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Monitor_Themes_Activity;
use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Monitor_Users_Activity;
use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Monitor_Widgets_Activity;
use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Monitor_WP_Core_Update_Activity;
use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Monitor_WP_Export_Content_Activity;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class MonitorActivities {

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

	protected static $instance;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

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
