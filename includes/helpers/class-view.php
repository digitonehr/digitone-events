<?php
/**
 * Tiny template renderer. Loads a PHP view file with sandboxed variables.
 *
 * Usage:
 *   DigitOne_Events_Helpers_View::render( 'modules/events/views/list', [ 'events' => $rows ] );
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Helpers_View {

	/**
	 * Render a template file relative to the plugin root.
	 *
	 * @param string               $relative_path  Path relative to plugin dir, no .php
	 * @param array<string,mixed>  $vars           Variables exposed to the template.
	 */
	public static function render( string $relative_path, array $vars = [] ) : void {
		$file = DIGITONE_EVENTS_DIR . ltrim( $relative_path, '/' ) . '.php';

		if ( ! is_readable( $file ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				echo '<!-- DigitOne Events: view not found: ' . esc_html( $relative_path ) . ' -->';
			}
			return;
		}

		// Sandbox: extract into closure scope, no leak to global.
		( static function ( string $__file, array $__vars ) {
			extract( $__vars, EXTR_SKIP );
			include $__file;
		} )( $file, $vars );
	}
}
