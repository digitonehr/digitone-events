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
	 * Venue filter — hides blocks/headers/mobile items that don't match.
	 * "All venues" (data-venue="") restores the full grid.
	 * Break-type sessions are shown only when they overlap with the
	 * selected venue's active time range, and de-duplicated by time slot.
	 * On mobile, items are also reordered chronologically while filtered.
	 * ============================================================ */
	function setupVenueFilter( schedule ) {
		const venueNav = schedule.querySelector( '.de-fe-venue-nav' );
		if ( ! venueNav ) return;
		const buttons = venueNav.querySelectorAll( '.de-fe-venue-nav-item' );
		const grids   = schedule.querySelectorAll( '.de-fe-schedule-grid' );
		const lists   = schedule.querySelectorAll( '.de-fe-mobile-list' );
		if ( ! buttons.length ) return;

		// Capture each mobile list's original DOM order so we can restore it
		// after the user clears the filter.
		const originalOrder = new WeakMap();
		lists.forEach( function ( list ) {
			originalOrder.set( list, Array.prototype.slice.call( list.children ) );
		} );

		function computeVenueRange( items, venueId ) {
			let venueStart = null, venueEnd = null;
			items.forEach( function ( item ) {
				if ( item.classList.contains( 'is-break' ) ) return;
				if ( item.getAttribute( 'data-sub-venue-id' ) !== venueId ) return;
				const s = parseInt( item.getAttribute( 'data-start-minutes' ), 10 );
				const e = parseInt( item.getAttribute( 'data-end-minutes' ),   10 );
				if ( isNaN( s ) || isNaN( e ) ) return;
				venueStart = ( venueStart === null ) ? s : Math.min( venueStart, s );
				venueEnd   = ( venueEnd   === null ) ? e : Math.max( venueEnd,   e );
			} );
			return { venueStart: venueStart, venueEnd: venueEnd };
		}

		function apply( venueId ) {
			buttons.forEach( function ( b ) {
				b.classList.toggle( 'is-active', b.getAttribute( 'data-venue' ) === venueId );
				b.setAttribute( 'aria-pressed', b.getAttribute( 'data-venue' ) === venueId ? 'true' : 'false' );
			} );

			grids.forEach( function ( grid ) {
				const blocks  = grid.querySelectorAll( '.de-fe-block' );
				const headers = grid.querySelectorAll( '.de-fe-grid-hall' );

				if ( ! venueId ) {
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

				const range = computeVenueRange( blocks, venueId );

				grid.classList.add( 'is-venue-filtered' );
				grid.style.gridTemplateColumns = '70px minmax(200px, 1fr)';

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
					} else if ( b.getAttribute( 'data-sub-venue-id' ) === venueId ) {
						b.classList.remove( 'is-filtered-out' );
						b.style.gridColumn = '2';
					} else {
						b.classList.add( 'is-filtered-out' );
					}
				} );

				headers.forEach( function ( h ) {
					const match = h.getAttribute( 'data-sub-venue-id' ) === venueId;
					h.classList.toggle( 'is-filtered-out', ! match );
					if ( match ) h.style.gridColumn = '2';
				} );
			} );

			// Mobile list: time-sort when filtered, restore order when cleared,
			// dedupe break sessions, time-range filter on breaks.
			lists.forEach( function ( list ) {
				if ( ! venueId ) {
					// Restore original DOM order and show everything.
					const orig = originalOrder.get( list );
					if ( orig ) orig.forEach( function ( item ) { list.appendChild( item ); } );
					list.querySelectorAll( '.de-fe-mobile-item' ).forEach( function ( item ) {
						item.classList.remove( 'is-filtered-out' );
					} );
					return;
				}

				// Filtered: reorder by start_time so breaks fall into their
				// natural chronological slot among the visible sessions.
				const items = Array.prototype.slice.call( list.querySelectorAll( '.de-fe-mobile-item' ) );
				items.sort( function ( a, b ) {
					const sa = parseInt( a.getAttribute( 'data-start-minutes' ), 10 );
					const sb = parseInt( b.getAttribute( 'data-start-minutes' ), 10 );
					return ( isNaN( sa ) ? 0 : sa ) - ( isNaN( sb ) ? 0 : sb );
				} );
				items.forEach( function ( item ) { list.appendChild( item ); } );

				const range = computeVenueRange( items, venueId );
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
					} else if ( item.getAttribute( 'data-sub-venue-id' ) === venueId ) {
						item.classList.remove( 'is-filtered-out' );
					} else {
						item.classList.add( 'is-filtered-out' );
					}
				} );
			} );
		}

		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				apply( btn.getAttribute( 'data-venue' ) || '' );
			} );
		} );

		// Default state: no filter ("All venues" already has is-active in markup).
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.digitone-events-schedule' ).forEach( init );
	} );
} )();
