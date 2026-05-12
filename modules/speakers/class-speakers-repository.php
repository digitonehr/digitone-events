<?php
/**
 * Speakers repository.
 * Handles speaker CRUD + roles junction (many-to-many).
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Speakers_Repository {

	private function table() : string {
		return DigitOne_Events_Database_Schema::table( 'speakers' );
	}

	private function junction_table() : string {
		return DigitOne_Events_Database_Schema::table( 'speakers_roles' );
	}

	/** @return array<int,array<string,mixed>> */
	public function all_for_event( string $event_id ) : array {
		global $wpdb;
		$t    = $this->table();
		$j    = $this->junction_table();
		$r    = DigitOne_Events_Database_Schema::table( 'roles' );
		$ti   = DigitOne_Events_Database_Schema::table( 'titles' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, t.name AS title_name
				 FROM {$t} s
				 LEFT JOIN {$ti} t ON s.title_id = t.id
				 WHERE s.event_id = %s
				 ORDER BY s.last_name ASC, s.first_name ASC",
				$event_id
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return [];
		}

		// Attach roles for each speaker (single query to avoid N+1).
		if ( ! empty( $rows ) ) {
			$ids = array_column( $rows, 'id' );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
			$roles = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT sr.speaker_id, r.id, r.name, r.color
					 FROM {$j} sr
					 INNER JOIN {$r} r ON sr.role_id = r.id
					 WHERE sr.speaker_id IN ({$placeholders})
					 ORDER BY r.sort_order ASC, r.name ASC",
					...$ids
				),
				ARRAY_A
			);
			$by_speaker = [];
			foreach ( (array) $roles as $row ) {
				$sid = $row['speaker_id'];
				unset( $row['speaker_id'] );
				$by_speaker[ $sid ][] = $row;
			}
			foreach ( $rows as &$speaker ) {
				$speaker['roles'] = $by_speaker[ $speaker['id'] ] ?? [];
			}
			unset( $speaker );
		}

		return $rows;
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
		// Attach role IDs.
		$role_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT role_id FROM {$this->junction_table()} WHERE speaker_id = %s", $id )
		);
		$row['role_ids'] = is_array( $role_ids ) ? $role_ids : [];
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
	 * Save (insert or update) a speaker with their role assignments.
	 *
	 * @param array{
	 *   id?:string, event_id:string, title_id?:string,
	 *   first_name?:string, last_name?:string, bio?:string,
	 *   photo_url?:string, email?:string, role_ids?:string[]
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

		$first = isset( $data['first_name'] ) ? trim( (string) $data['first_name'] ) : '';
		$last  = isset( $data['last_name'] )  ? trim( (string) $data['last_name'] )  : '';
		if ( $first === '' && $last === '' ) {
			return null;
		}

		$email = isset( $data['email'] ) ? trim( (string) $data['email'] ) : '';
		if ( $email !== '' && ! is_email( $email ) ) {
			return null;
		}

		$photo = isset( $data['photo_url'] ) ? esc_url_raw( $data['photo_url'] ) : '';

		$title_id = isset( $data['title_id'] ) && $data['title_id'] !== '' ? (string) $data['title_id'] : null;

		$row = [
			'event_id'   => $event_id,
			'title_id'   => $title_id,
			'first_name' => $first,
			'last_name'  => $last,
			'bio'        => isset( $data['bio'] ) ? wp_kses_post( $data['bio'] ) : null,
			'photo_url'  => $photo ?: null,
			'email'      => $email ?: null,
		];
		$formats = [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ];

		if ( $is_new ) {
			$row['id']         = DigitOne_Events_Helpers_Format::uuid();
			$row['created_at'] = current_time( 'mysql' );
			$formats[]         = '%s';
			$formats[]         = '%s';
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

		// Sync role assignments.
		$incoming_roles = isset( $data['role_ids'] ) && is_array( $data['role_ids'] )
			? array_filter( array_map( 'sanitize_text_field', $data['role_ids'] ) )
			: [];
		$this->sync_roles( $id, $event_id, $incoming_roles );

		return $id;
	}

	/**
	 * Replace the speaker's role set. Validates that role IDs belong to the
	 * same event (prevents cross-event role assignment).
	 *
	 * @param string[] $role_ids
	 */
	private function sync_roles( string $speaker_id, string $event_id, array $role_ids ) : void {
		global $wpdb;
		$j = $this->junction_table();

		// Wipe existing.
		$wpdb->delete( $j, [ 'speaker_id' => $speaker_id ], [ '%s' ] );

		if ( empty( $role_ids ) ) {
			return;
		}

		// Validate via Roles repository.
		$roles_repo = DigitOne_Events_Plugin::instance()->module( 'roles' )->repo();
		$valid_ids  = $roles_repo->filter_valid_ids( $role_ids, $event_id );

		foreach ( $valid_ids as $rid ) {
			$wpdb->insert(
				$j,
				[
					'id'         => DigitOne_Events_Helpers_Format::uuid(),
					'speaker_id' => $speaker_id,
					'role_id'    => $rid,
				],
				[ '%s', '%s', '%s' ]
			);
		}
	}

	public function delete( string $id ) : bool {
		global $wpdb;
		if ( ! $this->find( $id ) ) {
			return false;
		}
		// Junction rows.
		$wpdb->delete( $this->junction_table(), [ 'speaker_id' => $id ], [ '%s' ] );
		// session_roles references.
		$session_roles = DigitOne_Events_Database_Schema::table( 'session_roles' );
		$wpdb->delete( $session_roles, [ 'speaker_id' => $id ], [ '%s' ] );
		// Speaker itself.
		return (bool) $wpdb->delete( $this->table(), [ 'id' => $id ], [ '%s' ] );
	}
}
