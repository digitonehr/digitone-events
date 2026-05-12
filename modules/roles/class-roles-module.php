<?php
/**
 * Roles module: taxonomy (Keynote Speaker, Moderator, Panelist...).
 * Each role has a name + optional color (for badge styling).
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Roles_Module {

	private DigitOne_Events_Roles_Repository $repo;
	private DigitOne_Events_Roles_Ajax $ajax;

	public function __construct() {
		$this->repo = new DigitOne_Events_Roles_Repository();
		$this->ajax = new DigitOne_Events_Roles_Ajax( $this->repo );
	}

	public function register() : void {
		$this->ajax->register();
	}

	public function repo() : DigitOne_Events_Roles_Repository {
		return $this->repo;
	}
}
