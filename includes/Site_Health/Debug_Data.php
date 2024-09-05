<?php
/**
 * WP Debug Data.
 *
 * @package UptimeMonster\SiteMonitor\API
 * @version 1.0.0
 */

namespace UptimeMonster\SiteMonitor\Site_Health;

use Exception;
use Imagick;
use ImagickException;
use WP_Debug_Data;
use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

// /** Load WordPress Administration APIs */
//require_once ABSPATH . 'wp-admin/includes/admin.php';

if ( ! function_exists( 'got_mod_rewrite' ) ) {
	require_once ABSPATH . 'wp-admin/includes/misc.php';
}

if ( ! function_exists( 'get_core_updates' ) ) {
	require_once ABSPATH . 'wp-admin/includes/update.php';
}

if ( ! class_exists( '\WP_Debug_Data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
}

class Debug_Data extends WP_Debug_Data {

	/**
	 * @param array $info
	 *
	 * @return void
	 * @see get_core_updates()
	 */
	protected static function process_core_update_status( &$info ) {
		$fields = $info['wp-core']['fields'];

		unset( $info['wp-core']['fields'], $fields['version'] );

		$core_version = get_bloginfo( 'version' );
		$from_api     = get_site_transient( 'update_core' );

		$info['wp-core']['version']        = $core_version;
		$info['wp-core']['latest_version'] = $core_version;
		$info['wp-core']['update']         = false;
		$info['wp-core']['fields']         = $fields;

		if ( ! empty( $from_api->updates ) && is_array( $from_api->updates ) ) {
			foreach ( $from_api->updates as $update ) {
				if ( 'upgrade' === $update->response ) {
					$info['wp-core']['latest_version'] = $update->version;
					$info['wp-core']['update']         = version_compare( $core_version, $update->version, '<' );
					break;
				}
			}
		}

		unset( $fields );
	}

	/**
	 * @param array $info
	 *
	 * @return void
	 */
	protected static function process_dropins_data( &$info ) {
		$dropins = get_dropins();
		// Get core dropins descriptions.
		$_dropin = _get_dropins();

		// Remove fields data from wp-debug-data.
		$info['wp-dropins']['fields'] = [];
		foreach ( $dropins as $dropin_key => $dropin ) {
			$value = uptimemonster_prepare_plugin_data( $dropin );
			// Keep core description.
			$value['description'] = trim( sprintf(
			/* translators: 1. Dropin description from WP core (_get_dropins()) 2. Dropin description from plugin header. */
				_x( '%1$s %2$s', 'Concatenating dropin (WP core) description with plugin provided description (header comment).', 'uptimemonster-site-monitor' ),
				$_dropin[ $dropin_key ][0],
				$value['description']
			) );
			// Assign field value.
			$info['wp-dropins']['fields'][] = [
				'label' => $dropin['Name'],
				'slug'  => sanitize_text_field( $dropin_key ),
				'value' => $value,
			];
		}
	}

	/**
	 * @param array $info
	 *
	 * @return void
	 */
	protected static function process_mu_plugins_data( &$info ) {
		$mu_plugins = get_mu_plugins();
		// Remove fields data from wp-debug-data.
		$info['wp-mu-plugins']['fields'] = [];
		foreach ( $mu_plugins as $plugin_path => $plugin ) {
			$info['wp-mu-plugins']['fields'][] = [
				'label' => $plugin['Name'],
				'slug'  => $plugin_path,
				'value' => uptimemonster_prepare_plugin_data( $plugin ),
			];
		}
	}

	/**
	 * @param array $info
	 *
	 * @return void
	 */
	protected static function process_plugins_data( &$info ) {
		// Reset fields.
		$info['wp-plugins-active']['fields']   = [];
		$info['wp-plugins-inactive']['fields'] = [];

		// List all available plugins.
		$plugins        = get_plugins();
		$plugin_updates = get_plugin_updates();
		$transient      = get_site_transient( 'update_plugins' );
		$auto_updates   = [];

		$auto_updates_enabled = wp_is_auto_update_enabled_for_type( 'plugin' );

		if ( $auto_updates_enabled ) {
			$auto_updates = (array) get_site_option( 'auto_update_plugins', [] );
		}

		foreach ( $plugins as $plugin_path => $plugin ) {
			$plugin_part = ( is_plugin_active( $plugin_path ) ) ? 'wp-plugins-active' : 'wp-plugins-inactive';
			$plugin_data = uptimemonster_prepare_plugin_data( $plugin );

			if ( array_key_exists( $plugin_path, $plugin_updates ) ) {
				/* translators: %s: Latest plugin version number. */
				$plugin_data['new_version'] = $plugin_updates[ $plugin_path ]->update->new_version;

				if ( version_compare( $plugin['Version'], $plugin_updates[ $plugin_path ]->update->new_version, '<' ) ) {
					$plugin_data['need_update'] = true;
				}
			}

			if ( $auto_updates_enabled ) {
				if ( isset( $transient->response[ $plugin_path ] ) ) {
					$item = $transient->response[ $plugin_path ];
				} elseif ( isset( $transient->no_update[ $plugin_path ] ) ) {
					$item = $transient->no_update[ $plugin_path ];
				} else {
					$item = [
						'id'            => $plugin_path,
						'slug'          => '',
						'plugin'        => $plugin_path,
						'new_version'   => '',
						'url'           => '',
						'package'       => '',
						'icons'         => [],
						'banners'       => [],
						'banners_rtl'   => [],
						'tested'        => '',
						'requires_php'  => '',
						'compatibility' => new \stdClass(),
					];
					$item = wp_parse_args( $plugin, $item );
				}

				$auto_update_forced = wp_is_auto_update_forced_for_item( 'plugin', null, (object) $item );

				if ( ! is_null( $auto_update_forced ) ) {
					$enabled = $auto_update_forced;
				} else {
					$enabled = in_array( $plugin_path, $auto_updates, true );
				}

				if ( $enabled ) {
					$plugin_data['auto_update'] = 'enabled';
				}
			}

			$plugin_data['is_uninstallable'] = is_uninstallable_plugin( $plugin_path );

			$info[ $plugin_part ]['fields'][] = [
				'label' => $plugin['Name'],
				'slug'  => $plugin_path,
				'value' => $plugin_data,
			];
		}
	}

	/**
	 * @param array $info
	 *
	 * @return void
	 */
	protected static function process_themes_data( &$info ) {
		$info['wp-active-theme']    = [];
		$info['wp-parent-theme']    = [];
		$info['wp-themes-inactive'] = [];

		// Populate the section for the currently active theme.
		global $_wp_theme_features;
		$theme_features = [];

		if ( ! empty( $_wp_theme_features ) ) {
			foreach ( $_wp_theme_features as $feature => $options ) {
				$theme_features[] = $feature;
			}
		}

		$active_theme  = wp_get_theme();
		$theme_updates = get_theme_updates();
		$transient     = get_site_transient( 'update_themes' );

		$auto_updates         = [];
		$auto_updates_enabled = wp_is_auto_update_enabled_for_type( 'theme' );
		if ( $auto_updates_enabled ) {
			$auto_updates = (array) get_site_option( 'auto_update_themes', [] );
		}

		$need_to_update_theme     = false;
		$theme_update_new_version = null;
		if ( array_key_exists( $active_theme->stylesheet, $theme_updates ) ) {
			$theme_update_new_version = $theme_updates[ $active_theme->stylesheet ]->update['new_version'];
			if ( version_compare( $active_theme->version, $theme_update_new_version, '<' ) ) {
				$need_to_update_theme = true;
			}
		}

		$parent_theme = null;

		if ( $active_theme->parent_theme ) {
			$parent_theme = [
				'name' => $active_theme->parent_theme,
				'slug' => $active_theme->get_template(),
			];
		}

		$info['wp-active-theme'] = [
			'name'           => $active_theme->name,
			'slug'           => $active_theme->stylesheet,
			'version'        => $active_theme->version,
			'latest_version' => $theme_update_new_version,
			'update'         => $need_to_update_theme,
			'author'         => wp_kses( $active_theme->author, [] ),
			'author_website' => $active_theme->display( 'AuthorURI' ),
			'parent_theme'   => $parent_theme,
			'theme_features' => $theme_features,
			'theme_path'     => get_stylesheet_directory(),
			'auto_update'    => 'disabled',
		];

		if ( $auto_updates_enabled ) {
			if ( isset( $transient->response[ $active_theme->stylesheet ] ) ) {
				$item = $transient->response[ $active_theme->stylesheet ];
			} elseif ( isset( $transient->no_update[ $active_theme->stylesheet ] ) ) {
				$item = $transient->no_update[ $active_theme->stylesheet ];
			} else {
				$item = [
					'theme'        => $active_theme->stylesheet,
					'new_version'  => $active_theme->version,
					'url'          => '',
					'package'      => '',
					'requires'     => '',
					'requires_php' => '',
				];
			}
			$auto_update_forced = wp_is_auto_update_forced_for_item( 'theme', null, (object) $item );

			if ( ! is_null( $auto_update_forced ) ) {
				$enabled = $auto_update_forced;
			} else {
				$enabled = in_array( $active_theme->stylesheet, $auto_updates, true );
			}

			if ( $enabled ) {
				$info['wp-active-theme']['auto_update'] = 'enabled';
			}
		}

		$parent_theme = $active_theme->parent();

		if ( $parent_theme ) {
			$need_to_update_parent_theme     = false;
			$parent_theme_update_new_version = null;
			if ( array_key_exists( $parent_theme->stylesheet, $theme_updates ) ) {
				$parent_theme_update_new_version = $theme_updates[ $parent_theme->stylesheet ]->update['new_version'];
				if ( version_compare( $parent_theme->version, $parent_theme_update_new_version, '<' ) ) {
					$need_to_update_parent_theme = true;
				}
			}


			$info['wp-parent-theme'] = [
				'slug'           => $parent_theme->stylesheet,
				'name'           => $parent_theme->name,
				'version'        => $parent_theme->version,
				'latest_version' => $parent_theme_update_new_version,
				'update'         => $need_to_update_parent_theme,
				'author'         => wp_kses( $parent_theme->author, [] ),
				'author_website' => $parent_theme->display( 'AuthorURI' ),
				'theme_features' => null,
				'theme_path'     => get_template_directory(),
				'auto_update'    => 'disabled',
			];

			if ( $auto_updates_enabled ) {
				if ( isset( $transient->response[ $parent_theme->stylesheet ] ) ) {
					$item = $transient->response[ $parent_theme->stylesheet ];
				} elseif ( isset( $transient->no_update[ $parent_theme->stylesheet ] ) ) {
					$item = $transient->no_update[ $parent_theme->stylesheet ];
				} else {
					$item = array(
						'theme'        => $parent_theme->stylesheet,
						'new_version'  => $parent_theme->version,
						'url'          => '',
						'package'      => '',
						'requires'     => '',
						'requires_php' => '',
					);
				}

				$auto_update_forced = wp_is_auto_update_forced_for_item( 'theme', null, (object) $item );

				if ( ! is_null( $auto_update_forced ) ) {
					$enabled = $auto_update_forced;
				} else {
					$enabled = in_array( $parent_theme->stylesheet, $auto_updates, true );
				}

				if ( $enabled ) {
					$info['wp-parent-theme']['auto_update'] = 'enabled';
				}
			}
		}

		// Populate a list of all themes available in the `wp-content/themes` directory.
		$all_themes = wp_get_themes();

		foreach ( $all_themes as $theme_slug => $theme ) {
			// Exclude the currently active theme from the list of all themes.
			if ( $active_theme->stylesheet === $theme_slug ) {
				continue;
			}

			// Exclude the currently active parent theme from the list of all themes.
			if ( ! empty( $parent_theme ) && $parent_theme->stylesheet === $theme_slug ) {
				continue;
			}

			$data = [
				'slug'           => $theme->stylesheet,
				'name'           => $theme->name,
				'version'        => $theme->version,
				'latest_version' => null,
				'update'         => false,
				'author'         => wp_kses( $theme->author, [] ),
				'author_website' => $theme->display( 'AuthorURI' ),
				'parent_theme'   => ! $theme->parent_theme ? null : [
					'name' => $theme->parent_theme,
					'slug' => $theme->get_template(),
				],
				'theme_features' => null,
				'theme_path'     => get_template_directory(),
				'auto_update'    => 'disabled',
			];

			if ( array_key_exists( $theme_slug, $theme_updates ) ) {
				$data['latest_version'] = $theme_updates[ $theme_slug ]->update['new_version'];
				if ( version_compare( $theme->version, $theme_updates[ $theme_slug ]->update['new_version'], '<' ) ) {
					$data['update'] = true;
				}
			}

			if ( $auto_updates_enabled ) {
				if ( isset( $transient->response[ $theme_slug ] ) ) {
					$item = $transient->response[ $theme_slug ];
				} elseif ( isset( $transient->no_update[ $theme_slug ] ) ) {
					$item = $transient->no_update[ $theme_slug ];
				} else {
					$item = [
						'theme'        => $theme_slug,
						'new_version'  => $theme->version,
						'url'          => '',
						'package'      => '',
						'requires'     => '',
						'requires_php' => '',
					];
				}

				$auto_update_forced = wp_is_auto_update_forced_for_item( 'theme', null, (object) $item );

				if ( ! is_null( $auto_update_forced ) ) {
					$enabled = $auto_update_forced;
				} else {
					$enabled = in_array( $theme_slug, $auto_updates, true );
				}

				if ( $enabled ) {
					$data['auto_update'] = 'enabled';
				}
			}

			$info['wp-themes-inactive'][] = $data;
		}
	}

	/**
	 * @param array $info
	 *
	 * @return void
	 */
	protected static function update_path_and_sizes( &$info ) {
		$sizes = self::get_sizes();
		foreach ( $info['wp-paths-sizes']['fields'] as $key => &$data ) {
			if ( ! empty( $sizes[ $key ] ) ) {
				$data['path']  = $sizes[ $key ]['path'] ?? null;
				$data['raw']   = $sizes[ $key ]['raw'] ?? null;
				$data['value'] = $sizes[ $key ]['size'];
				$data['debug'] = $sizes[ $key ]['debug'];
			}
		}
	}

	/**
	 * Static function for generating site debug data when required.
	 *
	 * @return array<string, array> The debug data for the site.
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public static function get_report(): array {
		try {
			$info = parent::debug_data();
			// WP Core Update.
			self::process_core_update_status( $info );
			// Dropins data.
			self::process_dropins_data( $info );
			// MustUse plugins data.
			self::process_mu_plugins_data( $info );
			// Installed plugins data.
			self::process_plugins_data( $info );
			// Installed themes data.
			self::process_themes_data( $info );
			// Update directory/path sizes.
			self::update_path_and_sizes( $info );
		} catch ( Exception $e ) {
			$info = [];
		}

		return [
			'version' => '1.0.0',
			'data'    => $info,
		];
	}
}

// End of file Debug_Data.php.
