<?php
/**
 * Autoloader for prefixed class names.
 *
 * Maps DigitOne_Events_Foo_Bar -> includes/class-foo-bar.php
 * Maps DigitOne_Events_Security_Nonce -> includes/security/class-nonce.php
 * Maps DigitOne_Events_Module_Events_Repository -> modules/events/class-events-repository.php (special)
 *
 * The autoloader knows about a small set of known sub-namespaces and falls back
 * to a flat lookup in includes/ for everything else.
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

final class DigitOne_Events_Autoloader {

	/** Sub-directories inside includes/ keyed by class-name fragment. */
	private const INCLUDES_SUBDIRS = [
		'security' => 'security',
		'database' => 'database',
		'helpers'  => 'helpers',
	];

	public static function register() : void {
		spl_autoload_register( [ __CLASS__, 'load' ] );
	}

	public static function load( string $class ) : void {
		// Only handle our own prefixed classes.
		if ( strpos( $class, 'DigitOne_Events_' ) !== 0 ) {
			return;
		}

		// Strip prefix and lowercase.
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
	 * Resolve a class name to a file path.
	 *
	 * @param string[] $parts Lowercased class-name parts after the plugin prefix.
	 */
	private static function resolve_path( array $parts ) : ?string {
		$first = $parts[0];

		// Module-specific: DigitOne_Events_Events_Repository -> modules/events/class-events-repository.php
		// (Module classes still start with the module slug.)
		$module_dir = DIGITONE_EVENTS_DIR . 'modules/' . $first . '/';
		if ( is_dir( $module_dir ) ) {
			$file = 'class-' . implode( '-', $parts ) . '.php';
			return $module_dir . $file;
		}

		// Known sub-directory under includes/.
		if ( isset( self::INCLUDES_SUBDIRS[ $first ] ) ) {
			$sub_parts = array_slice( $parts, 1 );
			if ( empty( $sub_parts ) ) {
				return null;
			}
			$file = 'class-' . implode( '-', $sub_parts ) . '.php';
			return DIGITONE_EVENTS_DIR . 'includes/' . self::INCLUDES_SUBDIRS[ $first ] . '/' . $file;
		}

		// Default: includes/class-<parts>.php
		$file = 'class-' . implode( '-', $parts ) . '.php';
		return DIGITONE_EVENTS_DIR . 'includes/' . $file;
	}
}
