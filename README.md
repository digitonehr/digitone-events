# DigitOne Events

Conference and event management plugin for WordPress. Modular architecture, security-first, and auto-update from this repository's Releases.

> **Status: Phase 1 (v0.1.0).** Events module is fully shipped. Days, Venues, Speakers, Sessions, Session Types, Export/Import and frontend shortcodes are coming in subsequent phases.

## Requirements

- WordPress 6.4+
- PHP 8.0+

## Install

### Fresh install (manual)

1. Download the latest `digitone-events.zip` from the [Releases](../../releases) page.
2. WordPress → Plugins → Add New → Upload Plugin → upload the zip → Activate.

### Auto-update (built-in)

Once installed, the plugin checks this repository for new releases. When a newer version is published, it appears in **Plugins → Installed Plugins** with a standard "Update now" button. No manual download needed.

## Releasing a new version

The workflow at `.github/workflows/release.yml` handles everything when you push a tag:

```bash
# Bump the version
# 1) edit digitone-events.php — Version: X.Y.Z (and DIGITONE_EVENTS_VERSION)
# 2) update CHANGELOG.md
git add .
git commit -m "Release vX.Y.Z"
git tag vX.Y.Z
git push origin main --tags
```

The Action:
1. Builds a clean zip excluding `.git`, `.github`, `node_modules`, etc.
2. Names it `digitone-events.zip`.
3. Creates a GitHub Release for the tag and attaches the zip.

WordPress sites running this plugin will see the new version on their next update check (within ~6 hours, or instantly via **DigitOne Events → Settings → Force check GitHub**).

## Configuration

**GitHub repo location** — auto-detected from the plugin code. Override via constants in `wp-config.php`:

```php
define( 'DIGITONE_EVENTS_GH_OWNER', 'YourOrg' );
define( 'DIGITONE_EVENTS_GH_REPO',  'digitone-events' );
```

Or set the same values in **DigitOne Events → Settings**.

**Private repository?** Provide a GitHub personal access token with `repo` scope:

```php
define( 'DIGITONE_EVENTS_GH_TOKEN', 'ghp_xxx...' );
```

The constant is preferred over the database option — it's not exposed via WP admin and not stored in backups of `wp_options`.

**Custom capability** — by default any user with `manage_options` can use the plugin. To wire it to a custom role:

```php
add_filter( 'digitone_events_manage_cap', fn() => 'manage_digitone_events' );
```

## Architecture

```
digitone-events/
├── digitone-events.php           ← plugin bootstrap
├── uninstall.php                 ← drops tables + options
├── includes/
│   ├── class-autoloader.php      ← maps DigitOne_Events_Foo_Bar → files
│   ├── class-plugin.php          ← singleton, wires services + modules
│   ├── class-activator.php
│   ├── class-deactivator.php
│   ├── class-admin-menu.php
│   ├── class-assets.php          ← per-screen CSS/JS enqueue
│   ├── class-settings.php
│   ├── class-github-updater.php  ← the auto-update flow
│   ├── security/
│   │   ├── class-nonce.php
│   │   └── class-capabilities.php
│   ├── database/
│   │   └── class-schema.php      ← all 10 tables, schema versioning
│   └── helpers/
│       ├── class-event-context.php
│       ├── class-format.php
│       └── class-view.php
├── modules/
│   └── events/
│       ├── class-events-module.php
│       ├── class-events-repository.php
│       ├── class-events-ajax.php
│       └── views/list.php
├── admin/views/
│   ├── dashboard.php
│   └── settings.php
└── assets/
    ├── css/
    │   ├── admin-common.css
    │   └── modules/{events,dashboard}.css
    └── js/
        ├── admin-common.js
        └── modules/events.js
```

Each module follows the same pattern (module / repository / ajax / views). To add a new module, copy `modules/events/` as a template.

## Security model

- Every AJAX handler starts with `DigitOne_Events_Security_Nonce::verify_ajax()` — a single call that checks the user capability **and** verifies the nonce, dying with a 403 JSON response on failure.
- All SQL uses `$wpdb->prepare`.
- All output uses `esc_html` / `esc_attr` / `wp_kses_post` as appropriate.
- All inputs are sanitized at the boundary (`sanitize_text_field`, `sanitize_title`, `wp_kses_post`).
- Foreign-key cascades are enforced in PHP (transactions where the table engine supports them).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
