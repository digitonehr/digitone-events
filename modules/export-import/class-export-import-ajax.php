<?php
/**
 * Export / Import handlers — both AJAX and admin-post.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Export_Import_Ajax {

	public const EXPORT_NONCE = 'digitone_events_export';
	public const IMPORT_NONCE = 'digitone_events_import';

	private DigitOne_Events_Export_Import_Repository $repo;

	public function __construct( DigitOne_Events_Export_Import_Repository $repo ) {
		$this->repo = $repo;
	}

	public function register() : void {
		// (admin_post_ hooks are registered in module->register())
		add_action( 'wp_ajax_digitone_events_excel_snapshot', [ $this, 'excel_snapshot' ] );
		add_action( 'wp_ajax_digitone_events_excel_preview',  [ $this, 'excel_preview' ] );
		add_action( 'wp_ajax_digitone_events_excel_commit',   [ $this, 'excel_commit' ] );
	}

	/* ============================================================ */
	/* EXCEL: snapshot / preview / commit (v0.9.0)                  */
	/* ============================================================ */

	/**
	 * Returns the current event's data as a sheet-shaped JSON. Client side
	 * turns this into an .xlsx via SheetJS. `mode=empty` returns the same
	 * shape but with zero data rows — used for the "Download template" link.
	 */
	public function excel_snapshot() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$event_id = isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '';
		$mode     = isset( $_POST['mode'] )     ? sanitize_text_field( wp_unslash( $_POST['mode'] ) )     : 'full';
		if ( $event_id === '' ) {
			wp_send_json_error( [ 'message' => __( 'No active event.', 'digitone-events' ) ] );
		}
		$excel = new DigitOne_Events_Export_Import_Excel();
		$data  = $excel->snapshot( $event_id, $mode === 'empty' );
		wp_send_json_success( [
			'event_id' => $event_id,
			'data'     => $data,
			'schema'   => DigitOne_Events_Export_Import_Excel::ENTITIES,
		] );
	}

	/**
	 * Dry-run a parsed Excel payload: validate FKs, count what would happen.
	 * Same code path as commit, just `dry_run=true`.
	 */
	public function excel_preview() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$this->run_excel_payload( /* dry_run = */ true );
	}

	/**
	 * Actually commit a parsed Excel payload to the database.
	 */
	public function excel_commit() : void {
		DigitOne_Events_Security_Nonce::verify_ajax();
		$this->run_excel_payload( /* dry_run = */ false );
	}

	private function run_excel_payload( bool $dry_run ) : void {
		$event_id    = isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '';
		$mode        = isset( $_POST['mode'] )     ? sanitize_text_field( wp_unslash( $_POST['mode'] ) )     : 'incremental';
		$payload_raw = isset( $_POST['payload'] )  ? wp_unslash( $_POST['payload'] )                          : '';
		if ( $event_id === '' || ! is_string( $payload_raw ) || $payload_raw === '' ) {
			wp_send_json_error( [ 'message' => __( 'Missing event or payload.', 'digitone-events' ) ] );
		}
		if ( ! in_array( $mode, [ 'incremental', 'full' ], true ) ) {
			$mode = 'incremental';
		}

		$payload = json_decode( $payload_raw, true );
		if ( ! is_array( $payload ) ) {
			wp_send_json_error( [ 'message' => __( 'Payload is not valid JSON.', 'digitone-events' ) ] );
		}

		// 0.9.0 ships incremental only; full mode is in 0.9.1.
		if ( $mode === 'full' ) {
			wp_send_json_error( [ 'message' => __( 'Full mode is not yet enabled.', 'digitone-events' ) ] );
		}

		$excel  = new DigitOne_Events_Export_Import_Excel();
		$report = $excel->run( $payload, $event_id, $mode, $dry_run );
		wp_send_json_success( $report );
	}

	/* ============================================================ */
	/* EXPORT (admin-post.php?action=digitone_events_export)        */
	/* ============================================================ */
	public function handle_export() : void {
		if ( ! DigitOne_Events_Security_Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'digitone-events' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( self::EXPORT_NONCE );

		$format   = isset( $_GET['format'] )   ? sanitize_text_field( wp_unslash( $_GET['format'] ) )   : 'json';
		$event_id = isset( $_GET['event_id'] ) ? sanitize_text_field( wp_unslash( $_GET['event_id'] ) ) : '';
		if ( $event_id === '' ) {
			wp_die( esc_html__( 'No event specified.', 'digitone-events' ), '', [ 'response' => 400 ] );
		}

		$snapshot = $this->repo->snapshot( $event_id );
		if ( ! $snapshot ) {
			wp_die( esc_html__( 'Event not found.', 'digitone-events' ), '', [ 'response' => 404 ] );
		}

		$slug = $snapshot['event']['slug'] ?: 'event';
		$base = 'digitone-events_' . sanitize_file_name( $slug ) . '_' . gmdate( 'Ymd-His' );

		if ( $format === 'csv' ) {
			$this->stream_csv_zip( $snapshot, $base );
		} else {
			$this->stream_json( $snapshot, $base );
		}
	}

	private function stream_json( array $snapshot, string $base ) : void {
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $base . '.json"' );
		echo wp_json_encode( $snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	private function stream_csv_zip( array $snapshot, string $base ) : void {
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'PHP ZipArchive extension is required for CSV export.', 'digitone-events' ), '', [ 'response' => 500 ] );
		}

		$tmp = wp_tempnam( $base . '.zip' );
		$zip = new ZipArchive();
		if ( $zip->open( $tmp, ZipArchive::OVERWRITE | ZipArchive::CREATE ) !== true ) {
			wp_die( esc_html__( 'Could not create zip file.', 'digitone-events' ), '', [ 'response' => 500 ] );
		}

		// One CSV per entity table.
		$zip->addFromString( 'event.csv',          $this->rows_to_csv( [ $snapshot['event'] ] ) );
		$zip->addFromString( 'days.csv',           $this->rows_to_csv( $snapshot['days'] ) );
		$zip->addFromString( 'venues.csv',         $this->rows_to_csv( $snapshot['venues'] ) );
		$zip->addFromString( 'titles.csv',         $this->rows_to_csv( $snapshot['titles'] ) );
		$zip->addFromString( 'roles.csv',          $this->rows_to_csv( $snapshot['roles'] ) );
		$zip->addFromString( 'session_types.csv',  $this->rows_to_csv( $snapshot['session_types'] ) );

		// Speakers: flatten role_ids to a pipe-separated string column.
		$speakers_flat = array_map( function ( $sp ) {
			$sp['role_ids'] = isset( $sp['role_ids'] ) ? implode( '|', $sp['role_ids'] ) : '';
			return $sp;
		}, $snapshot['speakers'] );
		$zip->addFromString( 'speakers.csv', $this->rows_to_csv( $speakers_flat ) );

		// Sessions: emit two CSVs — sessions.csv (one row per session) and session_speakers.csv (one row per assignment).
		$sessions_flat = array_map( function ( $s ) {
			unset( $s['speaker_assignments'] );
			return $s;
		}, $snapshot['sessions'] );
		$zip->addFromString( 'sessions.csv', $this->rows_to_csv( $sessions_flat ) );

		$assignments = [];
		foreach ( $snapshot['sessions'] as $s ) {
			foreach ( $s['speaker_assignments'] ?? [] as $a ) {
				$assignments[] = [
					'session_id' => $s['id'],
					'speaker_id' => $a['speaker_id'] ?? '',
					'role_id'    => $a['role_id']    ?? '',
				];
			}
		}
		$zip->addFromString( 'session_speakers.csv', $this->rows_to_csv( $assignments ) );

		$zip->close();

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $base . '.zip"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}

	/**
	 * Convert array of rows (assoc) to CSV string. First row = headers.
	 */
	private function rows_to_csv( array $rows ) : string {
		if ( empty( $rows ) ) {
			return '';
		}
		// Use stable header set from first row.
		$headers = array_keys( $rows[0] );

		$fh = fopen( 'php://temp', 'r+' );
		// Excel-friendly UTF-8 BOM.
		fwrite( $fh, "\xEF\xBB\xBF" );
		fputcsv( $fh, $headers );
		foreach ( $rows as $row ) {
			$out = [];
			foreach ( $headers as $h ) {
				$v = $row[ $h ] ?? '';
				if ( is_array( $v ) ) {
					$v = wp_json_encode( $v );
				}
				$out[] = (string) $v;
			}
			fputcsv( $fh, $out );
		}
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh );
		return $csv;
	}

	/* ============================================================ */
	/* IMPORT (admin-post.php?action=digitone_events_import)        */
	/* ============================================================ */
	public function handle_import() : void {
		if ( ! DigitOne_Events_Security_Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'digitone-events' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( self::IMPORT_NONCE );

		$redirect = admin_url( 'admin.php?page=digitone-events-export-import' );

		if ( empty( $_FILES['import_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'no_file', $redirect ) );
			exit;
		}

		$file = $_FILES['import_file'];
		$name = strtolower( $file['name'] );
		if ( substr( $name, -5 ) !== '.json' ) {
			wp_safe_redirect( add_query_arg( 'error', 'bad_type', $redirect ) );
			exit;
		}
		// Size limit 10 MB — sensible for event JSON.
		if ( ! empty( $file['size'] ) && $file['size'] > 10 * MB_IN_BYTES ) {
			wp_safe_redirect( add_query_arg( 'error', 'too_big', $redirect ) );
			exit;
		}

		$content = file_get_contents( $file['tmp_name'] );
		if ( $content === false ) {
			wp_safe_redirect( add_query_arg( 'error', 'unreadable', $redirect ) );
			exit;
		}

		$snapshot = json_decode( $content, true );
		if ( ! is_array( $snapshot ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'invalid_json', $redirect ) );
			exit;
		}

		$override_name = isset( $_POST['new_name'] ) ? sanitize_text_field( wp_unslash( $_POST['new_name'] ) ) : '';

		$result = $this->repo->restore_as_new_event( $snapshot, $override_name );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( $result->get_error_message() ), $redirect ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( [ 'imported' => '1', 'new_id' => $result['event_id'] ], $redirect ) );
		exit;
	}
}
