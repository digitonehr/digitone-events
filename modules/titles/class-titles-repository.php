<?php
/**
 * Titles repository.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Titles_Repository {

	private function table() : string {
		return DigitOne_Events_Database_Schema::table( 'titles' );
	}

	/** @return array<int,array<string,mixed>> */
	public function all_for_event( string $event_id ) : array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE event_id = %s ORDER BY sort_order ASC, name ASC",
				$event_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : [];
	}

	public function find( string $id ) : ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %s", $id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	public function count_for_event( string $event_id ) : int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$this->table()} WHERE event_id = %s", $event_id )
		);
	}

	public function save( array $data ) : ?string {
		global $wpdb;
		$id       = isset( $data['id'] ) && $data['id'] !== '' ? (string) $data['id'] : '';
		$is_new   = $id === '';
		$event_id = isset( $data['event_id'] ) ? (string) $data['event_id'] : '';
		$name     = isset( $data['name'] ) ? trim( (string) $data['name'] ) : '';
		if ( $event_id === '' || $name === '' ) {
			return null;
		}

		$row = [
			'event_id'   => $event_id,
			'name'       => $name,
			'sort_order' => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
		];
		$formats = [ '%s', '%s', '%d' ];

		if ( $is_new ) {
			$row['id'] = DigitOne_Events_Helpers_Format::uuid();
			$formats[] = '%s';
			$ok = $wpdb->insert( $this->table(), $row, $formats );
			return $ok ? $row['id'] : null;
		}

		$ok = $wpdb->update( $this->table(), $row, [ 'id' => $id ], $formats, [ '%s' ] );
		return $ok !== false ? $id : null;
	}

	public function delete( string $id ) : bool {
		global $wpdb;
		if ( ! $this->find( $id ) ) {
			return false;
		}
		// Clear references in speakers.
		$speakers = DigitOne_Events_Database_Schema::table( 'speakers' );
		$wpdb->update( $speakers, [ 'title_id' => null ], [ 'title_id' => $id ], [ '%s' ], [ '%s' ] );
		return (bool) $wpdb->delete( $this->table(), [ 'id' => $id ], [ '%s' ] );
	}
}
