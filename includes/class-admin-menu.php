<?php
/**
 * Admin menu registration.
 *
 * Top-level: "DigitOne Events" with submenus:
 *   - Dashboard (default)
 *   - Events
 *   - Settings
 *
 * More submenus will be added as modules ship (Days, Venues, Speakers, etc).
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Admin_Menu {

	public const PARENT_SLUG = 'digitone-events';

	public function register() : void {
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
	}

	public function register_menus() : void {
		$cap = DigitOne_Events_Security_Capabilities::manage_cap();

		add_menu_page(
			__( 'DigitOne Events', 'digitone-events' ),
			__( 'DigitOne Events', 'digitone-events' ),
			$cap,
			self::PARENT_SLUG,
			[ $this, 'render_dashboard' ],
			'dashicons-calendar-alt',
			26
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Dashboard', 'digitone-events' ),
			__( 'Dashboard', 'digitone-events' ),
			$cap,
			self::PARENT_SLUG,
			[ $this, 'render_dashboard' ]
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Events', 'digitone-events' ),
			__( 'Events', 'digitone-events' ),
			$cap,
			'digitone-events-events',
			[ $this, 'render_events' ]
		);

		// Placeholders for future modules (rendered as "coming soon" until shipped).
		$placeholders = [
			'digitone-events-days'          => __( 'Days', 'digitone-events' ),
			'digitone-events-venues'        => __( 'Venues', 'digitone-events' ),
			'digitone-events-speakers'      => __( 'Speakers', 'digitone-events' ),
			'digitone-events-sessions'      => __( 'Sessions', 'digitone-events' ),
			'digitone-events-session-types' => __( 'Session Types', 'digitone-events' ),
			'digitone-events-export-import' => __( 'Export / Import', 'digitone-events' ),
		];
		foreach ( $placeholders as $slug => $label ) {
			add_submenu_page(
				self::PARENT_SLUG,
				$label,
				$label,
				$cap,
				$slug,
				[ $this, 'render_placeholder' ]
			);
		}

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Settings', 'digitone-events' ),
			__( 'Settings', 'digitone-events' ),
			$cap,
			'digitone-events-settings',
			[ $this, 'render_settings' ]
		);
	}

	public function render_dashboard() : void {
		DigitOne_Events_Helpers_View::render( 'admin/views/dashboard' );
	}

	public function render_events() : void {
		$module = DigitOne_Events_Plugin::instance()->module( 'events' );
		if ( $module && method_exists( $module, 'render_page' ) ) {
			$module->render_page();
		}
	}

	public function render_settings() : void {
		DigitOne_Events_Helpers_View::render( 'admin/views/settings' );
	}

	public function render_placeholder() : void {
		$screen = get_current_screen();
		$label  = $screen ? $screen->id : 'Module';
		echo '<div class="wrap"><h1>' . esc_html__( 'Coming soon', 'digitone-events' ) . '</h1>';
		echo '<p>' . esc_html__( 'This module is part of an upcoming phase.', 'digitone-events' ) . '</p></div>';
	}
}
