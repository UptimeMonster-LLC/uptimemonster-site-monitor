<?php
/**
 * Upgrader Skin
 *
 * @package UptimeMonster\SiteMonitor
 */

namespace UptimeMonster\SiteMonitor\CoreUpdate;

use WP_Error;
use WP_Upgrader_Skin;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit; // Exit if accessed directly
}

/**
 * An Upgrader Skin for WordPress that only generates plain-text
 */
#[\AllowDynamicProperties]
class UptimeMonsterUpgraderSkin extends WP_Upgrader_Skin {

	protected $messages = [];

	protected $errors = [];

	/**
	 * Show error message.
	 *
	 * @param string|WP_Error $errors Error message.
	 *
	 * @return void
	 */
	public function error( $errors ) {
		if ( ! $errors ) {
			return;
		}

		if ( is_string( $errors ) ) {
			$errors = $this->process_feedback( $errors );
			if ( ! $errors ) {
				return;
			}
			$this->errors[] = $errors;
		} elseif ( is_wp_error( $errors ) && $errors->has_errors() ) {
			foreach ( $errors->get_error_messages() as $message ) {
				if ( $errors->get_error_data() && is_string( $errors->get_error_data() ) ) {
					$errors = $this->process_feedback( $message . ' ' . esc_html( wp_strip_all_tags( $errors->get_error_data() ) ) );
				} else {
					$errors = $this->process_feedback( $message );
				}

				if ( ! $errors ) {
					continue;
				}

				$this->errors[] = $errors;
			}
		}
	}

	/**
	 * @param string $feedback Message data.
	 * @param mixed ...$args Optional text replacements.
	 */
	public function feedback( $feedback, ...$args ) { // phpcs:ignore PHPCompatibility.LanguageConstructs.NewLanguageConstructs.t_ellipsisFound
		$feedback = $this->process_feedback( $feedback, $args );
		if ( ! $feedback ) {
			return;
		}

		$this->messages[] = $feedback;
	}

	/**
	 * Process the feedback collected through the compat indirection.
	 *
	 * @param string $feedback Message data.
	 * @param array $args Optional text replacements.
	 */
	public function process_feedback( $feedback, $args = [] ) { // phpcs:ignore PHPCompatibility.LanguageConstructs.NewLanguageConstructs.t_ellipsisFound
		if ( isset( $this->upgrader->strings[ $feedback ] ) ) {
			$feedback = $this->upgrader->strings[ $feedback ];
		}

		if ( str_contains( $feedback, '%' ) ) {
			if ( $args ) {
				$args     = array_map( 'wp_strip_all_tags', $args );
				$args     = array_map( 'esc_html', $args );
				$feedback = vsprintf( $feedback, $args );
			}
		}

		$feedback = str_replace( '&#8230;', '...', wp_strip_all_tags( $feedback ) );
		return html_entity_decode( $feedback, ENT_QUOTES, get_bloginfo( 'charset' ) );
	}

	public function get_feedbacks(): array {
		return [
			'messages' => $this->messages,
			'errors'   => $this->errors,
		];
	}
}
