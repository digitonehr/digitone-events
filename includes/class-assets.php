<?php
/**
 * Asset registration.
 *
 * - Loads admin-common.css and admin-common.js on every plugin admin page.
 * - For each registered module, auto-loads
 *     assets/css/modules/{slug}.css and assets/js/modules/{slug}.js
 *   if those files exist.
 * - Never loaded on non-plugin admin pages.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Assets {

	public function register() : void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_frontend' ] );
	}

	/**
	 * Front-end: register the stylesheet so shortcode handlers can enqueue it
	 * on demand. We do NOT use has_shortcode() against $post->post_content
	 * because block themes resolve shortcodes from template parts, blocks,
	 * widgets, and patterns that aren't visible there. The shortcode handlers
	 * themselves call wp_enqueue_style( 'digitone-events-frontend' ) so the
	 * CSS loads exactly when (and only when) a shortcode actually renders.
	 */
	public function enqueue_frontend() : void {
		if ( is_admin() ) {
			return;
		}
		wp_register_style(
			'digitone-events-frontend',
			DIGITONE_EVENTS_URL . 'assets/css/frontend.css',
			[],
			DIGITONE_EVENTS_VERSION
		);
	}

	public function enqueue( string $hook ) : void {
		// Heuristic: load on any admin page whose hook contains our slug.
		if ( strpos( $hook, 'digitone-events' ) === false ) {
			return;
		}

		$ver = DIGITONE_EVENTS_VERSION;
		$url = DIGITONE_EVENTS_URL;

		wp_enqueue_style(
			'digitone-events-admin-common',
			$url . 'assets/css/admin-common.css',
			[],
			$ver
		);

		wp_enqueue_script(
			'digitone-events-admin-common',
			$url . 'assets/js/admin-common.js',
			[],
			$ver,
			true
		);

		wp_localize_script(
			'digitone-events-admin-common',
			'DigitoneEvents',
			[
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => DigitOne_Events_Security_Nonce::create(),
				'pluginUrl'     => $url,
				'activeEvent'   => DigitOne_Events_Helpers_Event_Context::active_event_id(),
				'currentScreen' => $hook,
				'i18n'          => [
					'confirmDelete' => __( 'Are you sure? This cannot be undone.', 'digitone-events' ),
					'saving'        => __( 'Saving…', 'digitone-events' ),
					'saved'         => __( 'Saved.', 'digitone-events' ),
					'error'         => __( 'Something went wrong.', 'digitone-events' ),
					'checking'      => __( 'Checking GitHub…', 'digitone-events' ),
				],
			]
		);

		// Auto-load module-specific assets based on current page.
		$module_slug = $this->module_from_hook( $hook );
		if ( $module_slug ) {
			$this->maybe_enqueue_module_asset( 'css', $module_slug, [ 'digitone-events-admin-common' ], $ver, $url );
			$this->maybe_enqueue_module_asset( 'js', $module_slug, [ 'digitone-events-admin-common' ], $ver, $url );
		}

		// Dashboard styles (special case).
		if ( $hook === 'toplevel_page_digitone-events' ) {
			$this->maybe_enqueue_module_asset( 'css', 'dashboard', [ 'digitone-events-admin-common' ], $ver, $url );
		}
	}

	private function module_from_hook( string $hook ) : string {
		// Examples:
		//   "digitone-events_page_digitone-events-events" -> "events"
		//   "toplevel_page_digitone-events" -> ""
		if ( preg_match( '/digitone-events-([a-z\-]+)$/', $hook, $m ) ) {
			return $m[1];
		}
		return '';
	}

	private function maybe_enqueue_module_asset( string $kind, string $slug, array $deps, string $ver, string $url ) : void {
		$rel = "assets/{$kind}/modules/{$slug}." . $kind;
		$abs = DIGITONE_EVENTS_DIR . $rel;
		if ( ! is_readable( $abs ) ) {
			return;
		}
		$handle = "digitone-events-{$kind}-{$slug}";
		if ( $kind === 'css' ) {
			wp_enqueue_style( $handle, $url . $rel, $deps, $ver );
		} else {
			wp_enqueue_script( $handle, $url . $rel, $deps, $ver, true );
		}
	}
}
