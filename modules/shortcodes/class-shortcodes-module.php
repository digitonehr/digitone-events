<?php
/**
 * Frontend shortcodes module. Registers:
 *   [digitone_events_agenda   event="slug-or-id"]
 *   [digitone_events_speakers event="slug-or-id"]
 *   [digitone_events_venues   event="slug-or-id"]
 *
 * Only published events are rendered. If "event" attribute is omitted, the
 * first published event is used.
 *
 * Frontend CSS is enqueued only on pages that actually contain one of these
 * shortcodes (see DigitOne_Events_Assets::enqueue_frontend).
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Shortcodes_Module {

	public const TAG_AGENDA   = 'digitone_events_agenda';
	public const TAG_SPEAKERS = 'digitone_events_speakers';
	public const TAG_VENUES   = 'digitone_events_venues';

	public function register() : void {
		add_action( 'init', [ $this, 'register_shortcodes' ] );
	}

	public function register_shortcodes() : void {
		add_shortcode( self::TAG_AGENDA,   [ $this, 'render_agenda' ] );
		add_shortcode( self::TAG_SPEAKERS, [ $this, 'render_speakers' ] );
		add_shortcode( self::TAG_VENUES,   [ $this, 'render_venues' ] );
	}

	/* ============================================================ */
	/* Public renderers                                             */
	/* ============================================================ */

	public function render_agenda( $atts ) : string {
		$this->enqueue_styles();
		$event = $this->resolve_event( $atts );
		if ( ! $event ) {
			return $this->not_found_message();
		}
		$plugin   = DigitOne_Events_Plugin::instance();
		$days     = $plugin->module( 'days' )->repo()->all_for_event( $event['id'] );
		$sessions_by_day = [];
		foreach ( $days as $d ) {
			$sessions_by_day[ $d['id'] ] = $plugin->module( 'sessions' )->repo()->all_for_day( $d['id'] );
		}
		return $this->capture( 'modules/shortcodes/views/agenda', [
			'event'           => $event,
			'days'            => $days,
			'sessions_by_day' => $sessions_by_day,
		] );
	}

	public function render_speakers( $atts ) : string {
		$this->enqueue_styles();
		$event = $this->resolve_event( $atts );
		if ( ! $event ) {
			return $this->not_found_message();
		}
		$speakers = DigitOne_Events_Plugin::instance()->module( 'speakers' )->repo()->all_for_event( $event['id'] );
		return $this->capture( 'modules/shortcodes/views/speakers', [
			'event'    => $event,
			'speakers' => $speakers,
		] );
	}

	public function render_venues( $atts ) : string {
		$this->enqueue_styles();
		$event = $this->resolve_event( $atts );
		if ( ! $event ) {
			return $this->not_found_message();
		}
		$tree = DigitOne_Events_Plugin::instance()->module( 'venues' )->repo()->tree_for_event( $event['id'] );
		return $this->capture( 'modules/shortcodes/views/venues', [
			'event' => $event,
			'tree'  => $tree,
		] );
	}

	/* ============================================================ */
	/* Helpers                                                      */
	/* ============================================================ */

	/**
	 * Resolve event from shortcode attributes. Accepts both `event_slug=` /
	 * `event=` (slug or id). Returns null if not found or not published.
	 */
	private function resolve_event( $atts ) : ?array {
		$atts = shortcode_atts( [
			'event'      => '',
			'event_slug' => '', // legacy compat
			'event_id'   => '', // legacy compat
		], (array) $atts, 'digitone_events' );

		$repo = DigitOne_Events_Plugin::instance()->module( 'events' )->repo();
		$candidate = $atts['event'] !== '' ? $atts['event'] : ( $atts['event_slug'] ?: $atts['event_id'] );

		$event = null;
		if ( $candidate !== '' ) {
			$event = $repo->find_by_slug( $candidate );
			if ( ! $event ) {
				$event = $repo->find( $candidate );
			}
		} else {
			$all = $repo->all( 'published' );
			$event = $all ? $all[0] : null;
		}

		if ( ! $event || $event['status'] !== 'published' ) {
			return null;
		}
		return $event;
	}

	private function not_found_message() : string {
		return '<div class="digitone-events-frontend digitone-events-notfound">'
			. esc_html__( 'Event not found or not published.', 'digitone-events' )
			. '</div>';
	}

	private function capture( string $template, array $vars ) : string {
		ob_start();
		DigitOne_Events_Helpers_View::render( $template, $vars );
		return (string) ob_get_clean();
	}

	/**
	 * Enqueue the frontend stylesheet. Called on demand by each shortcode
	 * render method so the CSS loads regardless of whether the shortcode is
	 * in post_content, a block, a template part, a widget, or a pattern.
	 *
	 * wp_enqueue_style after wp_head fired is fine — WordPress will print the
	 * tag in the footer with a notice (acceptable for shortcode-loaded styles).
	 */
	private function enqueue_styles() : void {
		if ( ! wp_style_is( 'digitone-events-frontend', 'registered' ) ) {
			wp_register_style(
				'digitone-events-frontend',
				DIGITONE_EVENTS_URL . 'assets/css/frontend.css',
				[],
				DIGITONE_EVENTS_VERSION
			);
		}
		wp_enqueue_style( 'digitone-events-frontend' );

		if ( ! wp_script_is( 'digitone-events-frontend', 'registered' ) ) {
			wp_register_script(
				'digitone-events-frontend',
				DIGITONE_EVENTS_URL . 'assets/js/frontend.js',
				[],
				DIGITONE_EVENTS_VERSION,
				true
			);
		}
		wp_enqueue_script( 'digitone-events-frontend' );
	}

}
