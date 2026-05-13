# Changelog

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.8.5] - 2026-05-13

### Fixed — PDF now actually contains the schedule, not just day headers

The 0.8.4 fix moved `.de-fe-pdf-context` to the wrapper element, but the PDF still came out blank under the day headers. Root cause was a layer deeper than DOM placement:

- **html2canvas doesn't run `@media print`.** It captures the DOM as a regular on-screen view via its internal iframe; the print stylesheet's `display: table` toggle on `.de-fe-print-table` never fires, so the table stays at its baseline `display: none`. Even when `.de-fe-pdf-context` was correctly placed and the mirror rules were scoped, the iframe environment apparently didn't see them apply via cascade through the runtime style tag.

The new approach removes both the mirror-style mechanism and the off-screen wrapper entirely:

1. **All `.de-fe-print-*` styling rules were moved out of `@media print` to global scope.** The base `.de-fe-print-table { display: none }` still hides the table on a normal screen. The print path and the PDF path now both rely on flipping `display: table` to show the table; once it's visible, the cell colors, fonts, borders, etc. apply automatically because their rules live at the top level.
2. **JS now uses html2canvas's `onclone` callback.** The live schedule element is passed directly to `html2pdf().from(schedule)`. html2canvas clones the document internally; in `onclone`, the cloned schedule has its on-screen widgets (grid, mobile-list, day-nav, filters, action buttons, session modal) hidden with `style.display = 'none' !important`, every `.de-fe-schedule-day` gets `is-active` so all days render, and the print tables get `style.display = 'table' !important`. Only the rasterizer sees these changes — the user's page is never touched.

No more cloning, no more off-screen wrapper, no more runtime CSS scoping, no more `.de-fe-pdf-context` class.

## [0.8.4] - 2026-05-13

### Fixed — PDF was rendering only the day headers, no content

Two stacking bugs caused the v0.8.3 PDF to come out as a bare list of day-header boxes with no schedule beneath them:

1. **`.de-fe-pdf-context` was on the cloned schedule, not on its wrapper.** The runtime-generated mirror rules look like `.de-fe-pdf-context .digitone-events-schedule .X`, which is a *descendant* combinator — it requires the two classes to be on **different** elements. The clone had both classes on itself, so the selector never matched and the print table stayed hidden by its base `display: none`. Class moved to the wrapper element (the absolute-positioned off-screen `<div>` that holds the clone), so the descendant chain works as written.

2. **Cruft from v0.8.0 and v0.8.1 print blocks was still in the CSS.** The 0.8.2 cleanup script regex didn't match the old banner comments, so three `@media print` blocks coexisted, each declaring its own visibility toggles for grid / mobile-list / day-header. The runtime mirror scoped all three into the PDF context, where they fought each other. Removed both legacy blocks; only the 0.8.2 table-based print block remains.

## [0.8.3] - 2026-05-13

### Changed — "Export to PDF" now produces a real PDF file (no print dialog)

- The button no longer routes through `window.print()` + browser save-as-PDF. It now generates the PDF **client-side** via `html2pdf.js` (a bundled wrapper around jsPDF + html2canvas, ~150 KB), which downloads the file directly as `<Event Name> — Programme.pdf`. One click → file on disk. No print dialog, no "Save" confirmation step.
- The library is **lazy-loaded from cdnjs** on the first click and cached by the browser afterward, so the plugin zip itself stays small. No Composer dependency, no shell binaries, no server-side PDF library to install.
- During generation the button shows a small spinner and "Generating PDF…" label. On error it logs to the console and shows a brief alert; the page is otherwise untouched.

### Internal — how the off-screen render works
- The whole `.digitone-events-schedule` element is cloned into an absolutely-positioned wrapper at `left: -99999px`. UI chrome (filters, day-nav, action buttons, session modal) and the screen-only layouts (grid + mobile list) are stripped from the clone; every day is forced visible (`.is-active`); the print tables stay.
- The clone receives a new `.de-fe-pdf-context` class. To avoid CSS duplication, a runtime helper (`installPdfContextStyles`) reads every rule inside any `@media (... print ...)` block in the loaded stylesheets, scopes each selector under `.de-fe-pdf-context`, and emits the mirror as a single `<style id="de-fe-pdf-ctx-styles">`. The `@media print` source stays the single source of truth.
- `html2pdf.js` then renders the wrapper via html2canvas (scale 2 for sharpness) and assembles the resulting bitmap into an A4 landscape PDF (8 mm margins, JPEG quality 0.95, jsPDF compression). `pagebreak.avoid` is set to `tr, .de-fe-print-td-session, .de-fe-print-td-break, .de-fe-day-header` so sessions, breaks, and day headers don't get split across pages.
- The wrapper is removed in a `finally` block so a failed/cancelled generation doesn't leak DOM.

### Notes & trade-offs
- The output is a **rasterized PDF** (each page is a high-res image). Text inside the PDF is therefore not selectable or searchable. The trade-off is layout fidelity: whatever your browser would have shown via `@media print` is exactly what ends up in the PDF — including the colored hall headers, type colors, and Croatian diacritics (ć, č, š, ž, đ) which would have required custom font embedding in a text-mode jsPDF flow.
- Filesize for a 3-day, ~60-session program is roughly 1.5–3 MB.
- The first click after a page load triggers a one-off ~150 KB fetch from cdnjs; subsequent clicks reuse the cached library. If a site has a strict CSP that forbids cdnjs, the admin can either relax `script-src` to allow `cdnjs.cloudflare.com` or self-host html2pdf.js and override `HTML2PDF_CDN` (currently a constant near the top of `setupExportPdf` in `assets/js/frontend.js`).

## [0.8.2] - 2026-05-13

### Added — "Export to PDF" button + proper page break logic

- **New "Export to PDF" button** next to "Add to calendar" in the agenda header. Click triggers the browser's print dialog with `document.title` temporarily set to `<Event Name> — Programme`, so when you choose *Save as PDF* the suggested filename is sensible. After printing the title is restored.
- **Print output now uses a CSS `<table>`** instead of the on-screen CSS Grid. The grid layout doesn't fragment cleanly across printed pages — CSS Grid was never designed for paged media, which is why the 0.8.1 output had day headers stranded alone on a page with content overflowing onto the next. Tables, on the other hand, are the original paged-media browser layout primitive:
  - `<thead>` with the coloured hall headers **auto-repeats on every printed page**. So if Day 2 spills onto a second page, the second page starts with the hall headers again — exactly the behaviour you'd expect from a printed conference programme.
  - Every `<td>` (every session block, every break) has `break-inside: avoid` + `page-break-inside: avoid` so a session is **never split across two pages**. If a session won't fit at the bottom of the current page it gets pushed to the next page in its entirety.

