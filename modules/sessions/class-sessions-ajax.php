<?php
/**
 * Sessions module AJAX endpoints.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Sessions_Ajax {

	private DigitOne_Events_Sessions_Repository $repo;

	public function __construct( DigitOne_Events_Sessions_Repository $repo ) {
		$this->repo = $repo;
	}

	public function register() : void {
		add_action( 'wp_ajax_digitone_events_session_list',        [ $this, 'list_sessions' ] );
		add_action( 'wp_ajax_digitone_events_session_get',         [ $this, 'get_session' ] );
		add_action( 'wp_ajax_digitone_events_session_save',        [ $this, 'save_session' ] );
		add_action( 'wp_ajax_digitone_events_session_delete',      [ $this, 'delete_session' ] );
		add_action( 'wp_ajax_digitone_events_session_bulk_delete', [ $this, 'bulk_delete' ] );
		add_action( 'wp_ajax_digitone_events_session_masters_for_day', [ $this, 'masters_for_day' ] );
	}

	public function list_sessions() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$day_id = isset( $_POST['day_id'] ) ? sanitize_text_field( wp_unslash( $_POST['day_id'] ) ) : '';
		if ( $day_id === '' ) {
			wp_send_json_error( [ 'message' => __( 'Day ID required.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'sessions' => $this->repo->all_for_day( $day_id ) ] );
	}

	public function masters_for_day() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$day_id = isset( $_POST['day_id'] ) ? sanitize_text_field( wp_unslash( $_POST['day_id'] ) ) : '';
		if ( $day_id === '' ) {
			wp_send_json_error( [ 'message' => __( 'Day ID required.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'masters' => $this->repo->masters_for_day( $day_id ) ] );
	}

	public function get_session() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$id      = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$session = $this->repo->find( $id );
		if ( ! $session ) {
			wp_send_json_error( [ 'message' => __( 'Session not found.', 'digitone-events' ) ], 404 );
		}
		wp_send_json_success( [ 'session' => $session ] );
	}

	public function save_session() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();

		$event_id = isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '';
		if ( $event_id === '' ) {
			$event_id = DigitOne_Events_Helpers_Event_Context::active_event_id() ?? '';
		}
		if ( $event_id === '' ) {
			wp_send_json_error( [ 'message' => __( 'No active event.', 'digitone-events' ) ] );
		}

		$speaker_ids = isset( $_POST['speaker_ids'] ) ? (array) wp_unslash( $_POST['speaker_ids'] ) : [];
		$speaker_ids = array_filter( array_map( 'sanitize_text_field', $speaker_ids ) );

		$payload = [
			'id'              => isset( $_POST['id'] )              ? sanitize_text_field( wp_unslash( $_POST['id'] ) )              : '',
			'event_id'        => $event_id,
			'day_id'          => isset( $_POST['day_id'] )          ? sanitize_text_field( wp_unslash( $_POST['day_id'] ) )          : '',
			'parent_id'       => isset( $_POST['parent_id'] )       ? sanitize_text_field( wp_unslash( $_POST['parent_id'] ) )       : '',
			'session_type_id' => isset( $_POST['session_type_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_type_id'] ) ) : '',
			'venue_id'        => isset( $_POST['venue_id'] )        ? sanitize_text_field( wp_unslash( $_POST['venue_id'] ) )        : '',
			'sub_venue_id'    => isset( $_POST['sub_venue_id'] )    ? sanitize_text_field( wp_unslash( $_POST['sub_venue_id'] ) )    : '',
			'title'           => isset( $_POST['title'] )           ? sanitize_text_field( wp_unslash( $_POST['title'] ) )           : '',
			'description'     => isset( $_POST['description'] )     ? wp_kses_post( wp_unslash( $_POST['description'] ) )            : '',
			'start_time'      => isset( $_POST['start_time'] )      ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) )      : '',
			'end_time'        => isset( $_POST['end_time'] )        ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) )        : '',
			'session_level'   => isset( $_POST['session_level'] )   ? sanitize_text_field( wp_unslash( $_POST['session_level'] ) )   : 'master',
			'speaker_ids'     => array_values( $speaker_ids ),
			'default_role_id' => isset( $_POST['default_role_id'] ) ? sanitize_text_field( wp_unslash( $_POST['default_role_id'] ) ) : '',
		];

		if ( $payload['title'] === '' ) {
			wp_send_json_error( [ 'message' => __( 'Session title is required.', 'digitone-events' ) ] );
		}
		if ( $payload['day_id'] === '' ) {
			wp_send_json_error( [ 'message' => __( 'Day is required.', 'digitone-events' ) ] );
		}

		$id = $this->repo->save( $payload );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Could not save session. Check fields and try again.', 'digitone-events' ) ] );
		}

		wp_send_json_success( [
			'id'      => $id,
			'session' => $this->repo->find( $id ),
			'message' => __( 'Session saved.', 'digitone-events' ),
		] );
	}

	public function delete_session() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( ! $this->repo->delete( $id ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not delete session.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'message' => __( 'Session deleted.', 'digitone-events' ) ] );
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
			/* translators: %d: number of sessions deleted */
			'message' => sprintf( _n( '%d session deleted.', '%d sessions deleted.', $deleted, 'digitone-events' ), $deleted ),
		] );
	}

}
