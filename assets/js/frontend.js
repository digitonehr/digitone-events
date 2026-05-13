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

		/* Print handler — when the user prints the page, we temporarily clear
		 * the filter so the printed PDF shows the whole event (printed program
		 * is a reference document, not a filtered slice). State is restored
		 * after print so the on-screen UI is unchanged. */
		let savedFilter = null;
		window.addEventListener( 'beforeprint', function () {
			savedFilter = {
				primary:  currentPrimary,
				sub:      currentSub,
				type:     currentTypeId,
				speaker:  currentSpeakerId,
				search:   currentSearch,
				input:    searchEl   ? searchEl.value   : '',
				selValue: speakerSel ? speakerSel.value : '',
			};
			currentPrimary   = '';
			currentSub       = '';
			currentTypeId    = '';
			currentSpeakerId = '';
			currentSearch    = '';
			if ( searchEl )   searchEl.value   = '';
			if ( speakerSel ) speakerSel.value = '';
			apply();
		} );
		window.addEventListener( 'afterprint', function () {
			if ( ! savedFilter ) return;
			currentPrimary   = savedFilter.primary;
			currentSub       = savedFilter.sub;
			currentTypeId    = savedFilter.type;
			currentSpeakerId = savedFilter.speaker;
			currentSearch    = savedFilter.search;
			if ( searchEl )   searchEl.value   = savedFilter.input;
			if ( speakerSel ) speakerSel.value = savedFilter.selValue;
			apply();
			savedFilter = null;
		} );

		// Default state: no filter.
		apply();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.digitone-events-schedule' ).forEach( init );
	} );
} )();
