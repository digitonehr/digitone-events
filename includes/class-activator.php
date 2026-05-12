<?php
/**
 * Plugin activation handler. Creates DB schema and seeds defaults.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Activator {

	public static function activate() : void {
		// Make sure the autoloader has resolved Schema.
		if ( ! class_exists( 'DigitOne_Events_Database_Schema' ) ) {
			require_once DIGITONE_EVENTS_DIR . 'includes/database/class-schema.php';
		}

		DigitOne_Events_Database_Schema::install();

		// Mark version so updater knows we're fresh.
		update_option( 'digitone_events_version', DIGITONE_EVENTS_VERSION );

		// Clear any caches.
		wp_cache_flush();
	}
}
