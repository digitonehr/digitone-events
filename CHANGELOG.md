# Changelog

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.4] - 2026-05-12

### Changed
- **Agenda shortcode is now a true day switcher.** Only one day is visible at a time. Clicking a day pill at the top switches the active panel — no page reload, no scrolling through stacked days.
- **Smart default day**: when the page loads, the active day is the one whose `day_date` matches today (via `current_time('Y-m-d')`). If today doesn't match any event day (e.g. the page is visited before the event, or after), Day 1 is selected. Initial state is set in PHP, so the right day is visible even before JS executes.
- **Roomier grid**: header row is now an explicit 48px (no more visual overlap into the first slot row), each 30-min slot is 36px (was 18px) → 1 hour = 72px tall. Session blocks have more room for title + speakers without truncation.
- Day picker buttons are real `<button>` elements with `is-active` state — previously anchor links to `#de-day-N` that did nothing useful when JS was off.
- Active day pill is now dark navy (matches the day header banner) instead of just hovered styling.
- Shareable URLs: `?de-day=2` query param or `#de-day-2` hash both work to deep-link to a specific day on page load.

### Added
- `assets/js/frontend.js` — small (no dependencies) day switcher script. Registered + enqueued on demand by the same mechanism as the stylesheet, so it works with page builders (Breakdance, Elementor, etc.) and block themes.

## [0.5.3] - 2026-05-12

### Fixed
- **Frontend CSS now loads when using page builders** (Breakdance, Elementor, Bricks, Beaver Builder, Oxygen) and **block themes** (Twenty Twenty-Five etc.) where the shortcode lives in a builder data structure, a block, a template part, a widget, or a pattern — not in raw `$post->post_content`.
- Removed the `has_shortcode( $post->post_content, ... )` precondition that silently prevented `frontend.css` from enqueuing in those contexts.
- The shortcode handlers (`render_agenda`, `render_speakers`, `render_venues`) now enqueue the stylesheet directly when they run, so the CSS loads exactly when and where it is needed, regardless of which mechanism rendered the shortcode.
- WordPress prints late-enqueued styles in `wp_footer` automatically, so this works even if the shortcode renders after `wp_head` has already fired.

## [0.5.2] - 2026-05-12

### Changed
- **Agenda shortcode redesigned as a true time-grid schedule** (ICFP-style). Sessions now render as colored blocks positioned by their `start_time` and sized by duration, with halls as columns and a time axis on the left.
  - 30-minute grid resolution (1 hour = 36px tall by default)
  - Halls auto-detected from the event's sessions and laid out as consistent columns across all days
  - Break-type sessions span all hall columns as a gray ribbon
  - Each block colored from its session type (with translucent fill + colored left bar)
  - Block shows: type badge · title · time · top 4 speakers (with +N indicator if more)
  - Hover lifts the block
- Day section now has a dark navy header with "DAY N" eyebrow + large date.
- New day-nav at the top: anchor pills (Day 1 · Mon, Day 2 · Tue, …) for quick jumping.
- **Mobile fallback under 800px**: grid is hidden and a clean vertical list takes over, with the type color preserved as a left border.

### Notes
- No data model changes. Pure template + CSS rewrite. Old `<ul class="de-fe-sessions">` list view is gone; if you relied on the old DOM in custom CSS, you'll need to retarget to `.de-fe-block` / `.de-fe-schedule-grid`.

## [0.5.1] - 2026-05-12

### Fixed
- **Frontend agenda visual polish**. The frontend stylesheet now defensively resets list bullets, list numbering, and theme heading overrides that block themes (Twenty Twenty-Five, Twenty Twenty-Three, Astra etc.) injected into our shortcode markup.
- Replaced `<ol>` with `<ul>` in the agenda template, and converted `<h3>`/`<h4>` session/day titles to scoped `<div>` elements so themes can no longer balloon their font-size.
- Sessions now render as proper cards with a 96px time column on the left and content on the right, including a subtle hover state.
- Day headers now have a gradient blue bar so they stand out at a glance.
- Speakers per session are rendered as small inline pills with an optional avatar and role badge.
- Description blocks have a subtle gray inset.
- Improved spacing, typography rhythm, and responsive behavior under 600px.

### Notes
- No data model changes. Pure CSS + minor template change; no schema migration needed.

## [0.5.0] - 2026-05-12

### Added — Phase 5 (feature complete)
- **Export/Import module**: dedicated admin page under DigitOne Events → Export / Import.
  - **JSON export**: complete round-trip snapshot of the active event including all days, venues (with hierarchy), titles, roles, speakers (with role assignments), session types, and sessions (with speaker assignments). Pretty-printed, UTF-8, single file.
  - **CSV export**: zip containing one CSV per entity (event, days, venues, titles, roles, session_types, speakers, sessions, session_speakers). UTF-8 with BOM so Excel opens it correctly.
  - **JSON import**: upload a previously exported JSON file → creates a brand new event with fresh UUIDs. All internal references (day_id, venue_id, parent_id, etc.) are automatically remapped via an old→new UUID table. Wrapped in a transaction; rolls back cleanly on any failure. Optional name override.
  - File downloads use the WordPress `admin-post.php` flow with nonces (no raw exposure of admin-ajax for binary streaming).
  - 10 MB upload limit; JSON-only validation.
- **Frontend shortcodes**:
  - `[digitone_events_agenda event="slug"]` — full agenda grouped by day, with session type badges, venue, speakers, role badges, child-session indentation.
  - `[digitone_events_speakers event="slug"]` — responsive grid of speaker cards with photo, name, role badges, bio.
  - `[digitone_events_venues event="slug"]` — venue list with sub-venues nested.
  - Legacy attributes `event_slug=` and `event_id=` accepted as fallbacks.
  - Frontend CSS (`assets/css/frontend.css`) is enqueued **only** on pages that actually contain one of these shortcodes (via `has_shortcode`).
- Dashboard shows "Phase 5 — feature complete" with shortcode hint.

### Notes
- Only published events are rendered on the frontend. Drafts/archived are silently hidden.
- The plugin now matches and exceeds the original DigiCal feature set with a far cleaner architecture, working GitHub auto-update, full security audit (consistent nonce + cap on every endpoint), and ~30% less code.

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
