<?php
/**
 * Days repository. All SQL for the days table lives here.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Days_Repository {

	private function table() : string {
		return DigitOne_Events_Database_Schema::table( 'days' );
	}

	/** @return array<int,array<string,mixed>> */
	public function all_for_event( string $event_id ) : array {
		global $wpdb;
		$t = $this->table();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE event_id = %s ORDER BY day_date ASC, start_time ASC, sort_order ASC",
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

	public function count_all() : int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table()}" );
	}

	/**
	 * Save (insert or update) a day. Returns the day ID or null on failure.
	 *
	 * @param array{
	 *   id?:string, event_id:string, day_date:string,
	 *   start_time?:string, end_time?:string, label?:string, sort_order?:int
	 * } $data
	 */
	public function save( array $data ) : ?string {
		global $wpdb;
		$t = $this->table();

		$id       = isset( $data['id'] ) && $data['id'] !== '' ? (string) $data['id'] : '';
		$is_new   = $id === '';
		$event_id = isset( $data['event_id'] ) ? (string) $data['event_id'] : '';
		if ( $event_id === '' ) {
			return null;
		}

		$date = isset( $data['day_date'] ) ? DigitOne_Events_Helpers_Format::date( $data['day_date'] ) : '';
		if ( $date === '' ) {
			return null;
		}

		$start = isset( $data['start_time'] ) ? DigitOne_Events_Helpers_Format::time( $data['start_time'] ) : '';
		$end   = isset( $data['end_time'] )   ? DigitOne_Events_Helpers_Format::time( $data['end_time'] )   : '';
		if ( $start && $end && strcmp( $start, $end ) > 0 ) {
			return null;
		}

		$row = [
			'event_id'   => $event_id,
			'day_date'   => $date,
			'start_time' => $start ?: null,
			'end_time'   => $end ?: null,
			'label'      => isset( $data['label'] ) ? sanitize_text_field( $data['label'] ) : null,
			'sort_order' => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
		];
		$formats = [ '%s', '%s', '%s', '%s', '%s', '%d' ];

		if ( $is_new ) {
			$row['id']         = DigitOne_Events_Helpers_Format::uuid();
			$row['created_at'] = current_time( 'mysql' );
			$formats[]         = '%s';
			$formats[]         = '%s';
			$ok = $wpdb->insert( $t, $row, $formats );
			return $ok ? $row['id'] : null;
		}

		$ok = $wpdb->update( $t, $row, [ 'id' => $id ], $formats, [ '%s' ] );
		return $ok !== false ? $id : null;
	}

	public function delete( string $id ) : bool {
		global $wpdb;
		if ( ! $this->find( $id ) ) {
			return false;
		}

		// Cascade: delete sessions belonging to this day.
		$sessions_table = DigitOne_Events_Database_Schema::table( 'sessions' );
		$session_roles  = DigitOne_Events_Database_Schema::table( 'session_roles' );

		// Get session IDs to clean junction.
		$session_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT id FROM {$sessions_table} WHERE day_id = %s", $id )
		);
		if ( ! empty( $session_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%s' ) );
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$session_roles} WHERE session_id IN ({$placeholders})",
				...$session_ids
			) );
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$sessions_table} WHERE day_id = %s",
				$id
			) );
		}

		return (bool) $wpdb->delete( $this->table(), [ 'id' => $id ], [ '%s' ] );
	}

	public function reorder( array $ordered_ids ) : int {
		global $wpdb;
		$updated = 0;
		foreach ( array_values( $ordered_ids ) as $idx => $id ) {
			$id = sanitize_text_field( $id );
			if ( $id === '' ) {
				continue;
			}
			$ok = $wpdb->update(
				$this->table(),
				[ 'sort_order' => $idx ],
				[ 'id' => $id ],
				[ '%d' ],
				[ '%s' ]
			);
			if ( $ok !== false ) {
				$updated++;
			}
		}
		return $updated;
	}
}
