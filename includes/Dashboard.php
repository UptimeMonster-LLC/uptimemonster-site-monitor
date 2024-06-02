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

use UptimeMonster\SiteMonitor\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

class Dashboard {

	use Singleton;

	const DROP_IN_ACTION = 'uptimemonster-install-drop-in';

	protected function __construct() {
		add_action( 'admin_notices', [ $this, 'admin_notice' ] );
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_init', [ $this, 'handle_post' ] );

		add_filter( 'plugin_action_links_' . UPTIMEMONSTER_SITE_PLUGIN_BASENAME, [ $this, 'plugin_action_links' ] );
	}

	public function admin_notice() {
		$data = get_transient( 'uptimemonster-setting-status' );
		delete_transient( 'uptimemonster-setting-status' );

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
		$links[] = sprintf( '<a href="%s">%s</a>', $this->get_page_url(), esc_html__( 'Settings', 'uptimemonster-site-monitor' ) );

		return $links;
	}

	public function register_menu() {
		add_options_page(
			esc_html__( 'UptimeMonster API Settings', 'uptimemonster-site-monitor' ),
			esc_html__( 'UptimeMonster Settings', 'uptimemonster-site-monitor' ),
			'manage_options',
			'uptimemonster-settings',
			[ $this, 'settings_page' ]
		);
	}

	protected function add_settings_status( $message, $type = 'success' ) {
		$data = get_transient( 'uptimemonster-setting-status' );
		if ( ! $data ) {
			$data = [];
		}
		$hash          = md5( $message . $type );
		$data[ $hash ] = [ $message, $type ];

		set_transient( 'uptimemonster-setting-status', $data, 60 );
	}

	public function get_page_url() {
		return admin_url( 'options-general.php?page=uptimemonster-settings' );
	}

	protected function installed_on() {
		return get_option( 'uptimemonster_first_installed' );
	}

	public function handle_post() {
		if ( 'yes' === get_option( 'uptimemonster_need_setup' ) ) {
			if ( empty( $this->installed_on() ) ) {
				$this->add_settings_status( esc_html__( 'Thank you for installing UptimeMonster Site Monitor.', 'uptimemonster-site-monitor' ) );
				$this->add_settings_status( esc_html__( 'Please update the api keys to activate it properly.', 'uptimemonster-site-monitor' ) );
			}

			$this->add_settings_status( esc_html__( 'Please update the api keys to activate it properly.', 'uptimemonster-site-monitor' ) );

			update_option( 'uptimemonster_need_setup', 'no' );

			wp_safe_redirect( $this->get_page_url() );
			die();
		}

		if ( isset( $_GET['action'], $_GET['_wpnonce'] ) && self::DROP_IN_ACTION === $_GET['action'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::DROP_IN_ACTION ) ) {
				UptimeMonster_Site_Monitor::maybe_install_drop_in();

				$this->add_settings_status( esc_html__( 'Error Logger Drop-In Updated.', 'uptimemonster-site-monitor' ) );

				wp_safe_redirect( $this->get_page_url() );
				die();
			}
		}

