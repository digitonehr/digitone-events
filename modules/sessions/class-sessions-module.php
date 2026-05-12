<?php
/**
 * Sessions module: main page with tabs for Sessions / Session Types.
 *
 * Sessions tab shows a day filter (dropdown) and a list of sessions for
 * the selected day, sorted by start_time. Each session can reference a
 * day, session type, venue/sub-venue, parent session, and a set of
 * (speaker, role) pairs.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Sessions_Module {

	private DigitOne_Events_Sessions_Repository $repo;
	private DigitOne_Events_Sessions_Ajax $ajax;

	public function __construct() {
		$this->repo = new DigitOne_Events_Sessions_Repository();
		$this->ajax = new DigitOne_Events_Sessions_Ajax( $this->repo );
	}

	public function register() : void {
		$this->ajax->register();
	}

	public function repo() : DigitOne_Events_Sessions_Repository {
		return $this->repo;
	}

	public function render_page() : void {
		$plugin              = DigitOne_Events_Plugin::instance();
		$days_repo           = $plugin->module( 'days' )->repo();
		$venues_repo         = $plugin->module( 'venues' )->repo();
		$speakers_repo       = $plugin->module( 'speakers' )->repo();
		$roles_repo          = $plugin->module( 'roles' )->repo();
		$types_repo          = $plugin->module( 'session_types' )->repo();
		$active_event_id     = DigitOne_Events_Helpers_Event_Context::active_event_id();

		$days          = $active_event_id ? $days_repo->all_for_event( $active_event_id )   : [];
		$venues_tree   = $active_event_id ? $venues_repo->tree_for_event( $active_event_id ) : [];
		$speakers      = $active_event_id ? $speakers_repo->all_for_event( $active_event_id ) : [];
		$roles         = $active_event_id ? $roles_repo->all_for_event( $active_event_id )   : [];
		$session_types = $active_event_id ? $types_repo->all_for_event( $active_event_id )   : [];

		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'sessions';
		if ( ! in_array( $tab, [ 'sessions', 'types' ], true ) ) {
			$tab = 'sessions';
		}

		// Selected day for the Sessions tab.
		$day_id = isset( $_GET['day_id'] ) ? sanitize_text_field( wp_unslash( $_GET['day_id'] ) ) : '';
		if ( $day_id === '' && ! empty( $days ) ) {
			$day_id = $days[0]['id'];
		}

		$sessions = ( $tab === 'sessions' && $day_id )
			? $this->repo->all_for_day( $day_id )
			: [];

		DigitOne_Events_Helpers_View::render(
			'modules/sessions/views/list',
			[
				'tab'             => $tab,
				'active_event_id' => $active_event_id,
				'days'            => $days,
				'day_id'          => $day_id,
				'sessions'        => $sessions,
				'venues_tree'     => $venues_tree,
				'speakers'        => $speakers,
				'roles'           => $roles,
				'session_types'   => $session_types,
			]
		);
	}
}
