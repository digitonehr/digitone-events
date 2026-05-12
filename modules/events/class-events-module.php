<?php
/**
 * Events module: entry point.
 *
 * Each module follows this pattern:
 *   - class-{slug}-module.php       (this file)  → glue, registration, page rendering
 *   - class-{slug}-repository.php   data-access layer (all SQL lives here)
 *   - class-{slug}-ajax.php         AJAX endpoints (security at top of every handler)
 *   - views/*.php                   templates
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Events_Module {

	private DigitOne_Events_Events_Repository $repo;
	private DigitOne_Events_Events_Ajax $ajax;

	public function __construct() {
		$this->repo = new DigitOne_Events_Events_Repository();
		$this->ajax = new DigitOne_Events_Events_Ajax( $this->repo );
	}

	public function register() : void {
		$this->ajax->register();
	}

	public function repo() : DigitOne_Events_Events_Repository {
		return $this->repo;
	}

	public function render_page() : void {
		$events = $this->repo->all();
		DigitOne_Events_Helpers_View::render(
			'modules/events/views/list',
			[ 'events' => $events ]
		);
	}
}
