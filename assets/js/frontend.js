/**
 * DigitOne Events — Frontend behaviour:
 *   - Day switcher (always defaults to Day 1, URL ?de-day=N / #de-day-N overrides)
 *   - Browser tab title swap (event-name — Programme)
 *   - Session detail modal (click on a block → modal with full info + add-to-calendar)
 */
( function () {
	'use strict';

	function init( schedule ) {
		setupDaySwitcher( schedule );
		swapTitle( schedule );
		setupSessionModal( schedule );
		setupFilters( schedule );
		setupExportPdf( schedule );
	}

	/* ============================================================
	 * Day switcher
	 * ============================================================ */
	function setupDaySwitcher( schedule ) {
		const buttons = schedule.querySelectorAll( '.de-fe-day-nav-item' );
		const panels  = schedule.querySelectorAll( '.de-fe-schedule-day' );
		if ( ! buttons.length || ! panels.length ) return;

		function show( idx ) {
			panels.forEach( function ( p ) {
				p.classList.remove( 'is-active' );
				p.setAttribute( 'aria-hidden', 'true' );
			} );
			buttons.forEach( function ( b ) {
				b.classList.remove( 'is-active' );
				b.setAttribute( 'aria-selected', 'false' );
			} );
			if ( panels[ idx ] ) {
				panels[ idx ].classList.add( 'is-active' );
				panels[ idx ].setAttribute( 'aria-hidden', 'false' );
			}
			if ( buttons[ idx ] ) {
				buttons[ idx ].classList.add( 'is-active' );
				buttons[ idx ].setAttribute( 'aria-selected', 'true' );
			}
		}

		buttons.forEach( function ( btn, idx ) {
			btn.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				show( idx );
			} );
		} );

		let selectedIdx = 0;
		try {
			const url = new URL( window.location.href );
			const queryDay = parseInt( url.searchParams.get( 'de-day' ), 10 );
			if ( ! isNaN( queryDay ) && queryDay >= 0 && queryDay < panels.length ) {
				selectedIdx = queryDay;
			} else {
				const hashMatch = window.location.hash.match( /^#de-day-(\d+)$/ );
				if ( hashMatch ) {
					const idx = parseInt( hashMatch[1], 10 );
					if ( idx >= 0 && idx < panels.length ) selectedIdx = idx;
				}
			}
		} catch ( e ) { /* stay on 0 */ }

		show( selectedIdx );
	}

	/* ============================================================
	 * Tab title swap
	 * ============================================================ */
	function swapTitle( schedule ) {
		const eventName = schedule.getAttribute( 'data-event-name' );
		if ( ! eventName ) return;
		let suffix = schedule.getAttribute( 'data-title-suffix' );
		if ( suffix === null ) suffix = 'Programme';
		if ( suffix === '' ) return;
		document.title = eventName + ' — ' + suffix;
	}

	/* ============================================================
	 * Session detail modal
	 * ============================================================ */
	function setupSessionModal( schedule ) {
		const modal = schedule.querySelector( '.de-fe-session-modal' );
		const body  = modal ? modal.querySelector( '.de-fe-modal-body' ) : null;
		const dataScript = schedule.querySelector( '.de-fe-session-data' );
		if ( ! modal || ! body || ! dataScript ) return;

		let dataMap = {};
		try {
			dataMap = JSON.parse( dataScript.textContent || '{}' );
		} catch ( e ) {
			console.error( '[DigitOne Events] Failed to parse session data:', e );
			return;
		}

		let previousFocus = null;

		function open( sessionId ) {
			const session = dataMap[ sessionId ];
			if ( ! session ) return;
			body.innerHTML = renderModal( session );
			previousFocus = document.activeElement;
			modal.hidden = false;
			modal.setAttribute( 'aria-hidden', 'false' );
			document.body.classList.add( 'de-fe-modal-open' );
			// Focus close button for accessibility
			const closeBtn = modal.querySelector( '.de-fe-modal-close' );
			if ( closeBtn ) closeBtn.focus();
		}

		function close() {
			modal.hidden = true;
			modal.setAttribute( 'aria-hidden', 'true' );
			body.innerHTML = '';
			document.body.classList.remove( 'de-fe-modal-open' );
			if ( previousFocus && typeof previousFocus.focus === 'function' ) {
				previousFocus.focus();
			}
		}

		// Click on a session block (desktop grid) OR mobile-item → open modal
		schedule.addEventListener( 'click', function ( ev ) {
			const target = ev.target.closest( '.de-fe-block, .de-fe-mobile-item' );
			if ( ! target || target.classList.contains( 'is-break' ) ) return;
			const sid = target.getAttribute( 'data-session-id' );
			if ( ! sid ) return;
			ev.preventDefault();
			open( sid );
		} );

		// Enter / Space on a focused block/mobile-item → open
		schedule.addEventListener( 'keydown', function ( ev ) {
			const target = ev.target.closest( '.de-fe-block, .de-fe-mobile-item' );
			if ( ! target || target.classList.contains( 'is-break' ) ) return;
			if ( ev.key !== 'Enter' && ev.key !== ' ' ) return;
			ev.preventDefault();
			const sid = target.getAttribute( 'data-session-id' );
			if ( sid ) open( sid );
		} );

		// Close button / backdrop click
		modal.addEventListener( 'click', function ( ev ) {
			if ( ev.target.hasAttribute( 'data-de-modal-close' ) ) {
				close();
			}
		} );

		// ESC key closes
		document.addEventListener( 'keydown', function ( ev ) {
			if ( ev.key === 'Escape' && ! modal.hidden ) close();
		} );
	}

	function renderModal( s ) {
		const time = ( s.start_time || '' ) + ( s.end_time ? ' – ' + s.end_time : '' );
		const venue = [ s.venue_name, s.sub_venue_name ].filter( Boolean ).join( ' / ' );
		const typeLabel = [ s.type_icon, s.type_name ].filter( Boolean ).join( ' ' );

		let html = '';

		// Header section
		if ( typeLabel ) {
			html += '<div class="de-fe-modal-type" style="color:' + escapeAttr( s.type_color || '#6b7280' ) + '">' + escapeHtml( typeLabel ) + '</div>';
		}
		html += '<h2 class="de-fe-modal-title">' + escapeHtml( s.title || '' ) + '</h2>';
		html += '<div class="de-fe-modal-meta">';
		if ( time )  html += '<span class="de-fe-modal-time">' + escapeHtml( time )  + '</span>';
		if ( venue ) html += '<span class="de-fe-modal-venue">' + escapeHtml( venue ) + '</span>';
		html += '</div>';

		// Add to calendar
		if ( s.ics_url ) {
			html += '<a class="de-fe-modal-cal" href="' + escapeAttr( s.ics_url ) + '" download>'
				+ '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
				+ '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>'
				+ '<line x1="16" y1="2" x2="16" y2="6"></line>'
				+ '<line x1="8" y1="2" x2="8" y2="6"></line>'
				+ '<line x1="3" y1="10" x2="21" y2="10"></line>'
				+ '</svg> Add to calendar</a>';
		}

		// Description
		if ( s.description ) {
			html += '<div class="de-fe-modal-description">' + s.description + '</div>';
		}

		// Speakers
		if ( s.speakers && s.speakers.length ) {
			html += '<div class="de-fe-modal-speakers">';
			html += '<h3 class="de-fe-modal-section-title">Speakers</h3>';
			html += '<ul class="de-fe-modal-speakers-list">';
			s.speakers.forEach( function ( sp ) {
				html += '<li class="de-fe-modal-speaker">';
				if ( sp.photo_url ) {
					html += '<img class="de-fe-modal-speaker-photo" src="' + escapeAttr( sp.photo_url ) + '" alt="' + escapeAttr( sp.name || '' ) + '">';
				} else {
					html += '<div class="de-fe-modal-speaker-photo de-fe-modal-speaker-photo-empty">' + escapeHtml( ( sp.name || '?' ).charAt( 0 ) ) + '</div>';
				}
				html += '<div class="de-fe-modal-speaker-body">';
				html += '<div class="de-fe-modal-speaker-name">' + escapeHtml( sp.name || '' ) + '</div>';
				if ( sp.role_name ) {
					html += '<span class="de-fe-modal-speaker-role" style="background:' + escapeAttr( sp.role_color || '#6b7280' ) + '">' + escapeHtml( sp.role_name ) + '</span>';
				}
				if ( sp.bio ) {
					html += '<div class="de-fe-modal-speaker-bio">' + escapeHtml( sp.bio ) + '</div>';
				}
				html += '</div></li>';
			} );
			html += '</ul></div>';
		}

		return html;
	}

	function escapeHtml( s ) {
		return String( s ).replace( /[&<>"']/g, function ( m ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ m ];
		} );
	}
	function escapeAttr( s ) {
		return escapeHtml( s );
	}

	/* ============================================================
	 * Filters — composes 5 independent filter dimensions:
	 *   - currentSearch   (free text against title + speakers + venue + type)
	 *   - currentTypeId   (session type)
	 *   - currentSpeakerId (one speaker)
	 *   - currentPrimary  (primary venue) and currentSub (sub-venue / hall)
	 *
	 * All active filters AND-combine: a block is visible only if every active
	 * dimension matches. Column layout is driven by venue filter only — other
	 * filters affect block visibility within the existing columns.
	 * ============================================================ */

	/* ============================================================
	 * Export to PDF button — sets a friendly document.title so the
	 * browser's "Save as PDF" dialog suggests a sensible filename,
	 * then triggers window.print(). Title is restored after.
	 * ============================================================ */
	/* ============================================================
	 * Export to PDF — client-side PDF generation (v0.8.6).
	 *
	 * html2pdf.js uses html2canvas internally for its own pagebreak
	 * handling, and that overrides any `onclone` we pass through, so
	 * we can't rely on it. Instead we clone the schedule directly,
	 * mutate the clone in our own off-screen wrapper, and hand the
	 * already-prepared clone to html2pdf. The user's live page is
	 * never touched — wrapper sits at left:-99999px the whole time.
	 *
	 * Works because v0.8.5 moved the `.de-fe-print-*` styling rules
	 * out of @media print to global scope: html2canvas can read them
	 * via the normal cascade, without any print-context magic.
	 *
	 * Filters on screen don't affect the PDF — the print tables are
	 * server-rendered from the full event data and contain every
	 * session regardless of UI filter state.
	 * ============================================================ */
	const HTML2PDF_CDN = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';

	function setupExportPdf( schedule ) {
		const btn = schedule.querySelector( '[data-de-export-pdf]' );
		if ( ! btn ) return;

		const title = schedule.querySelector( '.de-fe-title' );
		const eventName = title ? title.textContent.trim() : 'Programme';

		btn.addEventListener( 'click', async function () {
			const original = btn.innerHTML;
			btn.disabled = true;
			btn.innerHTML = '<span class="de-fe-spinner" aria-hidden="true"></span> Generating PDF…';

			try {
				await generatePdf( schedule, eventName );
			} catch ( err ) {
				// eslint-disable-next-line no-console
				console.error( '[digitone-events] PDF generation failed:', err );
				window.alert( 'PDF generation failed. Check the browser console for details.' );
			} finally {
				btn.disabled = false;
				btn.innerHTML = original;
			}
		} );
	}

	function loadScriptOnce( src ) {
		return new Promise( function ( resolve, reject ) {
			if ( document.querySelector( 'script[data-de-pdf-lib="1"]' ) ) {
				resolve();
				return;
			}
			const s = document.createElement( 'script' );
			s.src = src;
			s.async = true;
			s.setAttribute( 'data-de-pdf-lib', '1' );
			s.onload = function () { resolve(); };
			s.onerror = function () { reject( new Error( 'Failed to load ' + src ) ); };
			document.head.appendChild( s );
		} );
	}

	async function generatePdf( schedule, eventName ) {
		await loadScriptOnce( HTML2PDF_CDN );
		if ( typeof window.html2pdf !== 'function' ) {
			throw new Error( 'html2pdf.js failed to load' );
		}

		// Fixed-positioned off-screen wrapper. Width matches a typical
		// rendering viewport so the schedule's responsive CSS picks the
		// desktop branch (multi-column grid would be irrelevant here,
		// but the print-table sizing needs ~1100 px to look right).
		const wrapper = document.createElement( 'div' );
		wrapper.style.cssText = 'position:fixed;left:-99999px;top:0;width:1180px;background:#fff;z-index:-1;';

		const clone = schedule.cloneNode( true );

		// 1) Strip every on-screen widget that has no place in the PDF.
		//    `.de-fe-header-actions` is what holds the "Add to calendar"
		//    and "Export to PDF" buttons, including the spinner state.
		clone.querySelectorAll(
			'.de-fe-day-nav, .de-fe-filters, .de-fe-header-actions, ' +
			'.de-fe-session-modal, .de-fe-schedule-grid, .de-fe-mobile-list, script'
		).forEach( function ( el ) { el.remove(); } );

		// 2) Force every day visible (on screen only one carries `.is-active`)
		//    and mark days 2..N so the pagebreak engine starts each on a fresh
		//    page. Day 1 stays unmarked so the document doesn't start with a
		//    blank page.
		clone.querySelectorAll( '.de-fe-schedule-day' ).forEach( function ( day, idx ) {
			day.classList.add( 'is-active' );
			day.setAttribute( 'aria-hidden', 'false' );
			day.style.setProperty( 'display', 'block', 'important' );
			if ( idx > 0 ) {
				day.classList.add( 'de-fe-pdf-page-break' );
				day.style.pageBreakBefore = 'always';
				day.style.breakBefore = 'page';
			}
		} );

		// 3) Reveal the server-rendered print tables (base CSS hides them
		//    with display:none). With the v0.8.5 refactor their styling is
		//    global, so once display flips to `table` they render correctly.
		clone.querySelectorAll( '.de-fe-print-table' ).forEach( function ( t ) {
			t.style.setProperty( 'display', 'table', 'important' );
		} );

		wrapper.appendChild( clone );
		document.body.appendChild( wrapper );

		// Let the layout engine settle the styles + sizes before capture.
		await new Promise( function ( r ) { setTimeout( r, 200 ); } );

		try {
			await window.html2pdf().set( {
				margin:    [ 8, 8, 10, 8 ],
				filename:  eventName + ' — Programme.pdf',
				image:     { type: 'jpeg', quality: 0.95 },
				html2canvas: {
					scale:           2,
					useCORS:         true,
					backgroundColor: '#ffffff',
					letterRendering: true,
					logging:         false
				},
				jsPDF: {
					unit:        'mm',
					format:      'a4',
					orientation: 'landscape',
					compress:    true
				},
				pagebreak: {
					/* `before` forces a new page at every Day 2..N.
					 * `avoid` tells the slicer not to cut these elements
					 * in the middle — sessions, breaks, day headers, and
					 * any individual <tr> stay whole unless taller than
					 * one full page (in which case splitting is the only
					 * physically possible outcome). */
					mode:   [ 'css', 'legacy' ],
					before: '.de-fe-pdf-page-break',
					avoid:  [
						'.de-fe-print-td-session',
						'.de-fe-print-td-break',
						'.de-fe-day-header',
						'tr'
					]
				}
			} ).from( clone ).save();
		} finally {
			wrapper.remove();
		}
	}

	function setupFilters( schedule ) {
		const primaryNav = schedule.querySelector( '.de-fe-primary-nav' );
		const venueNav   = schedule.querySelector( '.de-fe-venue-nav' );
		const typeNav    = schedule.querySelector( '.de-fe-type-nav' );
		const searchEl   = schedule.querySelector( '.de-fe-search-input' );
		const searchClearEl = schedule.querySelector( '[data-de-search-clear]' );
		const speakerSel = schedule.querySelector( '.de-fe-speaker-select' );

		const primaryButtons = primaryNav ? primaryNav.querySelectorAll( '.de-fe-primary-nav-item' ) : [];
		const venueButtons   = venueNav   ? venueNav.querySelectorAll( '.de-fe-venue-nav-item' )   : [];
		const typeButtons    = typeNav    ? typeNav.querySelectorAll( '.de-fe-type-nav-item' )    : [];

		const grids = schedule.querySelectorAll( '.de-fe-schedule-grid' );
		const lists = schedule.querySelectorAll( '.de-fe-mobile-list' );

		// Nothing to set up if no filter UI was rendered.
		if ( ! venueButtons.length && ! typeButtons.length && ! searchEl && ! speakerSel ) return;

		// Snapshot original DOM order of each mobile list for restore-on-clear.
		const originalOrder = new WeakMap();
		lists.forEach( function ( list ) {
			originalOrder.set( list, Array.prototype.slice.call( list.children ) );
		} );

		// State.
		let currentPrimary  = '';
		let currentSub      = '';
		let currentTypeId   = '';
		let currentSpeakerId = '';
		let currentSearch   = '';

		/* ---------- predicates ---------- */
		function matchesVenue( el ) {
			if ( currentSub )     return el.getAttribute( 'data-sub-venue-id' ) === currentSub;
			if ( currentPrimary ) return el.getAttribute( 'data-venue-id' )     === currentPrimary;
			return true;
		}
		function matchesType( el ) {
			if ( ! currentTypeId ) return true;
			return el.getAttribute( 'data-type-id' ) === currentTypeId;
		}
		function matchesSpeaker( el ) {
			if ( ! currentSpeakerId ) return true;
			const ids = ( el.getAttribute( 'data-speaker-ids' ) || '' ).split( ',' );
			return ids.indexOf( currentSpeakerId ) !== -1;
		}
		function matchesSearch( el ) {
			if ( ! currentSearch ) return true;
			const txt = el.getAttribute( 'data-search-text' ) || '';
			return txt.indexOf( currentSearch ) !== -1;
		}
		function matchesAll( el ) {
			return matchesVenue( el ) && matchesType( el ) && matchesSpeaker( el ) && matchesSearch( el );
		}

		function venueFilterActive() {
			return !! currentPrimary || !! currentSub;
		}
		function nonVenueFilterActive() {
			return !! currentTypeId || !! currentSpeakerId || !! currentSearch;
		}
		function activeFilterPresent() {
			return venueFilterActive() || nonVenueFilterActive();
		}

		/* ---------- range computation (for break visibility) ---------- */
		function computeRange( items ) {
			let venueStart = null, venueEnd = null;
			items.forEach( function ( item ) {
				if ( item.classList.contains( 'is-break' ) ) return;
				if ( ! matchesAll( item ) ) return;
				const s = parseInt( item.getAttribute( 'data-start-minutes' ), 10 );
				const e = parseInt( item.getAttribute( 'data-end-minutes' ),   10 );
				if ( isNaN( s ) || isNaN( e ) ) return;
				venueStart = ( venueStart === null ) ? s : Math.min( venueStart, s );
				venueEnd   = ( venueEnd   === null ) ? e : Math.max( venueEnd,   e );
			} );
			return { venueStart: venueStart, venueEnd: venueEnd };
		}

		/* ---------- button + label syncing ---------- */
		function syncButtonsActive() {
			primaryButtons.forEach( function ( b ) {
				const isAll  = ! b.getAttribute( 'data-primary' );
				const isThis = b.getAttribute( 'data-primary' ) === currentPrimary;
				const active = ( isAll && ! venueFilterActive() ) || ( ! isAll && isThis );
				b.classList.toggle( 'is-active', active );
				b.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
			} );
			venueButtons.forEach( function ( b ) {
				const isAll  = ! b.getAttribute( 'data-venue' );
				const isThis = b.getAttribute( 'data-venue' ) === currentSub;
				const active = ( isAll && ! venueFilterActive() ) || ( ! isAll && isThis );
				b.classList.toggle( 'is-active', active );
				b.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
				if ( currentPrimary && ! isAll ) {
					const matchesPrimary = b.getAttribute( 'data-primary' ) === currentPrimary;
					b.classList.toggle( 'is-hidden-by-primary', ! matchesPrimary );
				} else {
					b.classList.remove( 'is-hidden-by-primary' );
				}
			} );
			typeButtons.forEach( function ( b ) {
				const isAll  = ! b.getAttribute( 'data-type' );
				const isThis = b.getAttribute( 'data-type' ) === currentTypeId;
				const active = ( isAll && ! currentTypeId ) || ( ! isAll && isThis );
				b.classList.toggle( 'is-active', active );
				b.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
			} );
			if ( searchClearEl ) searchClearEl.hidden = ! currentSearch;
		}

		function updateSubLabels() {
			venueButtons.forEach( function ( btn ) {
				if ( ! btn.getAttribute( 'data-venue' ) ) return;
				const useShort = currentPrimary && btn.getAttribute( 'data-primary' ) === currentPrimary;
				const labelShort = btn.getAttribute( 'data-label-short' );
				const labelLong  = btn.getAttribute( 'data-label-long' );
				if ( useShort && labelShort ) btn.textContent = labelShort;
				else if ( labelLong )         btn.textContent = labelLong;
			} );
		}

		function getActiveLabels() {
			const labels = [];
			if ( currentSub ) {
				const b = venueNav && venueNav.querySelector( '[data-venue="' + currentSub + '"]' );
				if ( b ) labels.push( b.getAttribute( 'data-label-long' ) || b.textContent.trim() );
			} else if ( currentPrimary && primaryNav ) {
				const b = primaryNav.querySelector( '[data-primary="' + currentPrimary + '"]' );
				if ( b ) labels.push( b.textContent.trim() );
			}
			if ( currentTypeId && typeNav ) {
				const b = typeNav.querySelector( '[data-type="' + currentTypeId + '"]' );
				if ( b ) labels.push( b.textContent.trim() );
			}
			if ( currentSpeakerId && speakerSel ) {
				const opt = speakerSel.querySelector( 'option[value="' + currentSpeakerId + '"]' );
				if ( opt ) labels.push( opt.textContent.trim() );
			}
			if ( currentSearch ) labels.push( '“' + currentSearch + '”' );
			return labels;
		}

		function updateFilterStatus() {
			const statusEl = schedule.querySelector( '[data-de-active-label]' );
			if ( ! statusEl ) return;
			const labels = getActiveLabels();
			if ( ! labels.length ) { statusEl.textContent = ''; return; }
			if ( labels.length === 1 ) statusEl.textContent = ': ' + labels[0];
			else statusEl.textContent = ' (' + labels.length + ' active)';
		}

		/* ---------- apply ---------- */
		function apply() {
			syncButtonsActive();
			updateSubLabels();
			updateFilterStatus();

			grids.forEach( function ( grid ) {
				const blocks  = grid.querySelectorAll( '.de-fe-block' );
				const headers = grid.querySelectorAll( '.de-fe-grid-hall' );

				if ( ! activeFilterPresent() ) {
					grid.style.gridTemplateColumns = '';
					grid.classList.remove( 'is-venue-filtered' );
					blocks.forEach( function ( b ) {
						b.classList.remove( 'is-filtered-out' );
						const orig = b.getAttribute( 'data-original-grid-column' );
						if ( orig ) b.style.gridColumn = orig;
					} );
					headers.forEach( function ( h ) {
						h.classList.remove( 'is-filtered-out' );
						const orig = h.getAttribute( 'data-original-grid-column' );
						if ( orig ) h.style.gridColumn = orig;
					} );
					return;
				}

				// Compute column layout based on VENUE filter only — other
				// filters narrow visibility within the existing columns.
				const matchingSubs = [];
				headers.forEach( function ( h ) {
					if ( ! matchesVenue( h ) ) return;
					matchingSubs.push( {
						id:      h.getAttribute( 'data-sub-venue-id' ),
						origCol: parseInt( h.getAttribute( 'data-original-grid-column' ), 10 ) || 0,
						header:  h,
					} );
				} );
				matchingSubs.sort( function ( a, b ) { return a.origCol - b.origCol; } );

				const subColMap = new Map();
				matchingSubs.forEach( function ( s, i ) { subColMap.set( s.id, i + 2 ); } );
				const numCols = matchingSubs.length;

				grid.classList.add( 'is-venue-filtered' );
				if ( numCols === 0 ) grid.style.gridTemplateColumns = '';
				else                 grid.style.gridTemplateColumns = '70px repeat(' + numCols + ', minmax(200px, 1fr))';

				const range = computeRange( blocks );
				const seenBreaks = new Set();

				blocks.forEach( function ( b ) {
					const isBreak = b.classList.contains( 'is-break' );
					if ( isBreak ) {
						// Break must pass type/speaker/search filters AND be in venue's range.
						if ( ! matchesType( b ) || ! matchesSpeaker( b ) || ! matchesSearch( b ) ) {
							b.classList.add( 'is-filtered-out' );
							return;
						}
						const s = parseInt( b.getAttribute( 'data-start-minutes' ), 10 );
						const e = parseInt( b.getAttribute( 'data-end-minutes' ),   10 );
						const inRange = range.venueStart !== null && range.venueEnd !== null
							&& ! isNaN( s ) && ! isNaN( e )
							&& s < range.venueEnd && e > range.venueStart;
						const key = s + '-' + e;
						if ( inRange && ! seenBreaks.has( key ) ) {
							seenBreaks.add( key );
							b.classList.remove( 'is-filtered-out' );
							b.style.gridColumn = '2 / -1';
						} else {
							b.classList.add( 'is-filtered-out' );
						}
					} else if ( matchesAll( b ) ) {
						const subId = b.getAttribute( 'data-sub-venue-id' );
						const newCol = subColMap.get( subId );
						if ( newCol !== undefined ) {
							b.style.gridColumn = String( newCol );
							b.classList.remove( 'is-filtered-out' );
						} else {
							b.classList.add( 'is-filtered-out' );
						}
					} else {
						b.classList.add( 'is-filtered-out' );
					}
				} );

				headers.forEach( function ( h ) {
					const subId = h.getAttribute( 'data-sub-venue-id' );
					const newCol = subColMap.get( subId );
					if ( newCol !== undefined ) {
						h.style.gridColumn = String( newCol );
						h.classList.remove( 'is-filtered-out' );
					} else {
						h.classList.add( 'is-filtered-out' );
					}
				} );
			} );

			// Mobile list: sort by time when any filter active, restore when clear.
			lists.forEach( function ( list ) {
				if ( ! activeFilterPresent() ) {
					const orig = originalOrder.get( list );
					if ( orig ) orig.forEach( function ( item ) { list.appendChild( item ); } );
					list.querySelectorAll( '.de-fe-mobile-item' ).forEach( function ( item ) {
						item.classList.remove( 'is-filtered-out' );
					} );
					return;
				}

				const items = Array.prototype.slice.call( list.querySelectorAll( '.de-fe-mobile-item' ) );
				items.sort( function ( a, b ) {
					const sa = parseInt( a.getAttribute( 'data-start-minutes' ), 10 );
					const sb = parseInt( b.getAttribute( 'data-start-minutes' ), 10 );
					return ( isNaN( sa ) ? 0 : sa ) - ( isNaN( sb ) ? 0 : sb );
				} );
				items.forEach( function ( item ) { list.appendChild( item ); } );

				const range = computeRange( items );
				const seenBreaks = new Set();

				items.forEach( function ( item ) {
					const isBreak = item.classList.contains( 'is-break' );
					if ( isBreak ) {
						if ( ! matchesType( item ) || ! matchesSpeaker( item ) || ! matchesSearch( item ) ) {
							item.classList.add( 'is-filtered-out' );
							return;
						}
						const s = parseInt( item.getAttribute( 'data-start-minutes' ), 10 );
						const e = parseInt( item.getAttribute( 'data-end-minutes' ),   10 );
						const inRange = range.venueStart !== null && range.venueEnd !== null
							&& ! isNaN( s ) && ! isNaN( e )
							&& s < range.venueEnd && e > range.venueStart;
						const key = s + '-' + e;
						if ( inRange && ! seenBreaks.has( key ) ) {
							seenBreaks.add( key );
							item.classList.remove( 'is-filtered-out' );
						} else {
							item.classList.add( 'is-filtered-out' );
						}
					} else if ( matchesAll( item ) ) {
						item.classList.remove( 'is-filtered-out' );
					} else {
						item.classList.add( 'is-filtered-out' );
					}
				} );
			} );
		}

		/* ---------- event wiring ---------- */
		primaryButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				const pid = btn.getAttribute( 'data-primary' ) || '';
				currentPrimary = pid;
				currentSub = '';
				apply();
			} );
		} );

		venueButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				const vid = btn.getAttribute( 'data-venue' ) || '';
				const pid = btn.getAttribute( 'data-primary' ) || '';
				if ( ! vid ) {
					currentPrimary = '';
					currentSub = '';
				} else {
					currentSub = vid;
					currentPrimary = pid;
				}
				apply();
			} );
		} );

		typeButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				currentTypeId = btn.getAttribute( 'data-type' ) || '';
				apply();
			} );
		} );

		if ( speakerSel ) {
			speakerSel.addEventListener( 'change', function () {
				currentSpeakerId = speakerSel.value || '';
				apply();
			} );
		}

		if ( searchEl ) {
			let searchDebounce;
			searchEl.addEventListener( 'input', function () {
				clearTimeout( searchDebounce );
				searchDebounce = setTimeout( function () {
					currentSearch = ( searchEl.value || '' ).toLowerCase().trim();
					apply();
				}, 200 );
			} );
		}
		if ( searchClearEl ) {
			searchClearEl.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				if ( searchEl ) searchEl.value = '';
				currentSearch = '';
				apply();
			} );
		}

		// Default state: no filter.
		apply();
	}

	/* ============================================================
	 * Speakers shortcode — search box + role filter pills (v0.8.7).
	 *
	 * Server pre-bakes per-card filter metadata into data attributes:
	 *   data-search-text   "first last, last first, title, role role"
	 *   data-role-ids      "uuid,uuid,uuid"
	 * So the filter loop is just substring / set-intersection — no
	 * normalization, no per-keystroke string work.
	 *
	 * Server also pre-sorts speakers by last_name → first_name in the
	 * repository, so the rendered DOM order is already alphabetical.
	 * ============================================================ */
	function setupSpeakers( root ) {
		const grid       = root.querySelector( '.de-fe-speakers-grid' );
		if ( ! grid ) return;

		const searchEl   = root.querySelector( '[data-de-speakers-search]' );
		const clearEl    = root.querySelector( '[data-de-speakers-search-clear]' );
		const pills      = root.querySelectorAll( '[data-de-speakers-role]' );
		const emptyEl    = root.querySelector( '[data-de-speakers-empty]' );
		const shownEl    = root.querySelector( '[data-de-speakers-shown]' );
		const cards      = Array.from( grid.querySelectorAll( '.de-fe-speaker-card' ) );

		let currentSearch = '';
		let currentRoleId = '';

		function apply() {
			let visible = 0;
			cards.forEach( function ( card ) {
				const searchText = card.getAttribute( 'data-search-text' ) || '';
				const roleIds    = ( card.getAttribute( 'data-role-ids' ) || '' ).split( ',' );

				const matchesSearch = currentSearch === ''
					|| searchText.indexOf( currentSearch ) !== -1;
				const matchesRole = currentRoleId === ''
					|| roleIds.indexOf( currentRoleId ) !== -1;

				if ( matchesSearch && matchesRole ) {
					card.removeAttribute( 'hidden' );
					visible++;
				} else {
					card.setAttribute( 'hidden', '' );
				}
			} );

			if ( shownEl ) shownEl.textContent = String( visible );
			if ( emptyEl ) emptyEl.hidden = visible !== 0;
			if ( clearEl ) clearEl.hidden = currentSearch === '';
		}

		/* Search box — debounced 200 ms */
		if ( searchEl ) {
			let timer = null;
			searchEl.addEventListener( 'input', function () {
				window.clearTimeout( timer );
				timer = window.setTimeout( function () {
					currentSearch = searchEl.value.trim().toLowerCase();
					apply();
				}, 200 );
			} );
		}
		if ( clearEl && searchEl ) {
			clearEl.addEventListener( 'click', function () {
				searchEl.value = '';
				currentSearch = '';
				searchEl.focus();
				apply();
			} );
		}

		/* Role pills */
		pills.forEach( function ( pill ) {
			pill.addEventListener( 'click', function () {
				const rid = pill.getAttribute( 'data-de-speakers-role' ) || '';
				currentRoleId = rid;
				pills.forEach( function ( p ) {
					const isActive = p === pill;
					p.classList.toggle( 'is-active', isActive );
					p.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
				} );
				apply();
			} );
		} );

		apply();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.digitone-events-schedule' ).forEach( init );
		document.querySelectorAll( '.digitone-events-speakers' ).forEach( setupSpeakers );
	} );
} )();
