<?php
/**
 * Core Upgrader Skin
 *
 * @package UptimeMonster\SiteMonitor
 */

namespace UptimeMonster\SiteMonitor\CoreUpdate;

use WP_Error;
use Core_Upgrader as DefaultCoreUpgrader;
use WP_Filesystem_Base;
use WP_Upgrader_Skin;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class UptimeMonsterCoreUpgrader extends DefaultCoreUpgrader {

	public $insecure;

	/**
	 * CoreUpgrader constructor.
	 *
	 * @param UptimeMonsterUpgraderSkin|WP_Upgrader_Skin|null $skin
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
		set_error_handler( [ __CLASS__, 'error_handler' ], E_USER_WARNING | E_USER_NOTICE );

		$result = parent::upgrade( $current, $args );

		restore_error_handler();

		return $result;
	}

	/**
	 * Error handler to ignore failures on accessing SSL "https://api.wordpress.org/core/checksums/1.0/" in `get_core_checksums()` which seem to occur intermittently.
	 */
	public static function error_handler( $errno, $errstr, $errfile, $errline, $errcontext = null ) {
		// If ignoring E_USER_WARNING | E_USER_NOTICE, default.
		if ( ! ( error_reporting() & $errno ) ) {
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
