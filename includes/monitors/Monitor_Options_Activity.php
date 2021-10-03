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

class Monitor_Options_Activity extends Activity_Monitor_Base {

	use Activity_Monitor_Trait;

	protected $check_maybe_log = false;

	public function init() {
		//add_action( 'added_option', [ $this, 'log_on_change' ], 10, 1 );
		add_action( 'updated_option', [ $this, 'log_on_change' ], 10, 2 );
	}

	protected function maybe_log_option( $action, $option, $value, $new_value = null ) {

		/**
		 * Should report activity for WP Core Updates?
		 *
		 * @param bool $status
		 * @param string $option
		 * @param string $action
		 * @param string $value
		 * @param string $new_value
		 */
		return (bool) apply_filters( 'roxwp_should_log_options_activity', true, $option, $action, $value, $new_value );
	}

	public function log_on_change( $option, $old, $new = null ) {

		$action = 'added_option' === current_filter() ? Activity_Monitor_Base::ITEM_CREATED : Activity_Monitor_Base::ITEM_UPDATED;

		if ( ! $this->maybe_log_option( $action, $option, $old, $new ) ) {
			return;
		}

		// @XXX Do not log the values (old/new) as those might contain 3rd party credentials.
		//      We may log some of them, non-sensitive options

		$this->log_activity(
			Activity_Monitor_Base::ITEM_UPDATED,
			0,
			$option,
			$option
		);
	}

	public static function get_options_to_log() {
		return apply_filters(
			'roxwp_options_to_log', [
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
