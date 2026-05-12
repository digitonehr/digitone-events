/**
 * DigitOne Events — Frontend day switcher + tab title swap.
 *
 * Initial active day:
 *   1. URL ?de-day=N or #de-day-N
 *   2. Otherwise: Day 1 (always)
 *
 * Document title:
 *   Set to "<event-name> <suffix>" (default suffix = "Programme") from
 *   data-event-name and data-title-suffix attributes on the schedule wrapper.
 *   Empty suffix disables the title swap.
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

		// Initial selection. Default = Day 1.
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

		// Always apply final selection — overrides any stale HTML state.
		show( selectedIdx );
	}

	function swapTitle( schedule ) {
		const eventName = schedule.getAttribute( 'data-event-name' );
		if ( ! eventName ) return;
		// title-suffix attribute lets the shortcode customise or disable the swap.
		// Missing attribute → default "Programme". Empty string → don't swap.
		let suffix = schedule.getAttribute( 'data-title-suffix' );
		if ( suffix === null ) suffix = 'Programme';
		if ( suffix === '' ) return;
		document.title = eventName + ' — ' + suffix;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.digitone-events-schedule' ).forEach( function ( schedule ) {
			swapTitle( schedule );
			init( schedule );
		} );
	} );
} )();
