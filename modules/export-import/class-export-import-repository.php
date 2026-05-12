<?php
/**
 * Export / Import repository.
 * Gathers a complete event snapshot into a single array, and restores from one.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Export_Import_Repository {

	public const FORMAT_VERSION = '1.0';

	/**
	 * Build a portable snapshot of the entire event.
	 *
	 * @return array|null Null if event not found.
	 */
	public function snapshot( string $event_id ) : ?array {
		global $wpdb;

		$plugin       = DigitOne_Events_Plugin::instance();
		$event        = $plugin->module( 'events' )->repo()->find( $event_id );
		if ( ! $event ) {
			return null;
		}

		$days          = $plugin->module( 'days' )->repo()->all_for_event( $event_id );
		$venues        = $plugin->module( 'venues' )->repo()->all_for_event( $event_id );
		$titles        = $plugin->module( 'titles' )->repo()->all_for_event( $event_id );
		$roles         = $plugin->module( 'roles' )->repo()->all_for_event( $event_id );
		$speakers      = $plugin->module( 'speakers' )->repo()->all_for_event( $event_id );
		$session_types = $plugin->module( 'session_types' )->repo()->all_for_event( $event_id );

		// Sessions from all days, flattened.
		$sessions = [];
		foreach ( $days as $d ) {
			foreach ( $plugin->module( 'sessions' )->repo()->all_for_day( $d['id'] ) as $s ) {
				$sessions[] = $s;
			}
		}

		// Speaker → role IDs (already attached in speakers repo? Check.)
		// Speakers repo all_for_event returns 'roles' array of objects, not 'role_ids'.
		// Normalize to role_ids for clean export.
		foreach ( $speakers as &$sp ) {
			$sp['role_ids'] = ! empty( $sp['roles'] ) ? array_column( $sp['roles'], 'id' ) : [];
			unset( $sp['roles'], $sp['title_name'] );
		}
		unset( $sp );

		// Sessions: convert speakers array into [{speaker_id, role_id}] structure.
		foreach ( $sessions as &$s ) {
			$assignments = [];
			if ( ! empty( $s['speakers'] ) ) {
				foreach ( $s['speakers'] as $sp ) {
					$assignments[] = [
						'speaker_id' => $sp['speaker_id'],
						'role_id'    => $sp['role_id'] ?: null,
					];
				}
			}
			$s['speaker_assignments'] = $assignments;
			unset( $s['speakers'], $s['type_name'], $s['type_icon'], $s['type_color'], $s['venue_name'], $s['sub_venue_name'] );
		}
		unset( $s );

		return [
			'format_version' => self::FORMAT_VERSION,
			'plugin_version' => defined( 'DIGITONE_EVENTS_VERSION' ) ? DIGITONE_EVENTS_VERSION : '0.0.0',
			'exported_at'    => gmdate( 'c' ),
			'event'          => $event,
			'days'           => $days,
			'venues'         => $venues,
			'titles'         => $titles,
			'roles'          => $roles,
			'speakers'       => $speakers,
			'session_types'  => $session_types,
			'sessions'       => $sessions,
		];
	}

	/**
	 * Restore a snapshot as a brand-new event. Generates fresh UUIDs for everything
	 * and maintains relationships via an old→new ID map.
	 *
	 * @return array{event_id:string,counts:array<string,int>}|WP_Error
	 */
	public function restore_as_new_event( array $snapshot, ?string $override_name = null ) {
		if ( ! isset( $snapshot['event'], $snapshot['format_version'] ) ) {
			return new WP_Error( 'invalid_format', __( 'File does not look like a DigitOne Events export.', 'digitone-events' ) );
		}
		if ( $snapshot['format_version'] !== self::FORMAT_VERSION ) {
			return new WP_Error( 'wrong_version', sprintf(
				/* translators: %s: format version found */
				__( 'Unsupported export format version: %s', 'digitone-events' ),
				$snapshot['format_version']
			) );
		}

		global $wpdb;
		$plugin = DigitOne_Events_Plugin::instance();

		// Mapping tables.
		$id_map = [
			'days'          => [],
			'venues'        => [],
			'titles'        => [],
			'roles'         => [],
			'speakers'      => [],
			'session_types' => [],
			'sessions'      => [],
		];

		$wpdb->query( 'START TRANSACTION' );

		try {
			// 1. Create the event.
			$src_event = $snapshot['event'];
			$name = $override_name !== null && $override_name !== '' ? $override_name : ( $src_event['name'] . ' (imported)' );
			$new_event_id = $plugin->module( 'events' )->repo()->save( [
				'name'        => $name,
				'description' => $src_event['description'] ?? '',
				'start_date'  => $src_event['start_date'] ?? '',
				'end_date'    => $src_event['end_date'] ?? '',
				'status'      => 'draft',
			] );
			if ( ! $new_event_id ) {
				throw new Exception( 'Failed to create event.' );
			}

			$counts = [ 'event' => 1 ];

			// 2. Days.
			$counts['days'] = 0;
			foreach ( $snapshot['days'] ?? [] as $row ) {
				$new = $plugin->module( 'days' )->repo()->save( [
					'event_id'   => $new_event_id,
					'day_date'   => $row['day_date']   ?? '',
					'start_time' => $row['start_time'] ?? '',
					'end_time'   => $row['end_time']   ?? '',
					'label'      => $row['label']      ?? '',
					'sort_order' => $row['sort_order'] ?? 0,
				] );
				if ( $new ) {
					$id_map['days'][ $row['id'] ] = $new;
					$counts['days']++;
				}
			}

			// 3. Venues — primary first, then sub-venues (need parent mapping).
			$counts['venues'] = 0;
			$primaries = array_filter( $snapshot['venues'] ?? [], fn( $v ) => ( $v['venue_type'] ?? 'primary' ) === 'primary' );
			$subs      = array_filter( $snapshot['venues'] ?? [], fn( $v ) => ( $v['venue_type'] ?? '' ) === 'sub-venue' );
			foreach ( $primaries as $row ) {
				$new = $plugin->module( 'venues' )->repo()->save( [
					'event_id'   => $new_event_id,
					'name'       => $row['name'] ?? '',
					'address'    => $row['address'] ?? '',
					'venue_type' => 'primary',
					'sort_order' => $row['sort_order'] ?? 0,
				] );
				if ( $new ) {
					$id_map['venues'][ $row['id'] ] = $new;
					$counts['venues']++;
				}
			}
			foreach ( $subs as $row ) {
				$parent_new = $id_map['venues'][ $row['parent_id'] ?? '' ] ?? null;
				if ( ! $parent_new ) continue;
				$new = $plugin->module( 'venues' )->repo()->save( [
					'event_id'   => $new_event_id,
					'name'       => $row['name'] ?? '',
					'address'    => $row['address'] ?? '',
					'venue_type' => 'sub-venue',
					'parent_id'  => $parent_new,
					'sort_order' => $row['sort_order'] ?? 0,
				] );
				if ( $new ) {
					$id_map['venues'][ $row['id'] ] = $new;
					$counts['venues']++;
				}
			}

			// 4. Titles + Roles + Session Types — straightforward.
			foreach ( [
				'titles'        => 'titles',
				'roles'         => 'roles',
				'session_types' => 'session_types',
			] as $key => $module ) {
				$counts[ $key ] = 0;
				foreach ( $snapshot[ $key ] ?? [] as $row ) {
					$args = [
						'event_id'   => $new_event_id,
						'name'       => $row['name'] ?? '',
						'sort_order' => $row['sort_order'] ?? 0,
					];
					if ( $key === 'roles' || $key === 'session_types' ) {
						$args['color'] = $row['color'] ?? '';
					}
					if ( $key === 'session_types' ) {
						$args['icon'] = $row['icon'] ?? '';
					}
					$new = $plugin->module( $module )->repo()->save( $args );
					if ( $new ) {
						$id_map[ $key ][ $row['id'] ] = $new;
						$counts[ $key ]++;
					}
				}
			}

			// 5. Speakers (with title_id remap + role_ids remap).
			$counts['speakers'] = 0;
			foreach ( $snapshot['speakers'] ?? [] as $row ) {
				$mapped_role_ids = [];
				foreach ( $row['role_ids'] ?? [] as $rid ) {
					if ( isset( $id_map['roles'][ $rid ] ) ) {
						$mapped_role_ids[] = $id_map['roles'][ $rid ];
					}
				}
				$new = $plugin->module( 'speakers' )->repo()->save( [
					'event_id'   => $new_event_id,
					'title_id'   => isset( $row['title_id'], $id_map['titles'][ $row['title_id'] ] ) ? $id_map['titles'][ $row['title_id'] ] : '',
					'first_name' => $row['first_name'] ?? '',
					'last_name'  => $row['last_name']  ?? '',
					'bio'        => $row['bio']        ?? '',
					'photo_url'  => $row['photo_url']  ?? '',
					'email'      => $row['email']      ?? '',
					'role_ids'   => $mapped_role_ids,
				] );
				if ( $new ) {
					$id_map['speakers'][ $row['id'] ] = $new;
					$counts['speakers']++;
				}
			}

			// 6. Sessions — two passes: master sessions first, then child sessions (to resolve parent_id).
			$counts['sessions'] = 0;
			$masters_to_create = array_filter( $snapshot['sessions'] ?? [], fn( $s ) => ( $s['session_level'] ?? 'master' ) === 'master' );
			$children_to_create = array_filter( $snapshot['sessions'] ?? [], fn( $s ) => ( $s['session_level'] ?? '' ) === 'child' );

			foreach ( array_merge( $masters_to_create, $children_to_create ) as $row ) {
				$day_new = $id_map['days'][ $row['day_id'] ?? '' ] ?? null;
				if ( ! $day_new ) continue;

				$assignments = $row['speaker_assignments'] ?? [];
				$speaker_ids = [];
				$role_map    = [];
				foreach ( $assignments as $a ) {
					$old_sp = $a['speaker_id'] ?? '';
					if ( ! isset( $id_map['speakers'][ $old_sp ] ) ) continue;
					$new_sp = $id_map['speakers'][ $old_sp ];
					$speaker_ids[] = $new_sp;
					if ( ! empty( $a['role_id'] ) && isset( $id_map['roles'][ $a['role_id'] ] ) ) {
						$role_map[ $new_sp ] = $id_map['roles'][ $a['role_id'] ];
					}
				}

				$args = [
					'event_id'         => $new_event_id,
					'day_id'           => $day_new,
					'session_type_id'  => isset( $row['session_type_id'], $id_map['session_types'][ $row['session_type_id'] ] ) ? $id_map['session_types'][ $row['session_type_id'] ] : '',
					'venue_id'         => isset( $row['venue_id'], $id_map['venues'][ $row['venue_id'] ] )                     ? $id_map['venues'][ $row['venue_id'] ]                     : '',
					'sub_venue_id'     => isset( $row['sub_venue_id'], $id_map['venues'][ $row['sub_venue_id'] ] )             ? $id_map['venues'][ $row['sub_venue_id'] ]                 : '',
					'title'            => $row['title']         ?? '',
					'description'      => $row['description']   ?? '',
					'start_time'       => $row['start_time']    ?? '',
					'end_time'         => $row['end_time']      ?? '',
					'session_level'    => $row['session_level'] ?? 'master',
					'sort_order'       => $row['sort_order']    ?? 0,
					'speaker_ids'      => $speaker_ids,
					'speaker_role_map' => $role_map,
				];
				if ( ( $row['session_level'] ?? '' ) === 'child' && ! empty( $row['parent_id'] ) ) {
					$args['parent_id'] = $id_map['sessions'][ $row['parent_id'] ] ?? '';
					if ( $args['parent_id'] === '' ) {
						// Parent not yet mapped (could happen with bad data). Demote to master.
						$args['session_level'] = 'master';
						$args['parent_id']     = '';
					}
				}
				$new = $plugin->module( 'sessions' )->repo()->save( $args );
				if ( $new ) {
					$id_map['sessions'][ $row['id'] ] = $new;
					$counts['sessions']++;
				}
			}

			$wpdb->query( 'COMMIT' );

			return [ 'event_id' => $new_event_id, 'counts' => $counts ];

		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'restore_failed', $e->getMessage() );
		}
	}
}
