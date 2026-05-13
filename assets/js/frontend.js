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
	 * Break-type sessions are always visible (they apply to everyone).
	 * ============================================================ */
	function setupVenueFilter( schedule ) {
		const venueNav = schedule.querySelector( '.de-fe-venue-nav' );
		if ( ! venueNav ) return;
		const buttons = venueNav.querySelectorAll( '.de-fe-venue-nav-item' );
		const grids   = schedule.querySelectorAll( '.de-fe-schedule-grid' );
		const lists   = schedule.querySelectorAll( '.de-fe-mobile-list' );
		if ( ! buttons.length ) return;

		function apply( venueId ) {
			buttons.forEach( function ( b ) {
				b.classList.toggle( 'is-active', b.getAttribute( 'data-venue' ) === venueId );
				b.setAttribute( 'aria-pressed', b.getAttribute( 'data-venue' ) === venueId ? 'true' : 'false' );
			} );

			grids.forEach( function ( grid ) {
				const blocks  = grid.querySelectorAll( '.de-fe-block' );
				const headers = grid.querySelectorAll( '.de-fe-grid-hall' );

				if ( ! venueId ) {
					// Restore everything.
					grid.style.gridTemplateColumns = '';
					grid.classList.remove( 'is-venue-filtered' );
					blocks.forEach( function ( b ) {
						b.classList.remove( 'is-filtered-out' );
						const orig = b.getAttribute( 'data-original-grid-column' );
						if ( orig ) {
							// Reapply the original grid-column from inline style (the style attribute already has it)
							b.style.gridColumn = orig;
						}
					} );
					headers.forEach( function ( h ) { h.classList.remove( 'is-filtered-out' ); } );
					return;
				}

				// Filtered: shrink grid to a single hall column.
				grid.classList.add( 'is-venue-filtered' );
				grid.style.gridTemplateColumns = '70px minmax(200px, 1fr)';

				blocks.forEach( function ( b ) {
					const isBreak = b.classList.contains( 'is-break' );
					if ( isBreak ) {
						b.classList.remove( 'is-filtered-out' );
						b.style.gridColumn = '2 / -1';
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
					if ( match ) {
						h.style.gridColumn = '2';
					}
				} );
			} );

			// Mobile list: simple show/hide.
			lists.forEach( function ( list ) {
				list.querySelectorAll( '.de-fe-mobile-item' ).forEach( function ( item ) {
					const isBreak = item.classList.contains( 'is-break' );
					if ( isBreak || ! venueId || item.getAttribute( 'data-sub-venue-id' ) === venueId ) {
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
