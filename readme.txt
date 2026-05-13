=== DigitOne Events ===
Contributors: digit
Tags: events, conferences, calendar, speakers, sessions
Requires at least: 6.4
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 0.8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Conference and event management for WordPress: multi-event backend, sessions, speakers, venues, days, and frontend shortcodes. Modular, secure, GitHub auto-update.

== Description ==

DigitOne Events is a modular WordPress plugin for managing conferences and multi-day events directly from your dashboard.

Phase 1 (this release) ships:

* Modular plugin architecture with autoloader.
* Events module: full CRUD with create/edit modal, status filter, bulk delete.
* All 10 database tables installed via schema versioning.
* Centralized security layer: nonce + capability checks on every endpoint.
* User-meta active event tracking.
* GitHub auto-update flow tied to your repository's Releases.

Upcoming phases will add Days, Venues, Speakers, Sessions, Session Types, Export/Import, and public frontend shortcodes.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/digitone-events`.
2. Activate it from the Plugins screen.
3. Go to **DigitOne Events → Settings** and verify the GitHub owner/repo. Add a personal access token if your repo is private.

== Changelog ==

= 0.1.0 =
* Initial Phase 1 release: foundation, Events module, GitHub updater.
