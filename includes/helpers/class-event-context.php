<?php
/**
 * Active-event context. Replaces DigiCal's $_SESSION-based active event with
 * user meta — survives page caching, works on multiple browser tabs, doesn't
 * require session_start().
 *
 * Priority order for resolving "the active event":
 *   1. ?event_id= GET parameter (per-request override)
 *   2. user meta 'digitone_events_active_event'
 *   3. first published event
 *   4. null
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Helpers_Event_Context {

	private const USER_META_KEY = 'digitone_events_active_event';

	public static function active_event_id() : ?string {
		// 1. URL override.
		if ( isset( $_GET['event_id'] ) ) {
			$id = sanitize_text_field( wp_unslash( $_GET['event_id'] ) );
			if ( self::event_exists( $id ) ) {
				return $id;
			}
		}

		// 2. User meta.
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$saved = get_user_meta( $user_id, self::USER_META_KEY, true );
			if ( $saved && self::event_exists( $saved ) ) {
				return $saved;
			}
			// Stale reference — clear it.
			if ( $saved ) {
				delete_user_meta( $user_id, self::USER_META_KEY );
			}
		}

		// 3. First published event.
		global $wpdb;
		$table = DigitOne_Events_Database_Schema::table( 'events' );
		$id    = $wpdb->get_var( "SELECT id FROM {$table} WHERE status = 'published' ORDER BY created_at DESC LIMIT 1" );

		return $id ?: null;
	}

	public static function set_active_event( string $event_id ) : bool {
		if ( ! self::event_exists( $event_id ) ) {
			return false;
		}
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		return (bool) update_user_meta( $user_id, self::USER_META_KEY, $event_id );
	}

	public static function clear_active_event() : void {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			delete_user_meta( $user_id, self::USER_META_KEY );
		}
	}

	private static function event_exists( string $event_id ) : bool {
		if ( $event_id === '' ) {
			return false;
		}
		global $wpdb;
		$table  = DigitOne_Events_Database_Schema::table( 'events' );
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %s LIMIT 1", $event_id ) );
		return ! empty( $exists );
	}
}
