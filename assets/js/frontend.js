/**
 * DigitOne Events — Frontend day switcher.
 *
 * Initial active day:
 *   1. If URL has ?de-day=N or #de-day-N → that day
 *   2. Else if today's local date matches a day's data-day-date → that day
 *   3. Else → first day (already set by PHP)
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

		// 1. URL override wins.
		const url = new URL( window.location.href );
		const queryDay = parseInt( url.searchParams.get( 'de-day' ), 10 );
		if ( ! isNaN( queryDay ) && queryDay >= 0 && queryDay < panels.length ) {
			show( queryDay );
			return;
		}
		const hashMatch = window.location.hash.match( /^#de-day-(\d+)$/ );
		if ( hashMatch ) {
			const idx = parseInt( hashMatch[1], 10 );
			if ( idx >= 0 && idx < panels.length ) {
				show( idx );
				return;
			}
		}

		// 2. Match browser's local date to a day's data-day-date.
		const today = localDateYMD();
		let matched = -1;
		panels.forEach( function ( p, i ) {
			if ( p.getAttribute( 'data-day-date' ) === today ) {
				matched = i;
			}
		} );
		if ( matched >= 0 ) {
			show( matched );
			return;
		}

		// 3. Otherwise PHP-set first day stays active — nothing to do.
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.digitone-events-schedule' ).forEach( init );
	} );
} )();
