<?php
/**
 * iCal (RFC 5545) generation helper.
 *
 * Builds .ics calendar files for whole events or individual sessions.
 * Session times are stored as local dates+times in the database and converted
 * to UTC for output so calendar apps display the right wall-clock time in the
 * delegate's own timezone.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Helpers_Ical {

	/**
	 * Build a full VCALENDAR for the given event, containing one VEVENT per
	 * non-break session.
	 *
	 * @param array                 $event    Event row (id, name, slug, ...).
	 * @param array<int,array>      $days     All days belonging to the event.
	 * @param array<string,array>   $sessions Map of day_id → array of session rows
	 *                                        (with speakers, venue, type joined in).
	 * @return string                          Full ICS text, terminated with CRLF.
	 */
	public static function event_ics( array $event, array $days, array $sessions ) : string {
		$tz = self::site_timezone();

		$lines   = self::vcalendar_header( $event );
		foreach ( $days as $day ) {
			$day_sessions = $sessions[ $day['id'] ] ?? [];
			foreach ( $day_sessions as $s ) {
				if ( self::is_break( $s ) ) {
					continue; // skip "Break" sessions in calendar export
				}
				$lines = array_merge(
					$lines,
					self::vevent_lines( $s, $day, $event, $tz )
				);
			}
		}
		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", array_map( [ self::class, 'fold_line' ], $lines ) ) . "\r\n";
	}

	/**
	 * Build a VCALENDAR containing a single VEVENT for one session.
	 */
	public static function session_ics( array $event, array $day, array $session ) : string {
		$tz    = self::site_timezone();
		$lines = self::vcalendar_header( $event );
		$lines = array_merge( $lines, self::vevent_lines( $session, $day, $event, $tz ) );
		$lines[] = 'END:VCALENDAR';
		return implode( "\r\n", array_map( [ self::class, 'fold_line' ], $lines ) ) . "\r\n";
	}

	/**
	 * Suggest a filename like "iaas-2026.ics" or "iaas-2026-session-uuid.ics".
	 */
	public static function filename( string $event_slug, ?string $session_id = null ) : string {
		$base = sanitize_file_name( $event_slug ?: 'event' );
		if ( $session_id ) {
			$base .= '-session-' . substr( preg_replace( '/[^a-z0-9]/i', '', $session_id ), 0, 8 );
		}
		return $base . '.ics';
	}

	/* ============================================================
	 * Internals
	 * ============================================================ */

	private static function vcalendar_header( array $event ) : array {
		return [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//DigitOne Events//' . DIGITONE_EVENTS_VERSION . '//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . self::escape_text( $event['name'] ?? 'Event' ),
		];
	}

	private static function vevent_lines( array $session, array $day, array $event, DateTimeZone $tz ) : array {
		$start_local = self::combine_datetime( $day['day_date'], $session['start_time'], $tz );
		$end_local   = self::combine_datetime( $day['day_date'], $session['end_time'],   $tz );
		if ( ! $start_local || ! $end_local ) {
			return [];
		}

		$venue_parts = array_filter( [
			$session['venue_name']     ?? '',
			$session['sub_venue_name'] ?? '',
		], 'strlen' );
		$location = implode( ' / ', $venue_parts );

		// Build description: plain description text + speakers list.
		$desc_parts = [];
		if ( ! empty( $session['description'] ) ) {
			$desc_parts[] = wp_strip_all_tags( $session['description'] );
		}
		if ( ! empty( $session['speakers'] ) ) {
			$names = [];
			foreach ( $session['speakers'] as $sp ) {
				$full = trim( ( $sp['first_name'] ?? '' ) . ' ' . ( $sp['last_name'] ?? '' ) );
				$role = $sp['role_name'] ?? '';
				if ( $full && $role ) {
					$names[] = "{$full} ({$role})";
				} elseif ( $full ) {
					$names[] = $full;
				}
			}
			if ( $names ) {
				$desc_parts[] = 'Speakers: ' . implode( ', ', $names );
			}
		}
		$description = implode( "\n\n", $desc_parts );

		$uid = ( $session['id'] ?? wp_generate_uuid4() ) . '@' . self::host();

		$lines = [
			'BEGIN:VEVENT',
			'UID:' . $uid,
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			'DTSTART:' . self::to_utc_string( $start_local ),
			'DTEND:'   . self::to_utc_string( $end_local ),
			'SUMMARY:' . self::escape_text( $session['title'] ?? '' ),
		];
		if ( $description !== '' ) {
			$lines[] = 'DESCRIPTION:' . self::escape_text( $description );
		}
		if ( $location !== '' ) {
			$lines[] = 'LOCATION:' . self::escape_text( $location );
		}
		if ( ! empty( $session['type_name'] ) ) {
			$lines[] = 'CATEGORIES:' . self::escape_text( $session['type_name'] );
		}
		$lines[] = 'STATUS:CONFIRMED';
		$lines[] = 'TRANSP:OPAQUE';
		$lines[] = 'END:VEVENT';

		return $lines;
	}

	private static function combine_datetime( ?string $date, ?string $time, DateTimeZone $tz ) : ?DateTime {
		if ( ! $date || ! $time ) {
			return null;
		}
		// Times may arrive as HH:MM or HH:MM:SS.
		if ( strlen( $time ) === 5 ) {
			$time .= ':00';
		}
		try {
			return new DateTime( $date . ' ' . $time, $tz );
		} catch ( Exception $e ) {
			return null;
		}
	}

	private static function to_utc_string( DateTime $dt ) : string {
		$utc = clone $dt;
		$utc->setTimezone( new DateTimeZone( 'UTC' ) );
		return $utc->format( 'Ymd\THis\Z' );
	}

	private static function site_timezone() : DateTimeZone {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}
		$tz_string = get_option( 'timezone_string' );
		if ( ! empty( $tz_string ) ) {
			try { return new DateTimeZone( $tz_string ); } catch ( Exception $e ) {}
		}
		$offset = (float) get_option( 'gmt_offset', 0 );
		$hours  = (int) $offset;
		$mins   = abs( ( $offset - $hours ) * 60 );
		$sign   = $offset >= 0 ? '+' : '-';
		try {
			return new DateTimeZone( sprintf( '%s%02d:%02d', $sign, abs( $hours ), $mins ) );
		} catch ( Exception $e ) {
			return new DateTimeZone( 'UTC' );
		}
	}

	private static function host() : string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host ?: 'localhost';
	}

	private static function is_break( array $session ) : bool {
		return ! empty( $session['type_name'] ) && strtolower( $session['type_name'] ) === 'break';
	}

	/**
	 * Escape text per RFC 5545 §3.3.11: backslash, semicolon, comma, newline.
	 */
	private static function escape_text( string $s ) : string {
		$s = str_replace( [ "\\", ";", ",", "\r\n", "\n", "\r" ], [ "\\\\", "\\;", "\\,", "\\n", "\\n", "\\n" ], $s );
		return $s;
	}

	/**
	 * Fold lines longer than 75 octets per RFC 5545 §3.1 (CRLF + space).
	 * Operates byte-safely on UTF-8 by never splitting in the middle of a multibyte sequence.
	 */
	private static function fold_line( string $line ) : string {
		if ( strlen( $line ) <= 75 ) {
			return $line;
		}
		$out      = '';
		$position = 0;
		$length   = strlen( $line );
		while ( $position < $length ) {
			$chunk_len = ( $position === 0 ) ? 75 : 74; // continuation lines start with a leading space
			$chunk     = substr( $line, $position, $chunk_len );
			// Don't break in the middle of a UTF-8 multibyte sequence: back off to a valid boundary.
			$chunk = self::trim_to_utf8_boundary( $chunk );
			$out  .= ( $position === 0 ? '' : "\r\n " ) . $chunk;
			$position += strlen( $chunk );
		}
		return $out;
	}

	private static function trim_to_utf8_boundary( string $s ) : string {
		// If the trailing byte is a continuation byte (10xxxxxx), back off to the last lead byte.
		$len = strlen( $s );
		while ( $len > 0 ) {
			$byte = ord( $s[ $len - 1 ] );
			if ( ( $byte & 0xC0 ) !== 0x80 ) {
				break; // not a continuation byte → safe to cut here
			}
			$len--;
		}
		return substr( $s, 0, $len );
	}
}
