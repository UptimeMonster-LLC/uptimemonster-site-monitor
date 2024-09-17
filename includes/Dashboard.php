<?php
/**
 * Settings Dashboard
 *
 * @package UptimeMonster\SiteMonitor
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
		add_action( 'admin_init', [ $this, 'handle_request' ] );

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
			esc_html__( 'UptimeMonster Site Monitor Settings', 'uptimemonster-site-monitor' ),
			esc_html__( 'UptimeMonster', 'uptimemonster-site-monitor' ),
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

	public function handle_request() {
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
				if ( ! empty( $_GET['uninstall'] ) ) {
					UptimeMonster_Site_Monitor::uninstall();
					$this->add_settings_status( esc_html__( 'Error Logger Drop-In Uninstalled.', 'uptimemonster-site-monitor' ) );
				} else {
					UptimeMonster_Site_Monitor::maybe_install_drop_in();
					if ( ! empty( $_GET['update'] ) ) {
						$this->add_settings_status( esc_html__( 'Error Logger Drop-In Updated.', 'uptimemonster-site-monitor' ) );
					} else {
						$this->add_settings_status( esc_html__( 'Error Logger Drop-In Installed.', 'uptimemonster-site-monitor' ) );
					}
				}


				wp_safe_redirect( $this->get_page_url() );
				die();
			}
		}

		// Disconnect api.
		if ( isset( $_POST['uptimemonster-disconnect-api'] ) ) {
			check_admin_referer( 'uptimemonster-site-monitor-settings' );

			$client = UptimeMonster_Client::get_instance();

			delete_transient( 'uptimemonster-is-connected' );
			update_option( 'uptimemonster_site_monitor_api_keys', [] );

			$this->is_connected( false );
			$this->add_settings_status( esc_html__( 'UptimeMonster API Disconnected.', 'uptimemonster-site-monitor' ), 'warning' );

			if ( $client->has_keys() ) {
				$client->send_log( [
					'action'    => 'disconnect_api',
					'activity'  => 'Self_DeActivation',
					'subtype'   => 'monitor',
					'object_id' => 0,
					'name'      => 'Site Monitor Deactivated',
					'timestamp' => uptimemonster_get_current_time(),
					'actor'     => uptimemonster_get_current_actor(),
					'extra'     => [
						'wp_version' => get_bloginfo( 'version' ),
						'locale'     => get_locale(),
						'timezone'   => wp_timezone()->getName(),
					],
				] );
			}

			do_action( 'uptimemonster_site_monitor_api_deactivated' );

			wp_safe_redirect( $this->get_page_url() );
			die();
		}

		if ( isset( $_POST['uptimemonster-save-settings'] ) && ! empty( $_POST['uptimemonster'] ) && is_array( $_POST['uptimemonster'] ) ) {
			check_admin_referer( 'uptimemonster-site-monitor-settings' );

			$api_key    = isset( $_POST['uptimemonster']['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['uptimemonster']['api_key'] ) ) : '';
			$api_secret = isset( $_POST['uptimemonster']['api_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['uptimemonster']['api_secret'] ) ) : '';

			if ( $api_key && $api_secret ) {
				$response = UptimeMonster_Client::get_instance()
												->set_api_key( $api_key )
												->set_api_secret( $api_secret )
												->ping();

				if ( ! is_wp_error( $response ) ) {
					$new_keys = [
						'api_key'    => $api_key,
						'api_secret' => $api_secret,
					];

					$this->is_connected( false );

					update_option( 'uptimemonster_site_monitor_api_keys', $new_keys );

					$this->add_settings_status( esc_html__( 'API connected.', 'uptimemonster-site-monitor' ) );

					do_action( 'uptimemonster_site_monitor_api_updated' );
				} else {
					$this->add_settings_status( sprintf(
					/* translators: 1. Error Details. */
						esc_html__( 'Error connecting UptimeMonster API with following error: %s.', 'uptimemonster-site-monitor' ),
						$response->get_error_message()
					), 'error' );
				}
			}

			if ( ! $api_key ) {
				$this->add_settings_status( esc_html__( 'API key is required.', 'uptimemonster-site-monitor' ), 'error' );
			}

			if ( ! $api_secret ) {
				$this->add_settings_status( esc_html__( 'API secret is required.', 'uptimemonster-site-monitor' ), 'error' );
			}

			wp_safe_redirect( $this->get_page_url() );
			die();
		}
	}

	protected function is_connected( $cached = true ): bool {
		$client = UptimeMonster_Client::get_instance();

		if ( ! $client->has_keys() ) {
			return false;
		}

		$cache_key = 'uptimemonster-is-connected';

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
			<h1 class="wp-heading-inline" style="display:flex;align-items:center;gap:10px">
				<img style="width:30px" src="data:image/svg+xml,%3Csvg width='274' height='263' viewBox='0 0 274 263' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M55.3141 225.483C65.6464 235.815 77.7843 244.342 91.126 250.762C107.678 258.586 126.236 263 145.797 263C161.947 263 177.496 259.991 191.74 254.473C208.593 247.953 223.74 238.022 236.28 225.483C248.016 213.746 257.446 199.802 263.966 184.254C268.28 174.022 271.189 163.088 272.693 151.752C273.396 146.235 273.797 140.618 273.797 135C273.797 113.533 268.48 93.3699 259.251 75.6144C253.233 64.0784 245.508 53.6458 236.38 44.5172C223.74 31.9781 208.593 21.9467 191.74 15.5266C177.496 10.0094 162.048 7 145.797 7C126.236 7 107.678 11.4138 91.126 19.2382C77.7843 25.558 65.6464 34.185 55.3141 44.4169C46.1856 53.5455 38.4614 63.9781 32.4426 75.5141C23.1135 93.3699 17.7969 113.533 17.7969 135C17.7969 140.718 18.1981 146.335 18.9003 151.752C20.405 163.088 23.4144 174.022 27.6276 184.254C34.148 199.802 43.5774 213.746 55.3141 225.483Z' fill='url(%23paint0_radial_5910_10928)' style=''/%3E%3Cpath opacity='0.45' d='M55.3141 225.483C65.6464 235.815 77.7843 244.342 91.126 250.762C107.678 258.586 126.236 263 145.797 263C161.947 263 177.496 259.991 191.74 254.473C208.593 247.953 223.74 238.022 236.28 225.483C248.016 213.746 257.446 199.802 263.966 184.254C268.28 174.022 271.189 163.088 272.693 151.752C273.396 146.235 273.797 140.618 273.797 135C273.797 113.533 268.48 93.3699 259.251 75.6144C253.233 64.0784 245.508 53.6458 236.38 44.5172C223.74 31.9781 208.593 21.9467 191.74 15.5266C177.496 10.0094 162.048 7 145.797 7C126.236 7 107.678 11.4138 91.126 19.2382C77.7843 25.558 65.6464 34.185 55.3141 44.4169C46.1856 53.5455 38.4614 63.9781 32.4426 75.5141C23.1135 93.3699 17.7969 113.533 17.7969 135C17.7969 140.718 18.1981 146.335 18.9003 151.752C20.405 163.088 23.4144 174.022 27.6276 184.254C34.148 199.802 43.5774 213.746 55.3141 225.483Z' fill='url(%23paint1_radial_5910_10928)' style=''/%3E%3Cpath opacity='0.3' d='M55.3141 225.483C65.6464 235.815 77.7843 244.342 91.126 250.762C107.678 258.586 126.236 263 145.797 263C161.947 263 177.496 259.991 191.74 254.473C208.593 247.953 223.74 238.022 236.28 225.483C248.016 213.746 257.446 199.802 263.966 184.254C268.28 174.022 271.189 163.088 272.693 151.752C273.396 146.235 273.797 140.618 273.797 135C273.797 113.533 268.48 93.3699 259.251 75.6144C253.233 64.0784 245.508 53.6458 236.38 44.5172C223.74 31.9781 208.593 21.9467 191.74 15.5266C177.496 10.0094 162.048 7 145.797 7C126.236 7 107.678 11.4138 91.126 19.2382C77.7843 25.558 65.6464 34.185 55.3141 44.4169C46.1856 53.5455 38.4614 63.9781 32.4426 75.5141C23.1135 93.3699 17.7969 113.533 17.7969 135C17.7969 140.718 18.1981 146.335 18.9003 151.752C20.405 163.088 23.4144 174.022 27.6276 184.254C34.148 199.802 43.5774 213.746 55.3141 225.483Z' fill='url(%23paint2_radial_5910_10928)' style=''/%3E%3Cg opacity='0.3'%3E%3Cpath d='M273.797 135C273.797 205.721 216.518 263 145.797 263C75.1762 263 17.7969 205.621 17.7969 135C17.7969 64.279 75.1762 7 145.797 7C216.518 7 273.797 64.279 273.797 135Z' fill='%238BC63F' style='fill:%238BC63F;fill:color(display-p3 0.5451 0.7765 0.2471);fill-opacity:1;'/%3E%3Cpath d='M259.858 76.718L236.284 118.148H212.309L201.174 75.9155L182.817 145.734H161.55L147.607 185.959L127.243 109.721L113.902 140.818H83.2056L63.0426 165.295L53.6131 135.602L43.8827 155.163H19.4062C29.0363 216.254 82.0019 263 145.801 263C216.522 263 273.801 205.721 273.801 135C273.801 114.035 268.786 94.2729 259.858 76.718Z' fill='%23448600' style='fill:%23448600;fill:color(display-p3 0.2666 0.5252 0.0000);fill-opacity:1;'/%3E%3C/g%3E%3Cg opacity='0.25'%3E%3Cpath d='M273.797 135C273.797 205.721 216.518 263 145.797 263C75.1762 263 17.7969 205.621 17.7969 135C17.7969 64.279 75.1762 7 145.797 7C216.518 7 273.797 64.279 273.797 135Z' fill='%239AFF00' style='fill:%239AFF00;fill:color(display-p3 0.6038 1.0000 0.0000);fill-opacity:1;'/%3E%3Cg filter='url(%23filter0_f_5910_10928)'%3E%3Cpath d='M109.29 76.7181C109.29 94.7309 94.7006 109.32 76.6878 109.32C58.7006 109.32 44.0859 94.7053 44.0859 76.7181C44.0859 58.7053 58.7006 44.1162 76.6878 44.1162C94.7006 44.1162 109.29 58.7053 109.29 76.7181Z' fill='%2355D341' style='fill:%2355D341;fill:color(display-p3 0.3333 0.8275 0.2549);fill-opacity:1;'/%3E%3C/g%3E%3Cpath d='M259.858 76.718L236.284 118.148H212.309L201.174 75.9155L182.817 145.734H161.55L147.607 185.959L127.243 109.721L113.902 140.818H83.2056L63.0426 165.295L53.6131 135.602L43.8827 155.163H19.4062C29.0363 216.254 82.0019 263 145.801 263C216.522 263 273.801 205.721 273.801 135C273.801 114.035 268.786 94.2729 259.858 76.718Z' fill='url(%23paint3_linear_5910_10928)' style=''/%3E%3C/g%3E%3Cpath d='M53.6138 135.602L63.0432 165.295L83.2063 140.718H114.002L127.344 109.621L147.708 185.859L161.651 145.633H182.918L201.275 75.815L212.41 118.047H236.385L259.959 76.6175C258.053 72.8056 255.846 69.094 253.539 65.583L229.965 107.113H220.937L201.275 32.6802L174.492 134.699H153.927L149.213 148.442L129.952 76.1159L106.981 129.784H77.99L67.3567 142.724L55.921 106.31L37.0621 144.028H18.2031C18.5041 147.74 18.9053 151.451 19.4069 155.063H43.7831L53.6138 135.602Z' fill='url(%23paint4_radial_5910_10928)' style=''/%3E%3Cdefs%3E%3Cfilter id='filter0_f_5910_10928' x='0.0859375' y='0.116211' width='153.203' height='153.204' filterUnits='userSpaceOnUse' color-interpolation-filters='sRGB'%3E%3CfeFlood flood-opacity='0' result='BackgroundImageFix'/%3E%3CfeBlend mode='normal' in='SourceGraphic' in2='BackgroundImageFix' result='shape'/%3E%3CfeGaussianBlur stdDeviation='22' result='effect1_foregroundBlur_5910_10928'/%3E%3C/filter%3E%3CradialGradient id='paint0_radial_5910_10928' cx='0' cy='0' r='1' gradientUnits='userSpaceOnUse' gradientTransform='translate(127.643 53.4916) scale(199.327)'%3E%3Cstop stop-color='%23B3D334' style='stop-color:%23B3D334;stop-color:color(display-p3 0.7020 0.8275 0.2039);stop-opacity:1;'/%3E%3Cstop offset='0.1024' stop-color='%23ACCE40' style='stop-color:%23ACCE40;stop-color:color(display-p3 0.6745 0.8078 0.2510);stop-opacity:1;'/%3E%3Cstop offset='0.292' stop-color='%239BC55F' style='stop-color:%239BC55F;stop-color:color(display-p3 0.6078 0.7725 0.3725);stop-opacity:1;'/%3E%3Cstop offset='0.5478' stop-color='%237BB887' style='stop-color:%237BB887;stop-color:color(display-p3 0.4824 0.7216 0.5294);stop-opacity:1;'/%3E%3Cstop offset='0.8568' stop-color='%2346A9B2' style='stop-color:%2346A9B2;stop-color:color(display-p3 0.2745 0.6627 0.6980);stop-opacity:1;'/%3E%3Cstop offset='1' stop-color='%2304A2C5' style='stop-color:%2304A2C5;stop-color:color(display-p3 0.0157 0.6353 0.7725);stop-opacity:1;'/%3E%3C/radialGradient%3E%3CradialGradient id='paint1_radial_5910_10928' cx='0' cy='0' r='1' gradientUnits='userSpaceOnUse' gradientTransform='translate(127.643 53.4916) scale(199.327)'%3E%3Cstop stop-color='%23FCF5A8' style='stop-color:%23FCF5A8;stop-color:color(display-p3 0.9882 0.9608 0.6588);stop-opacity:1;'/%3E%3Cstop offset='0.4233' stop-color='%23CDDD54' style='stop-color:%23CDDD54;stop-color:color(display-p3 0.8039 0.8667 0.3294);stop-opacity:1;'/%3E%3Cstop offset='1' stop-color='%234CBED6' style='stop-color:%234CBED6;stop-color:color(display-p3 0.2980 0.7451 0.8392);stop-opacity:1;'/%3E%3C/radialGradient%3E%3CradialGradient id='paint2_radial_5910_10928' cx='0' cy='0' r='1' gradientUnits='userSpaceOnUse' gradientTransform='translate(127.643 53.4916) scale(199.327)'%3E%3Cstop stop-color='%2397EB1B' style='stop-color:%2397EB1B;stop-color:color(display-p3 0.5922 0.9216 0.1059);stop-opacity:1;'/%3E%3Cstop offset='0.4233' stop-color='%2361D732' style='stop-color:%2361D732;stop-color:color(display-p3 0.3804 0.8431 0.1961);stop-opacity:1;'/%3E%3Cstop offset='1' stop-color='%234CBED6' style='stop-color:%234CBED6;stop-color:color(display-p3 0.2980 0.7451 0.8392);stop-opacity:1;'/%3E%3C/radialGradient%3E%3ClinearGradient id='paint3_linear_5910_10928' x1='146.604' y1='124.868' x2='146.604' y2='263' gradientUnits='userSpaceOnUse'%3E%3Cstop stop-color='%2366D92B' style='stop-color:%2366D92B;stop-color:color(display-p3 0.4000 0.8510 0.1686);stop-opacity:1;'/%3E%3Cstop offset='1' stop-color='%23448600' style='stop-color:%23448600;stop-color:color(display-p3 0.2667 0.5255 0.0000);stop-opacity:1;'/%3E%3C/linearGradient%3E%3CradialGradient id='paint4_radial_5910_10928' cx='0' cy='0' r='1' gradientUnits='userSpaceOnUse' gradientTransform='translate(138.996 109.351) scale(101.162)'%3E%3Cstop offset='0.6135' stop-color='white' style='stop-color:white;stop-opacity:1;'/%3E%3Cstop offset='0.7255' stop-color='%23F8FAF7' style='stop-color:%23F8FAF7;stop-color:color(display-p3 0.9725 0.9804 0.9686);stop-opacity:1;'/%3E%3Cstop offset='0.8517' stop-color='%23EBEFE9' style='stop-color:%23EBEFE9;stop-color:color(display-p3 0.9216 0.9373 0.9137);stop-opacity:1;'/%3E%3Cstop offset='0.9842' stop-color='%23D6E1D5' style='stop-color:%23D6E1D5;stop-color:color(display-p3 0.8392 0.8824 0.8353);stop-opacity:1;'/%3E%3Cstop offset='1' stop-color='%23D3DFD3' style='stop-color:%23D3DFD3;stop-color:color(display-p3 0.8275 0.8745 0.8275);stop-opacity:1;'/%3E%3C/radialGradient%3E%3C/defs%3E%3C/svg%3E%0A" alt="<?php esc_attr_e( 'UptimeMonster Logo', 'uptimemonster-site-monitor' ); ?>">
				<?php echo esc_html( get_admin_page_title() ); ?>
			</h1>
			<hr class="wp-header-end">
			<form method="post" action="<?php echo esc_url( $this->get_page_url() ); ?>">
				<?php wp_nonce_field( 'uptimemonster-site-monitor-settings' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
					<?php if ( $this->is_connected() ) { ?>
						<tr>
							<th scope="row"><label for="uptimemonster-api-key"><?php esc_html_e( 'API Key', 'uptimemonster-site-monitor' ); ?></label></th>
							<td><input type="text" id="uptimemonster-api-key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="none" readonly></td>
						</tr>
						<tr>
							<th scope="row"><label for="uptimemonster-api-secret"><?php esc_html_e( 'API Secret', 'uptimemonster-site-monitor' ); ?></label></th>
							<td><input type="password" id="uptimemonster-api-secret" value="<?php echo esc_attr( $api_secret ); ?>" class="regular-text" autocomplete="none" readonly></td>
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
							<th scope="row"><label for="uptimemonster-api-key"><?php esc_html_e( 'Api Key', 'uptimemonster-site-monitor' ); ?></label></th>
							<td><input name="uptimemonster[api_key]" type="text" id="uptimemonster-api-key" value="" class="regular-text" autocomplete="none"></td>
						</tr>
						<tr>
							<th scope="row"><label for="uptimemonster-api-secret"><?php esc_html_e( 'Api Secret', 'uptimemonster-site-monitor' ); ?></label></th>
							<td><input name="uptimemonster[api_secret]" type="password" id="uptimemonster-api-secret" value="" class="regular-text" autocomplete="none"></td>
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
						<th scope="row"><label><?php esc_html_e( 'Status', 'uptimemonster-site-monitor' ); ?></label>
						</th>
						<td>
							<table>
								<tbody>
								<tr>
									<th scope="row" style="padding:0;">
										<strong><?php esc_html_e( 'Drop-In (Error Monitor)', 'uptimemonster-site-monitor' ); ?></strong>
									</th>
									<td style="padding:0;">
										<?php
										if ( UptimeMonster_Site_Monitor::is_drop_in_installed() ) {
											$uninstall_url = false;
											if ( UptimeMonster_Site_Monitor::is_drop_in_writable() ) {
												$uninstall_url = wp_nonce_url( add_query_arg( [
													'action'    => self::DROP_IN_ACTION,
													'uninstall' => true,
												], $this->get_page_url() ), self::DROP_IN_ACTION );
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
											?>
											<br>
											<?php
											if ( $uninstall_url ) {
												printf(
												/* translators: 1. Uninstallation URL tag (anchor) opening, 2. Anchor tag closing */
													esc_html__( 'Click %1$shere%2$s to uninstall (disable) the Drop-In.', 'uptimemonster-site-monitor' ),
													'<a href="' . esc_url( $uninstall_url ) . '">',
													'</a>'
												);
											} else {
												printf(
												/* translators: 1: Source file path. 2: Destination file path. 3: Code opening tag. 4: Code closing tag. */
													esc_html__( 'WP Content (wp-content) directory is not writable. Please remove %2$s%1$s%3$s manually to disable error log monitoring.', 'uptimemonster-site-monitor' ),
													esc_html( UptimeMonster_Site_Monitor::get_drop_in_file() ),
													'<code>',
													'</code>'
												);
											}
											if ( UptimeMonster_Site_Monitor::drop_in_need_update() ) {
												$update_url = [
													'action' => self::DROP_IN_ACTION,
													'update' => true,
												];
												$update_url = wp_nonce_url( add_query_arg( $update_url, $this->get_page_url() ), self::DROP_IN_ACTION );
												?>
												<br>
												<?php
												printf(
												/* translators: 1. New Version Number, 2. Update URL tag (anchor) opening, 3. Anchor tag closing */
													esc_html__( 'A newer version (Version %1$s) of the drop-in available. Click %2$shere%3$s to update.', 'uptimemonster-site-monitor' ),
													esc_html( UptimeMonster_Site_Monitor::drop_in_version( false ) ),
													'<a href="' . esc_url( $update_url ) . '">',
													'</a>'
												);
											}
										} elseif ( UptimeMonster_Site_Monitor::is_wp_content_writable() ) {
											$install_url = wp_nonce_url( add_query_arg( [ 'action' => self::DROP_IN_ACTION ], $this->get_page_url() ), self::DROP_IN_ACTION );
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
										?>
									</td>
								</tr>
								<tr>
									<th scope="row" style="padding:0;">
										<strong><?php esc_html_e( 'WP Content Directory', 'uptimemonster-site-monitor' ); ?></strong>
									</th>
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
