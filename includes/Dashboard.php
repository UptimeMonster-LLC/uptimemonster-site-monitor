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

use AbsolutePlugins\RoxwpSiteMonitor\Monitors\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Dashboard {

	use Singleton;

	protected function __construct() {
		add_action( 'admin_notices', [ $this, 'admin_notice' ] );
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_init', [ $this, 'handle_post' ] );

		add_filter( 'plugin_action_links_' . ROXWP_SM_PLUGIN_BASENAME, [  // @phpstan-ignore-line
			$this,
			'plugin_action_links'
		] );
	}

	public function admin_notice() {
		$data = get_transient( 'rwp-setting-status' );
		delete_transient( 'rwp-setting-status' );

		if ( $data ) {
			foreach ( $data as $item ) {
				list( $message, $type ) = $item;
				?>
				<div class="notice notice-<?php echo esc_attr( $type ); ?>">
					<p><?php echo esc_html( $message ); ?></p>
				</div>
				<?php
			}
		}
	}

	public function plugin_action_links( $links ) {
		$links[] = sprintf( '<a href="%s">%s</a>', $this->get_page_url(), esc_html__( 'Settings', 'roxwp-site-mon' ) );

		return $links;
	}

	public function register_menu() {
		add_options_page(
			__( 'RoxWP API Settings', 'roxwp-site-mon' ),
			__( 'RoxWP Settings', 'roxwp-site-mon' ),
			'manage_options',
			'roxwp-settings',
			[ $this, 'settings_page' ]
		);
	}

	protected function add_settings_status( $message, $type = 'success' ) {
		$data = get_transient( 'rwp-setting-status' );
		if ( ! $data ) {
			$data = [];
		}
		$hash          = md5( $message . $type );
		$data[ $hash ] = [ $message, $type ];

		set_transient( 'rwp-setting-status', $data, 60 );
	}

	protected $loaded_keys;

	protected function get_api_keys() {
		if ( null === $this->loaded_keys ) {
			$api_keys = get_option( 'roxwp_site_monitor_api_keys', [] );

			if ( isset( $api_keys['api_key'], $api_keys['api_secret'] ) ) {
				$this->loaded_keys = $api_keys;
			}
		}

		return $this->loaded_keys;
	}

	public function get_page_url() {
		return admin_url( 'options-general.php?page=roxwp-settings' );
	}

	protected function installed_on() {
		return get_option( 'roxwp_first_installed' );
	}

	public function handle_post() {
		if ( 'yes' === get_option( 'roxwp_need_setup' ) ) {
			if ( empty( $this->installed_on() ) ) {
				$this->add_settings_status( __( 'Thank you for installing RoxWP Site Monitor.', 'roxwp-site-mon' ) );
				$this->add_settings_status( __( 'Please update the api keys to activate it properly.', 'roxwp-site-mon' ) );
			}

			$this->add_settings_status( __( 'Please update the api keys to activate it properly.', 'roxwp-site-mon' ) );

			update_option( 'roxwp_need_setup', 'no' );

			wp_safe_redirect( $this->get_page_url() );
			die();
		}

		if ( isset( $_GET['action'], $_GET['_wpnonce'] ) && 'install-drop-in' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( wp_verify_nonce( $_GET['_wpnonce'], 'roxwp-install-drop-in' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				RoxWP_Site_Monitor::maybe_install_drop_in();

				$this->add_settings_status( __( 'Error Logger Drop-In Updated.', 'roxwp-site-mon' ), 'success' );

				wp_safe_redirect( $this->get_page_url() );
				die();
			}
		}
		if ( isset( $_POST['rwp-settings-save'], $_POST['roxwp'] ) && is_array( $_POST['roxwp'] ) && ! empty( $_POST['roxwp'] ) ) {
			check_admin_referer( 'roxwp-site-monitor-settings' );

			$this->get_api_keys();

			$api_key    = isset( $_POST['roxwp']['api_key'] ) ? sanitize_text_field( $_POST['roxwp']['api_key'] ) : '';
			$api_secret = isset( $_POST['roxwp']['api_secret'] ) ? sanitize_text_field( $_POST['roxwp']['api_secret'] ) : '';
			$new_keys   = [
				'api_key'    => $api_key,
				'api_secret' => $api_secret,
			];

			if ( $api_key && ! $api_secret || $api_secret && ! $api_key ) {
				$this->add_settings_status( __( 'Both api key & secret required.', 'roxwp-site-mon' ), 'error' );
			} else {
				if ( $api_key && $api_secret ) {
					if ( $new_keys === $this->loaded_keys ) {
						$this->add_settings_status( __( 'Nothing Changed.', 'roxwp-site-mon' ), 'warning' );
					} else {
						$client = RoxWP_Client::get_instance();
						$client->set_api_key( $api_key );
						$client->set_api_secret( $api_secret );
						$response = $client->ping();

						if ( ! is_wp_error( $response ) ) {
							if ( empty( $this->loaded_keys ) ) {
								$message = __( 'Api connected.', 'roxwp-site-mon' );
							} else {
								$message = __( 'Api keys updated.', 'roxwp-site-mon' );
							}

							$this->is_connected( false );

							$this->loaded_keys = $new_keys;

							update_option( 'roxwp_site_monitor_api_keys', $new_keys );

							$this->add_settings_status( $message );

							do_action( 'roxwp_site_monitor_api_updated' );
						} else {
							if ( empty( $this->loaded_keys ) ) {
								/* translators: 1. Error Details. */
								$message = __( 'Error connecting to RoxWP Server with following error: %s.', 'roxwp-site-mon' );
							} else {
								/* translators: 1. Error Details. */
								$message = __( 'Error updating api keys with following error: %s.', 'roxwp-site-mon' );
							}

							$this->add_settings_status( sprintf( $message, $response->get_error_message() ), 'error' );
						}
					}
				} else {
					if ( empty( $this->loaded_keys ) ) {
						$this->add_settings_status( __( 'Nothing Changed.', 'roxwp-site-mon' ), 'warning' );
					} else {
						do_action( 'roxwp_site_monitor_api_deactivating' );
						$this->loaded_keys = null;
						$this->is_connected( false );
						delete_option( 'roxwp_site_monitor_api_keys' );
						$this->add_settings_status( __( 'Api keys removed.', 'roxwp-site-mon' ), 'warning' );

						do_action( 'roxwp_site_monitor_api_deactivated' );
					}
				}
			}
			wp_safe_redirect( $this->get_page_url() );
			die();
		}
	}

	protected function is_connected( $cached = true ) {
		$client = RoxWP_Client::get_instance();
		if ( ! $client->has_keys() ) {
			return false;
		}

		$cache_key = 'roxwp-is-connected';

		if ( ! $cached ) {
			delete_transient( $cache_key );
			$is_connected = false;
		} else {
			$is_connected = get_transient( $cache_key );
		}

		if ( ! $is_connected ) {
			$response     = $client->ping();
			$is_connected = ( ! is_wp_error( $response ) && 'pong' === $response ) ? 'yes' : 'no';

			set_transient( $cache_key, $is_connected, 5 * MINUTE_IN_SECONDS );
		}

		return 'yes' === $is_connected;
	}

	public function settings_page() {
		$keys        = $this->get_api_keys();
		$api_key     = isset( $keys['api_key'] ) ? $keys['api_key'] : '';
		$api_secret  = isset( $keys['api_secret'] ) ? $keys['api_secret'] : '';
		$install_url = wp_nonce_url(
			add_query_arg( [ 'action' => 'install-drop-in' ], $this->get_page_url() ),
			'roxwp-install-drop-in'
		);
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<i class="dashicons dashicons-superhero"
				   style="font-size:1.5em;display:inline-block;width:30px;height:30px;margin:-1px 10px 0 0;"></i>
				<?php echo esc_html( get_admin_page_title() ); ?>
			</h1>
			<hr class="wp-header-end">
			<form method="post" action="<?php echo esc_url( $this->get_page_url() ); ?>">
				<?php wp_nonce_field( 'roxwp-site-monitor-settings' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row">
							<label for="roxwp-api-key"><?php esc_html_e( 'Api Key', 'roxwp-site-mon' ); ?></label>
						</th>
						<td>
							<input name="roxwp[api_key]" type="text" id="roxwp-api-key"
								   value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off">
							<i class="dashicons dashicons-<?php echo $this->is_connected() ? 'yes-alt' : 'warning'; ?>"
							   style="position: relative;color:<?php echo $this->is_connected() ? '#067815' : '#a39622'; ?>;font-size: 1.7em;display:inline-block;width:30px;height:30px;margin:0 10px 0 0;top:3px;"></i>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="roxwp-api-secret"><?php esc_html_e( 'Api Secret', 'roxwp-site-mon' ); ?></label>
						</th>
						<td>
							<input name="roxwp[api_secret]" type="password" id="roxwp-api-secret"
								   value="<?php echo esc_attr( $api_secret ); ?>" class="regular-text"
								   autocomplete="off">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Error Logging Drop-in', 'roxwp-site-mon' ); ?></label>
						</th>
						<td>
							<table>
								<tbody>
								<tr>
									<th scope="row" style="padding:0;">
										<strong><?php esc_html_e( 'Status:', 'roxwp-site-mon' ); ?></strong></th>
									<td style="padding:0;">
										<?php

										if ( RoxWP_Site_Monitor::is_drop_in_installed() ) {
											if ( RoxWP_Site_Monitor::drop_in_need_update() ) {
												printf(
												/* translators: 1. New Version Number, 2. Update URL */
													__( 'A newer version (Version %1$s) of the drop-in available. Click <a href="%2$s">here</a> to update.', 'roxwp-site-mon' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
													esc_html( RoxWP_Site_Monitor::drop_in_version( false ) ),
													esc_url( $install_url )
												);
											} else {
												printf(
												/* translators: 1. Error handler drop-in version. */
													esc_html__( 'Installed (Version %s)', 'roxwp-site-mon' ),
													esc_html( RoxWP_Site_Monitor::drop_in_version() )
												);
											}
										} else {
											?>
											<p class="help">
												<?php
												if ( RoxWP_Site_Monitor::is_wp_content_writable() ) {
													printf(
													/* translators: 1. Installation URL */
														__( 'Click <a href="%s">here</a> to install the drop-in.', 'roxwp-site-mon' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
														esc_url( $install_url )
													);
												} else {
													printf(
													/* translators: 1. Source file path. 2. Destination file path. */
														__( 'Please copy <code>%1$s</code> into <code>%2$s</code> for enabling error monitoring', 'roxwp-site-mon' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
														esc_html( RoxWP_Site_Monitor::get_drop_in_dist_file() ),
														esc_html( RoxWP_Site_Monitor::get_drop_in_file() )
													);
												}
												?>
											</p>
											<?php
										}
										?>
									</td>
								</tr>
								<tr>
									<th scope="row" style="padding:0;">
										<strong><?php esc_html_e( 'WP Content Directory', 'roxwp-site-mon' ); ?></strong>
									</th>
									<td style="padding:0;">
										<?php
										if ( RoxWP_Site_Monitor::is_wp_content_writable() ) {
											esc_html_e( 'Writable', 'roxwp-site-mon' );
										} else {
											esc_html_e( 'Not Writable', 'roxwp-site-mon' );
										}
										?>
									</td>
								</tr>
								<?php if ( RoxWP_Site_Monitor::is_drop_in_installed() ) { ?>
									<tr>
										<th scope="row" style="padding:0;">
											<strong><?php esc_html_e( 'Drop-In', 'roxwp-site-mon' ); ?></strong></th>
										<td style="padding:0;">
											<?php
											if ( RoxWP_Site_Monitor::is_drop_in_writable() ) {
												esc_html_e( 'Writable', 'roxwp-site-mon' );
											} else {
												esc_html_e( 'Not Writable', 'roxwp-site-mon' );
											}
											?>
										</td>
									</tr>
								<?php } ?>
								</tbody>
							</table>
						</td>
					</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Save Changes', 'roxwp-site-mon' ), 'primary', 'rwp-settings-save' ); ?>
			</form>
		</div>
		<?php
	}
}

// End of file Dashboard.php.
