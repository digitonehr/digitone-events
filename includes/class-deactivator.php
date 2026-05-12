<?php
/**
 * Plugin deactivation handler. Cleans up transients and scheduled tasks.
 * Tables are NOT dropped on deactivation — only on uninstall.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Deactivator {

	public static function deactivate() : void {
		// Clear updater cache.
		delete_transient( 'digitone_events_gh_release' );
		delete_site_transient( 'update_plugins' );

		// Unschedule any cron events (none yet, but kept for forward compatibility).
		$timestamp = wp_next_scheduled( 'digitone_events_daily' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'digitone_events_daily' );
		}
	}
}
