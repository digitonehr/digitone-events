<?php
/**
 * Venues module: entry point.
 *
 * Supports primary venues + sub-venues (parent_id).
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Venues_Module {

	private DigitOne_Events_Venues_Repository $repo;
	private DigitOne_Events_Venues_Ajax $ajax;

	public function __construct() {
		$this->repo = new DigitOne_Events_Venues_Repository();
		$this->ajax = new DigitOne_Events_Venues_Ajax( $this->repo );
	}

	public function register() : void {
		$this->ajax->register();
	}

	public function repo() : DigitOne_Events_Venues_Repository {
		return $this->repo;
	}

	public function render_page() : void {
		$active_event_id = DigitOne_Events_Helpers_Event_Context::active_event_id();
		$tree            = $active_event_id ? $this->repo->tree_for_event( $active_event_id ) : [];
		$primaries       = $active_event_id ? $this->repo->primaries_for_event( $active_event_id ) : [];

		DigitOne_Events_Helpers_View::render(
			'modules/venues/views/list',
			[
				'tree'            => $tree,
				'primaries'       => $primaries,
				'active_event_id' => $active_event_id,
			]
		);
	}
}
