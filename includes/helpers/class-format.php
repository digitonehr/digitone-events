<?php
/**
 * Formatting helpers: UUIDs, time normalization, date display.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Helpers_Format {

	/**
	 * Generate a UUID v4 string (36 chars).
	 */
	public static function uuid() : string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		// Fallback (should never hit on WP 4.7+).
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}

	/**
	 * Normalize a time input to HH:MM:SS for MySQL TIME column.
	 * Accepts: "8", "8:00", "08:30", "1615", "16.15", "16,15", "16:15:30".
	 *
	 * Returns empty string if input cannot be parsed.
	 */
	public static function time( $input ) : string {
		if ( $input === null || $input === '' ) {
			return '';
		}
		$input = trim( (string) $input );

		// Already HH:MM or HH:MM:SS
		if ( preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $input, $m ) ) {
			$h = (int) $m[1];
			$i = (int) $m[2];
			$s = isset( $m[3] ) ? (int) $m[3] : 0;
			if ( $h < 24 && $i < 60 && $s < 60 ) {
				return sprintf( '%02d:%02d:%02d', $h, $i, $s );
			}
			return '';
		}

		// Digit-only forms ("1615", "830")
		$digits = preg_replace( '/[^\d]/', '', $input );
		$len    = strlen( $digits );
		if ( $len === 0 ) {
			return '';
		}
		if ( $len === 1 || $len === 2 ) {
			$h = (int) $digits;
			return $h < 24 ? sprintf( '%02d:00:00', $h ) : '';
		}
		if ( $len === 3 ) {
			$h = (int) substr( $digits, 0, 1 );
			$i = (int) substr( $digits, 1, 2 );
			return ( $h < 24 && $i < 60 ) ? sprintf( '%02d:%02d:00', $h, $i ) : '';
		}
		// 4+ digits -> first 2 hours, next 2 minutes.
		$h = (int) substr( $digits, 0, 2 );
		$i = (int) substr( $digits, 2, 2 );
		return ( $h < 24 && $i < 60 ) ? sprintf( '%02d:%02d:00', $h, $i ) : '';
	}

	/**
	 * Normalize a date input to YYYY-MM-DD for MySQL DATE column.
	 * Accepts YYYY-MM-DD, DD.MM.YYYY, DD/MM/YYYY, DDMMYYYY (legacy DigiCal).
	 */
	public static function date( $input ) : string {
		if ( $input === null || $input === '' ) {
			return '';
		}
		$input = trim( (string) $input );

		// YYYY-MM-DD
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $input, $m ) ) {
			return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $input : '';
		}

		// DD.MM.YYYY or DD/MM/YYYY or DD-MM-YYYY
		if ( preg_match( '/^(\d{2})[.\/-](\d{2})[.\/-](\d{4})$/', $input, $m ) ) {
			return checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ? "{$m[3]}-{$m[2]}-{$m[1]}" : '';
		}

		// Legacy DDMMYYYY (DigiCal)
		if ( preg_match( '/^(\d{2})(\d{2})(\d{4})$/', $input, $m ) ) {
			return checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ? "{$m[3]}-{$m[2]}-{$m[1]}" : '';
		}

		// Try strtotime as last resort.
		$ts = strtotime( $input );
		return $ts ? date( 'Y-m-d', $ts ) : '';
	}

	/**
	 * Human-readable date in site's locale (uses WordPress's date_i18n).
	 */
	public static function date_display( ?string $ymd ) : string {
		if ( ! $ymd ) {
			return '';
		}
		$ts = strtotime( $ymd );
		if ( ! $ts ) {
			return '';
		}
		return date_i18n( get_option( 'date_format', 'Y-m-d' ), $ts );
	}

	/**
	 * Human-readable time (HH:MM, no seconds).
	 */
	public static function time_display( ?string $hms ) : string {
		if ( ! $hms ) {
			return '';
		}
		return substr( $hms, 0, 5 );
	}
}
