<?php
/**
 * Session Types AJAX endpoints.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Session_Types_Ajax {

	private DigitOne_Events_Session_Types_Repository $repo;

	public function __construct( DigitOne_Events_Session_Types_Repository $repo ) {
		$this->repo = $repo;
	}

	public function register() : void {
		add_action( 'wp_ajax_digitone_events_session_type_list',   [ $this, 'list_types' ] );
		add_action( 'wp_ajax_digitone_events_session_type_save',   [ $this, 'save_type' ] );
		add_action( 'wp_ajax_digitone_events_session_type_delete', [ $this, 'delete_type' ] );
	}

	public function list_types() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$event_id = $this->resolve_event_id();
		if ( ! $event_id ) {
			wp_send_json_error( [ 'message' => __( 'No active event.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'session_types' => $this->repo->all_for_event( $event_id ) ] );
	}

	public function save_type() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$event_id = $this->resolve_event_id();
		if ( ! $event_id ) {
			wp_send_json_error( [ 'message' => __( 'No active event.', 'digitone-events' ) ] );
		}
		$payload = [
			'id'       => isset( $_POST['id'] )    ? sanitize_text_field( wp_unslash( $_POST['id'] ) )    : '',
			'event_id' => $event_id,
			'name'     => isset( $_POST['name'] )  ? sanitize_text_field( wp_unslash( $_POST['name'] ) )  : '',
			'icon'     => isset( $_POST['icon'] )  ? sanitize_text_field( wp_unslash( $_POST['icon'] ) )  : '',
			'color'    => isset( $_POST['color'] ) ? sanitize_text_field( wp_unslash( $_POST['color'] ) ) : '',
		];
		if ( $payload['name'] === '' ) {
			wp_send_json_error( [ 'message' => __( 'Name is required.', 'digitone-events' ) ] );
		}
		$id = $this->repo->save( $payload );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Could not save session type.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'id' => $id, 'session_type' => $this->repo->find( $id ), 'message' => __( 'Session type saved.', 'digitone-events' ) ] );
	}

	public function delete_type() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( ! $this->repo->delete( $id ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not delete session type.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'message' => __( 'Session type deleted.', 'digitone-events' ) ] );
	}

	private function resolve_event_id() : ?string {
		$event_id = isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '';
		if ( $event_id === '' ) {
			$event_id = DigitOne_Events_Helpers_Event_Context::active_event_id();
		}
		return $event_id ?: null;
	}
}
