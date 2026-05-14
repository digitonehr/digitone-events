<?php
/**
 * Excel import / export — bulk operations for the active event.
 *
 * The client (assets/js/modules/excel-import.js) does the heavy lifting of
 * parsing .xlsx via SheetJS in the browser, then POSTs a JSON shape like:
 *
 *   {
 *     "Venues":       [ { name: "...", address: "..." }, ... ],
 *     "Sub-Venues":   [ { name: "...", parent_venue: "..." }, ... ],
 *     "Session-Types":[ { name: "...", icon: "...", color: "..." }, ... ],
 *     "Roles":        [ ... ],
 *     "Titles":       [ ... ],
 *     "Days":         [ ... ],
 *     "Speakers":     [ ... ],
 *     "Sessions":     [ ... ]
 *   }
 *
 * The same shape (with rows populated) is what the EXPORT side produces,
 * so a round-trip (export → user edits → import) works cleanly.
 *
 * Modes:
 *   - incremental: skip rows whose natural key already exists; insert the rest.
 *   - full:        delete all of the entity's rows first, then insert
 *                  everything from the import. (0.9.1)
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Export_Import_Excel {

	/**
	 * Schema for every supported entity. Defines:
	 *   - sheet:        canonical Excel sheet name
	 *   - columns:      ordered list of field keys (these are also the
	 *                   header row when we export the .xlsx template)
	 *   - required:     fields that cannot be empty
	 *   - natural_key:  field(s) used to dedupe in incremental mode
	 */
	public const ENTITIES = [
		'titles' => [
			'sheet'       => 'Titles',
			'columns'     => [ 'name', 'sort_order' ],
			'required'    => [ 'name' ],
			'natural_key' => [ 'name' ],
		],
		'roles' => [
			'sheet'       => 'Roles',
			'columns'     => [ 'name', 'color', 'sort_order' ],
			'required'    => [ 'name' ],
			'natural_key' => [ 'name' ],
		],
		'session_types' => [
			'sheet'       => 'Session-Types',
			'columns'     => [ 'name', 'icon', 'color', 'sort_order' ],
			'required'    => [ 'name' ],
			'natural_key' => [ 'name' ],
		],
		'venues' => [
			'sheet'       => 'Venues',
			'columns'     => [ 'name', 'address', 'sort_order' ],
			'required'    => [ 'name' ],
			'natural_key' => [ 'name' ],
		],
		'sub_venues' => [
			'sheet'       => 'Sub-Venues',
			'columns'     => [ 'name', 'parent_venue', 'sort_order' ],
			'required'    => [ 'name', 'parent_venue' ],
			'natural_key' => [ 'name', 'parent_venue' ],
		],
		'days' => [
			'sheet'       => 'Days',
			'columns'     => [ 'day_date', 'label', 'start_time', 'end_time', 'sort_order' ],
			'required'    => [ 'day_date' ],
			'natural_key' => [ 'day_date' ],
		],
		'speakers' => [
			'sheet'       => 'Speakers',
			'columns'     => [ 'first_name', 'last_name', 'title', 'roles', 'bio', 'photo_url' ],
			'required'    => [ 'first_name', 'last_name' ],
			'natural_key' => [ 'first_name', 'last_name' ],
		],
		'sessions' => [
			'sheet'       => 'Sessions',
			'columns'     => [
				'day_date', 'start_time', 'end_time', 'title',
				'session_type', 'venue', 'sub_venue',
				'speakers', 'default_role', 'description',
			],
			'required'    => [ 'day_date', 'start_time', 'end_time', 'title' ],
			'natural_key' => [ 'day_date', 'start_time', 'title' ],
		],
	];

	/** Ordered list for dependency-correct import. */
	public const IMPORT_ORDER = [
		'titles',
		'roles',
		'session_types',
		'venues',
		'sub_venues',
		'days',
		'speakers',
		'sessions',
	];

	/**
	 * Build a JSON dump of the active event's current data in the same
	 * shape the importer accepts. Used by both the "Download Excel" button
	 * (client-side renders this to .xlsx) and the "Download empty template"
	 * button (same shape but every entity has zero rows).
	 */
	public function snapshot( string $event_id, bool $empty = false ) : array {
		$plugin = DigitOne_Events_Plugin::instance();
		$out    = [];

		if ( $empty ) {
			foreach ( self::ENTITIES as $key => $def ) {
				$out[ $def['sheet'] ] = [];
			}
			return $out;
		}

		$titles = $plugin->module( 'titles' )->repo()->all_for_event( $event_id );
		$out['Titles'] = array_map( function ( $t ) {
			return [
				'name'       => (string) ( $t['name'] ?? '' ),
				'sort_order' => (int)    ( $t['sort_order'] ?? 0 ),
			];
		}, $titles );

		$roles = $plugin->module( 'roles' )->repo()->all_for_event( $event_id );
		$out['Roles'] = array_map( function ( $r ) {
			return [
				'name'       => (string) ( $r['name']  ?? '' ),
				'color'      => (string) ( $r['color'] ?? '' ),
				'sort_order' => (int)    ( $r['sort_order'] ?? 0 ),
			];
		}, $roles );

		$types = $plugin->module( 'session_types' )->repo()->all_for_event( $event_id );
		$out['Session-Types'] = array_map( function ( $t ) {
			return [
				'name'       => (string) ( $t['name']  ?? '' ),
				'icon'       => (string) ( $t['icon']  ?? '' ),
				'color'      => (string) ( $t['color'] ?? '' ),
				'sort_order' => (int)    ( $t['sort_order'] ?? 0 ),
			];
		}, $types );

		$venues_tree = $plugin->module( 'venues' )->repo()->tree_for_event( $event_id );
		$venues_flat = [];
		$subs_flat   = [];
		foreach ( $venues_tree as $v ) {
			$venues_flat[] = [
				'name'       => (string) ( $v['name']    ?? '' ),
				'address'    => (string) ( $v['address'] ?? '' ),
				'sort_order' => (int)    ( $v['sort_order'] ?? 0 ),
			];
			foreach ( (array) ( $v['sub_venues'] ?? [] ) as $sub ) {
				$subs_flat[] = [
					'name'         => (string) ( $sub['name']    ?? '' ),
					'parent_venue' => (string) ( $v['name']      ?? '' ),
					'sort_order'   => (int)    ( $sub['sort_order'] ?? 0 ),
				];
			}
		}
		$out['Venues']     = $venues_flat;
		$out['Sub-Venues'] = $subs_flat;

		$days = $plugin->module( 'days' )->repo()->all_for_event( $event_id );
		$out['Days'] = array_map( function ( $d ) {
			return [
				'day_date'   => (string) ( $d['day_date']   ?? '' ),
				'label'      => (string) ( $d['label']      ?? '' ),
				'start_time' => substr( (string) ( $d['start_time'] ?? '' ), 0, 5 ),
				'end_time'   => substr( (string) ( $d['end_time']   ?? '' ), 0, 5 ),
				'sort_order' => (int)    ( $d['sort_order'] ?? 0 ),
			];
		}, $days );

		$speakers = $plugin->module( 'speakers' )->repo()->all_for_event( $event_id );
		$out['Speakers'] = array_map( function ( $sp ) {
			$role_names = [];
			foreach ( (array) ( $sp['roles'] ?? [] ) as $r ) {
				if ( ! empty( $r['name'] ) ) $role_names[] = $r['name'];
			}
			return [
				'first_name' => (string) ( $sp['first_name'] ?? '' ),
				'last_name'  => (string) ( $sp['last_name']  ?? '' ),
				'title'      => (string) ( $sp['title_name'] ?? '' ),
				'roles'      => implode( '; ', $role_names ),
				'bio'        => wp_strip_all_tags( (string) ( $sp['bio'] ?? '' ) ),
				'photo_url'  => (string) ( $sp['photo_url'] ?? '' ),
			];
		}, $speakers );

		// Sessions: flatten across days. We need the day's date in each row
		// and the speakers/types/venues by name. Sessions repo already does
		// the speaker join for us in all_for_day().
		$days_by_id = [];
		foreach ( $days as $d ) {
			$days_by_id[ $d['id'] ] = $d['day_date'];
		}
		$sub_by_id = [];
		$venue_by_id = [];
		foreach ( $venues_tree as $v ) {
			$venue_by_id[ $v['id'] ] = $v['name'];
			foreach ( (array) ( $v['sub_venues'] ?? [] ) as $sub ) {
				$sub_by_id[ $sub['id'] ] = [ 'name' => $sub['name'], 'parent_name' => $v['name'] ];
			}
		}
		$types_by_id = [];
		foreach ( $types as $t ) $types_by_id[ $t['id'] ] = $t['name'];

		$sessions_repo = $plugin->module( 'sessions' )->repo();
		$sessions_flat = [];
		foreach ( $days as $d ) {
			$sessions = $sessions_repo->all_for_day( $d['id'] );
			foreach ( $sessions as $s ) {
				$names = [];
				foreach ( (array) ( $s['speakers'] ?? [] ) as $sp ) {
					$nm = trim( ( $sp['first_name'] ?? '' ) . ' ' . ( $sp['last_name'] ?? '' ) );
					if ( $nm !== '' ) $names[] = $nm;
				}
				$sub_info = ! empty( $s['sub_venue_id'] ) ? ( $sub_by_id[ $s['sub_venue_id'] ] ?? null ) : null;
				$sessions_flat[] = [
					'day_date'     => $days_by_id[ $s['day_id'] ] ?? '',
					'start_time'   => substr( (string) ( $s['start_time'] ?? '' ), 0, 5 ),
					'end_time'     => substr( (string) ( $s['end_time']   ?? '' ), 0, 5 ),
					'title'        => (string) ( $s['title'] ?? '' ),
					'session_type' => $types_by_id[ $s['session_type_id'] ?? '' ] ?? '',
					'venue'        => ! empty( $s['venue_id'] ) ? ( $venue_by_id[ $s['venue_id'] ] ?? '' ) : ( $sub_info['parent_name'] ?? '' ),
					'sub_venue'    => $sub_info['name'] ?? '',
					'speakers'     => implode( '; ', $names ),
					'default_role' => '', // not stored as a single value per session — would require querying the junction; leave blank on export
					'description'  => wp_strip_all_tags( (string) ( $s['description'] ?? '' ) ),
				];
			}
		}
		$out['Sessions'] = $sessions_flat;

		return $out;
	}

	/**
	 * Run the importer in preview or commit mode. Preview just counts what
	 * would happen; commit actually inserts. Same code path so the two are
	 * always in sync.
	 *
	 * @param array  $payload  Data shaped like ::snapshot() output.
	 * @param string $event_id Target event.
	 * @param string $mode     'incremental' | 'full' (full = 0.9.1).
	 * @param bool   $dry_run  true for preview, false for actual commit.
	 *
	 * @return array {
	 *   results: array<entity_key, { inserted:int, skipped:int, errors:array }>
	 *   totals:  { inserted:int, skipped:int, errors:int }
	 * }
	 */
	public function run( array $payload, string $event_id, string $mode, bool $dry_run ) : array {
		$results = [];
		$caches  = $this->prime_caches( $event_id );

		foreach ( self::IMPORT_ORDER as $entity_key ) {
			$def       = self::ENTITIES[ $entity_key ];
			$sheet     = $def['sheet'];
			$rows      = isset( $payload[ $sheet ] ) && is_array( $payload[ $sheet ] ) ? $payload[ $sheet ] : [];
			$results[ $entity_key ] = $this->import_entity( $entity_key, $rows, $event_id, $mode, $dry_run, $caches );
		}

		$totals = [ 'inserted' => 0, 'skipped' => 0, 'errors' => 0 ];
		foreach ( $results as $r ) {
			$totals['inserted'] += $r['inserted'];
			$totals['skipped']  += $r['skipped'];
			$totals['errors']   += count( $r['errors'] );
		}

		return [ 'results' => $results, 'totals' => $totals, 'mode' => $mode, 'dry_run' => $dry_run ];
	}

	/* ============================================================ */
	/* Per-entity import + helpers                                  */
	/* ============================================================ */

	private function import_entity( string $entity_key, array $rows, string $event_id, string $mode, bool $dry_run, array &$caches ) : array {
		$def      = self::ENTITIES[ $entity_key ];
		$inserted = 0;
		$skipped  = 0;
		$errors   = [];
		$row_num  = 1; // 1 = header

		foreach ( $rows as $raw ) {
			$row_num++;
			$row = is_array( $raw ) ? $raw : [];

			if ( $this->row_is_empty( $row ) ) {
				continue;
			}

			// Required fields
			foreach ( $def['required'] as $req ) {
				if ( ! isset( $row[ $req ] ) || trim( (string) $row[ $req ] ) === '' ) {
					$errors[] = [ 'row' => $row_num, 'reason' => sprintf( /* translators: %s field name */ __( 'Missing required field: %s', 'digitone-events' ), $req ) ];
					continue 2;
				}
			}

			// Natural key dedup (incremental mode only)
			if ( $mode === 'incremental' ) {
				$nk = $this->natural_key( $entity_key, $row );
				if ( $nk !== '' && isset( $caches[ $entity_key ]['by_nk'][ $nk ] ) ) {
					$skipped++;
					continue;
				}
			}

			$result = $this->insert_entity_row( $entity_key, $row, $event_id, $dry_run, $caches );
			if ( isset( $result['error'] ) ) {
				$errors[] = [ 'row' => $row_num, 'reason' => $result['error'] ];
				continue;
			}
			$inserted++;
		}

		return [
			'sheet'    => $def['sheet'],
			'inserted' => $inserted,
			'skipped'  => $skipped,
			'errors'   => $errors,
		];
	}

	private function insert_entity_row( string $entity_key, array $row, string $event_id, bool $dry_run, array &$caches ) : array {
		$plugin = DigitOne_Events_Plugin::instance();

		switch ( $entity_key ) {
			case 'titles':
				$data = [
					'event_id'   => $event_id,
					'name'       => trim( (string) $row['name'] ),
					'sort_order' => (int) ( $row['sort_order'] ?? 0 ),
				];
				return $this->commit_simple( $entity_key, $plugin->module( 'titles' )->repo(), $data, $dry_run, $caches );

			case 'roles':
				$data = [
					'event_id'   => $event_id,
					'name'       => trim( (string) $row['name'] ),
					'color'      => trim( (string) ( $row['color'] ?? '' ) ),
					'sort_order' => (int) ( $row['sort_order'] ?? 0 ),
				];
				return $this->commit_simple( $entity_key, $plugin->module( 'roles' )->repo(), $data, $dry_run, $caches );

			case 'session_types':
				$data = [
					'event_id'   => $event_id,
					'name'       => trim( (string) $row['name'] ),
					'icon'       => trim( (string) ( $row['icon']  ?? '' ) ),
					'color'      => trim( (string) ( $row['color'] ?? '' ) ),
					'sort_order' => (int) ( $row['sort_order'] ?? 0 ),
				];
				return $this->commit_simple( $entity_key, $plugin->module( 'session_types' )->repo(), $data, $dry_run, $caches );

			case 'venues':
				$data = [
					'event_id'   => $event_id,
					'venue_type' => 'primary',
					'parent_id'  => null,
					'name'       => trim( (string) $row['name'] ),
					'address'    => trim( (string) ( $row['address'] ?? '' ) ),
					'sort_order' => (int) ( $row['sort_order'] ?? 0 ),
				];
				return $this->commit_simple( $entity_key, $plugin->module( 'venues' )->repo(), $data, $dry_run, $caches );

			case 'sub_venues':
				$parent_name = trim( (string) $row['parent_venue'] );
				$parent_id   = $caches['venues']['by_nk'][ $this->fold( $parent_name ) ] ?? null;
				if ( ! $parent_id ) {
					return [ 'error' => sprintf( /* translators: %s venue name */ __( 'Parent venue "%s" not found.', 'digitone-events' ), $parent_name ) ];
				}
				$data = [
					'event_id'   => $event_id,
					'venue_type' => 'sub',
					'parent_id'  => $parent_id,
					'name'       => trim( (string) $row['name'] ),
					'sort_order' => (int) ( $row['sort_order'] ?? 0 ),
				];
				return $this->commit_subvenue( $row, $data, $parent_name, $dry_run, $caches );

			case 'days':
				$date = $this->normalize_date( (string) $row['day_date'] );
				if ( ! $date ) return [ 'error' => __( 'Bad day_date (use YYYY-MM-DD).', 'digitone-events' ) ];
				$data = [
					'event_id'   => $event_id,
					'day_date'   => $date,
					'label'      => trim( (string) ( $row['label']      ?? '' ) ),
					'start_time' => $this->normalize_time( (string) ( $row['start_time'] ?? '' ) ),
					'end_time'   => $this->normalize_time( (string) ( $row['end_time']   ?? '' ) ),
					'sort_order' => (int) ( $row['sort_order'] ?? 0 ),
				];
				$nk = $this->fold( $date );
				if ( $dry_run ) {
					$caches['days']['by_nk'][ $nk ] = '__pending__' . $nk;
					return [ 'ok' => true ];
				}
				$id = $plugin->module( 'days' )->repo()->save( $data );
				if ( ! $id ) return [ 'error' => __( 'Database rejected day row.', 'digitone-events' ) ];
				$caches['days']['by_nk'][ $nk ] = $id;
				return [ 'ok' => true ];

			case 'speakers':
				$title_name = trim( (string) ( $row['title'] ?? '' ) );
				$title_id   = $title_name === '' ? null : ( $caches['titles']['by_nk'][ $this->fold( $title_name ) ] ?? null );
				if ( $title_name !== '' && ! $title_id ) {
					return [ 'error' => sprintf( /* translators: %s title */ __( 'Title "%s" not found.', 'digitone-events' ), $title_name ) ];
				}
				$role_ids   = [];
				$unresolved = [];
				if ( ! empty( $row['roles'] ) ) {
					$parts = array_filter( array_map( 'trim', preg_split( '/[;|]/', (string) $row['roles'] ) ?: [] ) );
					foreach ( $parts as $rn ) {
						$rid = $caches['roles']['by_nk'][ $this->fold( $rn ) ] ?? null;
						if ( $rid ) $role_ids[] = $rid;
						else        $unresolved[] = $rn;
					}
				}
				if ( $unresolved ) {
					return [ 'error' => sprintf( /* translators: %s names */ __( 'Roles not found: %s', 'digitone-events' ), implode( ', ', $unresolved ) ) ];
				}
				$data = [
					'event_id'   => $event_id,
					'first_name' => trim( (string) $row['first_name'] ),
					'last_name'  => trim( (string) $row['last_name'] ),
					'title_id'   => $title_id,
					'bio'        => isset( $row['bio'] ) ? sanitize_textarea_field( (string) $row['bio'] ) : '',
					'photo_url'  => trim( (string) ( $row['photo_url'] ?? '' ) ),
					'role_ids'   => $role_ids,
				];
				$nk = $this->fold( $data['first_name'] . ' ' . $data['last_name'] );
				if ( $dry_run ) {
					$caches['speakers']['by_nk'][ $nk ] = '__pending__';
					return [ 'ok' => true ];
				}
				$id = $plugin->module( 'speakers' )->repo()->save( $data );
				if ( ! $id ) return [ 'error' => __( 'Database rejected speaker row.', 'digitone-events' ) ];
				$caches['speakers']['by_nk'][ $nk ] = $id;
				return [ 'ok' => true ];

			case 'sessions':
				$date = $this->normalize_date( (string) $row['day_date'] );
				if ( ! $date ) return [ 'error' => __( 'Bad day_date (use YYYY-MM-DD).', 'digitone-events' ) ];
				$day_id = $caches['days']['by_nk'][ $this->fold( $date ) ] ?? null;
				if ( ! $day_id || strpos( (string) $day_id, '__pending__' ) === 0 ) {
					if ( $dry_run && $day_id ) {
						// preview: parent will be created on commit, so just continue with placeholder
					} else if ( ! $day_id ) {
						return [ 'error' => sprintf( /* translators: %s date */ __( 'Day "%s" not found.', 'digitone-events' ), $date ) ];
					}
				}
				$start_norm = $this->normalize_time( (string) $row['start_time'] );
				$end_norm   = $this->normalize_time( (string) $row['end_time'] );
				if ( ! $start_norm || ! $end_norm ) return [ 'error' => __( 'Bad start_time or end_time (HH:MM).', 'digitone-events' ) ];

				$type_name = trim( (string) ( $row['session_type'] ?? '' ) );
				$type_id   = $type_name === '' ? null : ( $caches['session_types']['by_nk'][ $this->fold( $type_name ) ] ?? null );
				if ( $type_name !== '' && ! $type_id ) return [ 'error' => sprintf( /* translators: %s type */ __( 'Session type "%s" not found.', 'digitone-events' ), $type_name ) ];

				$venue_name = trim( (string) ( $row['venue'] ?? '' ) );
				$venue_id   = $venue_name === '' ? null : ( $caches['venues']['by_nk'][ $this->fold( $venue_name ) ] ?? null );
				if ( $venue_name !== '' && ! $venue_id ) return [ 'error' => sprintf( /* translators: %s venue */ __( 'Venue "%s" not found.', 'digitone-events' ), $venue_name ) ];

				$sub_name = trim( (string) ( $row['sub_venue'] ?? '' ) );
				$sub_id   = null;
				if ( $sub_name !== '' ) {
					$sub_nk = $this->fold( $sub_name . '|' . $venue_name );
					$sub_id = $caches['sub_venues']['by_nk'][ $sub_nk ] ?? null;
					if ( ! $sub_id ) return [ 'error' => sprintf( /* translators: %s sub */ __( 'Sub-venue "%s" not found.', 'digitone-events' ), $sub_name ) ];
				}

				$speaker_ids = [];
				$unresolved  = [];
				if ( ! empty( $row['speakers'] ) ) {
					$parts = array_filter( array_map( 'trim', preg_split( '/[;|]/', (string) $row['speakers'] ) ?: [] ) );
					foreach ( $parts as $nm ) {
						$sid = $caches['speakers']['by_nk'][ $this->fold( $nm ) ] ?? null;
						if ( ! $sid ) {
							// Try reverse "Last First" → look up reversed
							$tokens = preg_split( '/\s+/', $nm );
							if ( $tokens && count( $tokens ) >= 2 ) {
								$rev = end( $tokens ) . ' ' . trim( str_replace( end( $tokens ), '', $nm ) );
								$sid = $caches['speakers']['by_nk'][ $this->fold( $rev ) ] ?? null;
							}
						}
						if ( $sid ) $speaker_ids[] = $sid;
						else        $unresolved[]  = $nm;
					}
				}
				if ( $unresolved ) return [ 'error' => sprintf( /* translators: %s names */ __( 'Speakers not found: %s', 'digitone-events' ), implode( ', ', $unresolved ) ) ];

				$role_name = trim( (string) ( $row['default_role'] ?? '' ) );
				$role_id   = $role_name === '' ? null : ( $caches['roles']['by_nk'][ $this->fold( $role_name ) ] ?? null );
				if ( $role_name !== '' && ! $role_id ) return [ 'error' => sprintf( /* translators: %s role */ __( 'Default role "%s" not found.', 'digitone-events' ), $role_name ) ];

				if ( $dry_run ) return [ 'ok' => true ];

				$session_id = $plugin->module( 'sessions' )->repo()->save( [
					'id'              => '',
					'event_id'        => $event_id,
					'day_id'          => $day_id,
					'session_type_id' => $type_id,
					'venue_id'        => $venue_id,
					'sub_venue_id'    => $sub_id,
					'title'           => trim( (string) $row['title'] ),
					'description'    => isset( $row['description'] ) ? (string) $row['description'] : '',
					'start_time'      => $start_norm,
					'end_time'        => $end_norm,
					'session_level'   => 'master',
					'parent_id'       => '',
					'speaker_ids'     => $speaker_ids,
					'default_role_id' => $role_id,
					'speaker_role_map' => (object) [],
				] );
				if ( ! $session_id ) return [ 'error' => __( 'Database rejected session row.', 'digitone-events' ) ];
				return [ 'ok' => true ];
		}

		return [ 'error' => 'Unknown entity: ' . $entity_key ];
	}

	private function commit_simple( string $entity_key, $repo, array $data, bool $dry_run, array &$caches ) : array {
		$nk = $this->fold( (string) $data['name'] );
		if ( $dry_run ) {
			$caches[ $entity_key ]['by_nk'][ $nk ] = '__pending__';
			return [ 'ok' => true ];
		}
		$id = $repo->save( $data );
		if ( ! $id ) return [ 'error' => __( 'Database rejected the row.', 'digitone-events' ) ];
		$caches[ $entity_key ]['by_nk'][ $nk ] = $id;
		return [ 'ok' => true ];
	}

	private function commit_subvenue( array $row, array $data, string $parent_name, bool $dry_run, array &$caches ) : array {
		$nk = $this->fold( $data['name'] . '|' . $parent_name );
		if ( $dry_run ) {
			$caches['sub_venues']['by_nk'][ $nk ] = '__pending__';
			return [ 'ok' => true ];
		}
		$id = DigitOne_Events_Plugin::instance()->module( 'venues' )->repo()->save( $data );
		if ( ! $id ) return [ 'error' => __( 'Database rejected sub-venue.', 'digitone-events' ) ];
		$caches['sub_venues']['by_nk'][ $nk ] = $id;
		return [ 'ok' => true ];
	}

	/**
	 * Build name→id caches for every entity, so resolving FKs and dedup
	 * by natural key is in-memory only.
	 */
	private function prime_caches( string $event_id ) : array {
		$plugin = DigitOne_Events_Plugin::instance();
		$caches = [];

		$caches['titles']['by_nk']        = [];
		foreach ( $plugin->module( 'titles' )->repo()->all_for_event( $event_id ) as $t ) {
			$caches['titles']['by_nk'][ $this->fold( $t['name'] ) ] = $t['id'];
		}

		$caches['roles']['by_nk'] = [];
		foreach ( $plugin->module( 'roles' )->repo()->all_for_event( $event_id ) as $r ) {
			$caches['roles']['by_nk'][ $this->fold( $r['name'] ) ] = $r['id'];
		}

		$caches['session_types']['by_nk'] = [];
		foreach ( $plugin->module( 'session_types' )->repo()->all_for_event( $event_id ) as $t ) {
			$caches['session_types']['by_nk'][ $this->fold( $t['name'] ) ] = $t['id'];
		}

		$tree = $plugin->module( 'venues' )->repo()->tree_for_event( $event_id );
		$caches['venues']['by_nk']     = [];
		$caches['sub_venues']['by_nk'] = [];
		foreach ( $tree as $v ) {
			$caches['venues']['by_nk'][ $this->fold( $v['name'] ) ] = $v['id'];
			foreach ( (array) ( $v['sub_venues'] ?? [] ) as $sub ) {
				$caches['sub_venues']['by_nk'][ $this->fold( $sub['name'] . '|' . $v['name'] ) ] = $sub['id'];
			}
		}

		$caches['days']['by_nk'] = [];
		foreach ( $plugin->module( 'days' )->repo()->all_for_event( $event_id ) as $d ) {
			$caches['days']['by_nk'][ $this->fold( $d['day_date'] ) ] = $d['id'];
		}

		$caches['speakers']['by_nk'] = [];
		foreach ( $plugin->module( 'speakers' )->repo()->all_for_event( $event_id ) as $sp ) {
			$caches['speakers']['by_nk'][ $this->fold( $sp['first_name'] . ' ' . $sp['last_name'] ) ] = $sp['id'];
			$caches['speakers']['by_nk'][ $this->fold( $sp['last_name']  . ' ' . $sp['first_name'] ) ] = $sp['id'];
		}

		return $caches;
	}

	private function natural_key( string $entity_key, array $row ) : string {
		$parts = [];
		foreach ( self::ENTITIES[ $entity_key ]['natural_key'] as $field ) {
			$parts[] = $this->fold( (string) ( $row[ $field ] ?? '' ) );
		}
		return implode( '|', $parts );
	}

	private function fold( string $s ) : string {
		return mb_strtolower( trim( $s ), 'UTF-8' );
	}

	private function row_is_empty( array $row ) : bool {
		foreach ( $row as $v ) {
			if ( is_array( $v ) ) continue;
			if ( trim( (string) $v ) !== '' ) return false;
		}
		return true;
	}

	private function normalize_date( string $s ) : ?string {
		$s = trim( $s );
		if ( $s === '' ) return null;
		if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m ) )      return sprintf( '%04d-%02d-%02d', $m[1], $m[2], $m[3] );
		if ( preg_match( '/^(\d{1,2})[\/\.](\d{1,2})[\/\.](\d{4})$/', $s, $m ) ) return sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
		return null;
	}

	private function normalize_time( string $s ) : ?string {
		$s = trim( $s );
		if ( $s === '' ) return null;
		if ( preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $s, $m ) ) return sprintf( '%02d:%02d:00', $m[1], $m[2] );
		return null;
	}
}