### Internal
- Per day, PHP builds a server-side map of which slot × hall combinations contain a session start, which are mid-session continuations (and therefore covered by rowspan from above), and where breaks fall. Then it emits one `<tr>` per time slot with proper `rowspan` on multi-slot sessions and `colspan`-less per-hall break cells (the break label appears in the first non-continuation column, which keeps multi-hour sessions running through breaks like Hall D's workshop visible).
- The print table is in the DOM at all times but hidden with `display: none` on screen; only `@media print` flips it to `display: table`. On-screen grid is hidden in the inverse direction. No JS needed to swap layouts.
- The 0.8.1 `beforeprint`/`afterprint` filter-reset handlers were removed — the print table is built server-side from the full event data and is unaffected by any UI filter, so there's nothing to reset.

### Notes
- A4 landscape. Background graphics ON in Chrome's print dialog (modern browsers honour `print-color-adjust: exact` regardless but this toggle is the safety net).
- Server-side fully-automatic PDF generation (single-click download, no print dialog) was considered but would have required bundling dompdf or mPDF (3–10 MB of dependency) and isn't possible in the current build environment without network access. The button + browser-print flow needs one extra click (the *Save* button in the print dialog) but the layout fidelity is much better than 0.8.1.

## [0.8.1] - 2026-05-13

### Fixed
- **Print stylesheet is now the time-grid layout, not the mobile-list.** The 0.8.0 attempt swapped the agenda to a mobile-list-style stack on print, but the existing mobile-item markup doesn't have a body wrapper — when forced into a 2-column grid, auto-placement put the type badge into the *next* grid cell next to the time string, causing visible overlap (e.g. *"09:30 → 09:30ARY LECTURE"*). The print output now uses the same multi-column time-grid that the desktop screen renders, with all hall columns visible at once.
- **Page orientation switched to A4 landscape**, giving the grid breathing room with 5+ halls. Time axis tightened to 14 mm, hall columns share remaining width as `minmax(0, 1fr)` so they don't blow out beyond the page.
- **Filter is automatically cleared at print time and restored after print** via `beforeprint`/`afterprint` window events. So even if you're staring at *Hall B + Lecture* in the on-screen UI, the printed PDF is the full event programme. After the print dialog closes, your on-screen filter is restored unchanged.
- **Tighter print typography** so each cell can carry type badge + title + time + speakers without wrapping awkwardly: block title 7.5 pt, type 6 pt, speakers 6.5 pt. Hall headers stay coloured (white on hall colour) and break ribbons stay amber.

### Notes
- A4 landscape was chosen because conferences with more than 3 halls don't fit comfortably on portrait without each column becoming too narrow. If you want portrait, change `@page { size: A4 landscape }` to `A4 portrait` in your child theme or a custom CSS override.
- "Background graphics" in the Chrome print dialog should be enabled if your colours don't appear. Modern browsers honour `print-color-adjust: exact` regardless but this toggle is the safety net.
- Long days may split across two pages of the printed PDF. `page-break-inside: avoid` is set on each session block so individual sessions aren't cut in half.

## [0.8.0] - 2026-05-13

### Added — A-bundle continuation: print stylesheet (2/3 of finale)
- **Color print / "Save as PDF" stylesheet for the agenda.** Hitting Ctrl/Cmd+P (or *File → Print*) on a page that hosts the `[digitone_events_schedule]` shortcode now produces a clean A4-portrait document with:
  - **All hall colours preserved** via `-webkit-print-color-adjust: exact` + `print-color-adjust: exact`. Hall A stays blue, Hall B pink, type badges keep their per-type colour, Break sessions keep their amber ribbon.
  - **All days included** (the on-screen "active day only" rule is overridden so the printed PDF is the whole event).
  - **Mobile-list layout** is used for print regardless of viewport — stacks cleanly on portrait paper without truncated columns. Speaker lists fully unfold (no `+N` overflow indicators).
  - **Page break before each day** (except the first), `page-break-inside: avoid` on individual session rows so you don't get a title split between pages.
  - **Filters are ignored on print** — even if "Hall B + Lecture" is currently active in the on-screen UI, the printed PDF is the full programme (`.is-filtered-out` rule is overridden inside `@media print`).
- **UI chrome stripped from print**: the filters accordion, day picker, "Add to calendar" buttons, session detail modal, and the WP admin bar are all hidden.

### Notes for users
- In Chrome/Edge's print dialog the **"Background graphics"** toggle is honoured if turned on. With `print-color-adjust: exact`, modern browsers should keep colours regardless, but in some niche browser-OS combos you may need to enable "Background graphics" to see hall/type colours. Firefox honours `print-color-adjust` automatically.
- "Save as PDF" works exactly the same way as printing — same stylesheet kicks in.

### Coming in 0.8.1
- Speakers shortcode polish (search box + filter by role + sort by surname). Completes the A bundle.

## [0.7.9] - 2026-05-13

### Added — A-bundle continuation: search + type + speaker filters

- **Free-text search box** above the type picker in the Filters accordion. Matches against session title, every speaker's first + last name, the session type name, and the sub-venue name (all lowercased + UTF-8 normalised so Croatian diacritics like *Veršić* match *versic*). Input is debounced ~200 ms so typing in long queries isn't laggy. A circular ✕ clear button appears once the field is non-empty.
- **Session type picker.** Horizontal pill row inside the accordion with one "All types" pill and one pill per unique session type used in the event. Each type pill uses its session-type colour as the active background (the same colour the type badge shows on each block), so picking *Lecture* turns the pill blue, *Nursing Session* pink, etc.
- **Speaker dropdown.** Standard `<select>` listing every speaker who appears in any of the event's sessions, sorted alphabetically by last name. Browsers' built-in type-ahead means you can press *B* and jump straight to *Bosak Veršić, Ana*. Picking a speaker filters to sessions they participate in.

### Changed
- **Filter logic now composes 5 dimensions** instead of just venue: search · type · speaker · primary venue · sub venue. They AND-combine — a block is visible only if every active dimension matches.
- **Column layout is driven by venue filter only.** Type/speaker/search narrow visibility within the columns the venue filter chose, so picking *Lecture* doesn't collapse a 4-column Sheraton view to 1 column just because only some halls have lecture sessions.
- **Active-filter status** in the accordion title shows the single active label when only one filter is set (`Filters: Hall B`) or a count when 2+ are set (`Filters (3 active)`).

### Internal
- `setupVenueFilter` was renamed and generalised to `setupFilters`. Five `matches*` predicates (`matchesVenue`, `matchesType`, `matchesSpeaker`, `matchesSearch`, `matchesAll`) compose. `matchesVenue` is still used standalone for column layout; `matchesAll` for visibility.
- Each block + mobile item now carries `data-type-id`, `data-speaker-ids` (comma-separated), and `data-search-text` (pre-lowercased UTF-8 string). Server-side pre-computation keeps the JS hot path cheap — no per-keystroke string normalisation, just a substring match against an attribute.
- PHP collects `$type_options` and `$speaker_options` once when rendering the agenda; they sort by type name and speaker last name respectively.

### Coming in 0.8.x
- Print stylesheet (colour PDF via `@media print`)
- Speakers shortcode polish (search, role filter, surname sort)

## [0.7.8] - 2026-05-13

### Changed
- **Desktop grid now physically resizes to the filter's column count instead of just hiding tracks.**
  Previously a primary-level filter (e.g. "Sheraton" with 4 halls) collapsed only when you also picked a specific sub-venue. Without a sub, the grid kept all 5 column tracks (Hall A · Hall B · Hall C · Hall D · sub-test 2) and just visually hid the non-matching one — leaving an awkward blank column.
  Now the JS:
  1. Collects only the hall headers that match the active filter,
  2. Sorts them by their original column position,
  3. Re-indexes them as columns 2 to N+1,
  4. Sets `grid-template-columns` to `70px repeat(N, minmax(200px, 1fr))`,
  5. Re-maps every visible block + header to its new column.
  Result: Sheraton with 4 halls → 4 columns, Test venue with 1 hall → 1 column, full event → 5 columns. Sub-level filter still collapses to a single column. Breaks span all visible columns via `grid-column: 2 / -1` regardless of count.

### Internal
- Hall headers now carry `data-venue-id` (the primary venue id) in addition to `data-sub-venue-id`. Built from a new `$hall_primary` map in `agenda.php` so primary-level matching also works for header elements.
- The sub-vs-primary branch in `apply()` was deleted. One unified path now computes the matching sub set, builds a column remap, and rewrites grid template + element columns. The same code path handles both filter modes — fewer special cases, fewer chances for the two paths to drift.

## [0.7.7] - 2026-05-13

### Added
- **Filters accordion.** The whole venue/hall picker now lives inside a collapsible "Filters" section that is closed by default. Click the header to expand and pick a filter. Implementation uses the native `<details>` + `<summary>` HTML pair, which gives keyboard support, screen-reader semantics, and the open/close animation for free. The chevron rotates 90° when open.
- **Active filter is shown in the accordion title.** When the accordion is collapsed and a filter is applied, the title reads e.g. *"Filters: Hall B"* or *"Filters: Test venue"* so users can see what's filtered without expanding.

### Changed
- **Sub-venue labels are now context-aware in multi-primary mode.** Previously every sub pill always carried its primary's name (e.g. *"Sheraton Dubrovnik Riviera Hotel — Hall A"*). Now: when a specific primary venue is selected, the still-visible sub pills shorten to just their sub-venue name (e.g. *"Hall A"*) because the parent primary is already implied by the active primary pill above. When "All venues" is active, the long *"Primary — Sub"* form is restored so each pill stays unambiguous. Single-primary events are unchanged (labels were already just the sub name).

### Internal
- Each sub-venue pill now carries `data-label-short` (sub name alone) and `data-label-long` (`Primary — Sub`) attributes. The new `updateSubLabels()` swaps `.textContent` between them based on `currentPrimary`.
- `updateFilterStatus()` writes the active filter text into the `[data-de-active-label]` slot in the accordion summary on every filter change.

## [0.7.6] - 2026-05-13

### Changed
- **Active venue pill now matches the hall's colour.** Each sub-venue pill carries an inline `--hall-color` CSS variable, and the `.is-active` rule reads from it (falling back to the primary blue for the "All venues" pill). So when you select *Hall B*, its pill turns pink (matching the Hall B header colour), *Hall A* turns blue, and so on — keeps the visual binding between picker and grid consistent.
- **Smart venue labels based on primary-venue count.** When the event has exactly *one* primary venue (the common case — one hotel, several halls), the pills show just the sub-venue name ("Hall A", "Hall B", …) instead of the longer "Sheraton Dubrovnik Riviera Hotel — Hall A". When the event has *two or more* primary venues, the longer "Primary — Sub" format is used so each pill is unambiguous.

### Added
- **Two-level picker for multi-venue events.** When the event spans 2+ primary venues, a top "Filter by venue" row is rendered above the hall picker. Clicking a primary (e.g. *Hotel A*) filters the schedule to all of that primary's halls; the sub picker is also narrowed to that primary's halls. Clicking a specific hall narrows further. Clicking "All venues" resets both. Single-primary events are unchanged.
- **Each block now carries `data-venue-id`** (the primary venue) in addition to `data-sub-venue-id`. The JS filter uses this for the new primary-level filter mode, where the grid keeps its multi-column layout but non-matching halls are hidden.

### Internal
- `setupVenueFilter` was rewritten around an explicit state pair `(currentPrimary, currentSub)`. The `matchesFilter` predicate prefers `sub` over `primary` when both are set, so picking a hall always wins over picking its parent venue. Picking a primary clears any selected sub, so the picker UX stays predictable.

## [0.7.5] - 2026-05-13

### Fixed
- **Mobile filter order is now correct.** Before, when filtering by Hall B (sessions starting at 13:00), the 15:00 Coffee Break appeared at the *top* of the list — above the 13:00 session. That happened because the PHP default sort is `sub_venue_name → time`, so the break (assigned to "Hall A") was rendered before any Hall B item alphabetically. The JS filter now re-orders the mobile list chronologically by `data-start-minutes` while a filter is active, and restores the original hall-grouped order when the filter is cleared.
- **Break sessions are de-duplicated by time slot when filtering.** If your data has the same break entered multiple times (e.g. one per hall), the filter view will now show each unique `(start, end)` time-pair *once* per filtered venue rather than rendering it twice. Applied to both the desktop grid and the mobile list.

### How the chronological-while-filtered logic works
On setup, each mobile list's original DOM order is captured into a `WeakMap`. When the user picks a specific venue, the JS sorts a snapshot of the items by `data-start-minutes` and re-appends them in that order. When "All venues" is clicked, the original snapshot is replayed back into the DOM so the default hall-grouped view returns intact.

## [0.7.4] - 2026-05-13

### Fixed
- **Desktop hall headers reappear correctly after clearing a venue filter.** Previously, applying a filter set the matching hall header to `style.grid-column: 2` inline, but clearing the filter only removed the hidden class — the override stayed and the header was stuck at column 2, overlapping Hall A so it looked like Hall A and Hall B had vanished. Hall headers now carry `data-original-grid-column` (same pattern as session blocks), and the restore path reapplies that value.
- **Breaks before a venue's first session are no longer shown when filtering.** Example: Hall A starts at 09:00 but Hall B has nothing until 13:00. Filtering on Hall B used to still show the 10:30 Coffee Break and the 12:00 Lunch Break, even though Hall B wasn't open yet. The JS filter now computes the venue's active time range from its non-break sessions and only shows break sessions that overlap that range — applied to both the desktop grid and the mobile list.

### Changed
- **Break ribbon is now warm amber** (light cream → amber gradient with dark amber text) instead of gray. Reads as "coffee / lunch time" at a glance and pops against the colourful hall blocks without competing with them. Applied to both the desktop grid ribbon and the mobile break tile.

### How the time-range break filter works
Each block in the rendered HTML now carries `data-start-minutes` and `data-end-minutes` (minutes-since-midnight integers). When a venue filter is applied, the JS scans the venue's non-break blocks to find the earliest start and latest end, then shows break blocks only when they overlap that window (`break.start < venue.lastEnd && break.end > venue.firstStart`). Clearing the filter restores everything.

## [0.7.3] - 2026-05-13

### Fixed
- **Venue picker is back.** In 0.7.2 the venue-filter UI referenced `$venue_options` but the PHP that builds the options array was silently dropped by a bad string-replace patch. The builder is now present (collects unique `sub_venue_id` → "Primary Venue — Sub Venue" labels), and the pills render on both desktop and mobile under the day picker.
- **Mobile list is now grouped by hall first, then by time within each hall.** Previously the list was sorted strictly by start time across all halls, which made it hard to follow a single track on a phone. Now you see all Hall A sessions chronologically, then all Hall B, etc.

### Changed
- **Desktop hall headers are now coloured.** Each hall header in the time-grid uses the same `--hall-color` swatch that the mobile pills use (blue for the first hall, pink for the second, teal for the third, etc.). White text on the colour with a subtle text shadow. Visually ties the two views together so you don't have to translate "blue pill on phone" → "third column on laptop".

### Notes
- Auto-assigned palette of 8 colours cycles after the 8th hall. Override via custom CSS targeting `.de-fe-grid-hall[data-sub-venue-id="..."]` if you want specific hall ↔ colour bindings.

## [0.7.2] - 2026-05-13

### Fixed
- **Speaker photos in the modal no longer get vertically stretched on mobile** (and in some desktop themes). Block themes commonly apply `img { height: auto !important; max-width: 100%; }` to make embedded images responsive, which was overriding our `height: 56px`. We now lock the modal photo with `width/height/min/max + aspect-ratio: 1 / 1`, all `!important`, so theme defaults can't squash or stretch it. Same hardening applied to the speakers-grid avatars in the `[digitone_events_speakers]` shortcode (110px).

### Added
- **Venue filter** below the day picker. A horizontal pill row labelled "All venues" (default) + one pill per unique sub-venue, each combining its primary venue and sub-venue name as "Primary — Sub" (e.g. *"Sheraton Dubrovnik Riviera Hotel — Hall A"*).
  - Default on page load: "All venues" is active and the grid renders exactly as before.
  - Click a venue pill → grid collapses to a single column showing only that hall's sessions; break-type sessions stay visible because they apply to the whole event.
  - The mobile list view filters the same way (non-matching items hidden, breaks always visible).
  - Filter state persists across day switches.
  - Click "All venues" → grid restores to its multi-hall layout with all blocks visible.

### Technical notes
- Filter logic lives entirely in the front-end JS; nothing changes on the server. Each block, hall header, and mobile item now carries `data-sub-venue-id` for matching. Blocks also carry `data-original-grid-column` so the JS can restore their position when filtering is cleared.

## [0.7.1] - 2026-05-13

### Fixed
- **Session detail modal now opens from the mobile list too.** Previously the click handler only matched `.de-fe-block` (desktop grid items), so on phones the modal never opened because the responsive view renders `.de-fe-mobile-item` instead. Both elements now share the same click + keyboard handling.

### Changed
- **Mobile session items now show speakers.** Up to 3 names are shown comma-separated inline, with a `+N` indicator for the rest, matching the desktop block content.
- Mobile items have proper focus styling (2px primary-coloured ring) and are keyboard-focusable so the modal can be opened with Enter/Space on touch + bluetooth keyboard combos.
- Break-type mobile items are styled as a centred ribbon (matching the desktop grid's break appearance) and are NOT clickable (no `data-session-id`).

## [0.8.5] - 2026-05-13

### Fixed — PDF now actually contains the schedule, not just day headers

The 0.8.4 fix moved `.de-fe-pdf-context` to the wrapper element, but the PDF still came out blank under the day headers. Root cause was a layer deeper than DOM placement:

- **html2canvas doesn't run `@media print`.** It captures the DOM as a regular on-screen view via its internal iframe; the print stylesheet's `display: table` toggle on `.de-fe-print-table` never fires, so the table stays at its baseline `display: none`. Even when `.de-fe-pdf-context` was correctly placed and the mirror rules were scoped, the iframe environment apparently didn't see them apply via cascade through the runtime style tag.

The new approach removes both the mirror-style mechanism and the off-screen wrapper entirely:

1. **All `.de-fe-print-*` styling rules were moved out of `@media print` to global scope.** The base `.de-fe-print-table { display: none }` still hides the table on a normal screen. The print path and the PDF path now both rely on flipping `display: table` to show the table; once it's visible, the cell colors, fonts, borders, etc. apply automatically because their rules live at the top level.
2. **JS now uses html2canvas's `onclone` callback.** The live schedule element is passed directly to `html2pdf().from(schedule)`. html2canvas clones the document internally; in `onclone`, the cloned schedule has its on-screen widgets (grid, mobile-list, day-nav, filters, action buttons, session modal) hidden with `style.display = 'none' !important`, every `.de-fe-schedule-day` gets `is-active` so all days render, and the print tables get `style.display = 'table' !important`. Only the rasterizer sees these changes — the user's page is never touched.

No more cloning, no more off-screen wrapper, no more runtime CSS scoping, no more `.de-fe-pdf-context` class.

## [0.8.4] - 2026-05-13

### Fixed — PDF was rendering only the day headers, no content

Two stacking bugs caused the v0.8.3 PDF to come out as a bare list of day-header boxes with no schedule beneath them:

1. **`.de-fe-pdf-context` was on the cloned schedule, not on its wrapper.** The runtime-generated mirror rules look like `.de-fe-pdf-context .digitone-events-schedule .X`, which is a *descendant* combinator — it requires the two classes to be on **different** elements. The clone had both classes on itself, so the selector never matched and the print table stayed hidden by its base `display: none`. Class moved to the wrapper element (the absolute-positioned off-screen `<div>` that holds the clone), so the descendant chain works as written.

2. **Cruft from v0.8.0 and v0.8.1 print blocks was still in the CSS.** The 0.8.2 cleanup script regex didn't match the old banner comments, so three `@media print` blocks coexisted, each declaring its own visibility toggles for grid / mobile-list / day-header. The runtime mirror scoped all three into the PDF context, where they fought each other. Removed both legacy blocks; only the 0.8.2 table-based print block remains.

## [0.8.3] - 2026-05-13

### Changed — "Export to PDF" now produces a real PDF file (no print dialog)

- The button no longer routes through `window.print()` + browser save-as-PDF. It now generates the PDF **client-side** via `html2pdf.js` (a bundled wrapper around jsPDF + html2canvas, ~150 KB), which downloads the file directly as `<Event Name> — Programme.pdf`. One click → file on disk. No print dialog, no "Save" confirmation step.
- The library is **lazy-loaded from cdnjs** on the first click and cached by the browser afterward, so the plugin zip itself stays small. No Composer dependency, no shell binaries, no server-side PDF library to install.
- During generation the button shows a small spinner and "Generating PDF…" label. On error it logs to the console and shows a brief alert; the page is otherwise untouched.

### Internal — how the off-screen render works
- The whole `.digitone-events-schedule` element is cloned into an absolutely-positioned wrapper at `left: -99999px`. UI chrome (filters, day-nav, action buttons, session modal) and the screen-only layouts (grid + mobile list) are stripped from the clone; every day is forced visible (`.is-active`); the print tables stay.
- The clone receives a new `.de-fe-pdf-context` class. To avoid CSS duplication, a runtime helper (`installPdfContextStyles`) reads every rule inside any `@media (... print ...)` block in the loaded stylesheets, scopes each selector under `.de-fe-pdf-context`, and emits the mirror as a single `<style id="de-fe-pdf-ctx-styles">`. The `@media print` source stays the single source of truth.
- `html2pdf.js` then renders the wrapper via html2canvas (scale 2 for sharpness) and assembles the resulting bitmap into an A4 landscape PDF (8 mm margins, JPEG quality 0.95, jsPDF compression). `pagebreak.avoid` is set to `tr, .de-fe-print-td-session, .de-fe-print-td-break, .de-fe-day-header` so sessions, breaks, and day headers don't get split across pages.
- The wrapper is removed in a `finally` block so a failed/cancelled generation doesn't leak DOM.

### Notes & trade-offs
- The output is a **rasterized PDF** (each page is a high-res image). Text inside the PDF is therefore not selectable or searchable. The trade-off is layout fidelity: whatever your browser would have shown via `@media print` is exactly what ends up in the PDF — including the colored hall headers, type colors, and Croatian diacritics (ć, č, š, ž, đ) which would have required custom font embedding in a text-mode jsPDF flow.
- Filesize for a 3-day, ~60-session program is roughly 1.5–3 MB.
- The first click after a page load triggers a one-off ~150 KB fetch from cdnjs; subsequent clicks reuse the cached library. If a site has a strict CSP that forbids cdnjs, the admin can either relax `script-src` to allow `cdnjs.cloudflare.com` or self-host html2pdf.js and override `HTML2PDF_CDN` (currently a constant near the top of `setupExportPdf` in `assets/js/frontend.js`).

## [0.8.2] - 2026-05-13

### Added — "Export to PDF" button + proper page break logic

- **New "Export to PDF" button** next to "Add to calendar" in the agenda header. Click triggers the browser's print dialog with `document.title` temporarily set to `<Event Name> — Programme`, so when you choose *Save as PDF* the suggested filename is sensible. After printing the title is restored.
- **Print output now uses a CSS `<table>`** instead of the on-screen CSS Grid. The grid layout doesn't fragment cleanly across printed pages — CSS Grid was never designed for paged media, which is why the 0.8.1 output had day headers stranded alone on a page with content overflowing onto the next. Tables, on the other hand, are the original paged-media browser layout primitive:
  - `<thead>` with the coloured hall headers **auto-repeats on every printed page**. So if Day 2 spills onto a second page, the second page starts with the hall headers again — exactly the behaviour you'd expect from a printed conference programme.
  - Every `<td>` (every session block, every break) has `break-inside: avoid` + `page-break-inside: avoid` so a session is **never split across two pages**. If a session won't fit at the bottom of the current page it gets pushed to the next page in its entirety.

### Internal
- Per day, PHP builds a server-side map of which slot × hall combinations contain a session start, which are mid-session continuations (and therefore covered by rowspan from above), and where breaks fall. Then it emits one `<tr>` per time slot with proper `rowspan` on multi-slot sessions and `colspan`-less per-hall break cells (the break label appears in the first non-continuation column, which keeps multi-hour sessions running through breaks like Hall D's workshop visible).
- The print table is in the DOM at all times but hidden with `display: none` on screen; only `@media print` flips it to `display: table`. On-screen grid is hidden in the inverse direction. No JS needed to swap layouts.
- The 0.8.1 `beforeprint`/`afterprint` filter-reset handlers were removed — the print table is built server-side from the full event data and is unaffected by any UI filter, so there's nothing to reset.

### Notes
- A4 landscape. Background graphics ON in Chrome's print dialog (modern browsers honour `print-color-adjust: exact` regardless but this toggle is the safety net).
- Server-side fully-automatic PDF generation (single-click download, no print dialog) was considered but would have required bundling dompdf or mPDF (3–10 MB of dependency) and isn't possible in the current build environment without network access. The button + browser-print flow needs one extra click (the *Save* button in the print dialog) but the layout fidelity is much better than 0.8.1.

## [0.8.1] - 2026-05-13

### Fixed
- **Print stylesheet is now the time-grid layout, not the mobile-list.** The 0.8.0 attempt swapped the agenda to a mobile-list-style stack on print, but the existing mobile-item markup doesn't have a body wrapper — when forced into a 2-column grid, auto-placement put the type badge into the *next* grid cell next to the time string, causing visible overlap (e.g. *"09:30 → 09:30ARY LECTURE"*). The print output now uses the same multi-column time-grid that the desktop screen renders, with all hall columns visible at once.
- **Page orientation switched to A4 landscape**, giving the grid breathing room with 5+ halls. Time axis tightened to 14 mm, hall columns share remaining width as `minmax(0, 1fr)` so they don't blow out beyond the page.
- **Filter is automatically cleared at print time and restored after print** via `beforeprint`/`afterprint` window events. So even if you're staring at *Hall B + Lecture* in the on-screen UI, the printed PDF is the full event programme. After the print dialog closes, your on-screen filter is restored unchanged.
- **Tighter print typography** so each cell can carry type badge + title + time + speakers without wrapping awkwardly: block title 7.5 pt, type 6 pt, speakers 6.5 pt. Hall headers stay coloured (white on hall colour) and break ribbons stay amber.

### Notes
- A4 landscape was chosen because conferences with more than 3 halls don't fit comfortably on portrait without each column becoming too narrow. If you want portrait, change `@page { size: A4 landscape }` to `A4 portrait` in your child theme or a custom CSS override.
- "Background graphics" in the Chrome print dialog should be enabled if your colours don't appear. Modern browsers honour `print-color-adjust: exact` regardless but this toggle is the safety net.
- Long days may split across two pages of the printed PDF. `page-break-inside: avoid` is set on each session block so individual sessions aren't cut in half.

## [0.8.0] - 2026-05-13

### Added — A-bundle continuation: print stylesheet (2/3 of finale)
- **Color print / "Save as PDF" stylesheet for the agenda.** Hitting Ctrl/Cmd+P (or *File → Print*) on a page that hosts the `[digitone_events_schedule]` shortcode now produces a clean A4-portrait document with:
  - **All hall colours preserved** via `-webkit-print-color-adjust: exact` + `print-color-adjust: exact`. Hall A stays blue, Hall B pink, type badges keep their per-type colour, Break sessions keep their amber ribbon.
  - **All days included** (the on-screen "active day only" rule is overridden so the printed PDF is the whole event).
  - **Mobile-list layout** is used for print regardless of viewport — stacks cleanly on portrait paper without truncated columns. Speaker lists fully unfold (no `+N` overflow indicators).
  - **Page break before each day** (except the first), `page-break-inside: avoid` on individual session rows so you don't get a title split between pages.
  - **Filters are ignored on print** — even if "Hall B + Lecture" is currently active in the on-screen UI, the printed PDF is the full programme (`.is-filtered-out` rule is overridden inside `@media print`).
- **UI chrome stripped from print**: the filters accordion, day picker, "Add to calendar" buttons, session detail modal, and the WP admin bar are all hidden.

### Notes for users
- In Chrome/Edge's print dialog the **"Background graphics"** toggle is honoured if turned on. With `print-color-adjust: exact`, modern browsers should keep colours regardless, but in some niche browser-OS combos you may need to enable "Background graphics" to see hall/type colours. Firefox honours `print-color-adjust` automatically.
- "Save as PDF" works exactly the same way as printing — same stylesheet kicks in.

### Coming in 0.8.1
- Speakers shortcode polish (search box + filter by role + sort by surname). Completes the A bundle.

## [0.7.9] - 2026-05-13

### Added — A-bundle continuation: search + type + speaker filters

- **Free-text search box** above the type picker in the Filters accordion. Matches against session title, every speaker's first + last name, the session type name, and the sub-venue name (all lowercased + UTF-8 normalised so Croatian diacritics like *Veršić* match *versic*). Input is debounced ~200 ms so typing in long queries isn't laggy. A circular ✕ clear button appears once the field is non-empty.
- **Session type picker.** Horizontal pill row inside the accordion with one "All types" pill and one pill per unique session type used in the event. Each type pill uses its session-type colour as the active background (the same colour the type badge shows on each block), so picking *Lecture* turns the pill blue, *Nursing Session* pink, etc.
- **Speaker dropdown.** Standard `<select>` listing every speaker who appears in any of the event's sessions, sorted alphabetically by last name. Browsers' built-in type-ahead means you can press *B* and jump straight to *Bosak Veršić, Ana*. Picking a speaker filters to sessions they participate in.

### Changed
- **Filter logic now composes 5 dimensions** instead of just venue: search · type · speaker · primary venue · sub venue. They AND-combine — a block is visible only if every active dimension matches.
- **Column layout is driven by venue filter only.** Type/speaker/search narrow visibility within the columns the venue filter chose, so picking *Lecture* doesn't collapse a 4-column Sheraton view to 1 column just because only some halls have lecture sessions.
- **Active-filter status** in the accordion title shows the single active label when only one filter is set (`Filters: Hall B`) or a count when 2+ are set (`Filters (3 active)`).

### Internal
- `setupVenueFilter` was renamed and generalised to `setupFilters`. Five `matches*` predicates (`matchesVenue`, `matchesType`, `matchesSpeaker`, `matchesSearch`, `matchesAll`) compose. `matchesVenue` is still used standalone for column layout; `matchesAll` for visibility.
- Each block + mobile item now carries `data-type-id`, `data-speaker-ids` (comma-separated), and `data-search-text` (pre-lowercased UTF-8 string). Server-side pre-computation keeps the JS hot path cheap — no per-keystroke string normalisation, just a substring match against an attribute.
- PHP collects `$type_options` and `$speaker_options` once when rendering the agenda; they sort by type name and speaker last name respectively.

### Coming in 0.8.x
- Print stylesheet (colour PDF via `@media print`)
- Speakers shortcode polish (search, role filter, surname sort)

## [0.7.8] - 2026-05-13

### Changed
- **Desktop grid now physically resizes to the filter's column count instead of just hiding tracks.**
  Previously a primary-level filter (e.g. "Sheraton" with 4 halls) collapsed only when you also picked a specific sub-venue. Without a sub, the grid kept all 5 column tracks (Hall A · Hall B · Hall C · Hall D · sub-test 2) and just visually hid the non-matching one — leaving an awkward blank column.
  Now the JS:
  1. Collects only the hall headers that match the active filter,
  2. Sorts them by their original column position,
  3. Re-indexes them as columns 2 to N+1,
  4. Sets `grid-template-columns` to `70px repeat(N, minmax(200px, 1fr))`,
  5. Re-maps every visible block + header to its new column.
  Result: Sheraton with 4 halls → 4 columns, Test venue with 1 hall → 1 column, full event → 5 columns. Sub-level filter still collapses to a single column. Breaks span all visible columns via `grid-column: 2 / -1` regardless of count.

### Internal
- Hall headers now carry `data-venue-id` (the primary venue id) in addition to `data-sub-venue-id`. Built from a new `$hall_primary` map in `agenda.php` so primary-level matching also works for header elements.
- The sub-vs-primary branch in `apply()` was deleted. One unified path now computes the matching sub set, builds a column remap, and rewrites grid template + element columns. The same code path handles both filter modes — fewer special cases, fewer chances for the two paths to drift.

## [0.7.7] - 2026-05-13

### Added
- **Filters accordion.** The whole venue/hall picker now lives inside a collapsible "Filters" section that is closed by default. Click the header to expand and pick a filter. Implementation uses the native `<details>` + `<summary>` HTML pair, which gives keyboard support, screen-reader semantics, and the open/close animation for free. The chevron rotates 90° when open.
- **Active filter is shown in the accordion title.** When the accordion is collapsed and a filter is applied, the title reads e.g. *"Filters: Hall B"* or *"Filters: Test venue"* so users can see what's filtered without expanding.

### Changed
- **Sub-venue labels are now context-aware in multi-primary mode.** Previously every sub pill always carried its primary's name (e.g. *"Sheraton Dubrovnik Riviera Hotel — Hall A"*). Now: when a specific primary venue is selected, the still-visible sub pills shorten to just their sub-venue name (e.g. *"Hall A"*) because the parent primary is already implied by the active primary pill above. When "All venues" is active, the long *"Primary — Sub"* form is restored so each pill stays unambiguous. Single-primary events are unchanged (labels were already just the sub name).

### Internal
- Each sub-venue pill now carries `data-label-short` (sub name alone) and `data-label-long` (`Primary — Sub`) attributes. The new `updateSubLabels()` swaps `.textContent` between them based on `currentPrimary`.
- `updateFilterStatus()` writes the active filter text into the `[data-de-active-label]` slot in the accordion summary on every filter change.

## [0.7.6] - 2026-05-13

### Changed
- **Active venue pill now matches the hall's colour.** Each sub-venue pill carries an inline `--hall-color` CSS variable, and the `.is-active` rule reads from it (falling back to the primary blue for the "All venues" pill). So when you select *Hall B*, its pill turns pink (matching the Hall B header colour), *Hall A* turns blue, and so on — keeps the visual binding between picker and grid consistent.
- **Smart venue labels based on primary-venue count.** When the event has exactly *one* primary venue (the common case — one hotel, several halls), the pills show just the sub-venue name ("Hall A", "Hall B", …) instead of the longer "Sheraton Dubrovnik Riviera Hotel — Hall A". When the event has *two or more* primary venues, the longer "Primary — Sub" format is used so each pill is unambiguous.

### Added
- **Two-level picker for multi-venue events.** When the event spans 2+ primary venues, a top "Filter by venue" row is rendered above the hall picker. Clicking a primary (e.g. *Hotel A*) filters the schedule to all of that primary's halls; the sub picker is also narrowed to that primary's halls. Clicking a specific hall narrows further. Clicking "All venues" resets both. Single-primary events are unchanged.
- **Each block now carries `data-venue-id`** (the primary venue) in addition to `data-sub-venue-id`. The JS filter uses this for the new primary-level filter mode, where the grid keeps its multi-column layout but non-matching halls are hidden.

### Internal
- `setupVenueFilter` was rewritten around an explicit state pair `(currentPrimary, currentSub)`. The `matchesFilter` predicate prefers `sub` over `primary` when both are set, so picking a hall always wins over picking its parent venue. Picking a primary clears any selected sub, so the picker UX stays predictable.

## [0.7.5] - 2026-05-13

### Fixed
- **Mobile filter order is now correct.** Before, when filtering by Hall B (sessions starting at 13:00), the 15:00 Coffee Break appeared at the *top* of the list — above the 13:00 session. That happened because the PHP default sort is `sub_venue_name → time`, so the break (assigned to "Hall A") was rendered before any Hall B item alphabetically. The JS filter now re-orders the mobile list chronologically by `data-start-minutes` while a filter is active, and restores the original hall-grouped order when the filter is cleared.
- **Break sessions are de-duplicated by time slot when filtering.** If your data has the same break entered multiple times (e.g. one per hall), the filter view will now show each unique `(start, end)` time-pair *once* per filtered venue rather than rendering it twice. Applied to both the desktop grid and the mobile list.

### How the chronological-while-filtered logic works
On setup, each mobile list's original DOM order is captured into a `WeakMap`. When the user picks a specific venue, the JS sorts a snapshot of the items by `data-start-minutes` and re-appends them in that order. When "All venues" is clicked, the original snapshot is replayed back into the DOM so the default hall-grouped view returns intact.

## [0.7.4] - 2026-05-13

### Fixed
- **Desktop hall headers reappear correctly after clearing a venue filter.** Previously, applying a filter set the matching hall header to `style.grid-column: 2` inline, but clearing the filter only removed the hidden class — the override stayed and the header was stuck at column 2, overlapping Hall A so it looked like Hall A and Hall B had vanished. Hall headers now carry `data-original-grid-column` (same pattern as session blocks), and the restore path reapplies that value.
- **Breaks before a venue's first session are no longer shown when filtering.** Example: Hall A starts at 09:00 but Hall B has nothing until 13:00. Filtering on Hall B used to still show the 10:30 Coffee Break and the 12:00 Lunch Break, even though Hall B wasn't open yet. The JS filter now computes the venue's active time range from its non-break sessions and only shows break sessions that overlap that range — applied to both the desktop grid and the mobile list.

### Changed
- **Break ribbon is now warm amber** (light cream → amber gradient with dark amber text) instead of gray. Reads as "coffee / lunch time" at a glance and pops against the colourful hall blocks without competing with them. Applied to both the desktop grid ribbon and the mobile break tile.

### How the time-range break filter works
Each block in the rendered HTML now carries `data-start-minutes` and `data-end-minutes` (minutes-since-midnight integers). When a venue filter is applied, the JS scans the venue's non-break blocks to find the earliest start and latest end, then shows break blocks only when they overlap that window (`break.start < venue.lastEnd && break.end > venue.firstStart`). Clearing the filter restores everything.

## [0.7.3] - 2026-05-13

### Fixed
- **Venue picker is back.** In 0.7.2 the venue-filter UI referenced `$venue_options` but the PHP that builds the options array was silently dropped by a bad string-replace patch. The builder is now present (collects unique `sub_venue_id` → "Primary Venue — Sub Venue" labels), and the pills render on both desktop and mobile under the day picker.
- **Mobile list is now grouped by hall first, then by time within each hall.** Previously the list was sorted strictly by start time across all halls, which made it hard to follow a single track on a phone. Now you see all Hall A sessions chronologically, then all Hall B, etc.

### Changed
- **Desktop hall headers are now coloured.** Each hall header in the time-grid uses the same `--hall-color` swatch that the mobile pills use (blue for the first hall, pink for the second, teal for the third, etc.). White text on the colour with a subtle text shadow. Visually ties the two views together so you don't have to translate "blue pill on phone" → "third column on laptop".

### Notes
- Auto-assigned palette of 8 colours cycles after the 8th hall. Override via custom CSS targeting `.de-fe-grid-hall[data-sub-venue-id="..."]` if you want specific hall ↔ colour bindings.

## [0.7.2] - 2026-05-13

### Fixed
- **Speaker photos in the modal no longer get vertically stretched on mobile** (and in some desktop themes). Block themes commonly apply `img { height: auto !important; max-width: 100%; }` to make embedded images responsive, which was overriding our `height: 56px`. We now lock the modal photo with `width/height/min/max + aspect-ratio: 1 / 1`, all `!important`, so theme defaults can't squash or stretch it. Same hardening applied to the speakers-grid avatars in the `[digitone_events_speakers]` shortcode (110px).

### Added
- **Venue filter** below the day picker. A horizontal pill row labelled "All venues" (default) + one pill per unique sub-venue, each combining its primary venue and sub-venue name as "Primary — Sub" (e.g. *"Sheraton Dubrovnik Riviera Hotel — Hall A"*).
  - Default on page load: "All venues" is active and the grid renders exactly as before.
  - Click a venue pill → grid collapses to a single column showing only that hall's sessions; break-type sessions stay visible because they apply to the whole event.
  - The mobile list view filters the same way (non-matching items hidden, breaks always visible).
  - Filter state persists across day switches.
  - Click "All venues" → grid restores to its multi-hall layout with all blocks visible.

### Technical notes
- Filter logic lives entirely in the front-end JS; nothing changes on the server. Each block, hall header, and mobile item now carries `data-sub-venue-id` for matching. Blocks also carry `data-original-grid-column` so the JS can restore their position when filtering is cleared.

## [0.7.1] - 2026-05-13

### Fixed / Changed — Mobile UX polish

- **Hall colour-coding on mobile.** With the desktop's per-hall columns lost to a single-column phone layout, halls now get distinct auto-assigned colours (blue / pink / teal / orange / purple / red / green / cyan, cycling for events with 8+ halls). Each mobile card has a 5px coloured left border matching its hall, plus a coloured pill at the top of the card with the hall name — so it's obvious at a glance whether a 13:00 session is in Hall A or Hall C.
- **Mobile card layout redesigned.** Top row shows the hall pill (left) and time (right); below that, the type label, then title, then speakers. Previously time was a separate column on the left which wasted space and pushed everything else over.
- **Break sessions on mobile** render as a centred dashed ribbon (matches the visual language of the desktop break row).
- **Tap-to-open detail modal works on mobile** — the JS click handler already accepts both `.de-fe-block` and `.de-fe-mobile-item` selectors. If you're still not seeing the modal open or speakers listed on your phone after this update, your browser is serving cached HTML/JS. Pull-to-refresh on the page, or close + reopen the tab, to force a fresh load.

### Notes on caching
- Versioned asset URLs (`?ver=0.7.1`) bust the browser cache for CSS/JS automatically.
- The agenda HTML itself isn't versioned. If a page cache plugin (LiteSpeed, WP Rocket, W3 Total Cache, host-level full-page caching) is active, purge the cache for the agenda page after upgrading.

## [0.7.0] - 2026-05-13

### Added — Frontend UX bundle, part 1 of 2 (calendar + detail modal)

- **iCal (.ics) export of the full event agenda.** A new "Add to calendar" button next to the event description downloads an RFC 5545 compliant `.ics` file containing one VEVENT per non-break session, with proper timezone conversion to UTC so calendar apps display the right wall-clock time in the delegate's own timezone. Speakers and role assignments are included in each session's DESCRIPTION field.
- **Per-session iCal download.** Inside the new session-detail modal there is an "Add to calendar" button that downloads just that one session as an `.ics`.
- **Session detail modal.** Clicking (or pressing Enter/Space on) any non-break block in the agenda opens a modal showing the type, full title, time + venue, full HTML description, and the full speakers list with photos, role badges, and bios. The modal supports backdrop-click, ✕ button, and ESC to close, with proper focus restoration and aria-modal semantics.
- **Two new REST endpoints** (both public, no auth):
  - `GET /wp-json/digitone-events/v1/ical/event/{slug}` — full event .ics
  - `GET /wp-json/digitone-events/v1/ical/session/{session-id}` — single session .ics
- **New module**: `DigitOne_Events_Calendar_Module` registers the REST routes.
- **New helper**: `DigitOne_Events_Helpers_Ical` generates RFC 5545 compliant calendar files (proper line folding at 75 octets, UTF-8 boundary-safe, text escaping per §3.3.11).

### Changed
- `DigitOne_Events_Sessions_Repository::speakers_for_sessions()` now joins the speaker `bio` field as well, so the new detail modal can show speaker bios.
- Each non-break block in the agenda grid is now keyboard-focusable (`tabindex="0"`, `role="button"`) so the detail modal can be opened without a mouse.

### What's next (0.7.1)
Search/filter + print stylesheet (in colour) + speakers page polish. Coming in a follow-up release so this one stays focused and easy to test.

## [0.6.0] - 2026-05-13

### Fixed
- **Speaker photo picker now usable.** Clicking "Choose from library" in the speaker edit modal previously opened the WP media library *underneath* our modal, making it un-clickable. Root cause: the modal uses the native `<dialog>` element, which renders on the browser's *top layer* — above every z-indexed element on the page, including WP's media frame. Fix: when "Choose from library" is clicked, we now close our dialog (form values are preserved in the DOM), let the WP media library run as the only foreground modal, then reopen our dialog when the library closes (select or cancel). The selected URL flows back into the photo field as before.

## [0.5.9] - 2026-05-12

### Fixed
- **"Update available" banner now disappears immediately after a successful upgrade.** Added a self-cleaning step to `inject_update`: if the GitHub release is `<=` our installed version, we now *actively unset* any stale entry from `$transient->response`. Previously we returned the transient unmodified, so a stale entry from before the upgrade kept showing the banner until WP's own 12-hour refresh cycle expired.
- **"Update now" button (Settings → Force Check)** is now built with `document.createElement` instead of an `innerHTML` string interpolation. The `href` is assigned via the DOM property, so the URL's literal `&` is preserved end-to-end (no HTML escaping at all). Even if a future change accidentally reintroduces `wp_nonce_url`, the JS-side is now bug-proof.
- **`.de-fe-schedule-day` display rules are `!important`** so heavy-handed themes that target `section` or generic block selectors can't accidentally force hidden panels to be visible.
- **Defensive day-picker reset on initial render**: `show(idx)` now wipes `is-active` from every panel + button first, then sets only the chosen one. Belt-and-suspenders against any stale state from partial updates.

### How to deploy this cleanly (one-time housekeeping)
The single-update version-key bug (`'plugin'` vs `'plugins'` in `$hook_extra`) wasn't fixed until 0.5.8. If you're on 0.5.7 right now and the **Settings → Update Now button** gives "Link has expired", that's the 0.5.7 `wp_nonce_url` double-escape bug — don't use that button yet. Instead:

1. Go to **Plugins** (left WP menu), find DigitOne Events
2. Click **update now** in the yellow banner under the plugin row
3. The banner may reappear once — that's the 0.5.7 single-update bug. Click **update now** a second time; it'll clear.
4. Once you're on 0.5.9, the Settings → Force Check + Update Now button works correctly, and subsequent upgrades will take one click.

## [0.5.8] - 2026-05-12

### Fixed
- **Persistent "Update available" banner after upgrading.** The `on_upgrade_complete` hook only handled the `$hook_extra['plugins']` key (bulk updates) and silently skipped single-plugin updates which use `$hook_extra['plugin']` (singular). Result: the `update_plugins` site transient was never invalidated for the most common upgrade path, so WP kept showing "There is a new version available" pointing at the version you just installed. Now both keys are handled, and `wp_clean_plugins_cache(true)` is called too to flush every related cache.
- **"Update now" button gave "Link has expired" or did nothing.** `wp_nonce_url()` HTML-escapes the URL (`&` → `&amp;`). After passing through JSON to JS and being inserted via `escapeHtml`, the URL was double-escaped, so the browser navigated to `update.php?action=upgrade-plugin&amp;plugin=…&amp;_wpnonce=…`. WP's PHP saw query params named `amp;plugin` and `amp;_wpnonce` instead of `plugin` and `_wpnonce` → the nonce check failed with "Link has expired". The URL is now built with `add_query_arg()` + `wp_create_nonce()` directly (no HTML escaping involved), so the parameter names arrive intact and the nonce validates.

## [0.5.7] - 2026-05-12

### Changed
- **Day picker now always opens on Day 1 by default.** Removed the today-matching logic that was causing edge-case landings on Day 2 or Day 3. Day picker buttons still let you switch days manually with a click. Deep-link override via `?de-day=N` URL parameter or `#de-day-N` hash still works.

### Added
- **Browser tab title swap.** When the agenda shortcode renders, the browser tab title is updated to `<event-name> — <suffix>` where `<suffix>` defaults to `Programme`. For the IAAS 2026 event this displays as **"16th International Congress on Ambulatory Surgery, 2026 — Programme"** (or whatever the event name is in your site).
- New `title_suffix` shortcode attribute lets you customise or disable the swap:
  - `[digitone_events_agenda event="iaas-2026"]` → "<event> — Programme" (default)
  - `[digitone_events_agenda event="iaas-2026" title_suffix="Schedule"]` → "<event> — Schedule"
  - `[digitone_events_agenda event="iaas-2026" title_suffix=""]` → keeps the WP page title (no swap)

### Notes
- Title swap happens on the client side (via JS) so it works regardless of which page builder rendered the shortcode. The HTML `<title>` in source remains whatever WP/the theme generated; only the live browser tab and bookmarks are updated.
- The "Update now" button after Force Check GitHub (added in 0.5.6) is already in your installed version of `admin-common.js`. If you don't see it, your browser is caching the old admin JS — hard-refresh `/wp-admin/admin.php?page=digitone-events-settings` with Ctrl+F5 / Cmd+Shift+R.

## [0.5.6] - 2026-05-12

### Fixed
- **Plugin update no longer requires two clicks.** After a successful update of DigitOne Events, the WP-level `update_plugins` site transient is now invalidated in addition to our own GitHub release cache. Previously only our cache was cleared, leaving WP showing a stale "update available" banner that needed a second update click to disappear.
- **Day picker initial state is now bulletproof.** The frontend JS unconditionally calls `show(selectedIdx)` after determining the right day, so any stale `is-active` classes in the HTML (e.g. due to a partial plugin update where new CSS hadn't loaded yet) are forcibly normalised.

### Added
- **"Update now" button after Force Check GitHub.** When Settings → Force Check finds a newer release, the result message now includes a direct **Update now** button that deep-links to WP's plugin upgrade screen with a valid nonce. No more navigating to Plugins → Update.

## [0.5.5] - 2026-05-12

### Fixed
- **Default day detection moved to JS** (uses browser's local date) so the picker no longer jumps to the wrong day due to server timezone misconfiguration or clock drift. PHP defaults to Day 1; JS upgrades to "today" only if it actually matches one of the event days.
- **Grid rows now auto-size** via `grid-auto-rows: minmax(44px, auto)` — each row starts at 44px (preserving time-proportionality) and grows as needed to fit content. Long titles or speaker lists are no longer clipped.
- Removed `-webkit-line-clamp` on session titles and speaker names so full content is always visible.

### How initial day is picked now
1. URL has `?de-day=N` or `#de-day-N` → that day wins
2. Otherwise: browser's local date is compared against each day's `data-day-date` attribute — match wins
3. Otherwise: Day 1 (set in PHP markup, so it's visible even before JS runs)

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
