<?php
/**
 * Capabilities helper. Single source of truth for "who can manage this plugin".
 *
 * Filterable via 'digitone_events_manage_cap' so site admins can wire it to a
 * custom role (e.g. an "Event Manager" role) without touching plugin code.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Security_Capabilities {

	public static function manage_cap() : string {
		$cap = apply_filters( 'digitone_events_manage_cap', 'manage_options' );
		return is_string( $cap ) && $cap !== '' ? $cap : 'manage_options';
	}

	public static function current_user_can_manage() : bool {
		return current_user_can( self::manage_cap() );
	}
}
