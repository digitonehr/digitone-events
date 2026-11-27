<?php
/**
 * Settings registration (Settings API).
 *
 * Stores GitHub owner/repo/token. Constants in wp-config.php override.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Settings {

	public const GROUP = 'digitone_events_settings';

	/** Checkbox on the Settings page that explicitly removes the saved token. */
	public const TOKEN_CLEAR_FIELD = 'digitone_events_gh_token_clear';

	public function register() : void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function register_settings() : void {
		register_setting(
			self::GROUP,
			'digitone_events_gh_owner',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => DIGITONE_EVENTS_GH_OWNER,
			]
		);
		register_setting(
			self::GROUP,
			'digitone_events_gh_repo',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => DIGITONE_EVENTS_GH_REPO,
			]
		);
		register_setting(
			self::GROUP,
			'digitone_events_gh_token',
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_token' ],
				'default'           => '',
			]
		);
	}

	/**
	 * The Settings page never prints the saved token (0.9.6), so the field is
	 * submitted blank unless the user types a new one. Blank means "keep the
	 * current token"; the "Remove saved token" checkbox clears it.
	 *
	 * @param mixed $value Submitted value.
	 */
	public function sanitize_token( $value ) : string {
		// options.php has already verified the settings nonce at this point.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST[ self::TOKEN_CLEAR_FIELD ] ) ) {
			return '';
		}
		$value = is_string( $value ) ? trim( sanitize_text_field( $value ) ) : '';
		if ( $value === '' ) {
			return (string) get_option( 'digitone_events_gh_token', '' );
		}
		return $value;
	}
}
