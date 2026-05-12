<?php
/**
 * Roles AJAX endpoints.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Roles_Ajax {

	private DigitOne_Events_Roles_Repository $repo;

	public function __construct( DigitOne_Events_Roles_Repository $repo ) {
		$this->repo = $repo;
	}

	public function register() : void {
		add_action( 'wp_ajax_digitone_events_role_list',   [ $this, 'list_roles' ] );
		add_action( 'wp_ajax_digitone_events_role_save',   [ $this, 'save_role' ] );
		add_action( 'wp_ajax_digitone_events_role_delete', [ $this, 'delete_role' ] );
	}

	public function list_roles() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$event_id = $this->resolve_event_id();
		if ( ! $event_id ) {
			wp_send_json_error( [ 'message' => __( 'No active event.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'roles' => $this->repo->all_for_event( $event_id ) ] );
	}

	public function save_role() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$event_id = $this->resolve_event_id();
		if ( ! $event_id ) {
			wp_send_json_error( [ 'message' => __( 'No active event.', 'digitone-events' ) ] );
		}
		$payload = [
			'id'       => isset( $_POST['id'] )    ? sanitize_text_field( wp_unslash( $_POST['id'] ) )    : '',
			'event_id' => $event_id,
			'name'     => isset( $_POST['name'] )  ? sanitize_text_field( wp_unslash( $_POST['name'] ) )  : '',
			'color'    => isset( $_POST['color'] ) ? sanitize_text_field( wp_unslash( $_POST['color'] ) ) : '',
		];
		if ( $payload['name'] === '' ) {
			wp_send_json_error( [ 'message' => __( 'Name is required.', 'digitone-events' ) ] );
		}
		$id = $this->repo->save( $payload );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Could not save role.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'id' => $id, 'role' => $this->repo->find( $id ), 'message' => __( 'Role saved.', 'digitone-events' ) ] );
	}

	public function delete_role() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( ! $this->repo->delete( $id ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not delete role.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'message' => __( 'Role deleted.', 'digitone-events' ) ] );
	}

	private function resolve_event_id() : ?string {
		$event_id = isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '';
		if ( $event_id === '' ) {
			$event_id = DigitOne_Events_Helpers_Event_Context::active_event_id();
		}
		return $event_id ?: null;
	}
}
