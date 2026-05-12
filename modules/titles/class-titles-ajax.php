<?php
/**
 * Titles AJAX endpoints.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Titles_Ajax {

	private DigitOne_Events_Titles_Repository $repo;

	public function __construct( DigitOne_Events_Titles_Repository $repo ) {
		$this->repo = $repo;
	}

	public function register() : void {
		add_action( 'wp_ajax_digitone_events_title_list',   [ $this, 'list_titles' ] );
		add_action( 'wp_ajax_digitone_events_title_save',   [ $this, 'save_title' ] );
		add_action( 'wp_ajax_digitone_events_title_delete', [ $this, 'delete_title' ] );
	}

	public function list_titles() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$event_id = $this->resolve_event_id();
		if ( ! $event_id ) {
			wp_send_json_error( [ 'message' => __( 'No active event.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'titles' => $this->repo->all_for_event( $event_id ) ] );
	}

	public function save_title() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$event_id = $this->resolve_event_id();
		if ( ! $event_id ) {
			wp_send_json_error( [ 'message' => __( 'No active event.', 'digitone-events' ) ] );
		}
		$payload = [
			'id'       => isset( $_POST['id'] )   ? sanitize_text_field( wp_unslash( $_POST['id'] ) )   : '',
			'event_id' => $event_id,
			'name'     => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
		];
		if ( $payload['name'] === '' ) {
			wp_send_json_error( [ 'message' => __( 'Name is required.', 'digitone-events' ) ] );
		}
		$id = $this->repo->save( $payload );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Could not save title.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'id' => $id, 'title' => $this->repo->find( $id ), 'message' => __( 'Title saved.', 'digitone-events' ) ] );
	}

	public function delete_title() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( ! $this->repo->delete( $id ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not delete title.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'message' => __( 'Title deleted.', 'digitone-events' ) ] );
	}

	private function resolve_event_id() : ?string {
		$event_id = isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '';
		if ( $event_id === '' ) {
			$event_id = DigitOne_Events_Helpers_Event_Context::active_event_id();
		}
		return $event_id ?: null;
	}
}
