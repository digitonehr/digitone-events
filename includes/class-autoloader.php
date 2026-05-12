<?php
/**
 * Autoloader for prefixed class names.
 *
 * Resolution order for DigitOne_Events_Foo_Bar_Baz (parts: [foo, bar, baz]):
 *   1. Multi-part module dir: try modules/foo-bar-baz, then modules/foo-bar, then modules/foo
 *      → if found, file = class-{joined-parts}.php inside that dir
 *   2. Known includes subdir (security/database/helpers): includes/{first}/class-{rest}.php
 *   3. Default: includes/class-{joined-parts}.php
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Autoloader {

	private const INCLUDES_SUBDIRS = [
		'security' => 'security',
		'database' => 'database',
		'helpers'  => 'helpers',
	];

	public static function register() : void {
		spl_autoload_register( [ __CLASS__, 'load' ] );
	}

	public static function load( string $class ) : void {
		if ( strpos( $class, 'DigitOne_Events_' ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( 'DigitOne_Events_' ) );
		$parts    = array_map( 'strtolower', explode( '_', $relative ) );
		if ( empty( $parts ) ) {
			return;
		}

		$path = self::resolve_path( $parts );
		if ( $path && is_readable( $path ) ) {
			require_once $path;
		}
	}

	/**
	 * @param string[] $parts Lowercased class-name parts after the plugin prefix.
	 */
	private static function resolve_path( array $parts ) : ?string {
		// 1. Try module directories — longest match first.
		// For class DigitOne_Events_Session_Types_Module: parts = [session, types, module]
		// Tries: modules/session-types-module, modules/session-types, modules/session
		for ( $i = count( $parts ); $i > 0; $i-- ) {
			$candidate  = implode( '-', array_slice( $parts, 0, $i ) );
			$module_dir = DIGITONE_EVENTS_DIR . 'modules/' . $candidate . '/';
			if ( is_dir( $module_dir ) ) {
				$file = 'class-' . implode( '-', $parts ) . '.php';
				return $module_dir . $file;
			}
		}

		// 2. Known sub-directory under includes/
		$first = $parts[0];
		if ( isset( self::INCLUDES_SUBDIRS[ $first ] ) ) {
			$sub_parts = array_slice( $parts, 1 );
			if ( empty( $sub_parts ) ) {
				return null;
			}
			$file = 'class-' . implode( '-', $sub_parts ) . '.php';
			return DIGITONE_EVENTS_DIR . 'includes/' . self::INCLUDES_SUBDIRS[ $first ] . '/' . $file;
		}

		// 3. Default: includes/class-<parts>.php
		$file = 'class-' . implode( '-', $parts ) . '.php';
		return DIGITONE_EVENTS_DIR . 'includes/' . $file;
	}
}
