# Changelog

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.0] - 2026-05-12

### Added — Phase 4
- **Sessions module**: full CRUD with rich edit modal:
  - Day picker at top of list (URL-controlled, switches whole view)
  - Day, start time, end time, title (required)
  - Session type dropdown with icon + color badge in list
  - Cascading Venue → Sub-venue dropdowns (sub-venue auto-filtered to selected parent venue's children)
  - Multi-checkbox speakers with a single role applied to all selected
  - Session level (master vs child) with parent-session dropdown loaded via AJAX when day changes
  - Description with `wp_kses_post`
- **Session Types module**: managed inline on the Sessions admin page (Session Types tab). Each type has a name, icon (emoji or short text) and color. Inline edit + quick-add form.
- Sessions list shows time, title (with sub-marker for child sessions), type badge, venue/sub-venue, and speakers with their role badges.
- Dashboard now shows a Sessions count card.

### Changed
- Autoloader now handles multi-word module directory names (e.g. `modules/session-types/`) by trying longest-prefix matches.
- "Session Types" removed from the top-level submenu — now lives as a tab on the Sessions page.

### Cascade behavior
- Deleting a session type nulls `sessions.session_type_id`.
- Deleting a session removes its `session_roles` junction rows and promotes any child sessions referencing it to master.

## [0.3.0] - 2026-05-12

### Added — Phase 3
- **Speakers module**: full CRUD with photo upload via the WordPress Media Library (`wp.media`), title selection, multi-role assignment via the `speakers_roles` junction table, email validation, and bio with `wp_kses_post` sanitization. Roles are validated against the active event to prevent cross-event assignment.
- **Titles module**: lightweight taxonomy (e.g. Dr., Prof., Mr.). Managed inline from the Speakers admin page (Titles tab) with quick-add + inline edit.
- **Roles module**: taxonomy with optional color per role (e.g. Keynote Speaker → blue, Moderator → green). Managed inline from the Speakers admin page (Roles tab). Colors are rendered as badges in the speakers list.
- Speakers admin page now uses WordPress tab navigation (`nav-tab-wrapper`) with three tabs: Speakers / Titles / Roles.
- Dashboard now shows a Speakers count card.

### Changed
- Cascade delete: removing a title nulls `speakers.title_id`; removing a role drops all junction rows and nulls `session_roles.role_id`; removing a speaker drops all their junction rows.

## [0.2.0] - 2026-05-12

### Added — Phase 2
- **Days module**: full CRUD with create/edit modal, drag-to-reorder, bulk delete. Uses proper `DATE`/`TIME` columns. Cascade deletes sessions when a day is removed.
- **Venues module**: full CRUD with parent/sub-venue hierarchy, modal with conditional parent selector, "Add sub-venue" shortcut from a primary venue row. Deleting a primary cascades to sub-venues; sessions referencing deleted venues have their references cleared.
- Reusable **Active Event bar** partial that appears on every module page, with a switcher dropdown when more than one event exists.
- Dashboard upgraded with stat cards for Events, Days, Venues plus a wide card showing active event with day/venue counts.

### Changed
- `class-admin-menu.php` now routes each submenu to its registered module's `render_page()` method, falling back to a "coming soon" notice for not-yet-shipped modules.
- `assets/js/admin-common.js` gained an active-event switcher handler.

## [0.1.1] - 2026-05-12

### Fixed
- Autoloader could not resolve `DigitOne_Events_Schema` (file lives under `includes/database/`).
  Renamed class to `DigitOne_Events_Database_Schema` and updated all references.
  This caused a fatal `class not found` on every admin/frontend request once the plugin was active.

## [0.1.0] - 2026-05-12

### Added — Initial release (Phase 1)
- Plugin skeleton with custom autoloader for prefixed class names.
- Database schema covering all 10 tables (events, days, venues, speakers,
  speakers_roles, titles, roles, sessions, session_roles, session_types)
  using proper `DATE` / `TIME` column types.
- Centralized security layer:
  - `DigitOne_Events_Security_Nonce` — single nonce action for all admin AJAX.
  - `DigitOne_Events_Security_Capabilities` — filterable capability via `digitone_events_manage_cap`.
- Active-event tracking via user meta (replaces DigiCal's `$_SESSION` approach).
- **Events module** fully implemented as the reference template:
  - Repository with prepared statements, slug uniqueness, PHP-level cascade delete.
  - 7 AJAX endpoints (list/get/save/delete/bulk delete/set active/set status), all nonce + capability gated.
  - List view with create/edit modal, search filter, status filter, bulk delete.
- Admin menu with Dashboard, Events, Settings + placeholders for upcoming modules.
- Asset registration: common + per-module CSS/JS auto-loaded by file convention.
- **GitHub auto-updater**:
  - Hooks `pre_set_site_transient_update_plugins`, `plugins_api`, `upgrader_source_selection`, `upgrader_process_complete`.
  - 6-hour transient cache.
  - Prefers release asset named `digitone-events*.zip`, falls back to zipball.
  - Supports private repos via Bearer token (constant preferred over option).
  - Force-check button on Settings page.
- GitHub Actions release workflow that builds a clean zip and attaches it to a Release on every `v*` tag push.

### Not yet shipped (Phase 2+)
- Days, Venues, Speakers, Sessions, Session Types modules.
- Export/Import (JSON, CSV, YAML, XML).
- Frontend shortcodes (`[digitone_events_agenda]`, `[digitone_events_speakers]`, `[digitone_events_venues]`).
