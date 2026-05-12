<?php
/**
 * Titles module: simple taxonomy (Mr., Dr., Prof., etc).
 * No render_page — managed inline on Speakers admin page.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Titles_Module {

	private DigitOne_Events_Titles_Repository $repo;
	private DigitOne_Events_Titles_Ajax $ajax;

	public function __construct() {
		$this->repo = new DigitOne_Events_Titles_Repository();
		$this->ajax = new DigitOne_Events_Titles_Ajax( $this->repo );
	}

	public function register() : void {
		$this->ajax->register();
	}

	public function repo() : DigitOne_Events_Titles_Repository {
		return $this->repo;
	}
}
