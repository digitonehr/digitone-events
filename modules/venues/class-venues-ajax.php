<?php
/**
 * Venues module AJAX endpoints.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Venues_Ajax {

	private DigitOne_Events_Venues_Repository $repo;

	public function __construct( DigitOne_Events_Venues_Repository $repo ) {
		$this->repo = $repo;
	}

	public function register() : void {
		add_action( 'wp_ajax_digitone_events_venue_list',        [ $this, 'list_venues' ] );
		add_action( 'wp_ajax_digitone_events_venue_get',         [ $this, 'get_venue' ] );
		add_action( 'wp_ajax_digitone_events_venue_save',        [ $this, 'save_venue' ] );
		add_action( 'wp_ajax_digitone_events_venue_delete',      [ $this, 'delete_venue' ] );
		add_action( 'wp_ajax_digitone_events_venue_bulk_delete', [ $this, 'bulk_delete' ] );
	}

	public function list_venues() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$event_id = isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '';
		if ( $event_id === '' ) {
			$event_id = DigitOne_Events_Helpers_Event_Context::active_event_id();
		}
		if ( ! $event_id ) {
			wp_send_json_error( [ 'message' => __( 'No active event.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'venues' => $this->repo->all_for_event( $event_id ) ] );
	}

	public function get_venue() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$id    = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$venue = $this->repo->find( $id );
		if ( ! $venue ) {
			wp_send_json_error( [ 'message' => __( 'Venue not found.', 'digitone-events' ) ], 404 );
		}
		wp_send_json_success( [ 'venue' => $venue ] );
	}

	public function save_venue() : void {
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
			'name'       => isset( $_POST['name'] )       ? sanitize_text_field( wp_unslash( $_POST['name'] ) )       : '',
			'address'    => isset( $_POST['address'] )    ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '',
			'venue_type' => isset( $_POST['venue_type'] ) ? sanitize_text_field( wp_unslash( $_POST['venue_type'] ) ) : 'primary',
			'parent_id'  => isset( $_POST['parent_id'] )  ? sanitize_text_field( wp_unslash( $_POST['parent_id'] ) )  : '',
			'sort_order' => isset( $_POST['sort_order'] ) ? (int) $_POST['sort_order']                                : 0,
		];

		if ( $payload['name'] === '' ) {
			wp_send_json_error( [ 'message' => __( 'Venue name is required.', 'digitone-events' ) ] );
		}
		if ( $payload['venue_type'] === 'sub-venue' && $payload['parent_id'] === '' ) {
			wp_send_json_error( [ 'message' => __( 'Sub-venue requires a parent venue.', 'digitone-events' ) ] );
		}

		$id = $this->repo->save( $payload );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Could not save venue. Check parent venue and try again.', 'digitone-events' ) ] );
		}

		wp_send_json_success( [
			'id'      => $id,
			'venue'   => $this->repo->find( $id ),
			'message' => __( 'Venue saved.', 'digitone-events' ),
		] );
	}

	public function delete_venue() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( ! $this->repo->delete( $id ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not delete venue.', 'digitone-events' ) ] );
		}
		wp_send_json_success( [ 'message' => __( 'Venue deleted.', 'digitone-events' ) ] );
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
			/* translators: %d: number of venues deleted */
			'message' => sprintf( _n( '%d venue deleted.', '%d venues deleted.', $deleted, 'digitone-events' ), $deleted ),
		] );
	}
}
