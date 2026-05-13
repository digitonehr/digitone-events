<?php
/**
 * Sessions repository.
 * Handles session CRUD + session_roles junction (speakers + optional role per session).
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Sessions_Repository {

	private function table() : string {
		return DigitOne_Events_Database_Schema::table( 'sessions' );
	}

	private function junction_table() : string {
		return DigitOne_Events_Database_Schema::table( 'session_roles' );
	}

	/**
	 * Get all sessions for a single day, enriched with type/venue/speaker info.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function all_for_day( string $day_id ) : array {
		global $wpdb;
		$s   = $this->table();
		$t   = DigitOne_Events_Database_Schema::table( 'session_types' );
		$v   = DigitOne_Events_Database_Schema::table( 'venues' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*,
				        t.name AS type_name, t.icon AS type_icon, t.color AS type_color,
				        v1.name AS venue_name, v2.name AS sub_venue_name
				 FROM {$s} s
				 LEFT JOIN {$t}  t  ON s.session_type_id = t.id
				 LEFT JOIN {$v}  v1 ON s.venue_id        = v1.id
				 LEFT JOIN {$v}  v2 ON s.sub_venue_id    = v2.id
				 WHERE s.day_id = %s
				 ORDER BY s.start_time ASC, s.sort_order ASC",
				$day_id
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return [];
		}
		// Attach speakers per session.
		if ( ! empty( $rows ) ) {
			$ids = array_column( $rows, 'id' );
			$by_session = $this->speakers_for_sessions( $ids );
			foreach ( $rows as &$row ) {
				$row['speakers'] = $by_session[ $row['id'] ] ?? [];
			}
			unset( $row );
		}
		return $rows;
	}

	/**
	 * @param string[] $session_ids
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	private function speakers_for_sessions( array $session_ids ) : array {
		if ( empty( $session_ids ) ) {
			return [];
		}
		global $wpdb;
		$j   = $this->junction_table();
		$sp  = DigitOne_Events_Database_Schema::table( 'speakers' );
		$r   = DigitOne_Events_Database_Schema::table( 'roles' );

		$placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%s' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sr.session_id, sr.role_id,
				        s.id AS speaker_id, s.first_name, s.last_name, s.photo_url, s.bio,
				        r.name AS role_name, r.color AS role_color
				 FROM {$j} sr
				 INNER JOIN {$sp} s ON sr.speaker_id = s.id
				 LEFT JOIN  {$r}  r ON sr.role_id    = r.id
				 WHERE sr.session_id IN ({$placeholders})
				 ORDER BY s.last_name ASC",
				...$session_ids
			),
			ARRAY_A
		);
		$by_session = [];
		foreach ( (array) $rows as $row ) {
			$sid = $row['session_id'];
			unset( $row['session_id'] );
			$by_session[ $sid ][] = $row;
		}
		return $by_session;
	}

	public function find( string $id ) : ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %s", $id ),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		// Attach speaker IDs + the role assigned to each.
		$junction_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT speaker_id, role_id FROM {$this->junction_table()} WHERE session_id = %s",
				$id
			),
			ARRAY_A
		);
		$row['speaker_ids'] = [];
		$row['speaker_role_map'] = []; // speaker_id => role_id
		foreach ( (array) $junction_rows as $jr ) {
			$row['speaker_ids'][] = $jr['speaker_id'];
			$row['speaker_role_map'][ $jr['speaker_id'] ] = $jr['role_id'];
		}
		return $row;
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
	 * @return array<int,array<string,mixed>> Master sessions (level=master) on a given day.
	 */
	public function masters_for_day( string $day_id ) : array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, start_time, end_time FROM {$this->table()}
				 WHERE day_id = %s AND session_level = 'master'
				 ORDER BY start_time ASC",
				$day_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Save (insert or update) a session with speaker assignments.
	 */
	public function save( array $data ) : ?string {
		global $wpdb;
		$t = $this->table();

		$id       = isset( $data['id'] ) && $data['id'] !== '' ? (string) $data['id'] : '';
		$is_new   = $id === '';
		$event_id = isset( $data['event_id'] ) ? (string) $data['event_id'] : '';
		$day_id   = isset( $data['day_id'] ) ? (string) $data['day_id'] : '';
		$title    = isset( $data['title'] ) ? trim( (string) $data['title'] ) : '';

		if ( $event_id === '' || $day_id === '' || $title === '' ) {
			return null;
		}

		$start = isset( $data['start_time'] ) ? DigitOne_Events_Helpers_Format::time( $data['start_time'] ) : '';
		$end   = isset( $data['end_time'] )   ? DigitOne_Events_Helpers_Format::time( $data['end_time'] )   : '';
		if ( $start && $end && strcmp( $start, $end ) > 0 ) {
			return null;
		}

		$level = isset( $data['session_level'] ) && $data['session_level'] === 'child' ? 'child' : 'master';

		$parent_id = isset( $data['parent_id'] ) && $data['parent_id'] !== '' ? (string) $data['parent_id'] : null;
		// A master session cannot have a parent. A child must have a parent.
		if ( $level === 'master' ) {
			$parent_id = null;
		} elseif ( $level === 'child' && ! $parent_id ) {
			return null;
		}
		// Cannot be own parent.
		if ( ! $is_new && $parent_id === $id ) {
			return null;
		}

		$row = [
			'event_id'        => $event_id,
			'day_id'          => $day_id,
			'parent_id'       => $parent_id,
			'session_type_id' => $this->nullable_id( $data['session_type_id'] ?? '' ),
			'venue_id'        => $this->nullable_id( $data['venue_id'] ?? '' ),
			'sub_venue_id'    => $this->nullable_id( $data['sub_venue_id'] ?? '' ),
			'title'           => $title,
			'description'     => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
			'start_time'      => $start ?: null,
			'end_time'        => $end ?: null,
			'session_level'   => $level,
			'sort_order'      => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
			'updated_at'      => current_time( 'mysql' ),
		];
		$formats = [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ];

		if ( $is_new ) {
			$row['id']         = DigitOne_Events_Helpers_Format::uuid();
			$row['created_at'] = current_time( 'mysql' );
			$formats[] = '%s';
			$formats[] = '%s';
			$ok = $wpdb->insert( $t, $row, $formats );
			if ( ! $ok ) {
				return null;
			}
			$id = $row['id'];
		} else {
			$ok = $wpdb->update( $t, $row, [ 'id' => $id ], $formats, [ '%s' ] );
			if ( $ok === false ) {
				return null;
			}
		}

		// Sync speaker assignments.
		$speaker_ids = isset( $data['speaker_ids'] ) && is_array( $data['speaker_ids'] )
			? array_filter( array_map( 'sanitize_text_field', $data['speaker_ids'] ) )
			: [];
		$default_role = isset( $data['default_role_id'] ) && $data['default_role_id'] !== ''
			? sanitize_text_field( $data['default_role_id'] )
			: null;
		// Optional per-speaker role overrides.
		$role_map = isset( $data['speaker_role_map'] ) && is_array( $data['speaker_role_map'] )
			? $data['speaker_role_map']
			: [];

		$this->sync_speakers( $id, $event_id, $speaker_ids, $default_role, $role_map );

		return $id;
	}

	private function nullable_id( $val ) : ?string {
		$val = (string) $val;
		return $val === '' ? null : $val;
	}

	/**
	 * Replace session's speakers. role_map = speaker_id => role_id overrides.
	 */
	private function sync_speakers( string $session_id, string $event_id, array $speaker_ids, ?string $default_role, array $role_map ) : void {
		global $wpdb;
		$j = $this->junction_table();
		$wpdb->delete( $j, [ 'session_id' => $session_id ], [ '%s' ] );

		if ( empty( $speaker_ids ) ) {
			return;
		}

		// Validate role belongs to event (if set).
		if ( $default_role ) {
			$valid = DigitOne_Events_Plugin::instance()->module( 'roles' )->repo()->filter_valid_ids( [ $default_role ], $event_id );
			if ( empty( $valid ) ) {
				$default_role = null;
			}
		}

		foreach ( $speaker_ids as $sid ) {
			$role = $role_map[ $sid ] ?? $default_role;
			if ( $role ) {
				$valid = DigitOne_Events_Plugin::instance()->module( 'roles' )->repo()->filter_valid_ids( [ $role ], $event_id );
				if ( empty( $valid ) ) {
					$role = null;
				}
			}
			$wpdb->insert(
				$j,
				[
					'id'         => DigitOne_Events_Helpers_Format::uuid(),
					'session_id' => $session_id,
					'speaker_id' => $sid,
					'role_id'    => $role,
				],
				[ '%s', '%s', '%s', '%s' ]
			);
		}
	}

	public function delete( string $id ) : bool {
		global $wpdb;
		if ( ! $this->find( $id ) ) {
			return false;
		}
		// Remove child sessions referencing this as parent (becomes orphans → promote to master).
		$wpdb->update( $this->table(), [ 'parent_id' => null, 'session_level' => 'master' ], [ 'parent_id' => $id ], [ '%s', '%s' ], [ '%s' ] );
		// Junction.
		$wpdb->delete( $this->junction_table(), [ 'session_id' => $id ], [ '%s' ] );
		// Self.
		return (bool) $wpdb->delete( $this->table(), [ 'id' => $id ], [ '%s' ] );
	}
}
