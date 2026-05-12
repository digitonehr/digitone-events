<?php
/**
 * Venues repository — all SQL for the venues table lives here.
 *
 * Supports two-level hierarchy: primary venues and their sub-venues.
 * Deleting a primary cascades to its sub-venues (PHP-level).
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Venues_Repository {

	private function table() : string {
		return DigitOne_Events_Database_Schema::table( 'venues' );
	}

	/** @return array<int,array<string,mixed>> */
	public function all_for_event( string $event_id ) : array {
		global $wpdb;
		$t = $this->table();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE event_id = %s ORDER BY venue_type DESC, sort_order ASC, name ASC",
				$event_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : [];
	}

	/** @return array<int,array<string,mixed>> */
	public function primaries_for_event( string $event_id ) : array {
		global $wpdb;
		$t = $this->table();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE event_id = %s AND venue_type = 'primary' ORDER BY sort_order ASC, name ASC",
				$event_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Returns primary venues each with a 'sub_venues' key containing children.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function tree_for_event( string $event_id ) : array {
		$all = $this->all_for_event( $event_id );
		$by_parent = [];
		$primaries = [];

		foreach ( $all as $v ) {
			if ( $v['venue_type'] === 'sub-venue' && $v['parent_id'] ) {
				$by_parent[ $v['parent_id'] ][] = $v;
			} elseif ( $v['venue_type'] === 'primary' ) {
				$primaries[] = $v;
			}
		}

		foreach ( $primaries as &$p ) {
			$p['sub_venues'] = $by_parent[ $p['id'] ] ?? [];
		}
		unset( $p );

		return $primaries;
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
	 * Save (insert or update) a venue.
	 *
	 * @param array{
	 *   id?:string, event_id:string, name:string, address?:string,
	 *   venue_type?:string, parent_id?:string, sort_order?:int
	 * } $data
	 */
	public function save( array $data ) : ?string {
		global $wpdb;
		$t = $this->table();

		$id       = isset( $data['id'] ) && $data['id'] !== '' ? (string) $data['id'] : '';
		$is_new   = $id === '';
		$event_id = isset( $data['event_id'] ) ? (string) $data['event_id'] : '';
		$name     = isset( $data['name'] ) ? trim( (string) $data['name'] ) : '';

		if ( $event_id === '' || $name === '' ) {
			return null;
		}

		$type = isset( $data['venue_type'] ) && $data['venue_type'] === 'sub-venue' ? 'sub-venue' : 'primary';
		$parent_id = null;

		if ( $type === 'sub-venue' ) {
			$parent_id = isset( $data['parent_id'] ) ? (string) $data['parent_id'] : '';
			if ( $parent_id === '' ) {
				return null;
			}
			// Parent must exist and belong to same event, must itself be primary.
			$parent = $this->find( $parent_id );
			if ( ! $parent || $parent['event_id'] !== $event_id || $parent['venue_type'] !== 'primary' ) {
				return null;
			}
			// Cannot make a venue its own sub-venue.
			if ( ! $is_new && $parent_id === $id ) {
				return null;
			}
		}

		$row = [
			'event_id'   => $event_id,
			'parent_id'  => $parent_id,
			'name'       => $name,
			'address'    => isset( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : null,
			'venue_type' => $type,
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

	/**
	 * Delete a venue. If it's a primary, all sub-venues are cascaded.
	 * Sessions referencing the venue have their venue_id / sub_venue_id cleared.
	 */
	public function delete( string $id ) : bool {
		global $wpdb;
		$venue = $this->find( $id );
		if ( ! $venue ) {
			return false;
		}

		$sessions_table = DigitOne_Events_Database_Schema::table( 'sessions' );

		if ( $venue['venue_type'] === 'primary' ) {
			// Find sub-venues for cascade.
			$subs = $wpdb->get_col(
				$wpdb->prepare( "SELECT id FROM {$this->table()} WHERE parent_id = %s", $id )
			);

			// Null out references in sessions for both primary and sub-venues.
			$all_ids = array_merge( [ $id ], $subs );
			if ( ! empty( $all_ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $all_ids ), '%s' ) );
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$sessions_table} SET venue_id = NULL WHERE venue_id IN ({$placeholders})",
					...$all_ids
				) );
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$sessions_table} SET sub_venue_id = NULL WHERE sub_venue_id IN ({$placeholders})",
					...$all_ids
				) );
			}

			// Delete sub-venues.
			if ( ! empty( $subs ) ) {
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$this->table()} WHERE parent_id = %s",
					$id
				) );
			}
		} else {
			// Sub-venue: clear references only for this id.
			$wpdb->update( $sessions_table, [ 'sub_venue_id' => null ], [ 'sub_venue_id' => $id ], [ '%s' ], [ '%s' ] );
		}

		return (bool) $wpdb->delete( $this->table(), [ 'id' => $id ], [ '%s' ] );
	}
}
