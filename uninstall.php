<?php
/**
 * Plugin uninstall handler. Drops all DigitOne Events tables and options.
 *
 * Triggered by WordPress when the user clicks "Delete" on the plugins page.
 *
 * @package DigitOne_Events
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Need to bootstrap our schema class without booting the full plugin.
$plugin_dir = plugin_dir_path( __FILE__ );

if ( ! defined( 'DIGITONE_EVENTS_TABLE_PREFIX' ) ) {
	define( 'DIGITONE_EVENTS_TABLE_PREFIX', 'digitone_' );
}

require_once $plugin_dir . 'includes/database/class-schema.php';

DigitOne_Events_Database_Schema::drop_all();

// Clean up options.
delete_option( 'digitone_events_version' );
delete_option( 'digitone_events_gh_owner' );
delete_option( 'digitone_events_gh_repo' );
delete_option( 'digitone_events_gh_token' );

// Clean up transients.
delete_transient( 'digitone_events_gh_release' );

// Clean up user meta (active event references).
global $wpdb;
$wpdb->delete( $wpdb->usermeta, [ 'meta_key' => 'digitone_events_active_event' ], [ '%s' ] );
