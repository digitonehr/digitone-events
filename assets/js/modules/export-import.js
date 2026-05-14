/**
 * DigitOne Events — Excel import/export (v0.9.0).
 *
 * Workflow:
 *   1. User clicks "Download empty template" or "Download current data"
 *      → AJAX fetches the entity schema (+ optional rows) → SheetJS builds
 *        an .xlsx in the browser and triggers a download.
 *   2. User edits the workbook in Excel / Google Sheets / LibreOffice.
 *   3. User uploads the file → SheetJS parses to JSON → we auto-map sheet
 *      names + column headers to the entity schema → POST as JSON to the
 *      preview endpoint.
 *   4. We render a per-entity preview table.
 *   5. User clicks "Confirm import" → POST same payload to commit endpoint
 *      → reload the page on success.
 *
 * The mapping is fully automatic in this release:
 *   - Sheet name match: case-insensitive, normalized (spaces/underscores/
 *     hyphens collapsed). "Session Types" → matches "session_types" entity.
 *   - Column header match: same normalization on header strings.
 * Sheets / columns that don't auto-match are listed in the preview as
 * warnings but don't block the import; their rows are simply ignored.
 * Manual mapping fallback UI lands in 0.9.2.
 */
( function () {
	'use strict';

	const DE = window.DigitoneEvents;
	if ( ! DE || typeof DE.api !== 'function' ) return;

	const SHEETJS_CDN = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';

	document.addEventListener( 'DOMContentLoaded', function () {
		const panel = document.querySelector( '[data-de-excel-panel]' );
		if ( ! panel ) return;
		initExcelPanel( panel );
	} );

	function initExcelPanel( panel ) {
		const activeEvent = panel.getAttribute( 'data-active-event' ) || '';
		if ( ! activeEvent ) return;

		const fileInput  = panel.querySelector( '[data-de-excel-file]' );
		const previewBtn = panel.querySelector( '[data-de-action="excel-preview"]' );
		const result     = panel.querySelector( '[data-de-excel-result]' );
		const summaryEl  = panel.querySelector( '[data-de-excel-summary]' );
		const rowsEl     = panel.querySelector( '[data-de-excel-rows]' );
		const errorsWrap = panel.querySelector( '[data-de-excel-errors]' );
		const errorsBody = panel.querySelector( '[data-de-excel-errors-body]' );
		const commitBtn  = panel.querySelector( '[data-de-action="excel-commit"]' );

		let lastPayload         = null;
		let lastSchema          = null;
		let lastReport          = null;
		let lastStructure       = null;  // cached parsed workbook (don't re-read on every mapping change)
		let lastFileRef         = null;  // sentinel — File object identity; reset structure when changed
		let mappingOverrides    = {};    // user choices: { sheetEntityMap, columnFieldMap }
		let mappingExplicitlyOk = false; // true after user clicks "Apply mapping & preview"

		if ( fileInput && previewBtn ) {
			fileInput.addEventListener( 'change', function () {
				previewBtn.disabled = ! ( fileInput.files && fileInput.files.length );
				// New file → wipe any mapping carried over from the previous one.
				mappingOverrides    = {};
				mappingExplicitlyOk = false;
				lastStructure       = null;
				lastFileRef         = null;
				hideMappingUI();
			} );
		}

		panel.addEventListener( 'click', function ( ev ) {
			const t = ev.target.closest( '[data-de-action]' );
			if ( ! t ) return;
			const action = t.getAttribute( 'data-de-action' );
			if ( action === 'excel-download-template' ) { ev.preventDefault(); downloadWorkbook( 'empty' ); }
			if ( action === 'excel-download-current' )  { ev.preventDefault(); downloadWorkbook( 'full' ); }
			if ( action === 'excel-preview' )           { ev.preventDefault(); runPreview(); }
			if ( action === 'excel-cancel' )            { ev.preventDefault(); resetUi(); }
			if ( action === 'excel-commit' )            { ev.preventDefault(); runCommit(); }
			if ( action === 'excel-danger-cancel' )     { ev.preventDefault(); closeDangerDialog(); }
			if ( action === 'excel-danger-proceed' )    { ev.preventDefault(); runCommitNow(); }
			if ( action === 'excel-mapping-apply' )     { ev.preventDefault(); applyMappingAndPreview(); }
			if ( action === 'excel-mapping-cancel' )    { ev.preventDefault(); hideMappingUI(); }
			if ( action === 'excel-mapping-toggle-cols' ) {
				ev.preventDefault();
				const row = t.closest( '.de-excel-map-sheet' );
				if ( row ) row.classList.toggle( 'is-expanded' );
			}
		} );

		/* Style the active mode pill + toggle label class.
		 * Selecting "Full overwrite" doesn't trigger anything destructive
		 * by itself — the warning + backup gate only appears at commit. */
		const modeRadios = panel.querySelectorAll( 'input[name="de-excel-mode"]' );
		const modeLabels = panel.querySelectorAll( '.de-excel-mode-option' );
		modeRadios.forEach( function ( r ) {
			r.addEventListener( 'change', function () {
				modeLabels.forEach( function ( lbl ) {
					lbl.classList.toggle( 'is-active', lbl.contains( r ) && r.checked );
				} );
				// Re-run preview if we already have one — counts change between modes.
				if ( lastPayload && ! result.hidden ) runPreview();
			} );
		} );

		/* Danger dialog wiring */
		const dangerDialog   = panel.querySelector( '[data-de-excel-danger]' );
		const dangerList     = panel.querySelector( '[data-de-danger-list]' );
		const dangerCheckbox = panel.querySelector( '[data-de-danger-checkbox]' );
		const dangerProceed  = panel.querySelector( '[data-de-action="excel-danger-proceed"]' );
		if ( dangerCheckbox && dangerProceed ) {
			dangerCheckbox.addEventListener( 'change', function () {
				dangerProceed.disabled = ! dangerCheckbox.checked;
			} );
		}
		function openDangerDialog( report ) {
			if ( ! dangerDialog ) return;
			// Populate the "will delete" summary list from the report
			if ( dangerList ) {
				dangerList.innerHTML = '';
				const results = report.results || {};
				Object.keys( results ).forEach( function ( ek ) {
					const r = results[ ek ];
					if ( ! r.deleted ) return;
					const li = document.createElement( 'li' );
					li.innerHTML = '<strong>' + r.deleted + '</strong> ' + escapeHtml( ( r.sheet || ek ).toLowerCase() ) + ' will be deleted';
					dangerList.appendChild( li );
				} );
				if ( ! dangerList.children.length ) {
					const li = document.createElement( 'li' );
					li.textContent = '(no existing data — workbook will be inserted as-is)';
					dangerList.appendChild( li );
				}
			}
			if ( dangerCheckbox ) dangerCheckbox.checked = false;
			if ( dangerProceed )  dangerProceed.disabled = true;
			if ( typeof dangerDialog.showModal === 'function' ) dangerDialog.showModal();
			else dangerDialog.setAttribute( 'open', '' );
		}
		function closeDangerDialog() {
			if ( ! dangerDialog ) return;
			if ( typeof dangerDialog.close === 'function' ) dangerDialog.close();
			else dangerDialog.removeAttribute( 'open' );
		}

		function currentMode() {
			const checked = panel.querySelector( 'input[name="de-excel-mode"]:checked' );
			return checked ? checked.value : 'incremental';
		}

		function resetUi() {
			result.hidden = true;
			rowsEl.innerHTML = '';
			summaryEl.innerHTML = '';
			errorsBody.innerHTML = '';
			errorsWrap.hidden = true;
			if ( fileInput ) fileInput.value = '';
			if ( previewBtn ) previewBtn.disabled = true;
			lastPayload         = null;
			lastSchema          = null;
			lastStructure       = null;
			lastFileRef         = null;
			mappingOverrides    = {};
			mappingExplicitlyOk = false;
			hideMappingUI();
		}

		/* ---- Download (template or current data) ---- */

		async function downloadWorkbook( mode ) {
			try {
				await loadSheetJs();
			} catch ( err ) {
				DE.feedback( 'Failed to load SheetJS: ' + err.message, 'error' );
				return;
			}

			DE.api( 'digitone_events_excel_snapshot', { event_id: activeEvent, mode: mode } )
				.then( function ( res ) {
					const filename = ( mode === 'empty' )
						? 'digitone-events_template.xlsx'
						: 'digitone-events_' + activeEvent + '_' + dateStamp() + '.xlsx';
					buildAndSaveWorkbook( res.data, res.schema, filename );
				} )
				.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
		}

		function buildAndSaveWorkbook( data, schema, filename ) {
			const XLSX = window.XLSX;
			const wb = XLSX.utils.book_new();

			// Walk the schema so sheets land in the canonical order with the
			// canonical column ordering, even if some sheets have zero rows.
			Object.keys( schema ).forEach( function ( entityKey ) {
				const def   = schema[ entityKey ];
				const sheet = def.sheet;
				const cols  = def.columns;
				const rows  = ( data && data[ sheet ] ) || [];

				const aoa = [ cols ];
				rows.forEach( function ( r ) {
					const line = [];
					cols.forEach( function ( c ) { line.push( r[ c ] != null ? r[ c ] : '' ); } );
					aoa.push( line );
				} );
				const ws = XLSX.utils.aoa_to_sheet( aoa );
				XLSX.utils.book_append_sheet( wb, ws, sheet.substring( 0, 31 ) /* Excel limit */ );
			} );

			XLSX.writeFile( wb, filename );
		}

		function dateStamp() {
			const d = new Date();
			const p = function ( n ) { return ( n < 10 ? '0' : '' ) + n; };
			return d.getFullYear() + p( d.getMonth() + 1 ) + p( d.getDate() ) + '-' + p( d.getHours() ) + p( d.getMinutes() );
		}

		/* ---- Preview ---- */

		async function runPreview( opts ) {
			opts = opts || {};
			const skipMappingCheck = !! opts.skipMappingCheck;

			if ( ! fileInput || ! fileInput.files || ! fileInput.files[ 0 ] ) return;
			previewBtn.disabled = true;
			previewBtn.textContent = 'Parsing…';

			try {
				await loadSheetJs();

				const file = fileInput.files[ 0 ];
				if ( file.size > 10 * 1024 * 1024 ) {
					throw new Error( 'File exceeds 10 MB.' );
				}

				// First time we read the file we cache the parsed workbook
				// (lastStructure) so the mapping UI can re-apply different
				// overrides without re-parsing. On a fresh file the cache
				// is invalidated below.
				if ( fileInput.files[ 0 ] !== lastFileRef ) {
					const buf = await file.arrayBuffer();
					const XLSX = window.XLSX;
					const wb = XLSX.read( buf, { type: 'array' } );
					lastStructure = extractStructure( wb );
					lastFileRef   = fileInput.files[ 0 ];
					mappingOverrides     = {}; // reset on new file
					mappingExplicitlyOk  = false;
				}

				// Need the entity schema to drive auto-mapping. Fetch it via
				// the snapshot endpoint with mode=empty (cheap, no data rows).
				if ( ! lastSchema ) {
					const meta = await DE.api( 'digitone_events_excel_snapshot', { event_id: activeEvent, mode: 'empty' } );
					lastSchema = meta.schema;
				}

				const mapping = applyMapping( lastStructure, lastSchema, mappingOverrides );
				lastPayload = mapping.payload;

				// Decide whether to show the mapping UI before going to
				// the preview. We show it if (a) the user hasn't already
				// approved the mapping in this session, AND (b) something
				// is unmapped (sheet with no entity, OR a mapped sheet has
				// at least one column the auto-detector couldn't place).
				const hasUnmappedSheet  = mapping.unmappedSheets.length > 0;
				const hasUnmappedColumn = Object.keys( mapping.sheetsByName ).some( function ( name ) {
					const info = mapping.sheetsByName[ name ];
					if ( ! info.chosenEk ) return false;
					return info.headers.some( function ( h, i ) { return ! info.colMap[ i ]; } );
				} );
				const needsMapping = ! skipMappingCheck
					&& ! mappingExplicitlyOk
					&& ( hasUnmappedSheet || hasUnmappedColumn );

				if ( needsMapping ) {
					showMappingUI( mapping );
					previewBtn.disabled = false;
					previewBtn.textContent = 'Preview import';
					return;
				}

				// Auto-mapped cleanly OR user has applied overrides.
				hideMappingUI();

				const res = await DE.api( 'digitone_events_excel_preview', {
					event_id: activeEvent,
					mode:     currentMode(),
					payload:  JSON.stringify( lastPayload ),
				} );

				renderReport( res, mappingWarnings( mapping, lastSchema ) );
				previewBtn.disabled = false;
				previewBtn.textContent = 'Preview import';
			} catch ( err ) {
				previewBtn.disabled = false;
				previewBtn.textContent = 'Preview import';
				DE.feedback( err.message || 'Preview failed.', 'error' );
			}
		}

		/* ---- Commit ----
		 * Incremental: just send. Full: open the danger dialog first,
		 * user has to download a backup (or tick the "I have one" box)
		 * before "Replace everything" enables. runCommitNow is the
		 * actual send, shared by both paths. */

		function runCommit() {
			if ( ! lastPayload ) { DE.feedback( 'Run preview first.', 'error' ); return; }
			if ( currentMode() === 'full' ) {
				openDangerDialog( lastReport || { results: {} } );
				return;
			}
			runCommitNow();
		}

		function runCommitNow() {
			if ( ! lastPayload ) return;
			closeDangerDialog();
			commitBtn.disabled = true;
			commitBtn.textContent = 'Importing…';

			DE.api( 'digitone_events_excel_commit', {
				event_id: activeEvent,
				mode:     currentMode(),
				payload:  JSON.stringify( lastPayload ),
			} )
				.then( function ( res ) {
					const t = res.totals || {};
					const parts = [];
					if ( t.deleted )  parts.push( 'deleted ' + t.deleted );
					parts.push( 'imported ' + ( t.inserted || 0 ) );
					if ( t.skipped )  parts.push( 'skipped ' + t.skipped );
					if ( t.errors )   parts.push( 'errors ' + t.errors );
					DE.feedback( parts.join( ', ' ) + '.', t.errors > 0 ? 'info' : 'success' );
					setTimeout( function () { window.location.reload(); }, 800 );
				} )
				.catch( function ( err ) {
					commitBtn.disabled = false;
					commitBtn.textContent = 'Confirm import';
					DE.feedback( err.message || 'Commit failed.', 'error' );
				} );
		}

		/* ---- Workbook structure extraction (0.9.5) ----
		 * Split the old monolithic autoMap into two passes:
		 *   1. extractStructure(wb) — read sheet names + headers + rows
		 *      once. Independent of schema or mapping.
		 *   2. applyMapping(structure, schema, overrides) — turn the
		 *      structure into the JSON payload using auto-detection
		 *      plus the user's override choices.
		 * The two-pass shape is what makes the manual-mapping UI work:
		 * we present the structure to the user, they pick overrides,
		 * applyMapping re-runs with their choices and we go to preview.
		 */
		function extractStructure( wb ) {
			const sheets = [];
			wb.SheetNames.forEach( function ( sheetName ) {
				const ws    = wb.Sheets[ sheetName ];
				const aoa   = window.XLSX.utils.sheet_to_json( ws, { header: 1, defval: '' } );
				if ( ! aoa || ! aoa.length ) return;
				const headers = ( aoa[ 0 ] || [] ).map( function ( h ) { return String( h == null ? '' : h ); } );
				const rows    = aoa.slice( 1 );
				sheets.push( { name: sheetName, headers: headers, rows: rows } );
			} );
			return { sheets: sheets };
		}

		/**
		 * @param structure {sheets:[{name, headers, rows}]}
		 * @param schema    entity_key → {sheet, columns, ...}
		 * @param overrides {sheetEntityMap:{sheetName→entity_key|'_skip'},
		 *                   columnFieldMap:{sheetName:{colIdx→field|'_skip'}}}
		 *                  Either map can be missing keys — falls back to
		 *                  the auto-detected value.
		 * @returns {payload, autoSheet, autoColumn, unmappedSheets, sheetsByName}
		 *   - payload: backend-shaped JSON (entity_sheet → rows[])
		 *   - autoSheet[sheetName] = entity_key | null  (best guess)
		 *   - autoColumn[sheetName][colIdx] = field | null
		 *   - unmappedSheets: array of sheet names that have no entity
		 *   - sheetsByName: same structure but indexed for the UI
		 */
		function applyMapping( structure, schema, overrides ) {
			overrides = overrides || {};
			const sheetEntityMap = overrides.sheetEntityMap || {};
			const columnFieldMap = overrides.columnFieldMap || {};

			// Auto sheet-name lookup: norm(sheetName) → entity_key
			const sheetIndex = {};
			Object.keys( schema ).forEach( function ( ek ) {
				sheetIndex[ normalize( schema[ ek ].sheet ) ] = ek;
			} );

			const payload         = {};
			const autoSheet       = {};
			const autoColumn      = {};
			const unmappedSheets  = [];
			const sheetsByName    = {};

			structure.sheets.forEach( function ( sheet ) {
				const sheetName = sheet.name;
				const headers   = sheet.headers;
				const rows      = sheet.rows;

				// Auto-detect entity for the sheet
				const autoEk = sheetIndex[ normalize( sheetName ) ] || null;
				autoSheet[ sheetName ] = autoEk;

				// Apply override if present (_skip = explicitly skip this sheet)
				let ek = ( sheetName in sheetEntityMap ) ? sheetEntityMap[ sheetName ] : autoEk;
				if ( ek === '_skip' ) ek = null;
				if ( ek && ! schema[ ek ] ) ek = null;

				sheetsByName[ sheetName ] = {
					autoEk:    autoEk,
					chosenEk:  ek,
					headers:   headers,
					rowCount:  rows.length,
				};

				if ( ! ek ) {
					unmappedSheets.push( sheetName );
					return;
				}

				const def = schema[ ek ];
				// Auto column-header lookup for THIS entity
				const wantedNk = {};
				def.columns.forEach( function ( f ) { wantedNk[ normalize( f ) ] = f; } );

				const overrideCols = columnFieldMap[ sheetName ] || {};

				autoColumn[ sheetName ] = {};
				const colMap = {}; // chosen col index → field name (after overrides)
				headers.forEach( function ( h, i ) {
					const autoField = wantedNk[ normalize( h ) ] || null;
					autoColumn[ sheetName ][ i ] = autoField;
					let field = ( i in overrideCols ) ? overrideCols[ i ] : autoField;
					if ( field === '_skip' ) field = null;
					if ( field && def.columns.indexOf( field ) === -1 ) field = null;
					if ( field ) colMap[ i ] = field;
				} );
				sheetsByName[ sheetName ].autoColumn = autoColumn[ sheetName ];
				sheetsByName[ sheetName ].colMap     = colMap;

				// Build entity rows
				const out = [];
				rows.forEach( function ( r ) {
					if ( ! r || ! r.length ) return;
					const obj = {};
					let any = false;
					Object.keys( colMap ).forEach( function ( i ) {
						const field = colMap[ i ];
						const val   = r[ i ];
						if ( val !== '' && val !== null && val !== undefined ) any = true;
						obj[ field ] = ( val === null || val === undefined ) ? '' : val;
					} );
					if ( any ) out.push( obj );
				} );
				payload[ def.sheet ] = out;
			} );

			return {
				payload:        payload,
				autoSheet:      autoSheet,
				autoColumn:     autoColumn,
				unmappedSheets: unmappedSheets,
				sheetsByName:   sheetsByName,
			};
		}

		/* ---- Manual mapping UI (0.9.5) ----
		 *
		 * Built imperatively into a single container so we can re-render
		 * after every user choice. Layout:
		 *
		 *   ┌── per workbook sheet ──┐
		 *   │ "Speakers Q1"          │
		 *   │   Maps to: [Speakers ▼]│      ← entity dropdown
		 *   │   [▸ Configure columns]│      ← expander
		 *   │     "First Name"  → [first_name ▼]
		 *   │     "Surname"     → [ — pick — ▼]
		 *   │     "Email"       → [ — ignore — ▼]
		 *   └────────────────────────┘
		 *
		 * "Apply mapping & preview" sets `mappingExplicitlyOk = true` so
		 * the next runPreview() call skips the gate and goes to preview.
		 */
		function showMappingUI( mapping ) {
			let container = panel.querySelector( '[data-de-mapping-ui]' );
			if ( ! container ) {
				container = document.createElement( 'div' );
				container.setAttribute( 'data-de-mapping-ui', '' );
				container.className = 'de-excel-mapping';
				// Insert after the upload row (.de-excel-row) so it appears
				// above the preview area.
				const uploadRow = panel.querySelector( '.de-excel-row' );
				if ( uploadRow ) uploadRow.insertAdjacentElement( 'afterend', container );
				else panel.appendChild( container );
			}
			container.innerHTML = '';
			container.hidden = false;

			const intro = document.createElement( 'div' );
			intro.className = 'de-excel-mapping-intro';
			intro.innerHTML =
				'<h3>Map your workbook</h3>' +
				'<p>Some sheet names or column headers in your file don\'t match the expected schema. Pick what each one should map to. Anything left as "— skip —" will be ignored.</p>';
			container.appendChild( intro );

			Object.keys( mapping.sheetsByName ).forEach( function ( sheetName ) {
				const info = mapping.sheetsByName[ sheetName ];
				const row  = document.createElement( 'div' );
				row.className = 'de-excel-map-sheet';
				if ( info.chosenEk ) row.classList.add( 'is-matched' );
				else                 row.classList.add( 'is-unmatched' );

				// Header line: sheet name + entity dropdown + row count
				const head = document.createElement( 'div' );
				head.className = 'de-excel-map-sheet-head';
				head.innerHTML =
					'<div class="de-excel-map-sheet-label"><strong>' + escapeHtml( sheetName ) + '</strong>' +
					' <span class="de-excel-map-sheet-rows">(' + info.rowCount + ' rows)</span></div>';

				const entitySel = document.createElement( 'select' );
				entitySel.className = 'de-excel-map-entity';
				entitySel.setAttribute( 'data-sheet', sheetName );
				const optSkip = document.createElement( 'option' );
				optSkip.value = '_skip';
				optSkip.textContent = '— skip this sheet —';
				entitySel.appendChild( optSkip );
				Object.keys( lastSchema ).forEach( function ( ek ) {
					const o = document.createElement( 'option' );
					o.value = ek;
					o.textContent = lastSchema[ ek ].sheet + ' (' + ek + ')';
					if ( info.chosenEk === ek ) o.selected = true;
					entitySel.appendChild( o );
				} );
				if ( ! info.chosenEk ) optSkip.selected = true;

				const entityWrap = document.createElement( 'div' );
				entityWrap.className = 'de-excel-map-entity-wrap';
				entityWrap.appendChild( document.createTextNode( 'Maps to: ' ) );
				entityWrap.appendChild( entitySel );
				head.appendChild( entityWrap );

				// Expander for columns (only useful when an entity is chosen).
				const toggleBtn = document.createElement( 'button' );
				toggleBtn.type = 'button';
				toggleBtn.className = 'button-link de-excel-map-toggle';
				toggleBtn.setAttribute( 'data-de-action', 'excel-mapping-toggle-cols' );
				toggleBtn.textContent = 'Configure columns';
				head.appendChild( toggleBtn );
				row.appendChild( head );

				const cols = document.createElement( 'div' );
				cols.className = 'de-excel-map-cols';
				row.appendChild( cols );

				function renderCols() {
					cols.innerHTML = '';
					const ek = entitySel.value;
					if ( ! ek || ek === '_skip' ) {
						cols.innerHTML = '<p class="de-excel-map-cols-empty">Sheet skipped — no columns to map.</p>';
						return;
					}
					const def = lastSchema[ ek ];
					const overridesForSheet = ( mappingOverrides.columnFieldMap && mappingOverrides.columnFieldMap[ sheetName ] ) || {};
					const autoFor = ( info.autoEk === ek ) ? info.autoColumn : null;
					const wantedNk = {};
					def.columns.forEach( function ( f ) { wantedNk[ normalize( f ) ] = f; } );

					const table = document.createElement( 'table' );
					table.className = 'de-excel-map-cols-table';
					table.innerHTML = '<thead><tr><th>Column in your sheet</th><th>Maps to field</th></tr></thead>';
					const tbody = document.createElement( 'tbody' );

					info.headers.forEach( function ( h, i ) {
						const tr = document.createElement( 'tr' );
						// Resolve current value: override > auto-for-current-entity > re-run wantedNk
						let chosen = ( i in overridesForSheet ) ? overridesForSheet[ i ] : null;
						if ( chosen == null ) {
							chosen = ( autoFor && autoFor[ i ] ) ? autoFor[ i ] : ( wantedNk[ normalize( h ) ] || null );
						}
						const sel = document.createElement( 'select' );
						sel.className = 'de-excel-map-col';
						sel.setAttribute( 'data-sheet', sheetName );
						sel.setAttribute( 'data-col', String( i ) );
						const sk = document.createElement( 'option' );
						sk.value = '_skip';
						sk.textContent = '— ignore this column —';
						sel.appendChild( sk );
						def.columns.forEach( function ( f ) {
							const o = document.createElement( 'option' );
							o.value = f;
							o.textContent = f;
							if ( chosen === f ) o.selected = true;
							sel.appendChild( o );
						} );
						if ( chosen === null || chosen === '_skip' ) sk.selected = true;

						const td1 = document.createElement( 'td' );
						td1.innerHTML = '<strong>' + escapeHtml( h ) + '</strong>';
						if ( ! chosen ) td1.innerHTML += ' <span class="de-excel-map-unmatched">unmapped</span>';
						const td2 = document.createElement( 'td' );
						td2.appendChild( sel );
						tr.appendChild( td1 );
						tr.appendChild( td2 );
						tbody.appendChild( tr );
					} );

					table.appendChild( tbody );
					cols.appendChild( table );
				}
				renderCols();
				entitySel.addEventListener( 'change', function () {
					row.classList.toggle( 'is-matched',    entitySel.value !== '_skip' );
					row.classList.toggle( 'is-unmatched',  entitySel.value === '_skip' );
					row.classList.add( 'is-expanded' );
					renderCols();
				} );

				// Auto-expand if anything is unmapped on this sheet
				const hasUnmapped = info.chosenEk
					? info.headers.some( function ( h, i ) { return ! info.colMap[ i ]; } )
					: true;
				if ( hasUnmapped ) row.classList.add( 'is-expanded' );

				container.appendChild( row );
			} );

			const actions = document.createElement( 'div' );
			actions.className = 'de-excel-mapping-actions';
			actions.innerHTML =
				'<button type="button" class="button" data-de-action="excel-mapping-cancel">Cancel</button> ' +
				'<button type="button" class="button button-primary" data-de-action="excel-mapping-apply">Apply mapping &amp; preview</button>';
			container.appendChild( actions );

			container.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}

		function hideMappingUI() {
			const container = panel.querySelector( '[data-de-mapping-ui]' );
			if ( container ) {
				container.hidden = true;
				container.innerHTML = '';
			}
		}

		function applyMappingAndPreview() {
			const container = panel.querySelector( '[data-de-mapping-ui]' );
			if ( ! container ) return;

			const sheetEntityMap = {};
			container.querySelectorAll( '.de-excel-map-entity' ).forEach( function ( sel ) {
				sheetEntityMap[ sel.getAttribute( 'data-sheet' ) ] = sel.value;
			} );

			const columnFieldMap = {};
			container.querySelectorAll( '.de-excel-map-col' ).forEach( function ( sel ) {
				const sheet = sel.getAttribute( 'data-sheet' );
				const idx   = parseInt( sel.getAttribute( 'data-col' ), 10 );
				if ( ! columnFieldMap[ sheet ] ) columnFieldMap[ sheet ] = {};
				columnFieldMap[ sheet ][ idx ] = sel.value;
			} );

			mappingOverrides    = { sheetEntityMap: sheetEntityMap, columnFieldMap: columnFieldMap };
			mappingExplicitlyOk = true;
			hideMappingUI();
			runPreview( { skipMappingCheck: true } );
		}

		/* Compact warnings for the preview-summary line — only complains
		 * about things the user explicitly chose to skip (or that didn't
		 * map and weren't overridden). */
		function mappingWarnings( mapping, schema ) {
			const out = [];
			Object.keys( mapping.sheetsByName ).forEach( function ( name ) {
				const info = mapping.sheetsByName[ name ];
				if ( ! info.chosenEk ) {
					out.push( 'Sheet "' + name + '" not mapped — ignored.' );
					return;
				}
				const def = schema[ info.chosenEk ];
				const unmapped = info.headers
					.map( function ( h, i ) { return info.colMap[ i ] ? null : h; } )
					.filter( Boolean );
				if ( unmapped.length ) {
					out.push( 'Sheet "' + name + '" → ' + def.sheet + ' — columns ignored: ' + unmapped.join( ', ' ) );
				}
			} );
			return out;
		}

		function normalize( s ) {
			return String( s == null ? '' : s ).trim().toLowerCase()
				.replace( /[-_\s]+/g, ' ' );
		}

		/* ---- Render preview report ---- */

		function renderReport( res, warnings ) {
			lastReport = res;
			const isFull = ( res.mode === 'full' );

			result.hidden = false;
			rowsEl.innerHTML = '';
			errorsBody.innerHTML = '';

			// Toggle the "Will delete" header column based on mode.
			const deleteHeader = panel.querySelector( '.de-excel-col-delete' );
			if ( deleteHeader ) deleteHeader.hidden = ! isFull;

			const totals = res.totals || { inserted: 0, skipped: 0, errors: 0, deleted: 0 };
			let cls = 'notice notice-success';
			if ( totals.errors > 0 )       cls = 'notice notice-warning';
			if ( totals.inserted === 0 && totals.errors > 0 ) cls = 'notice notice-error';
			if ( isFull )                   cls = 'notice notice-warning';

			let summary = '<strong>' + totals.inserted + '</strong> will be inserted';
			if ( isFull ) {
				summary += ', <strong>' + ( totals.deleted || 0 ) + '</strong> existing rows will be deleted';
			} else {
				summary += ', <strong>' + totals.skipped + '</strong> will be skipped (already exist)';
			}
			summary += ', <strong>' + totals.errors + '</strong> errors.';

			let html = '<div class="' + cls + ' inline" style="padding:10px;margin:0 0 12px"><p style="margin:0">' + summary + '</p></div>';
			if ( warnings && warnings.length ) {
				html += '<div class="notice notice-warning inline" style="padding:8px 10px;margin:0 0 12px"><p style="margin:0;font-size:12px">' +
					warnings.map( escapeHtml ).join( '<br>' ) + '</p></div>';
			}
			summaryEl.innerHTML = html;

			const results = res.results || {};
			Object.keys( results ).forEach( function ( ek ) {
				const r = results[ ek ];
				let row = '<td>' + escapeHtml( r.sheet || ek ) + '</td>';
				if ( isFull ) {
					row += '<td class="de-col-num">' + ( r.deleted || 0 ) + '</td>';
				}
				row += '<td class="de-col-num">' + ( r.inserted || 0 ) + '</td>' +
					'<td class="de-col-num">' + ( r.skipped || 0 ) + '</td>' +
					'<td class="de-col-num">' + ( r.errors ? r.errors.length : 0 ) + '</td>';
				const tr = document.createElement( 'tr' );
				tr.innerHTML = row;
				rowsEl.appendChild( tr );

				( r.errors || [] ).forEach( function ( e ) {
					const er = document.createElement( 'tr' );
					er.innerHTML =
						'<td>' + escapeHtml( r.sheet || ek ) + '</td>' +
						'<td class="de-col-num">' + ( e.row || '' ) + '</td>' +
						'<td>' + escapeHtml( e.reason || '' ) + '</td>';
					errorsBody.appendChild( er );
				} );
			} );

			errorsWrap.hidden = errorsBody.children.length === 0;

			if ( commitBtn ) {
				commitBtn.disabled = ( totals.inserted === 0 && ! isFull );
				commitBtn.textContent = isFull ? 'Replace event data…' : 'Confirm import';
				commitBtn.classList.toggle( 'button-danger', isFull );
			}

			result.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}

		function escapeHtml( s ) {
			return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( m ) {
				return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[ m ];
			} );
		}
	}

	/* ---- SheetJS loader ---- */

	function loadSheetJs() {
		return new Promise( function ( resolve, reject ) {
			if ( typeof window.XLSX !== 'undefined' ) { resolve(); return; }
			const s = document.createElement( 'script' );
			s.src = SHEETJS_CDN;
			s.async = true;
			s.onload = function () {
				if ( typeof window.XLSX !== 'undefined' ) resolve();
				else reject( new Error( 'XLSX global missing after load' ) );
			};
			s.onerror = function () { reject( new Error( 'Failed to load ' + SHEETJS_CDN ) ); };
			document.head.appendChild( s );
		} );
	}
} )();
