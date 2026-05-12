<?php
/**
 * Database schema for all DigitOne Events tables.
 *
 * - 10 tables, all prefixed with $wpdb->prefix . DIGITONE_EVENTS_TABLE_PREFIX
 * - Uses proper DATE/TIME column types (not varchar(8) DDMMYYYY).
 * - Foreign keys intentionally omitted: dbDelta does not reliably manage them
 *   across MySQL versions. Cascading deletes are enforced in PHP (see
 *   repositories' delete() methods).
 *
 * Versioning:
 *   - SCHEMA_VERSION is bumped whenever any table definition changes.
 *   - maybe_upgrade() compares against stored option and re-runs dbDelta if needed.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Database_Schema {

	public const SCHEMA_VERSION = '1';
	public const OPTION_KEY     = 'digitone_events_schema_version';

	public static function table( string $name ) : string {
		global $wpdb;
		return $wpdb->prefix . DIGITONE_EVENTS_TABLE_PREFIX . $name;
	}

	public static function install() : void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		foreach ( self::ddl_statements( $charset ) as $sql ) {
			dbDelta( $sql );
		}

		update_option( self::OPTION_KEY, self::SCHEMA_VERSION );
	}

	public static function maybe_upgrade() : void {
		$current = get_option( self::OPTION_KEY );
		if ( $current !== self::SCHEMA_VERSION ) {
			self::install();
		}
	}

	public static function drop_all() : void {
		global $wpdb;
		// Drop in reverse-dependency order.
		$tables = [
			'session_roles',
			'sessions',
			'session_types',
			'speakers_roles',
			'speakers',
			'roles',
			'titles',
			'venues',
			'days',
			'events',
		];
		foreach ( $tables as $t ) {
			$name = self::table( $t );
			$wpdb->query( "DROP TABLE IF EXISTS `{$name}`" );
		}
		delete_option( self::OPTION_KEY );
	}

	/**
	 * @return string[] DDL statements (one per table, dbDelta-safe formatting).
	 */
	private static function ddl_statements( string $charset ) : array {
		$events         = self::table( 'events' );
		$days           = self::table( 'days' );
		$venues         = self::table( 'venues' );
		$speakers       = self::table( 'speakers' );
		$speakers_roles = self::table( 'speakers_roles' );
		$titles         = self::table( 'titles' );
		$roles          = self::table( 'roles' );
		$sessions       = self::table( 'sessions' );
		$session_roles  = self::table( 'session_roles' );
		$session_types  = self::table( 'session_types' );

		$sql = [];

		$sql[] = "CREATE TABLE {$events} (
			id varchar(36) NOT NULL,
			name varchar(255) NOT NULL,
			slug varchar(255) NOT NULL,
			description longtext NULL,
			start_date date NULL,
			end_date date NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_by bigint(20) UNSIGNED NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uk_slug (slug),
			KEY idx_status (status),
			KEY idx_created_at (created_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$days} (
			id varchar(36) NOT NULL,
			event_id varchar(36) NOT NULL,
			day_date date NOT NULL,
			start_time time NULL,
			end_time time NULL,
			label varchar(255) NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_event (event_id),
			KEY idx_event_date (event_id, day_date)
		) {$charset};";

		$sql[] = "CREATE TABLE {$venues} (
			id varchar(36) NOT NULL,
			event_id varchar(36) NOT NULL,
			parent_id varchar(36) NULL,
			name varchar(255) NOT NULL,
			address text NULL,
			venue_type varchar(20) NOT NULL DEFAULT 'primary',
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_event (event_id),
			KEY idx_parent (parent_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$speakers} (
			id varchar(36) NOT NULL,
			event_id varchar(36) NOT NULL,
			title_id varchar(36) NULL,
			first_name varchar(100) NOT NULL,
			last_name varchar(100) NOT NULL,
			bio longtext NULL,
			photo_url text NULL,
			email varchar(190) NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_event (event_id),
			KEY idx_name (last_name, first_name)
		) {$charset};";

		$sql[] = "CREATE TABLE {$speakers_roles} (
			id varchar(36) NOT NULL,
			speaker_id varchar(36) NOT NULL,
			role_id varchar(36) NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uk_speaker_role (speaker_id, role_id),
			KEY idx_role (role_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$titles} (
			id varchar(36) NOT NULL,
			event_id varchar(36) NOT NULL,
			name varchar(100) NOT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY idx_event (event_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$roles} (
			id varchar(36) NOT NULL,
			event_id varchar(36) NOT NULL,
			name varchar(100) NOT NULL,
			color varchar(20) NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY idx_event (event_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$sessions} (
			id varchar(36) NOT NULL,
			event_id varchar(36) NOT NULL,
			day_id varchar(36) NOT NULL,
			parent_id varchar(36) NULL,
			session_type_id varchar(36) NULL,
			venue_id varchar(36) NULL,
			sub_venue_id varchar(36) NULL,
			title varchar(255) NOT NULL,
			description longtext NULL,
			start_time time NULL,
			end_time time NULL,
			session_level varchar(20) NOT NULL DEFAULT 'master',
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_event (event_id),
			KEY idx_day (day_id),
			KEY idx_parent (parent_id),
			KEY idx_venue (venue_id),
			KEY idx_event_day_start (event_id, day_id, start_time)
		) {$charset};";

		$sql[] = "CREATE TABLE {$session_roles} (
			id varchar(36) NOT NULL,
			session_id varchar(36) NOT NULL,
			speaker_id varchar(36) NOT NULL,
			role_id varchar(36) NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uk_session_speaker (session_id, speaker_id),
			KEY idx_speaker (speaker_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$session_types} (
			id varchar(36) NOT NULL,
			event_id varchar(36) NOT NULL,
			name varchar(100) NOT NULL,
			icon varchar(50) NULL,
			color varchar(20) NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY idx_event (event_id)
		) {$charset};";

		return $sql;
	}
}
