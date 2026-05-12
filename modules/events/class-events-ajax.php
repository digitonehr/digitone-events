<?php
/**
 * Events module AJAX endpoints.
 *
 * Every handler starts with:  DigitOne_Events_Security_Nonce::verify_ajax();
 * That centralizes capability + nonce + 403 response in one line.
 *
 * Action names follow the convention: digitone_events_event_{verb}
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Events_Ajax {

	private DigitOne_Events_Events_Repository $repo;

	public function __construct( DigitOne_Events_Events_Repository $repo ) {
		$this->repo = $repo;
	}

	public function register() : void {
		add_action( 'wp_ajax_digitone_events_event_list',       [ $this, 'list_events' ] );
		add_action( 'wp_ajax_digitone_events_event_get',        [ $this, 'get_event' ] );
		add_action( 'wp_ajax_digitone_events_event_save',       [ $this, 'save_event' ] );
		add_action( 'wp_ajax_digitone_events_event_delete',     [ $this, 'delete_event' ] );
		add_action( 'wp_ajax_digitone_events_event_bulk_delete', [ $this, 'bulk_delete' ] );
		add_action( 'wp_ajax_digitone_events_event_set_active', [ $this, 'set_active' ] );
		add_action( 'wp_ajax_digitone_events_event_set_status', [ $this, 'set_status' ] );
	}

	public function list_events() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		wp_send_json_success( [ 'events' => $this->repo->all() ] );
	}

	public function get_event() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$id    = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$event = $this->repo->find( $id );
		if ( ! $event ) {
			wp_send_json_error( [ 'message' => __( 'Event not found.', 'digitone-events' ) ], 404 );
		}
		wp_send_json_success( [ 'event' => $event ] );
	}

	public function save_event() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();

		$payload = [
			'id'          => isset( $_POST['id'] )          ? sanitize_text_field( wp_unslash( $_POST['id'] ) )          : '',
			'name'        => isset( $_POST['name'] )        ? sanitize_text_field( wp_unslash( $_POST['name'] ) )        : '',
			'slug'        => isset( $_POST['slug'] )        ? sanitize_title( wp_unslash( $_POST['slug'] ) )             : '',
			'description' => isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) )         : '',
			'start_date'  => isset( $_POST['start_date'] )  ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) )  : '',
			'end_date'    => isset( $_POST['end_date'] )    ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) )    : '',
			'status'      => isset( $_POST['status'] )      ? sanitize_text_field( wp_unslash( $_POST['status'] ) )      : 'draft',
		];

		if ( $payload['name'] === '' ) {
			wp_send_json_error( [ 'message' => __( 'Event name is required.', 'digitone-events' ) ] );
		}

		$id = $this->repo->save( $payload );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Could not save event. Check dates and try again.', 'digitone-events' ) ] );
		}

		wp_send_json_success( [
			'id'      => $id,
			'event'   => $this->repo->find( $id ),
			'message' => __( 'Event saved.', 'digitone-events' ),
		] );
	}

	public function delete_event() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$ok = $this->repo->delete( $id );
		if ( ! $ok ) {
			wp_send_json_error( [ 'message' => __( 'Could not delete event.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'message' => __( 'Event deleted.', 'digitone-events' ) ] );
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
			/* translators: %d: number of events deleted */
			'message' => sprintf( _n( '%d event deleted.', '%d events deleted.', $deleted, 'digitone-events' ), $deleted ),
		] );
	}

	public function set_active() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( ! DigitOne_Events_Helpers_Event_Context::set_active_event( $id ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not set active event.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'activeEvent' => $id ] );
	}

	public function set_status() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$id     = isset( $_POST['id'] )     ? sanitize_text_field( wp_unslash( $_POST['id'] ) )     : '';
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		if ( ! $this->repo->set_status( $id, $status ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid status or event ID.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'message' => __( 'Status updated.', 'digitone-events' ) ] );
	}
}