		// Disconnect api.
		if ( isset( $_GET['action'], $_GET['_wpnonce'] ) && 'disconnect-api' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'umon-disconnect-api' ) ) {
				$client = UptimeMonster_Client::get_instance();
				if ( $client->has_keys() ) {
					$client->send_log( [
						'action'    => 'disconnect_api',
						'activity'  => 'Self_DeActivation',
						'subtype'   => 'monitor',
						'object_id' => null,
						'name'      => 'Site Monitor Deactivated',
						'timestamp' => uptimemonster_get_current_time(),
						'actor'     => uptimemonster_get_current_actor(),
						'extra'     => [],
					] );
				}

				delete_transient( 'umon-is-connected' );

				update_option( 'uptimemonster_site_monitor_api_keys', [] );

				wp_safe_redirect( $this->get_page_url() );
				die();
			}
		}

		if ( isset( $_POST['uptimemonster-disconnect-api'] ) ) {
			check_admin_referer( 'uptimemonster-site-monitor-settings' );

			do_action( 'uptimemonster_site_monitor_api_deactivating' );

			$this->is_connected( false );
			delete_option( 'uptimemonster_site_monitor_api_keys' );
			$this->add_settings_status( esc_html__( 'UptimeMonster API Disconnected.', 'uptimemonster-site-monitor' ), 'warning' );

			do_action( 'uptimemonster_site_monitor_api_deactivated' );

			wp_safe_redirect( $this->get_page_url() );
			die();
		}

		if ( isset( $_POST['uptimemonster-save-settings'], $_POST['umon'] ) && is_array( $_POST['umon'] ) && ! empty( $_POST['umon'] ) ) {
			check_admin_referer( 'uptimemonster-site-monitor-settings' );

			$api_key    = isset( $_POST['umon']['api_key'] ) ? sanitize_text_field( $_POST['umon']['api_key'] ) : '';
			$api_secret = isset( $_POST['umon']['api_secret'] ) ? sanitize_text_field( $_POST['umon']['api_secret'] ) : '';

			if ( $api_key && $api_secret ) {
				$client = UptimeMonster_Client::get_instance();
				$client->set_api_key( $api_key );
				$client->set_api_secret( $api_secret );
				$response = $client->ping();

				if ( ! is_wp_error( $response ) ) {
					$new_keys = [
						'api_key'    => $api_key,
						'api_secret' => $api_secret,
					];

					$this->is_connected( false );

					update_option( 'uptimemonster_site_monitor_api_keys', $new_keys );

					$this->add_settings_status( esc_html__( 'Api connected.', 'uptimemonster-site-monitor' ) );

					do_action( 'uptimemonster_site_monitor_api_updated' );
				} else {
					$this->add_settings_status( sprintf(
						/* translators: 1. Error Details. */
						esc_html__( 'Error connecting UptimeMonster API with following error: %s.', 'uptimemonster-site-monitor' ),
						$response->get_error_message()
					), 'error' );
				}
			} else {
				$this->add_settings_status( esc_html__( 'Both api key & secret required.', 'uptimemonster-site-monitor' ), 'error' );
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

		$cache_key = 'umon-is-connected';

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
		$api_key    = '';
		$api_secret = '';
		if ( $this->is_connected() ) {
			$api_keys = get_option( 'uptimemonster_site_monitor_api_keys', [] );
			if ( isset( $api_keys['api_key'], $api_keys['api_secret'] ) ) {
				$api_key    = $api_keys['api_key'];
				$api_secret = $api_keys['api_secret'];
			}
		}
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<i class="dashicons dashicons-superhero" style="font-size:1.5em;display:inline-block;width:30px;height:30px;margin:-1px 10px 0 0;" aria-hidden="true"></i>
				<?php echo esc_html( get_admin_page_title() ); ?>
			</h1>
			<hr class="wp-header-end">
			<form method="post" action="<?php echo esc_url( $this->get_page_url() ); ?>">
				<?php wp_nonce_field( 'uptimemonster-site-monitor-settings' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
					<?php if ( $this->is_connected() ) { ?>
					<tr>
						<th scope="row"><label for="umon-api-key"><?php esc_html_e( 'Api Key', 'uptimemonster-site-monitor' ); ?></label></th>
						<td><input type="text" id="umon-api-key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="none" readonly></td>
					</tr>
					<tr>
						<th scope="row"><label for="umon-api-secret"><?php esc_html_e( 'Api Secret', 'uptimemonster-site-monitor' ); ?></label></th>
						<td><input type="password" id="umon-api-secret" value="<?php echo esc_attr( $api_secret ); ?>" class="regular-text" autocomplete="none" readonly></td>
					</tr>
					<tr>
						<th scope="row"></th>
						<td>
							<div style="display:flex;align-items:center;gap:10px;">
								<?php esc_html_e( 'Connected', 'uptimemonster-site-monitor' ); ?>
								<button class="button button-secondary button-warning" type="submit" name="uptimemonster-disconnect-api" value="1"><?php esc_html_e( 'Disconnect', 'uptimemonster-site-monitor' ); ?></button>
							</div>
							<p><?php printf(
								/* translators: %1$s: Manage Subscription link, %2$s: Dashboard link */
									esc_html__( 'Manage your UptimeMonster %1$s or goto %2$s', 'uptimemonster-site-monitor' ),
									'<a href="https://uptimemonster.com/my-account/" target="_blank" rel="noopener">' . esc_html__( 'Subscription', 'uptimemonster-site-monitor' ) . '</a>',
									'<a href="https://uptimemonster.com/my-account/" target="_blank" rel="noopener">' . esc_html__( 'Dashboard', 'uptimemonster-site-monitor' ) . '</a>'
								); ?></p>
						</td>
					</tr>
					<?php } else { ?>
					<tr>
						<th scope="row"><label for="umon-api-key"><?php esc_html_e( 'Api Key', 'uptimemonster-site-monitor' ); ?></label></th>
						<td><input name="umon[api_key]" type="text" id="umon-api-key" value="" class="regular-text" autocomplete="none"></td>
					</tr>
					<tr>
						<th scope="row"><label for="umon-api-secret"><?php esc_html_e( 'Api Secret', 'uptimemonster-site-monitor' ); ?></label></th>
						<td><input name="umon[api_secret]" type="password" id="umon-api-secret" value="" class="regular-text" autocomplete="none"></td>
					</tr>
					<tr>
						<th></th>
						<td>
							<p><?php printf(
								/* translators: %s: Signup link */
									esc_html__( 'Signup now on %s for free and start monitoring your site.', 'uptimemonster-site-monitor' ),
									'<a href="https://uptimemonster.com/pricing/" target="_blank" rel="noopener">' . esc_html__( 'UptimeMonster', 'uptimemonster-site-monitor' ) . '</a>'
								); ?></p>
						</td>
					</tr>
					<?php } ?>
					<tr>
						<th scope="row"><label><?php esc_html_e( 'Status', 'uptimemonster-site-monitor' ); ?></label></th>
						<td>
							<table>
								<tbody>
								<tr>
									<th scope="row" style="padding:0;"><strong><?php esc_html_e( 'Drop-In:', 'uptimemonster-site-monitor' ); ?></strong></th>
									<td style="padding:0;">
										<?php
										$install_url = wp_nonce_url( add_query_arg( [ 'action' => self::DROP_IN_ACTION ], $this->get_page_url() ), self::DROP_IN_ACTION );
										if ( UptimeMonster_Site_Monitor::is_drop_in_installed() ) {
											if ( UptimeMonster_Site_Monitor::is_drop_in_writable() ) {
												esc_html_e( 'Writable', 'uptimemonster-site-monitor' );
											} else {
												esc_html_e( 'Not Writable', 'uptimemonster-site-monitor' );
											}
											?> <span class="separator" aria-hidden="true">|</span> <?php
											printf(
											/* translators: 1. Error handler drop-in version. */
												esc_html__( 'Installed (Version %s)', 'uptimemonster-site-monitor' ),
												esc_html( UptimeMonster_Site_Monitor::drop_in_version() )
											);
										?><br><?php
											if ( UptimeMonster_Site_Monitor::drop_in_need_update() ) {
												printf(
												/* translators: 1. New Version Number, 2. Update URL tag (anchor) opening, 3. Anchor tag closing */
													esc_html__( 'A newer version (Version %1$s) of the drop-in available. Click %2$shere%3$s to update.', 'uptimemonster-site-monitor' ),
													esc_html( UptimeMonster_Site_Monitor::drop_in_version( false ) ),
													'<a href="' . esc_url( $install_url ) . '">',
													'</a>'
												);
											}
										} else {
											if ( UptimeMonster_Site_Monitor::is_wp_content_writable() ) {
												printf(
												/* translators: 1. Installation URL tag (anchor) opening, 2. Anchor tag closing */
													esc_html__( 'Click %1$shere%2$s to install the drop-in.', 'uptimemonster-site-monitor' ),
													'<a href="' . esc_url( $install_url ) . '">',
													'</a>'
												);
											} else {
												printf(
												/* translators: 1: Source file path. 2: Destination file path. 3: Code opening tag. 4: Code closing tag. */
													esc_html__( 'WP Content (wp-content) directory is not writable. Please copy %3$s%1$s%4$s into %3$s%2$s%4$s for enabling error monitoring', 'uptimemonster-site-monitor' ),
													esc_html( UptimeMonster_Site_Monitor::get_drop_in_dist_file() ),
													esc_html( UptimeMonster_Site_Monitor::get_drop_in_file() ),
													'<code>',
													'</code>'
												);
											}
										}
										?>
									</td>
								</tr>
								<tr>
									<th scope="row" style="padding:0;"><strong><?php esc_html_e( 'WP Content Directory', 'uptimemonster-site-monitor' ); ?></strong></th>
									<td style="padding:0;">
										<?php
										if ( UptimeMonster_Site_Monitor::is_wp_content_writable() ) {
											esc_html_e( 'Writable', 'uptimemonster-site-monitor' );
										} else {
											esc_html_e( 'Not Writable', 'uptimemonster-site-monitor' );
										}
										?>
									</td>
								</tr>
								</tbody>
							</table>
						</td>
					</tr>
					</tbody>
				</table>
				<?php
				if ( ! $this->is_connected() ) {
					submit_button( esc_html__( 'Save Changes', 'uptimemonster-site-monitor' ), 'primary', 'uptimemonster-save-settings' );
				}
				?>
			</form>
		</div>
		<?php
	}
}

// End of file Dashboard.php.
