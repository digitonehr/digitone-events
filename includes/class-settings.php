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
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			]
		);
	}
}
