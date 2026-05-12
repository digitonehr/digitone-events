<?php
/**
 * Speakers module: main page with tabs for Speakers / Titles / Roles.
 *
 * Renders all three sub-managers in one screen, each driven by its own
 * module's data layer. Titles and Roles do not have their own admin pages
 * since they are sub-features of Speakers management.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Speakers_Module {

	private DigitOne_Events_Speakers_Repository $repo;
	private DigitOne_Events_Speakers_Ajax $ajax;

	public function __construct() {
		$this->repo = new DigitOne_Events_Speakers_Repository();
		$this->ajax = new DigitOne_Events_Speakers_Ajax( $this->repo );
	}

	public function register() : void {
		$this->ajax->register();
	}

	public function repo() : DigitOne_Events_Speakers_Repository {
		return $this->repo;
	}

	public function render_page() : void {
		// We need WP media library on the speakers tab for photo uploads.
		wp_enqueue_media();

		$plugin          = DigitOne_Events_Plugin::instance();
		$titles_repo     = $plugin->module( 'titles' )->repo();
		$roles_repo      = $plugin->module( 'roles' )->repo();
		$active_event_id = DigitOne_Events_Helpers_Event_Context::active_event_id();

		$speakers = $active_event_id ? $this->repo->all_for_event( $active_event_id ) : [];
		$titles   = $active_event_id ? $titles_repo->all_for_event( $active_event_id ) : [];
		$roles    = $active_event_id ? $roles_repo->all_for_event( $active_event_id )  : [];

		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'speakers';
		if ( ! in_array( $tab, [ 'speakers', 'titles', 'roles' ], true ) ) {
			$tab = 'speakers';
		}

		DigitOne_Events_Helpers_View::render(
			'modules/speakers/views/list',
			[
				'tab'             => $tab,
				'speakers'        => $speakers,
				'titles'          => $titles,
				'roles'           => $roles,
				'active_event_id' => $active_event_id,
			]
		);
	}
}
