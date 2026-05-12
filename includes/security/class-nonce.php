<?php
/**
 * Centralized nonce helpers.
 *
 * One nonce action across the whole plugin admin: 'digitone_events_nonce'.
 * Every AJAX endpoint and every admin form MUST use this class.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Security_Nonce {

	public const ACTION = 'digitone_events_nonce';
	public const FIELD  = 'digitone_events_nonce';

	public static function create() : string {
		return wp_create_nonce( self::ACTION );
	}

	public static function field( bool $referer = true ) : void {
		wp_nonce_field( self::ACTION, self::FIELD, $referer, true );
	}

	/**
	 * Verify an AJAX request: capability THEN nonce. Dies with 403 on failure.
	 *
	 * @param string $cap Capability required. Empty = use plugin default.
	 */
	public static function verify_ajax( string $cap = '' ) : void {
		if ( $cap === '' ) {
			$cap = DigitOne_Events_Security_Capabilities::manage_cap();
		}

		if ( ! current_user_can( $cap ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'digitone-events' ) ], 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed. Please refresh and try again.', 'digitone-events' ) ], 403 );
		}
	}

	/**
	 * Verify a classic form POST. Returns true/false (does not die).
	 */
	public static function verify_form() : bool {
		if ( ! current_user_can( DigitOne_Events_Security_Capabilities::manage_cap() ) ) {
			return false;
		}
		$nonce = isset( $_POST[ self::FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) ) : '';
		return (bool) wp_verify_nonce( $nonce, self::ACTION );
	}
}
