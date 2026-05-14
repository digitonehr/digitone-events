<?php
/**
 * Sessions CSV importer.
 *
 * Reads a CSV with header row + one session per row. Resolves human-readable
 * names (day date, venue name, type name, speaker names, role name) to
 * internal UUIDs by case-insensitive lookup against the event's own data.
 * Anything that doesn't resolve becomes a skipped row in the report; rows
 * that do resolve are inserted via the regular `save()` method on the
 * sessions repository, so all the usual validation + uuid generation +
 * speaker-junction-table sync logic kicks in unchanged.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Sessions_Csv_Importer {

	/** Expected CSV columns. Case-insensitive header match. */
	public const COLUMNS = [
		'day',           // YYYY-MM-DD
		'start_time',    // HH:MM
		'end_time',      // HH:MM
		'title',         // string
		'session_type',  // type name
		'venue',         // primary venue name
		'sub_venue',     // optional, sub-venue name within the venue
		'speakers',      // optional, semicolon-separated "First Last" names
		'default_role',  // optional, role name applied to all speakers
		'description',   // optional
	];

	/**
	 * @return array {
	 *   imported: int,
	 *   skipped:  int,
	 *   errors:   array<int, array{ row:int, reason:string, raw:array }>,
	 *   created:  array<int, string>  array of new session IDs
	 * }
	 */
	public function import( string $csv_content, string $event_id ) : array {
		$report = [
			'imported' => 0,
			'skipped'  => 0,
			'errors'   => [],
			'created'  => [],
		];

		// Strip UTF-8 BOM if present.
		if ( substr( $csv_content, 0, 3 ) === "\xEF\xBB\xBF" ) {
			$csv_content = substr( $csv_content, 3 );
		}

		$rows = $this->parse_csv( $csv_content );
		if ( count( $rows ) < 2 ) {
			$report['errors'][] = [
				'row'    => 0,
				'reason' => __( 'CSV is empty or has no data rows.', 'digitone-events' ),
				'raw'    => [],
			];
			return $report;
		}

		// First row = header. Normalize keys.
		$header = array_map( function ( $h ) {
			return strtolower( trim( (string) $h ) );
		}, array_shift( $rows ) );

		// Build column-name => column-index map.
		$col_index = [];
		foreach ( self::COLUMNS as $name ) {
			$idx = array_search( $name, $header, true );
			if ( $idx !== false ) {
				$col_index[ $name ] = $idx;
			}
		}

		// Required columns check.
		foreach ( [ 'day', 'start_time', 'end_time', 'title' ] as $must ) {
			if ( ! isset( $col_index[ $must ] ) ) {
				$report['errors'][] = [
					'row'    => 0,
					'reason' => sprintf(
						/* translators: %s: column name */
						__( 'Missing required column: %s', 'digitone-events' ),
						$must
					),
					'raw'    => $header,
				];
				return $report;
			}
		}

		// Build lookup caches once.
		$caches = $this->build_caches( $event_id );

		$sessions_repo = DigitOne_Events_Plugin::instance()->module( 'sessions' )->repo();
		$row_num       = 1; // 1 = header; data starts at 2

		foreach ( $rows as $row ) {
			$row_num++;
			if ( $this->row_is_empty( $row ) ) {
				continue;
			}

			$resolved = $this->resolve_row( $row, $col_index, $event_id, $caches );

			if ( isset( $resolved['error'] ) ) {
				$report['skipped']++;
				$report['errors'][] = [
					'row'    => $row_num,
					'reason' => $resolved['error'],
					'raw'    => $row,
				];
				continue;
			}

			$new_id = $sessions_repo->save( $resolved['data'] );
			if ( ! $new_id ) {
				$report['skipped']++;
				$report['errors'][] = [
					'row'    => $row_num,
					'reason' => __( 'Database rejected the session (validation failed).', 'digitone-events' ),
					'raw'    => $row,
				];
				continue;
			}

			$report['imported']++;
			$report['created'][] = $new_id;
		}

		return $report;
	}

	/**
	 * Parse CSV string into array of rows. Detects comma or semicolon
	 * delimiter from the header line.
	 */
	private function parse_csv( string $csv ) : array {
		$rows  = [];
		$lines = preg_split( "/\r\n|\r|\n/", $csv );
		if ( ! $lines ) return $rows;

		// Auto-detect delimiter from header.
		$first    = $lines[0] ?? '';
		$delim    = ( substr_count( $first, ';' ) > substr_count( $first, ',' ) ) ? ';' : ',';

		// Use a tempstream so str_getcsv handles quoted fields with embedded delimiters.
		$fp = fopen( 'php://memory', 'r+' );
		fwrite( $fp, $csv );
		rewind( $fp );
		while ( ( $r = fgetcsv( $fp, 0, $delim, '"', '\\' ) ) !== false ) {
			$rows[] = $r;
		}
		fclose( $fp );
		return $rows;
	}

	private function row_is_empty( array $row ) : bool {
		foreach ( $row as $cell ) {
			if ( trim( (string) $cell ) !== '' ) return false;
		}
		return true;
	}

	private function field( array $row, array $col_index, string $name ) : string {
		if ( ! isset( $col_index[ $name ] ) ) return '';
		$idx = $col_index[ $name ];
		return isset( $row[ $idx ] ) ? trim( (string) $row[ $idx ] ) : '';
	}

	/**
	 * Build name→id lookup caches for the event so we don't hit the DB
	 * once per row. All lookups are case-insensitive on a UTF-8 fold.
	 */
	private function build_caches( string $event_id ) : array {
		$plugin = DigitOne_Events_Plugin::instance();

		$days = $plugin->module( 'days' )->repo()->all_for_event( $event_id );
		$days_by_date = [];
		foreach ( $days as $d ) {
			$days_by_date[ $d['day_date'] ] = $d['id'];
		}

		$venues_tree = $plugin->module( 'venues' )->repo()->tree_for_event( $event_id );
		$venues_by_name = []; // venue_name_lc => venue_id
		$subs_by_venue  = []; // venue_id => [ sub_name_lc => sub_id ]
		foreach ( $venues_tree as $v ) {
			$venues_by_name[ $this->fold( $v['name'] ) ] = $v['id'];
			if ( ! empty( $v['sub_venues'] ) ) {
				foreach ( $v['sub_venues'] as $sub ) {
					$subs_by_venue[ $v['id'] ][ $this->fold( $sub['name'] ) ] = $sub['id'];
				}
			}
		}

		$types = $plugin->module( 'session-types' )->repo()->all_for_event( $event_id );
		$types_by_name = [];
		foreach ( $types as $t ) {
			$types_by_name[ $this->fold( $t['name'] ) ] = $t['id'];
		}

		$speakers = $plugin->module( 'speakers' )->repo()->all_for_event( $event_id );
		$speakers_by_name = []; // "first last" lc => speaker_id
		foreach ( $speakers as $sp ) {
			$key  = $this->fold( trim( ( $sp['first_name'] ?? '' ) . ' ' . ( $sp['last_name'] ?? '' ) ) );
			$keyR = $this->fold( trim( ( $sp['last_name']  ?? '' ) . ' ' . ( $sp['first_name'] ?? '' ) ) );
			if ( $key  !== '' ) $speakers_by_name[ $key  ] = $sp['id'];
			if ( $keyR !== '' ) $speakers_by_name[ $keyR ] = $sp['id'];
		}

		$roles = $plugin->module( 'roles' )->repo()->all_for_event( $event_id );
		$roles_by_name = [];
		foreach ( $roles as $r ) {
			$roles_by_name[ $this->fold( $r['name'] ) ] = $r['id'];
		}

		return [
			'days_by_date'     => $days_by_date,
			'venues_by_name'   => $venues_by_name,
			'subs_by_venue'    => $subs_by_venue,
			'types_by_name'    => $types_by_name,
			'speakers_by_name' => $speakers_by_name,
			'roles_by_name'    => $roles_by_name,
		];
	}

	private function fold( string $s ) : string {
		return mb_strtolower( trim( $s ), 'UTF-8' );
	}

	/**
	 * Resolve a CSV row into a save()-ready payload, or an error message.
	 * @return array{ data?:array, error?:string }
	 */
	private function resolve_row( array $row, array $col_index, string $event_id, array $caches ) : array {
		$day_str      = $this->field( $row, $col_index, 'day' );
		$start        = $this->field( $row, $col_index, 'start_time' );
		$end          = $this->field( $row, $col_index, 'end_time' );
		$title        = $this->field( $row, $col_index, 'title' );
		$type_name    = $this->field( $row, $col_index, 'session_type' );
		$venue_name   = $this->field( $row, $col_index, 'venue' );
		$sub_name     = $this->field( $row, $col_index, 'sub_venue' );
		$speakers_str = $this->field( $row, $col_index, 'speakers' );
		$role_name    = $this->field( $row, $col_index, 'default_role' );
		$description  = $this->field( $row, $col_index, 'description' );

		if ( $title === '' ) {
			return [ 'error' => __( 'Empty title.', 'digitone-events' ) ];
		}

		// Day — accept YYYY-MM-DD; tolerate dd.mm.yyyy and dd/mm/yyyy by normalizing.
		$day_date = $this->normalize_date( $day_str );
		if ( ! $day_date ) {
			return [ 'error' => sprintf(
				/* translators: %s: raw cell value */
				__( 'Cannot parse day "%s" (use YYYY-MM-DD).', 'digitone-events' ),
				$day_str
			) ];
		}
		$day_id = $caches['days_by_date'][ $day_date ] ?? null;
		if ( ! $day_id ) {
			return [ 'error' => sprintf(
				/* translators: %s: date */
				__( 'No day exists for %s on this event.', 'digitone-events' ),
				$day_date
			) ];
		}

		// Times.
		$start_norm = $this->normalize_time( $start );
		$end_norm   = $this->normalize_time( $end );
		if ( ! $start_norm || ! $end_norm ) {
			return [ 'error' => __( 'Invalid start_time or end_time (use HH:MM).', 'digitone-events' ) ];
		}

		// Venue (optional but if present must resolve).
		$venue_id = null;
		$sub_id   = null;
		if ( $venue_name !== '' ) {
			$venue_id = $caches['venues_by_name'][ $this->fold( $venue_name ) ] ?? null;
			if ( ! $venue_id ) {
				return [ 'error' => sprintf(
					/* translators: %s: venue name */
					__( 'Venue "%s" not found.', 'digitone-events' ),
					$venue_name
				) ];
			}
			if ( $sub_name !== '' ) {
				$sub_id = $caches['subs_by_venue'][ $venue_id ][ $this->fold( $sub_name ) ] ?? null;
				if ( ! $sub_id ) {
					return [ 'error' => sprintf(
						/* translators: 1: sub-venue 2: parent venue */
						__( 'Sub-venue "%1$s" not found under "%2$s".', 'digitone-events' ),
						$sub_name, $venue_name
					) ];
				}
			}
		}

		// Session type (optional).
		$type_id = null;
		if ( $type_name !== '' ) {
			$type_id = $caches['types_by_name'][ $this->fold( $type_name ) ] ?? null;
			if ( ! $type_id ) {
				return [ 'error' => sprintf(
					/* translators: %s: type name */
					__( 'Session type "%s" not found.', 'digitone-events' ),
					$type_name
				) ];
			}
		}

		// Speakers — semicolon separated, each "First Last".
		$speaker_ids = [];
		$unresolved  = [];
		if ( $speakers_str !== '' ) {
			$parts = array_filter( array_map( 'trim', preg_split( '/[;|]/', $speakers_str ) ?: [] ) );
			foreach ( $parts as $name ) {
				$sid = $caches['speakers_by_name'][ $this->fold( $name ) ] ?? null;
				if ( $sid ) $speaker_ids[] = $sid;
				else        $unresolved[]  = $name;
			}
			if ( $unresolved ) {
				return [ 'error' => sprintf(
					/* translators: %s: comma-separated names */
					__( 'Speakers not found: %s', 'digitone-events' ),
					implode( ', ', $unresolved )
				) ];
			}
		}

		// Default role (optional).
		$role_id = null;
		if ( $role_name !== '' ) {
			$role_id = $caches['roles_by_name'][ $this->fold( $role_name ) ] ?? null;
			if ( ! $role_id ) {
				return [ 'error' => sprintf(
					/* translators: %s: role name */
					__( 'Role "%s" not found.', 'digitone-events' ),
					$role_name
				) ];
			}
		}

		return [
			'data' => [
				'id'              => '',
				'event_id'        => $event_id,
				'day_id'          => $day_id,
				'session_type_id' => $type_id,
				'venue_id'        => $venue_id,
				'sub_venue_id'    => $sub_id,
				'title'           => $title,
				'description'     => $description,
				'start_time'      => $start_norm,
				'end_time'        => $end_norm,
				'session_level'   => 'master',
				'parent_id'       => '',
				'speaker_ids'     => $speaker_ids,
				'default_role_id' => $role_id,
				'speaker_role_map' => (object) [],
			],
		];
	}

	private function normalize_date( string $s ) : ?string {
		$s = trim( $s );
		if ( $s === '' ) return null;
		// YYYY-MM-DD
		if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m ) ) {
			return sprintf( '%04d-%02d-%02d', $m[1], $m[2], $m[3] );
		}
		// dd.mm.yyyy or dd/mm/yyyy
		if ( preg_match( '/^(\d{1,2})[\/\.](\d{1,2})[\/\.](\d{4})$/', $s, $m ) ) {
			return sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
		}
		return null;
	}

	private function normalize_time( string $s ) : ?string {
		$s = trim( $s );
		if ( $s === '' ) return null;
		if ( preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $s, $m ) ) {
			return sprintf( '%02d:%02d:00', $m[1], $m[2] );
		}
		return null;
	}
}
