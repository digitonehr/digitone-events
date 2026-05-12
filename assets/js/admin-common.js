/**
 * DigitOne Events — common admin JS.
 *
 * Exposes window.DigitoneEvents.api() helper and feedback toasts.
 * Settings page "Force check GitHub" button is also wired here so it's
 * available even before module JS loads.
 */
( function () {
	'use strict';

	if ( typeof window.DigitoneEvents === 'undefined' ) {
		console.error( 'DigitoneEvents global is missing — plugin failed to localize script.' );
		return;
	}

	const DE = window.DigitoneEvents;

	/**
	 * AJAX helper. Returns a Promise that resolves to the response data
	 * on success, or rejects with { message, code } on error.
	 *
	 * @param {string} action  WordPress AJAX action name
	 * @param {Object} payload Extra POST fields
	 */
	DE.api = function ( action, payload ) {
		const body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', DE.nonce );
		if ( payload && typeof payload === 'object' ) {
			Object.keys( payload ).forEach( function ( key ) {
				const val = payload[ key ];
				if ( Array.isArray( val ) ) {
					val.forEach( function ( v ) {
						body.append( key + '[]', v );
					} );
				} else if ( val !== null && val !== undefined ) {
					body.append( key, val );
				}
			} );
		}

		return fetch( DE.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
		} ).then( function ( res ) {
			return res.json().then( function ( json ) {
				if ( json && json.success ) {
					return json.data;
				}
				const message = ( json && json.data && json.data.message ) || DE.i18n.error;
				throw { message: message, code: res.status, data: json && json.data };
			} );
		} );
	};

	/**
	 * Toast feedback. type: 'info' | 'success' | 'error'.
	 */
	DE.feedback = function ( message, type ) {
		type = type || 'info';
		let container = document.querySelector( '.de-feedback' );
		if ( ! container ) {
			container = document.createElement( 'div' );
			container.className = 'de-feedback';
			container.setAttribute( 'role', 'status' );
			container.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( container );
		}
		const toast = document.createElement( 'div' );
		toast.className = 'de-toast de-toast-' + type;
		toast.textContent = message;
		container.appendChild( toast );
		setTimeout( function () {
			toast.style.opacity = '0';
			toast.style.transition = 'opacity 0.3s ease';
			setTimeout( function () { toast.remove(); }, 300 );
		}, 3000 );
	};

	/**
	 * Settings page: force-check GitHub button.
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		const btn = document.getElementById( 'de-check-update-btn' );
		const out = document.getElementById( 'de-check-update-result' );
		if ( ! btn || ! out ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			out.className = 'de-check-result';
			out.textContent = DE.i18n.checking;

			DE.api( 'digitone_events_check_update', {} )
				.then( function ( data ) {
					if ( data.is_newer ) {
						out.className = 'de-check-result is-success';
						out.innerHTML = '🆕 v' + escapeHtml( data.latest ) +
							' available (installed v' + escapeHtml( data.installed ) +
							'). <a href="' + escapeHtml( data.html_url ) + '" target="_blank" rel="noopener">View release</a>';
					} else {
						out.className = 'de-check-result is-warning';
						out.textContent = '✓ You are on the latest version (v' + data.installed + ').';
					}
				} )
				.catch( function ( err ) {
					out.className = 'de-check-result is-error';
					out.textContent = '⚠ ' + ( err.message || DE.i18n.error );
				} )
				.finally( function () {
					btn.disabled = false;
				} );
		} );
	} );

	function escapeHtml( str ) {
		return String( str ).replace( /[&<>"']/g, function ( m ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ m ];
		} );
	}

	/**
	 * Active-event switcher (appears on module pages).
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		const select = document.getElementById( 'de-active-event-select' );
		if ( ! select ) return;
		select.addEventListener( 'change', function () {
			const newId = select.value;
			if ( ! newId ) return;
			DE.api( 'digitone_events_event_set_active', { id: newId } )
				.then( function () {
					DE.feedback( 'Active event switched.', 'success' );
					setTimeout( function () { window.location.reload(); }, 400 );
				} )
				.catch( function ( err ) {
					DE.feedback( err.message || DE.i18n.error, 'error' );
				} );
		} );
	} );

} )();
