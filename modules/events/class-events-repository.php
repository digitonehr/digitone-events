<?php
/**
 * Events repository — all SQL for the events table lives here.
 *
 * Every method uses $wpdb->prepare for any user-supplied value.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Events_Repository {

	private function table() : string {
		return DigitOne_Events_Database_Schema::table( 'events' );
	}

	/** @return array<int,array<string,mixed>> */
	public function all( ?string $status = null ) : array {
		global $wpdb;
		$t = $this->table();
		if ( $status ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$t} WHERE status = %s ORDER BY created_at DESC", $status ),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY created_at DESC", ARRAY_A );
		}
		return is_array( $rows ) ? $rows : [];
	}

	public function find( string $id ) : ?array {
		global $wpdb;
		$t   = $this->table();
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$t} WHERE id = %s", $id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	public function find_by_slug( string $slug ) : ?array {
		global $wpdb;
		$t   = $this->table();
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$t} WHERE slug = %s", $slug ),
			ARRAY_A
		);
		return $row ?: null;
	}

	public function count_all() : int {
		global $wpdb;
		$t = $this->table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" );
	}

	/**
	 * Insert or update an event.
	 *
	 * @param array{
	 *   id?:string, name:string, slug?:string, description?:string,
	 *   start_date?:string, end_date?:string, status?:string
	 * } $data
	 * @return string|null Event ID on success, null on failure.
	 */
	public function save( array $data ) : ?string {
		global $wpdb;
		$t = $this->table();

		$id      = isset( $data['id'] ) && $data['id'] !== '' ? (string) $data['id'] : '';
		$is_new  = $id === '';
		$name    = isset( $data['name'] ) ? trim( (string) $data['name'] ) : '';
		if ( $name === '' ) {
			return null;
		}

		$slug = isset( $data['slug'] ) && $data['slug'] !== ''
			? sanitize_title( $data['slug'] )
			: sanitize_title( $name );
		if ( $slug === '' ) {
			$slug = 'event';
		}
		$slug = $this->ensure_unique_slug( $slug, $is_new ? null : $id );

		$start = isset( $data['start_date'] ) ? DigitOne_Events_Helpers_Format::date( $data['start_date'] ) : '';
		$end   = isset( $data['end_date'] )   ? DigitOne_Events_Helpers_Format::date( $data['end_date'] )   : '';
		if ( $start && $end && strcmp( $start, $end ) > 0 ) {
			return null;
		}

		$status = isset( $data['status'] ) ? (string) $data['status'] : 'draft';
		if ( ! in_array( $status, [ 'draft', 'published', 'archived' ], true ) ) {
			$status = 'draft';
		}

		$row = [
			'name'        => $name,
			'slug'        => $slug,
			'description' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
			'start_date'  => $start ?: null,
			'end_date'    => $end ?: null,
			'status'      => $status,
			'updated_at'  => current_time( 'mysql' ),
		];
		$formats = [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ];

		if ( $is_new ) {
			$row['id']         = DigitOne_Events_Helpers_Format::uuid();
			$row['created_at'] = current_time( 'mysql' );
			$row['created_by'] = get_current_user_id();
			$formats[]         = '%s'; // id
			$formats[]         = '%s'; // created_at
			$formats[]         = '%d'; // created_by

			$ok = $wpdb->insert( $t, $row, $formats );
			return $ok ? $row['id'] : null;
		}

		$ok = $wpdb->update( $t, $row, [ 'id' => $id ], $formats, [ '%s' ] );
		return $ok !== false ? $id : null;
	}

	/**
	 * Delete an event and cascade to all related tables (PHP-level cascade).
	 */
	public function delete( string $id ) : bool {
		global $wpdb;
		if ( ! $this->find( $id ) ) {
			return false;
		}

		$tables = [
			DigitOne_Events_Database_Schema::table( 'sessions' ),
			DigitOne_Events_Database_Schema::table( 'session_types' ),
			DigitOne_Events_Database_Schema::table( 'speakers' ),
			DigitOne_Events_Database_Schema::table( 'venues' ),
			DigitOne_Events_Database_Schema::table( 'days' ),
			DigitOne_Events_Database_Schema::table( 'titles' ),
			DigitOne_Events_Database_Schema::table( 'roles' ),
		];

		// Wrap in implicit transaction (best-effort; MyISAM ignores).
		$wpdb->query( 'START TRANSACTION' );

		foreach ( $tables as $tbl ) {
			$wpdb->delete( $tbl, [ 'event_id' => $id ], [ '%s' ] );
		}

		// Also clean junction tables (no event_id, so wipe orphans).
		$sr = DigitOne_Events_Database_Schema::table( 'speakers_roles' );
		$sp = DigitOne_Events_Database_Schema::table( 'speakers' );
		$wpdb->query( "DELETE sr FROM {$sr} sr LEFT JOIN {$sp} s ON sr.speaker_id = s.id WHERE s.id IS NULL" );

		$sn  = DigitOne_Events_Database_Schema::table( 'session_roles' );
		$sess = DigitOne_Events_Database_Schema::table( 'sessions' );
		$wpdb->query( "DELETE sn FROM {$sn} sn LEFT JOIN {$sess} s ON sn.session_id = s.id WHERE s.id IS NULL" );

		// Finally, the event itself.
		$ok = (bool) $wpdb->delete( $this->table(), [ 'id' => $id ], [ '%s' ] );

		$wpdb->query( $ok ? 'COMMIT' : 'ROLLBACK' );

		// Clear active-event reference for any user pointing at it.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
				'digitone_events_active_event',
				$id
			)
		);

		return $ok;
	}

	public function set_status( string $id, string $status ) : bool {
		if ( ! in_array( $status, [ 'draft', 'published', 'archived' ], true ) ) {
			return false;
		}
		global $wpdb;
		$updated = $wpdb->update(
			$this->table(),
			[ 'status' => $status, 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%s' ]
		);
		return $updated !== false;
	}

	private function ensure_unique_slug( string $slug, ?string $exclude_id ) : string {
		global $wpdb;
		$t    = $this->table();
		$base = $slug;
		$i    = 2;
		while ( true ) {
			if ( $exclude_id ) {
				$exists = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$t} WHERE slug = %s AND id <> %s LIMIT 1",
					$slug, $exclude_id
				) );
			} else {
				$exists = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$t} WHERE slug = %s LIMIT 1",
					$slug
				) );
			}
			if ( ! $exists ) {
				return $slug;
			}
			$slug = $base . '-' . $i;
			$i++;
			if ( $i > 200 ) {
				return $base . '-' . wp_generate_password( 6, false );
			}
		}
	}
}
