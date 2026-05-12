<?php
/**
 * Main plugin singleton. Registers core services and modules.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Plugin {

	private static ?self $instance = null;

	/** Registered module instances (slug => object). */
	private array $modules = [];

	private bool $booted = false;

	public static function instance() : self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Wire all services. Idempotent.
	 */
	public function boot() : void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// Schema upgrades (run early; cheap if up-to-date).
		add_action( 'init', [ 'DigitOne_Events_Database_Schema', 'maybe_upgrade' ], 5 );

		// Settings (Settings API registration).
		( new DigitOne_Events_Settings() )->register();

		// Admin menu + assets.
		if ( is_admin() ) {
			( new DigitOne_Events_Admin_Menu() )->register();
			( new DigitOne_Events_Assets() )->register();
		}

		// GitHub updater (always — WP runs update checks on cron in front-end too).
		( new DigitOne_Events_Github_Updater() )->register();

		// Modules.
		$this->register_modules();
	}

	private function register_modules() : void {
		$this->modules['events'] = new DigitOne_Events_Events_Module();
		$this->modules['events']->register();

		$this->modules['days'] = new DigitOne_Events_Days_Module();
		$this->modules['days']->register();

		$this->modules['venues'] = new DigitOne_Events_Venues_Module();
		$this->modules['venues']->register();

		// Titles + Roles must register BEFORE speakers (speakers reads from their repos).
		$this->modules['titles'] = new DigitOne_Events_Titles_Module();
		$this->modules['titles']->register();

		$this->modules['roles'] = new DigitOne_Events_Roles_Module();
		$this->modules['roles']->register();

		$this->modules['speakers'] = new DigitOne_Events_Speakers_Module();
		$this->modules['speakers']->register();

		// Phase 4+ modules will be added here:
		// $this->modules['sessions']      = new DigitOne_Events_Sessions_Module();
		// $this->modules['session_types'] = new DigitOne_Events_Session_Types_Module();

		do_action( 'digitone_events_modules_registered', $this->modules );
	}

	/**
	 * Get a registered module by slug, or null.
	 */
	public function module( string $slug ) {
		return $this->modules[ $slug ] ?? null;
	}

	/**
	 * @return array<string,object>
	 */
	public function modules() : array {
		return $this->modules;
	}
}
