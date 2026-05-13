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
		setupVenueFilter( schedule );
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
	 * Venue filter — supports a two-level picker:
	 *   - Primary venue picker (rendered only when 2+ primaries exist)
	 *   - Sub-venue (hall) picker — always rendered
	 *
	 * Filter state is the LAST picker clicked:
	 *   - subId set → filter the schedule to that one sub-venue
	 *   - primaryId set (without subId) → filter to all sub-venues in that primary
	 *   - both null → no filter
	 *
	 * Breaks are shown only when their time overlaps the active filter's
	 * time range, and de-duplicated by `(start, end)` so the same break entered
	 * once per hall doesn't appear multiple times in the filtered view.
	 * ============================================================ */
	function setupVenueFilter( schedule ) {
		const primaryNav = schedule.querySelector( '.de-fe-primary-nav' );
		const venueNav   = schedule.querySelector( '.de-fe-venue-nav' );
		if ( ! venueNav ) return;
		const primaryButtons = primaryNav ? primaryNav.querySelectorAll( '.de-fe-primary-nav-item' ) : [];
		const venueButtons   = venueNav.querySelectorAll( '.de-fe-venue-nav-item' );
		const grids = schedule.querySelectorAll( '.de-fe-schedule-grid' );
		const lists = schedule.querySelectorAll( '.de-fe-mobile-list' );
		if ( ! venueButtons.length ) return;

		// Snapshot each mobile list's original DOM order so we can restore it
		// after the user clears the filter.
		const originalOrder = new WeakMap();
		lists.forEach( function ( list ) {
			originalOrder.set( list, Array.prototype.slice.call( list.children ) );
		} );

		// State.
		let currentPrimary = '';
		let currentSub     = '';

		function matchesFilter( el ) {
			if ( currentSub )     return el.getAttribute( 'data-sub-venue-id' ) === currentSub;
			if ( currentPrimary ) return el.getAttribute( 'data-venue-id' )     === currentPrimary;
			return true;
		}

		function computeRange( items ) {
			let venueStart = null, venueEnd = null;
			items.forEach( function ( item ) {
				if ( item.classList.contains( 'is-break' ) ) return;
				if ( ! matchesFilter( item ) ) return;
				const s = parseInt( item.getAttribute( 'data-start-minutes' ), 10 );
				const e = parseInt( item.getAttribute( 'data-end-minutes' ),   10 );
				if ( isNaN( s ) || isNaN( e ) ) return;
				venueStart = ( venueStart === null ) ? s : Math.min( venueStart, s );
				venueEnd   = ( venueEnd   === null ) ? e : Math.max( venueEnd,   e );
			} );
			return { venueStart: venueStart, venueEnd: venueEnd };
		}

		function activeFilterPresent() {
			return !! currentSub || !! currentPrimary;
		}

		function updateSubLabels() {
			// When a specific primary is selected, use the short ("Sub" only)
			// label for the sub pills belonging to that primary — the primary
			// name is already conveyed by the active primary pill above.
			// When "All venues" is active, use the long ("Primary — Sub") label
			// so each pill is unambiguous in multi-primary mode.
			venueButtons.forEach( function ( btn ) {
				if ( ! btn.getAttribute( 'data-venue' ) ) return;
				const useShort = currentPrimary && btn.getAttribute( 'data-primary' ) === currentPrimary;
				const labelShort = btn.getAttribute( 'data-label-short' );
				const labelLong  = btn.getAttribute( 'data-label-long' );
				if ( useShort && labelShort ) {
					btn.textContent = labelShort;
				} else if ( labelLong ) {
					btn.textContent = labelLong;
				}
			} );
		}

		function updateFilterStatus() {
			// Surface the active filter next to the accordion title so users
			// know what's filtered when the accordion is collapsed.
			const statusEl = schedule.querySelector( '[data-de-active-label]' );
			if ( ! statusEl ) return;
			if ( ! activeFilterPresent() ) {
				statusEl.textContent = '';
				return;
			}
			if ( currentSub ) {
				const btn = venueNav.querySelector( '[data-venue="' + currentSub + '"]' );
				if ( btn ) {
					const lbl = btn.getAttribute( 'data-label-long' ) || btn.textContent;
					statusEl.textContent = ': ' + lbl;
				}
				return;
			}
			if ( currentPrimary && primaryNav ) {
				const btn = primaryNav.querySelector( '[data-primary="' + currentPrimary + '"]' );
				if ( btn ) statusEl.textContent = ': ' + btn.textContent.trim();
			}
		}

		function syncButtonsActive() {
			primaryButtons.forEach( function ( b ) {
				const isAll  = ! b.getAttribute( 'data-primary' );
				const isThis = b.getAttribute( 'data-primary' ) === currentPrimary;
				const active = ( isAll && ! activeFilterPresent() ) || ( ! isAll && isThis );
				b.classList.toggle( 'is-active', active );
				b.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
			} );

			venueButtons.forEach( function ( b ) {
				const isAll  = ! b.getAttribute( 'data-venue' );
				const isThis = b.getAttribute( 'data-venue' ) === currentSub;
				const active = ( isAll && ! activeFilterPresent() ) || ( ! isAll && isThis );
				b.classList.toggle( 'is-active', active );
				b.setAttribute( 'aria-pressed', active ? 'true' : 'false' );

				// When a primary is selected, dim/hide sub pills that belong to other primaries.
				if ( currentPrimary && ! isAll ) {
					const matchesPrimary = b.getAttribute( 'data-primary' ) === currentPrimary;
					b.classList.toggle( 'is-hidden-by-primary', ! matchesPrimary );
				} else {
					b.classList.remove( 'is-hidden-by-primary' );
				}
			} );
		}

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

				// FILTER ACTIVE — unified logic for both "sub" and "primary" modes:
				// 1) Walk hall headers, collect ones that match the current filter
				//    (matchesFilter handles both sub_venue_id and venue_id).
				// 2) Sort matching subs by their original column position so visual
				//    order stays stable.
				// 3) Re-index them as columns 2..N+1 and build a sub_id → new column map.
				// 4) Apply that map to every visible block + hall header so the
				//    grid has exactly N columns, no empty tracks.
				const matchingSubs = [];
				headers.forEach( function ( h ) {
					if ( ! matchesFilter( h ) ) return;
					const subId  = h.getAttribute( 'data-sub-venue-id' );
					const origCol = parseInt( h.getAttribute( 'data-original-grid-column' ), 10 ) || 0;
					matchingSubs.push( { id: subId, origCol: origCol, header: h } );
				} );
				matchingSubs.sort( function ( a, b ) { return a.origCol - b.origCol; } );

				const subColMap = new Map();
				matchingSubs.forEach( function ( s, i ) { subColMap.set( s.id, i + 2 ); } );
				const numCols = matchingSubs.length;

				grid.classList.add( 'is-venue-filtered' );
				if ( numCols === 0 ) {
					grid.style.gridTemplateColumns = '';
				} else {
					grid.style.gridTemplateColumns = '70px repeat(' + numCols + ', minmax(200px, 1fr))';
				}

				const range = computeRange( blocks );
				const seenBreaks = new Set();

				blocks.forEach( function ( b ) {
					const isBreak = b.classList.contains( 'is-break' );
					if ( isBreak ) {
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
					} else if ( matchesFilter( b ) ) {
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

			// Mobile list: time-sort when filtered, restore order when cleared.
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
					} else if ( matchesFilter( item ) ) {
						item.classList.remove( 'is-filtered-out' );
					} else {
						item.classList.add( 'is-filtered-out' );
					}
				} );
			} );
		}

		primaryButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				const pid = btn.getAttribute( 'data-primary' ) || '';
				currentPrimary = pid;
				// Picking a different primary clears any sub selection.
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
					// "All venues" sub pill (only present in single-primary mode) → clear all.
					currentPrimary = '';
					currentSub = '';
				} else {
					currentSub = vid;
					currentPrimary = pid; // auto-sync primary highlight to this sub's parent
				}
				apply();
			} );
		} );

		// Default state: nothing filtered.
		apply();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.digitone-events-schedule' ).forEach( init );
	} );
} )();
