/**
 * DigitOne Events — Frontend day switcher.
 * Click a day picker → show only that day's panel, hide others.
 * Initial selection comes from PHP (today's date if it matches, else day 0).
 */
( function () {
	'use strict';

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

		// Honour ?de-day=N or #de-day-N for shareable links.
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
			}
		}
		// Otherwise the PHP-set initial day is already active in markup.
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.digitone-events-schedule' ).forEach( init );
	} );
} )();
