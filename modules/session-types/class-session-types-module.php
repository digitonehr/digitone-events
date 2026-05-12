<?php
/**
 * Session Types module. Managed inline on Sessions page (no own render_page).
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Session_Types_Module {

	private DigitOne_Events_Session_Types_Repository $repo;
	private DigitOne_Events_Session_Types_Ajax $ajax;

	public function __construct() {
		$this->repo = new DigitOne_Events_Session_Types_Repository();
		$this->ajax = new DigitOne_Events_Session_Types_Ajax( $this->repo );
	}

	public function register() : void {
		$this->ajax->register();
	}

	public function repo() : DigitOne_Events_Session_Types_Repository {
		return $this->repo;
	}
}
