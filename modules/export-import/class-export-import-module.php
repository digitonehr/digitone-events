<?php
/**
 * Export / Import module.
 *
 * Phase 5 scope:
 *  - JSON export (full event snapshot, round-trippable)
 *  - JSON import (creates NEW event with fresh UUIDs)
 *  - CSV export (zip with one CSV per entity)
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Export_Import_Module {

	private DigitOne_Events_Export_Import_Repository $repo;
	private DigitOne_Events_Export_Import_Ajax $ajax;

	public function __construct() {
		$this->repo = new DigitOne_Events_Export_Import_Repository();
		$this->ajax = new DigitOne_Events_Export_Import_Ajax( $this->repo );
	}

	public function register() : void {
		$this->ajax->register();
		// admin-post.php endpoints for file downloads / uploads.
		add_action( 'admin_post_digitone_events_export', [ $this->ajax, 'handle_export' ] );
		add_action( 'admin_post_digitone_events_import', [ $this->ajax, 'handle_import' ] );
	}

	public function repo() : DigitOne_Events_Export_Import_Repository {
		return $this->repo;
	}

	public function render_page() : void {
		$active_event_id = DigitOne_Events_Helpers_Event_Context::active_event_id();
		$result = isset( $_GET['imported'] ) ? sanitize_text_field( wp_unslash( $_GET['imported'] ) ) : '';
		$new_id = isset( $_GET['new_id'] )   ? sanitize_text_field( wp_unslash( $_GET['new_id'] ) )   : '';
		$err    = isset( $_GET['error'] )    ? sanitize_text_field( wp_unslash( $_GET['error'] ) )    : '';

		DigitOne_Events_Helpers_View::render(
			'modules/export-import/views/page',
			[
				'active_event_id' => $active_event_id,
				'imported'        => $result,
				'new_event_id'    => $new_id,
				'error'           => $err,
			]
		);
	}
}
