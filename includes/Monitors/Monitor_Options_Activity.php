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

class Monitor_Options_Activity extends Activity_Monitor_Base {

	use Singleton;

	protected $check_maybe_log = false;

	public function init() {
		add_action( 'added_option', [ $this, 'log_on_change' ], 10, 2 );
		add_action( 'updated_option', [ $this, 'log_on_change' ], 10, 2 );
		add_action( 'deleted_option', [ $this, 'log_on_change' ] );
	}

	protected function maybe_log_option( $action, $option, $value = null, $new_value = null ) {
		/**
		 * Should report activity?
		 *
		 * @param bool $status
		 * @param string $option
		 * @param string $action
		 * @param string $value
		 * @param string $new_value
		 */
		return (bool) apply_filters( 'uptimemonster_should_log_options_activity', true, $option, $action, $value, $new_value );
	}

	public function log_on_change( $option, $old_value = null, $new_value = null ) {
		$action = current_filter();
		if ( 'added_option' === $action ) {
			$action = Activity_Monitor_Base::ITEM_CREATED;
		} elseif ( 'updated_option' === $action ) {
			$action = Activity_Monitor_Base::ITEM_UPDATED;
		} else {
			$action = Activity_Monitor_Base::ITEM_DELETED;
		}

		if ( ! $this->maybe_log_option( $action, $option, $old_value, $new_value ) ) {
			return;
		}

		// @XXX Do not log the values (old/new) as those might contain 3rd party credentials.
		//      We may log some of them, non-sensitive options

		$this->log_activity( $action, 0, $option, $option );
	}

	public static function get_options_to_log() {
		return apply_filters(
			'uptimemonster_options_to_log', [
				// General
				'blogname',
				'blogdescription',
				'siteurl',
				'home',
				'admin_email',
				'users_can_register',
				'default_role',
				'timezone_string',
				'date_format',
				'time_format',
				'start_of_week',

				// Writing
				'use_smilies',
				'use_balanceTags',
				'default_category',
				'default_post_format',
				'mailserver_url',
				'mailserver_login',
				'mailserver_pass',
				'default_email_category',
				'ping_sites',

				// Reading
				'show_on_front',
				'page_on_front',
				'page_for_posts',
				'posts_per_page',
				'posts_per_rss',
				'rss_use_excerpt',
				'blog_public',

				// Discussion
				'default_pingback_flag',
				'default_ping_status',
				'default_comment_status',
				'require_name_email',
				'comment_registration',
				'close_comments_for_old_posts',
				'close_comments_days_old',
				'thread_comments',
				'thread_comments_depth',
				'page_comments',
				'comments_per_page',
				'default_comments_page',
				'comment_order',
				'comments_notify',
				'moderation_notify',
				'comment_moderation',
				'comment_whitelist',
				'comment_max_links',
				'moderation_keys',
				'blacklist_keys',
				'show_avatars',
				'avatar_rating',
				'avatar_default',

				// Media
				'thumbnail_size_w',
				'thumbnail_size_h',
				'thumbnail_crop',
				'medium_size_w',
				'medium_size_h',
				'large_size_w',
				'large_size_h',
				'uploads_use_yearmonth_folders',

				// Permalinks
				'permalink_structure',
				'category_base',
				'tag_base',

				// Widgets
				'sidebars_widgets',
			]
		);
	}
}

// End of file Monitor_Options_Activity.php.
