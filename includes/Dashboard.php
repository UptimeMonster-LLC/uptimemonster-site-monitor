<?php
/**
 * Monitor Activity
 *
 * @package UptimeMonster\SiteMonitor
 * @author Name <email>
 * @version
 * @since
 * @license
 */

namespace UptimeMonster\SiteMonitor;

use UptimeMonster\SiteMonitor\Monitors\Singleton;

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

		add_filter( 'plugin_action_links_' . UMSM_PLUGIN_BASENAME, [ $this, 'plugin_action_links' ] );
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
		$links[] = sprintf( '<a href="%s">%s</a>', $this->get_page_url(), esc_html__( 'Settings', 'uptime' ) );

		return $links;
	}

	public function register_menu() {
		add_options_page(
			__( 'UptimeMonster API Settings', 'uptime' ),
			__( 'UptimeMonster Settings', 'uptime' ),
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
		return get_option( 'umsm_first_installed' );
	}

	public function handle_post() {
		if ( 'yes' === get_option( 'umsm_need_setup' ) ) {
			if ( empty( $this->installed_on() ) ) {
				$this->add_settings_status( __( 'Thank you for installing UptimeMonster Site Monitor.', 'uptime' ) );
				$this->add_settings_status( __( 'Please update the api keys to activate it properly.', 'uptime' ) );
			}

			$this->add_settings_status( __( 'Please update the api keys to activate it properly.', 'uptime' ) );

			update_option( 'umsm_need_setup', 'no' );

			wp_safe_redirect( $this->get_page_url() );
			die();
		}

		if ( isset( $_GET['action'], $_GET['_wpnonce'] ) && 'install-drop-in' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( wp_verify_nonce( $_GET['_wpnonce'], 'roxwp-install-drop-in' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				UptimeMonster_Site_Monitor::maybe_install_drop_in();

				$this->add_settings_status( __( 'Error Logger Drop-In Updated.', 'uptime' ), 'success' );

				wp_safe_redirect( $this->get_page_url() );
				die();
			}
		}

		// Disconnect api.
		if ( isset( $_GET['action'], $_GET['_wpnonce'] ) && 'disconnect-api' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( wp_verify_nonce( $_GET['_wpnonce'], 'roxwp-disconnect-api' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

				$client = UptimeMonster_Client::get_instance();
				if ( $client->has_keys() ) {
					$client->send_log( [
						'action'    => 'disconnect_api',
						'activity'  => 'Self_DeActivation',
						'subtype'   => 'monitor',
						'object_id' => null,
						'name'      => 'Site Monitor Deactivated',
						'timestamp' => umsm_get_current_time(),
						'actor'     => umsm_get_current_actor(),
						'extra'     => [],
					] );
				}



				$cache_key = 'roxwp-is-connected';
				delete_transient( $cache_key );

				update_option( 'umsm_site_monitor_api_keys', [] );

				wp_safe_redirect( $this->get_page_url() );
				die();
			}
		}

		if ( isset( $_POST['rwp-disconnect-api'] ) ) {
			check_admin_referer( 'uptimemonster-site-monitor-settings' );

			do_action( 'umsm_site_monitor_api_deactivating' );

			$this->is_connected( false );
			delete_option( 'umsm_site_monitor_api_keys' );
			$this->add_settings_status( __( 'UptimeMonster API Disconnected.', 'uptime' ), 'warning' );

			do_action( 'umsm_site_monitor_api_deactivated' );

			wp_safe_redirect( $this->get_page_url() );
			die();
		}

		if ( isset( $_POST['rwp-settings-save'], $_POST['roxwp'] ) && is_array( $_POST['roxwp'] ) && ! empty( $_POST['roxwp'] ) ) {

			check_admin_referer( 'uptimemonster-site-monitor-settings' );

			$api_key    = isset( $_POST['roxwp']['api_key'] ) ? sanitize_text_field( $_POST['roxwp']['api_key'] ) : '';
			$api_secret = isset( $_POST['roxwp']['api_secret'] ) ? sanitize_text_field( $_POST['roxwp']['api_secret'] ) : '';

			if ( $api_key && $api_secret ) {
				$client = UptimeMonster_Client::get_instance();
				$client->set_api_key( $api_key );
				$client->set_api_secret( $api_secret );
				$response = $client->ping();

				if ( ! is_wp_error( $response ) ) {

					$new_keys   = [
						'api_key'    => $api_key,
						'api_secret' => $api_secret,
					];

					$this->is_connected( false );

					update_option( 'umsm_site_monitor_api_keys', $new_keys );

					$this->add_settings_status( __( 'Api connected.', 'uptime' ) );

					do_action( 'umsm_site_monitor_api_updated' );
				} else {
					$this->add_settings_status( sprintf(
						/* translators: 1. Error Details. */
						__( 'Error connecting UptimeMonster API with following error: %s.', 'uptime' ),
						$response->get_error_message()
					), 'error' );
				}
			} else {
				$this->add_settings_status( __( 'Both api key & secret required.', 'uptime' ), 'error' );
			}

			wp_safe_redirect( $this->get_page_url() );
			die();
		}
	}

	protected function is_connected( $cached = true ) {
		$client = UptimeMonster_Client::get_instance();
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
				<?php wp_nonce_field( 'uptimemonster-site-monitor-settings' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
					<?php
					if( $this->is_connected() ) {
						?>
						<tr>
							<th scope="row">
								<label for="roxwp-api-status"><?php esc_html_e( 'Status', 'uptime' ); ?></label>
							</th>
							<td>
								<div style="display:flex;align-items:center;gap:10px;">
									<?php esc_html_e( 'Connected', 'uptime' ); ?>
									<button class="button button-secondary button-warning" type="submit" name="rwp-disconnect-api" value="1"><?php esc_html_e( 'Disconnect', 'uptime' ); ?></button>
								</div>
							</td>
						</tr>
						<?php
					}else{
						?>
						<tr>
							<th scope="row"><label for="roxwp-api-key"><?php esc_html_e( 'Api Key', 'uptime' ); ?></label></th>
							<td><input name="roxwp[api_key]" type="text" id="roxwp-api-key" value="" class="regular-text" autocomplete="off"></td>
						</tr>
						<tr>
							<th scope="row"><label for="roxwp-api-secret"><?php esc_html_e( 'Api Secret', 'uptime' ); ?></label></th>
							<td><input name="roxwp[api_secret]" type="password" id="roxwp-api-secret" value="" class="regular-text" autocomplete="off"></td>
						</tr>
						<?php
					}
					?>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Error Logging Drop-in', 'uptime' ); ?></label>
						</th>
						<td>
							<table>
								<tbody>
								<tr>
									<th scope="row" style="padding:0;">
										<strong><?php esc_html_e( 'Status:', 'uptime' ); ?></strong></th>
									<td style="padding:0;">
										<?php

										if ( UptimeMonster_Site_Monitor::is_drop_in_installed() ) {
											if ( UptimeMonster_Site_Monitor::drop_in_need_update() ) {
												printf(
												/* translators: 1. New Version Number, 2. Update URL */
													__( 'A newer version (Version %1$s) of the drop-in available. Click <a href="%2$s">here</a> to update.', 'uptime' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
													esc_html( UptimeMonster_Site_Monitor::drop_in_version( false ) ),
													esc_url( $install_url )
												);
											} else {
												printf(
												/* translators: 1. Error handler drop-in version. */
													esc_html__( 'Installed (Version %s)', 'uptime' ),
													esc_html( UptimeMonster_Site_Monitor::drop_in_version() )
												);
											}
										} else {
											?>
											<p class="help">
												<?php
												if ( UptimeMonster_Site_Monitor::is_wp_content_writable() ) {
													printf(
													/* translators: 1. Installation URL */
														__( 'Click <a href="%s">here</a> to install the drop-in.', 'uptime' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
														esc_url( $install_url )
													);
												} else {
													printf(
													/* translators: 1. Source file path. 2. Destination file path. */
														__( 'Please copy <code>%1$s</code> into <code>%2$s</code> for enabling error monitoring', 'uptime' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
														esc_html( UptimeMonster_Site_Monitor::get_drop_in_dist_file() ),
														esc_html( UptimeMonster_Site_Monitor::get_drop_in_file() )
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
										<strong><?php esc_html_e( 'WP Content Directory', 'uptime' ); ?></strong>
									</th>
									<td style="padding:0;">
										<?php
										if ( UptimeMonster_Site_Monitor::is_wp_content_writable() ) {
											esc_html_e( 'Writable', 'uptime' );
										} else {
											esc_html_e( 'Not Writable', 'uptime' );
										}
										?>
									</td>
								</tr>
								<?php if ( UptimeMonster_Site_Monitor::is_drop_in_installed() ) { ?>
									<tr>
										<th scope="row" style="padding:0;">
											<strong><?php esc_html_e( 'Drop-In', 'uptime' ); ?></strong></th>
										<td style="padding:0;">
											<?php
											if ( UptimeMonster_Site_Monitor::is_drop_in_writable() ) {
												esc_html_e( 'Writable', 'uptime' );
											} else {
												esc_html_e( 'Not Writable', 'uptime' );
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
					submit_button( __( 'Save Changes', 'uptime' ), 'primary', 'rwp-settings-save' );
				}
				?>
			</form>
		</div>
		<?php
	}
}

// End of file Dashboard.php.
