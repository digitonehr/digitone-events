<?php
/**
 * Days module: entry point.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Days_Module {

	private DigitOne_Events_Days_Repository $repo;
	private DigitOne_Events_Days_Ajax $ajax;

	public function __construct() {
		$this->repo = new DigitOne_Events_Days_Repository();
		$this->ajax = new DigitOne_Events_Days_Ajax( $this->repo );
	}

	public function register() : void {
		$this->ajax->register();
	}

	public function repo() : DigitOne_Events_Days_Repository {
		return $this->repo;
	}

	public function render_page() : void {
		$active_event_id = DigitOne_Events_Helpers_Event_Context::active_event_id();
		$days = $active_event_id ? $this->repo->all_for_event( $active_event_id ) : [];

		DigitOne_Events_Helpers_View::render(
			'modules/days/views/list',
			[
				'days'            => $days,
				'active_event_id' => $active_event_id,
			]
		);
	}
}
