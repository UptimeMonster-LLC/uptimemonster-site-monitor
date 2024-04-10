<?php
/**
 * Core Upgrader Skin
 *
 * @package UptimeMonster\SiteMonitor
 */

namespace UptimeMonster\SiteMonitor\CoreUpdate;

use WP_Error;
use Core_Upgrader;
use WP_Filesystem_Base;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

if ( ! class_exists( '\WP_Upgrader', false ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
}

/**
 * @property UptimeMonsterUpgraderSkin $skin
 */
class UptimeMonsterCoreUpgrader extends Core_Upgrader {

	public $insecure;

	/**
	 * CoreUpgrader constructor.
	 *
	 * @param UptimeMonsterUpgraderSkin|null $skin
	 */
	public function __construct( $skin = null, $insecure = false ) {
		$this->insecure = $insecure;
		parent::__construct( $skin );
	}

	/**
	 * Upgrade WordPress core.
	 *
	 * @access public
	 *
	 * @param object $current Response object for whether WordPress is current.
	 * @param array $args {
	 *        Optional. Arguments for upgrading WordPress core. Default empty array.
	 *
	 * @type bool $pre_check_md5 Whether to check the file checksums before
	 *                                     attempting the upgrade. Default true.
	 * @type bool $attempt_rollback Whether to attempt to rollback the chances if
	 *                                     there is a problem. Default false.
	 * @type bool $do_rollback Whether to perform this "upgrade" as a rollback.
	 *                                     Default false.
	 * }
	 * @return null|false|WP_Error False or WP_Error on failure, null on success.
	 * @global callable $_wp_filesystem_direct_method
	 *
	 * @global WP_Filesystem_Base $wp_filesystem Subclass
	 */
	public function upgrade( $current, $args = [] ) {
		set_error_handler( [ __CLASS__, 'error_handler' ], E_USER_WARNING | E_USER_NOTICE );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler

		$result = parent::upgrade( $current, $args );

		restore_error_handler();

		return $result;
	}

	/**
	 * Error handler to ignore failures on accessing SSL "https://api.wordpress.org/core/checksums/1.0/" in `get_core_checksums()` which seem to occur intermittently.
	 * This to suppress ```An unexpected error occurred. Something may be wrong with WordPress.org or this server's configuration.
	 * If you continue to have problems, please try the support forums.``` notice while updating WordPress core.
	 */
	public static function error_handler( $errno, $errstr, $errfile, $errline, $errcontext = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed
		// If ignoring E_USER_WARNING | E_USER_NOTICE, default.
		if ( ! ( ini_get( 'error_reporting' ) & $errno ) ) {
			return false;
		}

		// If not in "wp-admin/includes/update.php", default.
		$update_php = 'wp-admin/includes/update.php';
		if ( 0 !== substr_compare( $errfile, $update_php, - strlen( $update_php ) ) ) {
			return false;
		}

		// Else assume it's in `get_core_checksums()` and just ignore it.
		return true;
	}
}
