<?php
/**
 * Days module AJAX endpoints.
 *
 * Action names: digitone_events_day_{verb}.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Days_Ajax {

	private DigitOne_Events_Days_Repository $repo;

	public function __construct( DigitOne_Events_Days_Repository $repo ) {
		$this->repo = $repo;
	}

	public function register() : void {
		add_action( 'wp_ajax_digitone_events_day_list',        [ $this, 'list_days' ] );
		add_action( 'wp_ajax_digitone_events_day_get',         [ $this, 'get_day' ] );
		add_action( 'wp_ajax_digitone_events_day_save',        [ $this, 'save_day' ] );
		add_action( 'wp_ajax_digitone_events_day_delete',      [ $this, 'delete_day' ] );
		add_action( 'wp_ajax_digitone_events_day_bulk_delete', [ $this, 'bulk_delete' ] );
		add_action( 'wp_ajax_digitone_events_day_reorder',     [ $this, 'reorder' ] );
	}

	public function list_days() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$event_id = isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '';
		if ( $event_id === '' ) {
			$event_id = DigitOne_Events_Helpers_Event_Context::active_event_id();
		}
		if ( ! $event_id ) {
			wp_send_json_error( [ 'message' => __( 'No active event.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'days' => $this->repo->all_for_event( $event_id ) ] );
	}

	public function get_day() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$id  = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$day = $this->repo->find( $id );
		if ( ! $day ) {
			wp_send_json_error( [ 'message' => __( 'Day not found.', 'digitone-events' ) ], 404 );
		}
		wp_send_json_success( [ 'day' => $day ] );
	}

	public function save_day() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();

		$event_id = isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '';
		if ( $event_id === '' ) {
			$event_id = DigitOne_Events_Helpers_Event_Context::active_event_id() ?? '';
		}
		if ( $event_id === '' ) {
			wp_send_json_error( [ 'message' => __( 'No active event.', 'digitone-events' ) ] );
		}

		$payload = [
			'id'         => isset( $_POST['id'] )         ? sanitize_text_field( wp_unslash( $_POST['id'] ) )         : '',
			'event_id'   => $event_id,
			'day_date'   => isset( $_POST['day_date'] )   ? sanitize_text_field( wp_unslash( $_POST['day_date'] ) )   : '',
			'start_time' => isset( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : '',
			'end_time'   => isset( $_POST['end_time'] )   ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) )   : '',
			'label'      => isset( $_POST['label'] )      ? sanitize_text_field( wp_unslash( $_POST['label'] ) )      : '',
			'sort_order' => isset( $_POST['sort_order'] ) ? (int) $_POST['sort_order']                                : 0,
		];

		if ( $payload['day_date'] === '' ) {
			wp_send_json_error( [ 'message' => __( 'Date is required.', 'digitone-events' ) ] );
		}

		$id = $this->repo->save( $payload );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Could not save day. Check date and times.', 'digitone-events' ) ] );
		}

		wp_send_json_success( [
			'id'      => $id,
			'day'     => $this->repo->find( $id ),
			'message' => __( 'Day saved.', 'digitone-events' ),
		] );
	}

	public function delete_day() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( ! $this->repo->delete( $id ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not delete day.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'message' => __( 'Day deleted.', 'digitone-events' ) ] );
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
			/* translators: %d: number of days deleted */
			'message' => sprintf( _n( '%d day deleted.', '%d days deleted.', $deleted, 'digitone-events' ), $deleted ),
		] );
	}

	public function reorder() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$ids = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : [];
		$ids = array_filter( array_map( 'sanitize_text_field', $ids ) );
		if ( empty( $ids ) ) {
			wp_send_json_error( [ 'message' => __( 'No IDs provided.', 'digitone-events' ) ] );
		}
		$updated = $this->repo->reorder( $ids );
		wp_send_json_success( [ 'updated' => $updated ] );
	}
}
