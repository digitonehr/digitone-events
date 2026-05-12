/**
 * DigitOne Events — Frontend day switcher.
 *
 * Initial active day priority:
 *   1. URL ?de-day=N or #de-day-N
 *   2. Browser's local date matches a panel's data-day-date
 *   3. First day (always falls through to this)
 *
 * JS always normalises state at the end, so even if multiple panels had
 * is-active in the HTML (which shouldn't happen) the correct single one wins.
 */
( function () {
	'use strict';

	function localDateYMD() {
		const d = new Date();
		const y = d.getFullYear();
		const m = String( d.getMonth() + 1 ).padStart( 2, '0' );
		const day = String( d.getDate() ).padStart( 2, '0' );
		return y + '-' + m + '-' + day;
	}

	function init( schedule ) {
		const buttons = schedule.querySelectorAll( '.de-fe-day-nav-item' );
		const panels  = schedule.querySelectorAll( '.de-fe-schedule-day' );
		if ( ! buttons.length || ! panels.length ) return;

		function show( idx ) {
			buttons.forEach( function ( b, i ) {
				const active = ( i === idx );
				b.classList.toggle( 'is-active', active );
				b.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			} );
			panels.forEach( function ( p, i ) {
				const active = ( i === idx );
				p.classList.toggle( 'is-active', active );
				p.setAttribute( 'aria-hidden', active ? 'false' : 'true' );
			} );
		}

		buttons.forEach( function ( btn, idx ) {
			btn.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				show( idx );
			} );
		} );

		// Determine initial selection. Default = first day, then override.
		let selectedIdx = 0;

		// 1. URL override.
		try {
			const url = new URL( window.location.href );
			const queryDay = parseInt( url.searchParams.get( 'de-day' ), 10 );
			if ( ! isNaN( queryDay ) && queryDay >= 0 && queryDay < panels.length ) {
				selectedIdx = queryDay;
			} else {
				const hashMatch = window.location.hash.match( /^#de-day-(\d+)$/ );
				if ( hashMatch ) {
					const idx = parseInt( hashMatch[1], 10 );
					if ( idx >= 0 && idx < panels.length ) {
						selectedIdx = idx;
					}
				} else {
					// 2. Match today's local date.
					const today = localDateYMD();
					for ( let i = 0; i < panels.length; i++ ) {
						if ( panels[i].getAttribute( 'data-day-date' ) === today ) {
							selectedIdx = i;
							break;
						}
					}
				}
			}
		} catch ( e ) {
			// Stay on selectedIdx = 0
		}

		// 3. Always apply final selection (defensive — overrides any stale HTML state).
		show( selectedIdx );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.digitone-events-schedule' ).forEach( init );
	} );
} )();
