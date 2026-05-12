<?php
/**
 * Speakers AJAX endpoints.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Speakers_Ajax {

	private DigitOne_Events_Speakers_Repository $repo;

	public function __construct( DigitOne_Events_Speakers_Repository $repo ) {
		$this->repo = $repo;
	}

	public function register() : void {
		add_action( 'wp_ajax_digitone_events_speaker_list',        [ $this, 'list_speakers' ] );
		add_action( 'wp_ajax_digitone_events_speaker_get',         [ $this, 'get_speaker' ] );
		add_action( 'wp_ajax_digitone_events_speaker_save',        [ $this, 'save_speaker' ] );
		add_action( 'wp_ajax_digitone_events_speaker_delete',      [ $this, 'delete_speaker' ] );
		add_action( 'wp_ajax_digitone_events_speaker_bulk_delete', [ $this, 'bulk_delete' ] );
	}

	public function list_speakers() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$event_id = $this->resolve_event_id();
		if ( ! $event_id ) {
			wp_send_json_error( [ 'message' => __( 'No active event.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'speakers' => $this->repo->all_for_event( $event_id ) ] );
	}

	public function get_speaker() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$id      = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$speaker = $this->repo->find( $id );
		if ( ! $speaker ) {
			wp_send_json_error( [ 'message' => __( 'Speaker not found.', 'digitone-events' ) ], 404 );
		}
		wp_send_json_success( [ 'speaker' => $speaker ] );
	}

	public function save_speaker() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();

		$event_id = $this->resolve_event_id();
		if ( ! $event_id ) {
			wp_send_json_error( [ 'message' => __( 'No active event.', 'digitone-events' ) ] );
		}

		$role_ids = isset( $_POST['role_ids'] ) ? (array) wp_unslash( $_POST['role_ids'] ) : [];
		$role_ids = array_filter( array_map( 'sanitize_text_field', $role_ids ) );

		$payload = [
			'id'         => isset( $_POST['id'] )         ? sanitize_text_field( wp_unslash( $_POST['id'] ) )         : '',
			'event_id'   => $event_id,
			'title_id'   => isset( $_POST['title_id'] )   ? sanitize_text_field( wp_unslash( $_POST['title_id'] ) )   : '',
			'first_name' => isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '',
			'last_name'  => isset( $_POST['last_name'] )  ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) )  : '',
			'email'      => isset( $_POST['email'] )      ? sanitize_email( wp_unslash( $_POST['email'] ) )           : '',
			'photo_url'  => isset( $_POST['photo_url'] )  ? esc_url_raw( wp_unslash( $_POST['photo_url'] ) )          : '',
			'bio'        => isset( $_POST['bio'] )        ? wp_kses_post( wp_unslash( $_POST['bio'] ) )               : '',
			'role_ids'   => array_values( $role_ids ),
		];

		if ( $payload['first_name'] === '' && $payload['last_name'] === '' ) {
			wp_send_json_error( [ 'message' => __( 'At least a first or last name is required.', 'digitone-events' ) ] );
		}
		if ( $payload['email'] !== '' && ! is_email( $payload['email'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid email address.', 'digitone-events' ) ] );
		}

		$id = $this->repo->save( $payload );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Could not save speaker.', 'digitone-events' ) ] );
		}

		wp_send_json_success( [
			'id'      => $id,
			'speaker' => $this->repo->find( $id ),
			'message' => __( 'Speaker saved.', 'digitone-events' ),
		] );
	}

	public function delete_speaker() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( ! $this->repo->delete( $id ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not delete speaker.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'message' => __( 'Speaker deleted.', 'digitone-events' ) ] );
	}

	public function bulk_delete() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$ids = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : [];
		$ids = array_filter( array_map( 'sanitize_text_field', $ids ) );

		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( $this->repo->delete( $id ) ) {
				$deleted++;
			}
		}
		wp_send_json_success( [
			'deleted' => $deleted,
			/* translators: %d: number of speakers deleted */
			'message' => sprintf( _n( '%d speaker deleted.', '%d speakers deleted.', $deleted, 'digitone-events' ), $deleted ),
		] );
	}

	private function resolve_event_id() : ?string {
		$event_id = isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '';
		if ( $event_id === '' ) {
			$event_id = DigitOne_Events_Helpers_Event_Context::active_event_id();
		}
		return $event_id ?: null;
	}
}
