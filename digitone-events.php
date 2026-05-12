<?php
/**
 * Plugin Name:       DigitOne Events
 * Plugin URI:        https://github.com/digitonehr/digitone-events
 * Description:       Conference & event management with multi-event backend, sessions, speakers, venues, days, and frontend shortcodes. Modular, secure, GitHub auto-update.
 * Version:           0.1.1
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            DIGIT
 * Author URI:        https://github.com/digitonehr
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       digitone-events
 * Domain Path:       /languages
 * Update URI:        https://github.com/digitonehr/digitone-events
 *
 * @package DigitOne_Events
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------------------
 * Plugin constants
 * ------------------------------------------------------------------------ */

define( 'DIGITONE_EVENTS_VERSION', '0.1.1' );
define( 'DIGITONE_EVENTS_FILE', __FILE__ );
define( 'DIGITONE_EVENTS_BASENAME', plugin_basename( __FILE__ ) );
define( 'DIGITONE_EVENTS_SLUG', 'digitone-events' );
define( 'DIGITONE_EVENTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'DIGITONE_EVENTS_URL', plugin_dir_url( __FILE__ ) );
define( 'DIGITONE_EVENTS_TABLE_PREFIX', 'digitone_' );

/* GitHub repository defaults — override via constants in wp-config.php
 * or via the Settings page (Settings stored in wp_options). */
if ( ! defined( 'DIGITONE_EVENTS_GH_OWNER' ) ) {
	define( 'DIGITONE_EVENTS_GH_OWNER', 'digitonehr' );
}
if ( ! defined( 'DIGITONE_EVENTS_GH_REPO' ) ) {
	define( 'DIGITONE_EVENTS_GH_REPO', 'digitone-events' );
}
/* Optional: define( 'DIGITONE_EVENTS_GH_TOKEN', 'ghp_xxx' ); for private repos */

/* ---------------------------------------------------------------------------
 * Minimum environment check
 * ------------------------------------------------------------------------ */

if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'DigitOne Events requires PHP 8.0 or higher. Plugin not loaded.', 'digitone-events' );
		echo '</p></div>';
	} );
	return;
}

/* ---------------------------------------------------------------------------
 * Autoloader
 * ------------------------------------------------------------------------ */

require_once DIGITONE_EVENTS_DIR . 'includes/class-autoloader.php';
DigitOne_Events_Autoloader::register();

/* ---------------------------------------------------------------------------
 * Lifecycle hooks
 * ------------------------------------------------------------------------ */

register_activation_hook( __FILE__, [ 'DigitOne_Events_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'DigitOne_Events_Deactivator', 'deactivate' ] );

/* ---------------------------------------------------------------------------
 * Boot
 * ------------------------------------------------------------------------ */

add_action( 'plugins_loaded', static function () {
	load_plugin_textdomain( 'digitone-events', false, dirname( DIGITONE_EVENTS_BASENAME ) . '/languages' );
	DigitOne_Events_Plugin::instance()->boot();
}, 10 );
