<?php
/**
 * Calendar module — exposes REST endpoints for iCal (.ics) downloads.
 *
 * Routes:
 *   GET /wp-json/digitone-events/v1/ical/event/{slug}
 *     → full event agenda as .ics (all non-break sessions across all days)
 *
 *   GET /wp-json/digitone-events/v1/ical/session/{session_id}
 *     → single session as .ics
 *
 * Both endpoints are public (no auth) and return Content-Type: text/calendar
 * with a Content-Disposition: attachment header so browsers offer a download
 * or hand off to the OS calendar app.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Calendar_Module {

	private const REST_NAMESPACE = 'digitone-events/v1';

	public function register() : void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() : void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/ical/event/(?P<slug>[A-Za-z0-9_-]+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'ics_event' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'slug' => [
						'sanitize_callback' => 'sanitize_title',
						'validate_callback' => function ( $v ) { return is_string( $v ) && $v !== ''; },
					],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ical/session/(?P<id>[A-Za-z0-9-]+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'ics_session' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'id' => [
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $v ) { return is_string( $v ) && $v !== ''; },
					],
				],
			]
		);
	}

	/* ============================================================
	 * Route handlers
	 * ============================================================ */

	public function ics_event( WP_REST_Request $request ) {
		$slug = (string) $request->get_param( 'slug' );

		$plugin = DigitOne_Events_Plugin::instance();
		$event  = $plugin->module( 'events' )->repo()->find_by_slug( $slug );
		if ( ! $event || ( $event['status'] ?? '' ) !== 'published' ) {
			return new WP_Error( 'not_found', 'Event not found or not published.', [ 'status' => 404 ] );
		}

		$days      = $plugin->module( 'days' )->repo()->all_for_event( $event['id'] );
		$sessions  = [];
		foreach ( $days as $day ) {
			$sessions[ $day['id'] ] = $plugin->module( 'sessions' )->repo()->all_for_day( $day['id'] );
		}

		$ics = DigitOne_Events_Helpers_Ical::event_ics( $event, $days, $sessions );

		return $this->send_ics( $ics, DigitOne_Events_Helpers_Ical::filename( $event['slug'] ) );
	}

	public function ics_session( WP_REST_Request $request ) {
		$session_id = (string) $request->get_param( 'id' );

		$plugin  = DigitOne_Events_Plugin::instance();
		$session = $plugin->module( 'sessions' )->repo()->find( $session_id );
		if ( ! $session ) {
			return new WP_Error( 'not_found', 'Session not found.', [ 'status' => 404 ] );
		}

		$day = $plugin->module( 'days' )->repo()->find( $session['day_id'] );
		if ( ! $day ) {
			return new WP_Error( 'not_found', 'Day not found.', [ 'status' => 404 ] );
		}

		$event = $plugin->module( 'events' )->repo()->find( $session['event_id'] );
		if ( ! $event || ( $event['status'] ?? '' ) !== 'published' ) {
			return new WP_Error( 'not_found', 'Event not published.', [ 'status' => 404 ] );
		}

		// Re-fetch the session with joined speaker/type/venue data.
		$enriched = $plugin->module( 'sessions' )->repo()->all_for_day( $day['id'] );
		$session  = null;
		foreach ( $enriched as $candidate ) {
			if ( $candidate['id'] === $session_id ) {
				$session = $candidate;
				break;
			}
		}
		if ( ! $session ) {
			return new WP_Error( 'not_found', 'Session not found.', [ 'status' => 404 ] );
		}

		$ics = DigitOne_Events_Helpers_Ical::session_ics( $event, $day, $session );

		return $this->send_ics( $ics, DigitOne_Events_Helpers_Ical::filename( $event['slug'], $session_id ) );
	}

	/**
	 * Emit the ICS as a download. We bypass WP's JSON response wrapper.
	 */
	private function send_ics( string $ics, string $filename ) {
		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $ics ) );
		echo $ics; // already RFC-5545-escaped
		exit;
	}
}
