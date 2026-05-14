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

		let lastPayload = null;
		let lastSchema  = null;

		if ( fileInput && previewBtn ) {
			fileInput.addEventListener( 'change', function () {
				previewBtn.disabled = ! ( fileInput.files && fileInput.files.length );
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
		} );

		function resetUi() {
			result.hidden = true;
			rowsEl.innerHTML = '';
			summaryEl.innerHTML = '';
			errorsBody.innerHTML = '';
			errorsWrap.hidden = true;
			if ( fileInput ) fileInput.value = '';
			if ( previewBtn ) previewBtn.disabled = true;
			lastPayload = null;
			lastSchema  = null;
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

		async function runPreview() {
			if ( ! fileInput || ! fileInput.files || ! fileInput.files[ 0 ] ) return;
			previewBtn.disabled = true;
			previewBtn.textContent = 'Parsing…';

			try {
				await loadSheetJs();

				const file = fileInput.files[ 0 ];
				if ( file.size > 10 * 1024 * 1024 ) {
					throw new Error( 'File exceeds 10 MB.' );
				}

				const buf = await file.arrayBuffer();
				const XLSX = window.XLSX;
				const wb = XLSX.read( buf, { type: 'array' } );

				// Need the entity schema to drive auto-mapping. Fetch it via
				// the snapshot endpoint with mode=empty (cheap, no data rows).
				const meta = await DE.api( 'digitone_events_excel_snapshot', { event_id: activeEvent, mode: 'empty' } );
				lastSchema = meta.schema;

				const { payload, warnings } = autoMap( wb, lastSchema );
				lastPayload = payload;

				const res = await DE.api( 'digitone_events_excel_preview', {
					event_id: activeEvent,
					mode:     'incremental',
					payload:  JSON.stringify( payload ),
				} );

				renderReport( res, warnings );
				previewBtn.disabled = false;
				previewBtn.textContent = 'Preview import';
			} catch ( err ) {
				previewBtn.disabled = false;
				previewBtn.textContent = 'Preview import';
				DE.feedback( err.message || 'Preview failed.', 'error' );
			}
		}

		/* ---- Commit ---- */

		function runCommit() {
			if ( ! lastPayload ) { DE.feedback( 'Run preview first.', 'error' ); return; }
			commitBtn.disabled = true;
			commitBtn.textContent = 'Importing…';

			DE.api( 'digitone_events_excel_commit', {
				event_id: activeEvent,
				mode:     'incremental',
				payload:  JSON.stringify( lastPayload ),
			} )
				.then( function ( res ) {
					DE.feedback(
						'Imported ' + res.totals.inserted + ', skipped ' + res.totals.skipped + ', errors ' + res.totals.errors + '.',
						res.totals.errors > 0 ? 'info' : 'success'
					);
					setTimeout( function () { window.location.reload(); }, 800 );
				} )
				.catch( function ( err ) {
					commitBtn.disabled = false;
					commitBtn.textContent = 'Confirm import';
					DE.feedback( err.message || 'Commit failed.', 'error' );
				} );
		}

		/* ---- Auto-map workbook → JSON shape that backend expects ---- */

		function autoMap( wb, schema ) {
			const payload  = {};
			const warnings = [];

			// Build canonical map: norm(sheetName) → entity_key
			const sheetIndex = {}; // normalized → entity_key
			Object.keys( schema ).forEach( function ( ek ) {
				sheetIndex[ normalize( schema[ ek ].sheet ) ] = ek;
			} );

			wb.SheetNames.forEach( function ( sheetName ) {
				const ws    = wb.Sheets[ sheetName ];
				const aoa   = window.XLSX.utils.sheet_to_json( ws, { header: 1, defval: '' } );
				if ( ! aoa || ! aoa.length ) return;

				const headers = ( aoa[ 0 ] || [] ).map( function ( h ) { return String( h ); } );
				const rows    = aoa.slice( 1 );

				const ek = sheetIndex[ normalize( sheetName ) ];
				if ( ! ek ) {
					warnings.push( 'Sheet "' + sheetName + '" did not match any known entity — ignored.' );
					return;
				}
				const def = schema[ ek ];

				// Column header → field name map
				const wanted   = def.columns;
				const wantedNk = {};
				wanted.forEach( function ( f ) { wantedNk[ normalize( f ) ] = f; } );

				const colMap = {}; // column index → field name
				headers.forEach( function ( h, i ) {
					const norm = normalize( h );
					if ( wantedNk[ norm ] ) {
						colMap[ i ] = wantedNk[ norm ];
					}
				} );

				const unmappedCols = headers.filter( function ( h, i ) { return ! colMap[ i ]; } );
				if ( unmappedCols.length ) {
					warnings.push( 'Sheet "' + sheetName + '" — columns ignored: ' + unmappedCols.join( ', ' ) );
				}

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

			return { payload: payload, warnings: warnings };
		}

		function normalize( s ) {
			return String( s == null ? '' : s ).trim().toLowerCase()
				.replace( /[-_\s]+/g, ' ' );
		}

		/* ---- Render preview report ---- */

		function renderReport( res, warnings ) {
			result.hidden = false;
			rowsEl.innerHTML = '';
			errorsBody.innerHTML = '';

			const totals = res.totals || { inserted: 0, skipped: 0, errors: 0 };
			let cls = 'notice notice-success';
			if ( totals.errors > 0 )       cls = 'notice notice-warning';
			if ( totals.inserted === 0 && totals.errors > 0 ) cls = 'notice notice-error';

			let html = '<div class="' + cls + ' inline" style="padding:10px;margin:0 0 12px">' +
				'<p style="margin:0"><strong>' + totals.inserted + '</strong> will be inserted, ' +
				'<strong>' + totals.skipped + '</strong> will be skipped (already exist), ' +
				'<strong>' + totals.errors + '</strong> errors.</p></div>';
			if ( warnings && warnings.length ) {
				html += '<div class="notice notice-warning inline" style="padding:8px 10px;margin:0 0 12px"><p style="margin:0;font-size:12px">' +
					warnings.map( escapeHtml ).join( '<br>' ) + '</p></div>';
			}
			summaryEl.innerHTML = html;

			const results = res.results || {};
			Object.keys( results ).forEach( function ( ek ) {
				const r = results[ ek ];
				const tr = document.createElement( 'tr' );
				tr.innerHTML =
					'<td>' + escapeHtml( r.sheet || ek ) + '</td>' +
					'<td class="de-col-num">' + r.inserted + '</td>' +
					'<td class="de-col-num">' + r.skipped + '</td>' +
					'<td class="de-col-num">' + ( r.errors ? r.errors.length : 0 ) + '</td>';
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
				commitBtn.disabled = totals.inserted === 0;
				commitBtn.textContent = 'Confirm import';
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
