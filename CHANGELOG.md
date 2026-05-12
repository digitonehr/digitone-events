# Changelog

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
