# SheetJS (bundled)

`xlsx.full.min.js` is SheetJS Community Edition **0.20.3**, bundled locally
since plugin 0.9.6 instead of loading 0.18.5 from cdnjs (0.18.5 is affected by
CVE-2023-30533 and CVE-2024-22363; SheetJS publishes fixed builds only on its
own CDN, not on npm or cdnjs).

- Source: https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js
- Size: 951904 bytes
- SHA-256: `cc015130aa8521e7f088f88898eba949ccdcbfb38df0bd129b44b7273c3a6f41`
- License: Apache-2.0 (https://cdn.sheetjs.com/xlsx-0.20.3/package/LICENSE)

## Updating

1. Check https://cdn.sheetjs.com/ for the latest version.
2. Download `https://cdn.sheetjs.com/xlsx-<ver>/package/dist/xlsx.full.min.js`
   over this file and note its SHA-256 here.
3. Bump `SHEETJS_VERSION` at the top of `assets/js/modules/export-import.js`
   (used as the cache-busting `?ver=`).

Loaded on demand by `assets/js/modules/export-import.js` (`loadSheetJs()`),
only on the Export / Import admin page.
