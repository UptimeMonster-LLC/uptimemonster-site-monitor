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
			'plugin_action_links',
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

		// Disconnect api.
		if ( isset( $_GET['action'], $_GET['_wpnonce'] ) && 'disconnect-api' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( wp_verify_nonce( $_GET['_wpnonce'], 'roxwp-disconnect-api' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

				$client = RoxWP_Client::get_instance();
				if ( $client->has_keys() ) {
					$client->send_log( [
						'action'    => 'disconnect_api',
						'activity'  => 'Self_DeActivation',
						'subtype'   => 'monitor',
						'object_id' => null,
						'name'      => 'Site Monitor Deactivated',
						'timestamp' => roxwp_get_current_time(),
						'actor'     => roxwp_get_current_actor(),
						'extra'     => [],
					] );
				}



				$cache_key = 'roxwp-is-connected';
				delete_transient( $cache_key );

				update_option( 'roxwp_site_monitor_api_keys', [] );

				wp_safe_redirect( $this->get_page_url() );
				die();
			}
		}

		if ( isset( $_POST['rwp-disconnect-api'] ) ) {
			check_admin_referer( 'roxwp-site-monitor-settings' );

			do_action( 'roxwp_site_monitor_api_deactivating' );

			$this->is_connected( false );
			delete_option( 'roxwp_site_monitor_api_keys' );
			$this->add_settings_status( __( 'RoxWP API Disconnected.', 'roxwp-site-mon' ), 'warning' );

			do_action( 'roxwp_site_monitor_api_deactivated' );

			wp_safe_redirect( $this->get_page_url() );
			die();
		}

		if ( isset( $_POST['rwp-settings-save'], $_POST['roxwp'] ) && is_array( $_POST['roxwp'] ) && ! empty( $_POST['roxwp'] ) ) {

			check_admin_referer( 'roxwp-site-monitor-settings' );

			$api_key    = isset( $_POST['roxwp']['api_key'] ) ? sanitize_text_field( $_POST['roxwp']['api_key'] ) : '';
			$api_secret = isset( $_POST['roxwp']['api_secret'] ) ? sanitize_text_field( $_POST['roxwp']['api_secret'] ) : '';

			if ( $api_key && $api_secret ) {
				$client = RoxWP_Client::get_instance();
				$client->set_api_key( $api_key );
				$client->set_api_secret( $api_secret );
				$response = $client->ping();

				if ( ! is_wp_error( $response ) ) {

					$new_keys   = [
						'api_key'    => $api_key,
						'api_secret' => $api_secret,
					];

					$this->is_connected( false );

					update_option( 'roxwp_site_monitor_api_keys', $new_keys );

					$this->add_settings_status( __( 'Api connected.', 'roxwp-site-mon' ) );

					do_action( 'roxwp_site_monitor_api_updated' );
				} else {
					$this->add_settings_status( sprintf(
						/* translators: 1. Error Details. */
						__( 'Error connecting RoxWP API with following error: %s.', 'roxwp-site-mon' ),
						$response->get_error_message()
					), 'error' );
				}
			} else {
				$this->add_settings_status( __( 'Both api key & secret required.', 'roxwp-site-mon' ), 'error' );
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
		$install_url = wp_nonce_url(
			add_query_arg( [ 'action' => 'install-drop-in' ], $this->get_page_url() ),
			'roxwp-install-drop-in'
		);

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<i class="dashicons dashicons-superhero" style="font-size:1.5em;display:inline-block;width:30px;height:30px;margin:-1px 10px 0 0;"></i>
				<?php echo esc_html( get_admin_page_title() ); ?>
			</h1>
			<hr class="wp-header-end">
			<form method="post" action="<?php echo esc_url( $this->get_page_url() ); ?>">
				<?php wp_nonce_field( 'roxwp-site-monitor-settings' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
					<?php
					if( $this->is_connected() ) {
						?>
						<tr>
							<th scope="row">
								<label for="roxwp-api-status"><?php esc_html_e( 'Status', 'roxwp-site-mon' ); ?></label>
							</th>
							<td>
								<div style="display:flex;align-items:center;gap:10px;">
									<?php esc_html_e( 'Connected', 'roxwp-site-mon' ); ?>
									<button class="button button-secondary button-warning" type="submit" name="rwp-disconnect-api" value="1"><?php esc_html_e( 'Disconnect', 'roxwp-site-mon' ); ?></button>
								</div>
							</td>
						</tr>
						<?php
					}else{
						?>
						<tr>
							<th scope="row"><label for="roxwp-api-key"><?php esc_html_e( 'Api Key', 'roxwp-site-mon' ); ?></label></th>
							<td><input name="roxwp[api_key]" type="text" id="roxwp-api-key" value="" class="regular-text" autocomplete="off"></td>
						</tr>
						<tr>
							<th scope="row"><label for="roxwp-api-secret"><?php esc_html_e( 'Api Secret', 'roxwp-site-mon' ); ?></label></th>
							<td><input name="roxwp[api_secret]" type="password" id="roxwp-api-secret" value="" class="regular-text" autocomplete="off"></td>
						</tr>
						<?php
					}
					?>
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
				<?php
				if( ! $this->is_connected() ) {
					submit_button( __( 'Save Changes', 'roxwp-site-mon' ), 'primary', 'rwp-settings-save' );
				}
				?>
			</form>
		</div>
		<?php
	}
}

// End of file Dashboard.php.
