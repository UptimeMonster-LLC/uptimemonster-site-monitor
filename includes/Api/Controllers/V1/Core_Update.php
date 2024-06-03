<?php
/**
 * Core Updater API
 *
 * @package UptimeMonster\SiteMonitor\API
 * @version 1.0.0
 */

namespace UptimeMonster\SiteMonitor\Api\Controllers\V1;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

use Exception;
use InvalidArgumentException;
use stdClass;
use UptimeMonster\SiteMonitor\Api\Controllers\Controller_Base;
use UptimeMonster\SiteMonitor\CoreUpdate\UptimeMonsterCoreUpgrader;
use UptimeMonster\SiteMonitor\CoreUpdate\UptimeMonsterUpgraderSkin;
use WP_Error;
use WP_REST_Server;

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/class-core-upgrader.php';
require_once ABSPATH . 'wp-includes/class-wp-error.php';

/**
 * Class Core_Update
 */
class Core_Update extends Controller_Base {
	/**
	 * Route base.
	 *
	 * @var string
	 */
	public $rest_base = '/core';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Register core update route.
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/update', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'run_core_updater' ],
					'permission_callback' => [ $this, 'get_route_access' ],
					'args'                => [
						'minor'   => [
							'default'           => false,
							'description'       => esc_html__( 'Only perform updates for minor releases (e.g. update from WP 4.3 to 4.3.3 instead of 4.4.2.', 'uptimemonster-site-monitor' ),
							'type'              => 'boolean',
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'version' => [
							'default'           => 'latest',
							'description'       => esc_html__( 'Update to a specific version, instead of to the latest version. Alternatively accepts \'nightly\'.', 'uptimemonster-site-monitor' ),
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'force'   => [
							'default'           => false,
							'description'       => esc_html__( 'Update even when installed WP version is greater than the requested version.', 'uptimemonster-site-monitor' ),
							'type'              => 'boolean',
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'locale'  => [
							'default'           => '',
							'description'       => esc_html__( 'Select which language you want to download.', 'uptimemonster-site-monitor' ),
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => 'rest_validate_request_arg',
						],
					],
				],
			]
		);
	}

	public function run_core_updater( $request ) {
		remove_action( 'init', 'smilies_init', 5 );

		$update = $this->update( $request );

		if ( $update['need_db_update'] ) {
			// Process db update before return.
			$this->update_db();
		}

		if ( is_wp_error( $update['message'] ) ) {
			return $update['message'];
		}

		$response = [
			'status'  => true,
			'message' => $update['message'],
			'debug'   => ! empty( $update['debug'] ) ? $update['debug'] : null,
		];

		$this->add_extra_data( $response );

		return rest_ensure_response( $response );
	}

	protected function update( $request ): array {
		global $wp_version;

		$update = null;
		// Specific version is given
		$version = ! empty( $request['version'] ) ? $request['version'] : '';
		$locale  = ! empty( $request['locale'] ) ? $request['locale'] : get_locale();
		$force   = uptimemonster_parse_boolval( $request['force'] ?? false );
		$minor   = uptimemonster_parse_boolval( $request['minor'] ?? false );

		if ( 'trunk' === $request['version'] ) {
			$request['version'] = 'nightly';
		}

		$update = false;
		if ( empty( $request['version'] ) ) {
			// Update to next release.
			wp_version_check( [], $force );

			$from_api = get_site_transient( 'update_core' );

			if ( $minor ) {
				foreach ( $from_api->updates as $offer ) {
					$sem_ver = uptimemonster_get_named_sem_ver( $offer->version, $wp_version );
					if ( 'patch' !== $sem_ver ) {
						continue;
					}
					$update = $offer;
					break;
				}
				if ( empty( $update ) ) {
					return [
						'need_db_update' => false,
						'message'        => esc_html__( 'WordPress is at the latest minor release.', 'uptimemonster-site-monitor' ),
						'debug'          => null,
					];
				}
			} else {
				foreach ( $from_api->updates as $_update ) {
					if ( 'upgrade' === $_update->response ) {
						$update = $_update;
						break;
					}
				}
			}
		} elseif ( uptimemonster_wp_version_compare( $request['version'], '<' ) || 'nightly' === $request['version'] || $force ) {
			$new_package = $this->get_download_url( $version, $locale );
			$update      = (object) [
				'response' => 'upgrade',
				'current'  => $request['version'],
				'download' => $new_package,
				'packages' => (object) [
					'partial'     => null,
					'new_bundled' => null,
					'no_content'  => null,
					'full'        => $new_package,
				],
				'version'  => $version,
				'locale'   => $locale,
			];
		}

		// requested update (or rollback) version is not same as current version or forcing the update.
		if ( $update && ( $update->version !== $wp_version || $force ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$GLOBALS['wpcli_core_update_obj'] = $update; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
			$from_version                     = $wp_version;
			$upgrader                         = new UptimeMonsterCoreUpgrader( new UptimeMonsterUpgraderSkin() );
			$result                           = $upgrader->upgrade( $update );
			unset( $GLOBALS['wpcli_core_update_obj'] );

			if ( is_wp_error( $result ) ) {
				$message = self::error_to_string( $result );
				if ( 'up_to_date' !== $result->get_error_code() ) {
					$message = new WP_Error( $result->get_error_code(), $message );
				}

				return [
					'need_db_update' => false,
					'message'        => $message,
					'debug'          => $upgrader->skin->get_feedbacks(),
				];
			} else {
				$to_version = '';
				if ( file_exists( ABSPATH . 'wp-includes/version.php' ) ) {
					$wp_details = self::get_wp_details();
					$to_version = $wp_details['wp_version'];
				}

				$cleanup = $this->cleanup_extra_files( $from_version, $to_version, $locale );

				return [
					'need_db_update' => true,
					'message'        => is_wp_error( $cleanup ) ? $cleanup : esc_html__( 'WordPress updated successfully.', 'uptimemonster-site-monitor' ),
					'debug'          => $upgrader->skin->get_feedbacks(),
				];
			}
		} else {
			return [
				'need_db_update' => false,
				'message'        => esc_html__( 'WordPress is up to date.', 'uptimemonster-site-monitor' ),
				'debug'          => null,
			];
		}
	}

	protected function update_db() {
		global $wpdb, $wp_db_version, $wp_current_db_version;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		include ABSPATH . 'wp-includes/version.php';

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Replacing WP Core behavior is the goal here.
		$wp_current_db_version = (int) __get_option( 'db_version' );

		if ( $wp_db_version !== $wp_current_db_version ) {
			// WP upgrade isn't too fussy about generating MySQL warnings such as "Duplicate key name" during an upgrade so suppress.
			$wpdb->suppress_errors();

			// WP upgrade expects `$_SERVER['HTTP_HOST']` to be set in `wp_guess_url()`, otherwise get PHP notice.
			if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
				// As it will be running on already installed WordPress, we can just get value from db;
				// Remove url scheme and the trailing slash, keep everything else (can be a sub-dir).

				$site_host            = preg_replace( '#^\w+://#', '', site_url() );
				$_SERVER['HTTP_HOST'] = rtrim( $site_host, '/' );
			}

			wp_upgrade();
		}
	}

	/**
	 * Clean up extra files.
	 *
	 * @param string $version_from Starting version that the installation was updated from.
	 * @param string $version_to Target version that the installation is updated to.
	 * @param string $locale Locale of the installation.
	 */
	private function cleanup_extra_files( $version_from, $version_to, $locale = 'en_US' ) {
		if ( ! $version_from || ! $version_to ) {
			return new WP_Error( 'cleanup-error-wp-version', 'Failed to find WordPress version. Please cleanup files manually.' );
		}

		set_error_handler( [ UptimeMonsterCoreUpgrader::class, 'error_handler' ], E_USER_WARNING | E_USER_NOTICE );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		$old_checksums = get_core_checksums( $version_from, $locale );
		$new_checksums = get_core_checksums( $version_to, $locale );
		restore_error_handler();

		if ( ! is_array( $old_checksums ) || ! is_array( $new_checksums ) ) {
			return new WP_Error( 'cleanup-error-old-checksum', 'WordPress core update failed. Please cleanup files manually.' );
		}

		// phpcs:disable
		// Compare the files from the old version and the new version in a case-insensitive manner,
		// to prevent files being incorrectly deleted on systems with case-insensitive filesystems
		// when core changes the case of filenames.
		// The main logic for this was taken from the Joomla project and adapted for WP.
		// See: https://github.com/joomla/joomla-cms/blob/bb5368c7ef9c20270e6e9fcc4b364cd0849082a5/administrator/components/com_admin/script.php#L8158
		// phpcs:enable

		$old_file_paths = array_keys( $old_checksums );
		$new_file_paths = array_keys( $new_checksums );

		$new_file_paths = array_combine( array_map( 'strtolower', $new_file_paths ), $new_file_paths );

		$old_file_paths_to_check = array_diff( $old_file_paths, $new_file_paths );

		// Initialize file system.
		$fs = uptimemonster_get_file_systeam();

		foreach ( $old_file_paths_to_check as $old_filepath_to_check ) {
			$old_realpath = realpath( ABSPATH . $old_filepath_to_check );

			// On Unix without incorrectly cased file.
			if ( false === $old_realpath ) {
				continue;
			}

			$lowercase_old_filepath_to_check = strtolower( $old_filepath_to_check );

			if ( ! array_key_exists( $lowercase_old_filepath_to_check, $new_file_paths ) ) {
				$files_to_remove[] = $old_filepath_to_check;
				continue;
			}

			// We are now left with only the files that are similar from old to new except for their case.

			$old_basename      = basename( $old_realpath );
			$new_filepath      = $new_file_paths[ $lowercase_old_filepath_to_check ];
			$expected_basename = basename( $new_filepath );
			$new_realpath      = realpath( ABSPATH . $new_filepath );
			$new_basename      = basename( $new_realpath );

			// On Windows or Unix with only the incorrectly cased file.
			if ( $new_basename !== $expected_basename ) {
				$fs->move( ABSPATH . $old_filepath_to_check, ABSPATH . $old_filepath_to_check . '.tmp', true );
				$fs->move( ABSPATH . $old_filepath_to_check . '.tmp', ABSPATH . $new_filepath, true );

				continue;
			}

			// There might still be an incorrectly cased file on other OS than Windows.
			if ( basename( $old_filepath_to_check ) === $old_basename ) {
				// Check if case-insensitive file system, eg on OSX.
				if ( fileinode( $old_realpath ) === fileinode( $new_realpath ) ) {
					// Check deeper because even realpath or glob might not return the actual case.
					if ( ! in_array( $expected_basename, scandir( dirname( $new_realpath ) ), true ) ) {
						$fs->move( ABSPATH . $old_filepath_to_check, ABSPATH . $old_filepath_to_check . '.tmp', true );
						$fs->move( ABSPATH . $old_filepath_to_check . '.tmp', ABSPATH . $new_filepath, true );
					}
				} else {
					// On Unix with both files: Delete the incorrectly cased file.
					$files_to_remove[] = $old_filepath_to_check;
				}
			}
		}

		$count = 0;

		if ( ! empty( $files_to_remove ) ) {
			foreach ( $files_to_remove as $file ) {

				// wp-content should be considered user data.
				if ( 'wp-content' === substr( $file, 0, 10 ) ) {
					continue;
				}

				if ( file_exists( ABSPATH . $file ) ) {
					wp_delete_file( ABSPATH . $file );
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Gets download url based on version, locale and desired file type.
	 * only base version supports tar.gz archive format.
	 *
	 * @param $version
	 * @param string $locale
	 *
	 * @return string
	 */
	private function get_download_url( $version = null, $locale = 'en_US' ): string {
		if ( 'nightly' === $version ) {
			return 'https://wordpress.org/nightly-builds/wordpress-latest.zip';
		}

		$locale_subdomain = 'en_US' === $locale ? '' : substr( $locale, 0, 2 ) . '.';
		$locale_suffix    = 'en_US' === $locale ? '' : "-{$locale}";
		$download_url     = "https://{$locale_subdomain}wordpress.org/";

		if ( ! $version ) {
			return $download_url . 'latest' . $locale_suffix . '.zip';
		}

		return $download_url . 'wordpress-' . $version . $locale_suffix . '.zip';
	}

	/**
	 * Gets version information from `wp-includes/version.php`.
	 *
	 * @return ?string
	 */
	private static function get_wp_details() {
		// Load file systeam.
		$fs = uptimemonster_get_file_systeam();

		$versions_path = ABSPATH . 'wp-includes/version.php';

		if ( ! $fs->is_readable( $versions_path ) ) {
			return null;
		}

		$version_content = $fs->get_contents( $versions_path );

		return self::find_var( 'wp_version', $version_content );
	}

	/**
	 * Searches for the value assigned to variable `$var_name` in PHP code `$code`.
	 *
	 * This is equivalent to matching the `\$VAR_NAME = ([^;]+)` regular expression and returning
	 * the first match either as a `string` or as an `integer` (depending if it's surrounded by
	 * quotes or not).
	 *
	 * @param string $var_name Variable name to search for.
	 * @param string $code PHP code to search in.
	 *
	 * @return string|null
	 */
	private static function find_var( $var_name, $code ) {
		$start = strpos( $code, '$' . $var_name . ' = ' );

		if ( ! $start ) {
			return null;
		}

		$start = $start + strlen( $var_name ) + 3;
		$end   = strpos( $code, ';', $start );

		$value = substr( $code, $start, $end - $start );

		return trim( $value, " '" );
	}

	/**
	 * Convert a WP_Error or Exception into a string
	 *
	 * @param string|WP_Error|Exception| $errors
	 *
	 * @return string
	 * @throws InvalidArgumentException
	 */
	public static function error_to_string( $errors ) {
		if ( is_string( $errors ) ) {
			return $errors;
		}

		// Only json_encode() the data when it needs it
		$render_data = function ( $data ) {
			if ( is_array( $data ) || is_object( $data ) ) {
				return wp_json_encode( $data );
			}

			return '"' . $data . '"';
		};

		if ( $errors instanceof WP_Error ) {
			foreach ( $errors->get_error_messages() as $message ) {
				if ( $errors->get_error_data() ) {
					return $message . ' ' . $render_data( $errors->get_error_data() );
				}

				return $message;
			}
		}

		// PHP 7+: internal and user exceptions must implement Throwable interface.
		// PHP 5: internal and user exceptions must extend Exception class.
		// if ( interface_exists( 'Throwable' ) && ( $errors instanceof \Throwable ) || ( $errors instanceof \Exception ) ) {
		if ( $errors instanceof Exception ) {
			return get_class( $errors ) . ': ' . $errors->getMessage();
		}

		throw new InvalidArgumentException(
			sprintf(
				/* translators: %s: Argument type string. */
				esc_html__( 'Unsupported argument type passed. Argument 1 must be one of string, WP_Error, Exception (or any Throwable), %s given.', 'uptimemonster-site-monitor' ),
				esc_html( gettype( $errors ) )
			)
		);
	}
}

// End of file Core_Update.php
