/**
 * DigitOne Events — Sessions module JS.
 * Handles two tabs: Sessions (rich modal with cascading dropdowns) and Session Types (inline edit).
 */
( function () {
	'use strict';

	const DE = window.DigitoneEvents;
	if ( ! DE || typeof DE.api !== 'function' ) return;

	document.addEventListener( 'DOMContentLoaded', function () {
		const page = document.querySelector( '.digitone-events-page[data-page="sessions"]' );
		if ( ! page ) return;
		const activeEvent = page.getAttribute( 'data-active-event' );
		if ( ! activeEvent ) return;
		const currentTab = page.getAttribute( 'data-tab' );
		const initialDay = page.getAttribute( 'data-day-id' );

		if ( currentTab === 'sessions' ) initSessionsTab( page, activeEvent, initialDay );
		if ( currentTab === 'types' )    initTypesTab( page );
	} );

	/* ============================================================ */
	/* SESSIONS TAB                                                 */
	/* ============================================================ */
	function initSessionsTab( page, activeEvent, initialDay ) {
		const dayPicker  = page.querySelector( '#de-day-picker' );
		const modal      = document.getElementById( 'de-session-modal' );
		const form       = modal ? modal.querySelector( 'form' ) : null;
		const titleEl    = modal ? modal.querySelector( '.de-modal-title' ) : null;
		const closeBtn   = modal ? modal.querySelector( '.de-modal-close' ) : null;
		const cancelBtn  = modal ? modal.querySelector( '[data-de-action="cancel-session"]' ) : null;
		const tableBody  = page.querySelector( '.de-sessions-table tbody' );
		const checkAll   = page.querySelector( '.de-check-all' );
		const bulkBtn    = page.querySelector( '[data-de-action="bulk-delete-sessions"]' );

		// Day switcher reloads page with new day_id in URL
		if ( dayPicker ) {
			dayPicker.addEventListener( 'change', function () {
				const url = new URL( window.location.href );
				url.searchParams.set( 'day_id', dayPicker.value );
				url.searchParams.set( 'tab', 'sessions' );
				window.location.href = url.toString();
			} );
		}

		// Sub-venue cascade
		const subVenuesMap = form ? JSON.parse( form.getAttribute( 'data-sub-venues' ) || '{}' ) : {};
		const venueSelect = form ? form.querySelector( '.de-venue-select' ) : null;
		const subVenueSelect = form ? form.querySelector( '.de-sub-venue-select' ) : null;

		function refreshSubVenues( selectedVenueId, preselectSubId ) {
			if ( ! subVenueSelect ) return;
			subVenueSelect.innerHTML = '';
			const opts = [ { id: '', name: '— none —' } ];
			if ( selectedVenueId && subVenuesMap[ selectedVenueId ] ) {
				subVenuesMap[ selectedVenueId ].forEach( function ( s ) {
					opts.push( { id: s.id, name: s.name } );
				} );
				subVenueSelect.disabled = false;
			} else {
				subVenueSelect.disabled = true;
			}
			opts.forEach( function ( o ) {
				const el = document.createElement( 'option' );
				el.value = o.id;
				el.textContent = o.name;
				if ( o.id === preselectSubId ) el.selected = true;
				subVenueSelect.appendChild( el );
			} );
		}
		if ( venueSelect ) {
			venueSelect.addEventListener( 'change', function () {
				refreshSubVenues( venueSelect.value, '' );
			} );
		}

		// Session level → parent field visibility
		const levelSelect = form ? form.querySelector( '.de-level-select' ) : null;
		const parentField = form ? form.querySelector( '.de-parent-field' ) : null;
		const parentSelect = form ? form.querySelector( '.de-parent-select' ) : null;
		const daySelect = form ? form.querySelector( 'select[name="day_id"]' ) : null;

		function refreshParentOptions( dayId, preselectId ) {
			if ( ! parentSelect ) return;
			parentSelect.innerHTML = '<option value="">— select parent —</option>';
			if ( ! dayId ) return;
			DE.api( 'digitone_events_session_masters_for_day', { day_id: dayId } )
				.then( function ( data ) {
					( data.masters || [] ).forEach( function ( m ) {
						const el = document.createElement( 'option' );
						el.value = m.id;
						const t = m.start_time ? ( m.start_time.substring( 0, 5 ) + ' — ' ) : '';
						el.textContent = t + m.title;
						if ( m.id === preselectId ) el.selected = true;
						parentSelect.appendChild( el );
					} );
				} )
				.catch( function () { /* silent */ } );
		}
		function toggleLevel() {
			if ( ! levelSelect || ! parentField ) return;
			parentField.style.display = levelSelect.value === 'child' ? '' : 'none';
		}
		if ( levelSelect ) levelSelect.addEventListener( 'change', toggleLevel );
		if ( daySelect )   daySelect.addEventListener( 'change', function () { refreshParentOptions( daySelect.value, '' ); } );

		/* ============================================================
		 * Speakers autocomplete (v0.8.8) — replaces the old checkbox grid.
		 *
		 * Source of truth = a <script type="application/json"> blob the
		 * server embeds beside the widget, with one entry per speaker
		 * { id, name, search }. The `search` field is already UTF-8
		 * lowercased so filtering is a plain substring check.
		 *
		 * Selected speakers render as removable "chips"; each chip carries
		 * a hidden <input name="speaker_ids[]"> so the existing form-submit
		 * harvest just keeps working.
		 * ============================================================ */
		const acRoot     = modal ? modal.querySelector( '[data-de-speakers-ac]' ) : null;
		const acChips    = acRoot ? acRoot.querySelector( '[data-de-chips]' ) : null;
		const acInput    = acRoot ? acRoot.querySelector( '[data-de-speakers-input]' ) : null;
		const acDropdown = acRoot ? acRoot.querySelector( '[data-de-dropdown]' ) : null;
		const acDataEl   = acRoot ? acRoot.querySelector( '[data-de-speakers-data]' ) : null;
		let acData = [];
		if ( acDataEl ) {
			try { acData = JSON.parse( acDataEl.textContent ) || []; }
			catch ( e ) { acData = []; }
		}
		let acHighlightIdx = -1;
		let acVisibleMatches = [];

		function speakersSelectedIds() {
			if ( ! acChips ) return [];
			return Array.prototype.slice.call(
				acChips.querySelectorAll( 'input[name="speaker_ids[]"]' )
			).map( function ( inp ) { return inp.value; } );
		}
		function setSpeakersAutocomplete( ids ) {
			if ( ! acRoot || ! acChips ) return;
			acChips.innerHTML = '';
			( ids || [] ).forEach( function ( id ) { addSpeakerChip( id ); } );
			if ( acInput ) acInput.value = '';
			hideDropdown();
		}
		function addSpeakerChip( id ) {
			if ( ! acChips ) return;
			if ( speakersSelectedIds().indexOf( id ) !== -1 ) return;
			const sp = acData.find( function ( s ) { return s.id === id; } );
			if ( ! sp ) return;
			const chip = document.createElement( 'span' );
			chip.className = 'de-speakers-chip';
			chip.setAttribute( 'data-speaker-id', id );

			const label = document.createElement( 'span' );
			label.className = 'de-speakers-chip-label';
			label.textContent = sp.name;
			chip.appendChild( label );

			const remove = document.createElement( 'button' );
			remove.type = 'button';
			remove.className = 'de-speakers-chip-remove';
			remove.setAttribute( 'aria-label', 'Remove ' + sp.name );
			remove.textContent = '×';
			remove.addEventListener( 'click', function () {
				chip.remove();
				if ( acInput ) acInput.focus();
			} );
			chip.appendChild( remove );

			const hidden = document.createElement( 'input' );
			hidden.type  = 'hidden';
			hidden.name  = 'speaker_ids[]';
			hidden.value = id;
			chip.appendChild( hidden );

			acChips.appendChild( chip );
		}
		function hideDropdown() {
			if ( ! acDropdown ) return;
			acDropdown.hidden = true;
			acDropdown.innerHTML = '';
			acHighlightIdx = -1;
			acVisibleMatches = [];
		}
		function renderDropdown( matches ) {
			if ( ! acDropdown ) return;
			acDropdown.innerHTML = '';
			acVisibleMatches = matches;
			if ( ! matches.length ) {
				const empty = document.createElement( 'li' );
				empty.className = 'de-speakers-dropdown-empty';
				empty.textContent = 'No matches';
				acDropdown.appendChild( empty );
			} else {
				matches.forEach( function ( sp, idx ) {
					const li = document.createElement( 'li' );
					li.className = 'de-speakers-dropdown-item';
					li.setAttribute( 'role', 'option' );
					li.setAttribute( 'data-speaker-id', sp.id );
					li.textContent = sp.name;
					li.addEventListener( 'mousedown', function ( ev ) {
						ev.preventDefault(); // keep input focused
						pickFromDropdown( idx );
					} );
					acDropdown.appendChild( li );
				} );
			}
			acDropdown.hidden = false;
			acHighlightIdx = matches.length ? 0 : -1;
			updateHighlight();
		}
		function updateHighlight() {
			if ( ! acDropdown ) return;
			const items = acDropdown.querySelectorAll( '.de-speakers-dropdown-item' );
			items.forEach( function ( li, i ) {
				li.classList.toggle( 'is-highlighted', i === acHighlightIdx );
			} );
		}
		function pickFromDropdown( idx ) {
			if ( ! acVisibleMatches[ idx ] ) return;
			addSpeakerChip( acVisibleMatches[ idx ].id );
			if ( acInput ) acInput.value = '';
			hideDropdown();
			if ( acInput ) acInput.focus();
		}
		function filterAndRender() {
			if ( ! acInput ) return;
			const q = acInput.value.trim().toLowerCase();
			if ( q === '' ) { hideDropdown(); return; }
			const selected = speakersSelectedIds();
			const matches = acData.filter( function ( sp ) {
				return selected.indexOf( sp.id ) === -1 && sp.search.indexOf( q ) !== -1;
			} ).slice( 0, 10 );
			renderDropdown( matches );
		}
		if ( acInput ) {
			acInput.addEventListener( 'input', filterAndRender );
			acInput.addEventListener( 'focus', filterAndRender );
			acInput.addEventListener( 'keydown', function ( ev ) {
				if ( acDropdown && acDropdown.hidden ) return;
				if ( ev.key === 'ArrowDown' ) {
					ev.preventDefault();
					acHighlightIdx = Math.min( acHighlightIdx + 1, acVisibleMatches.length - 1 );
					updateHighlight();
				} else if ( ev.key === 'ArrowUp' ) {
					ev.preventDefault();
					acHighlightIdx = Math.max( acHighlightIdx - 1, 0 );
					updateHighlight();
				} else if ( ev.key === 'Enter' ) {
					if ( acHighlightIdx >= 0 ) {
						ev.preventDefault();
						pickFromDropdown( acHighlightIdx );
					}
				} else if ( ev.key === 'Escape' ) {
					hideDropdown();
				} else if ( ev.key === 'Backspace' && acInput.value === '' ) {
					// Remove last chip on backspace in empty input
					if ( acChips ) {
						const chips = acChips.querySelectorAll( '.de-speakers-chip' );
						if ( chips.length ) chips[ chips.length - 1 ].remove();
					}
				}
			} );
		}
		// Close dropdown on click outside
		document.addEventListener( 'click', function ( ev ) {
			if ( ! acRoot ) return;
			if ( ! acRoot.contains( ev.target ) ) hideDropdown();
		} );

		function openModal( session ) {
			if ( ! modal || ! form ) return;
			form.reset();
			form.elements.id.value              = session ? session.id              : '';
			form.elements.day_id.value          = session ? session.day_id          : ( initialDay || '' );
			form.elements.start_time.value      = session ? trimSec( session.start_time ) : '';
			form.elements.end_time.value        = session ? trimSec( session.end_time   ) : '';
			form.elements.title.value           = session ? session.title           : '';
			form.elements.session_type_id.value = session ? ( session.session_type_id || '' ) : '';
			form.elements.venue_id.value        = session ? ( session.venue_id        || '' ) : '';
			form.elements.description.value     = session ? ( session.description     || '' ) : '';
			form.elements.session_level.value   = session ? session.session_level   : 'master';

			refreshSubVenues( form.elements.venue_id.value, session ? ( session.sub_venue_id || '' ) : '' );

			// Populate speakers autocomplete from session.speaker_ids (or empty)
			const selectedSpeakers = session && session.speaker_ids ? session.speaker_ids : [];
			setSpeakersAutocomplete( selectedSpeakers );

			// Default role: pick the first non-null role from the role map if editing
			form.elements.default_role_id.value = '';
			if ( session && session.speaker_role_map ) {
				for ( const k in session.speaker_role_map ) {
					if ( session.speaker_role_map[ k ] ) {
						form.elements.default_role_id.value = session.speaker_role_map[ k ];
						break;
					}
				}
			}

			toggleLevel();
			refreshParentOptions( form.elements.day_id.value, session ? ( session.parent_id || '' ) : '' );

			titleEl.textContent = session ? 'Edit session' : 'New session';
			if ( typeof modal.showModal === 'function' ) modal.showModal();
			else modal.setAttribute( 'open', '' );
		}
		function closeModal() {
			if ( ! modal ) return;
			if ( typeof modal.close === 'function' ) modal.close();
			else modal.removeAttribute( 'open' );
		}
		function trimSec( hms ) {
			if ( ! hms ) return '';
			return hms.length >= 5 ? hms.substring( 0, 5 ) : hms;
		}

		const createBtn = page.querySelector( '[data-de-action="open-create-session"]' );
		if ( createBtn ) createBtn.addEventListener( 'click', function () { openModal( null ); } );
		if ( closeBtn )  closeBtn.addEventListener( 'click', closeModal );
		if ( cancelBtn ) cancelBtn.addEventListener( 'click', closeModal );

		if ( form ) {
			form.addEventListener( 'submit', function ( ev ) {
				ev.preventDefault();
				const speaker_ids = Array.prototype.slice.call(
					modal.querySelectorAll( '[data-de-chips] input[name="speaker_ids[]"]' )
				).map( function ( inp ) { return inp.value; } );

				const payload = {
					id:              form.elements.id.value,
					event_id:        activeEvent,
					day_id:          form.elements.day_id.value,
					start_time:      form.elements.start_time.value,
					end_time:        form.elements.end_time.value,
					title:           form.elements.title.value.trim(),
					session_type_id: form.elements.session_type_id.value,
					venue_id:        form.elements.venue_id.value,
					sub_venue_id:    subVenueSelect ? subVenueSelect.value : '',
					session_level:   form.elements.session_level.value,
					parent_id:       form.elements.session_level.value === 'child' ? parentSelect.value : '',
					description:     form.elements.description.value,
					speaker_ids:     speaker_ids,
					default_role_id: form.elements.default_role_id.value,
				};
				if ( ! payload.title ) { DE.feedback( 'Title is required.', 'error' ); return; }
				if ( ! payload.day_id ) { DE.feedback( 'Day is required.', 'error' ); return; }

				DE.api( 'digitone_events_session_save', payload )
					.then( function ( data ) {
						DE.feedback( data.message || DE.i18n.saved, 'success' );
						closeModal();
						setTimeout( function () { window.location.reload(); }, 400 );
					} )
					.catch( function ( err ) { DE.feedback( err.message || DE.i18n.error, 'error' ); } );
			} );
		}

		// Row actions
		if ( tableBody ) {
			tableBody.addEventListener( 'click', function ( ev ) {
				const target = ev.target.closest( '[data-de-action]' );
				if ( ! target ) return;
				const row = target.closest( 'tr' );
				if ( ! row ) return;
				const id = row.getAttribute( 'data-id' );
				if ( ! id ) return;
				const action = target.getAttribute( 'data-de-action' );

				if ( action === 'edit-session' ) {
					ev.preventDefault();
					DE.api( 'digitone_events_session_get', { id: id } )
						.then( function ( data ) { openModal( data.session ); } )
						.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
				}
				if ( action === 'delete-session' ) {
					ev.preventDefault();
					if ( ! window.confirm( DE.i18n.confirmDelete ) ) return;
					DE.api( 'digitone_events_session_delete', { id: id } )
						.then( function ( data ) {
							DE.feedback( data.message, 'success' );
							row.remove();
						} )
						.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
				}
			} );
		}

		// Bulk select
		if ( checkAll && tableBody ) {
			checkAll.addEventListener( 'change', function () {
				tableBody.querySelectorAll( '.de-row-check' ).forEach( function ( b ) { b.checked = checkAll.checked; } );
				updateBulk();
			} );
		}
		if ( tableBody ) {
			tableBody.addEventListener( 'change', function ( ev ) {
				if ( ev.target.classList.contains( 'de-row-check' ) ) updateBulk();
			} );
		}
		if ( bulkBtn ) {
			bulkBtn.addEventListener( 'click', function () {
				const ids = Array.prototype.slice.call(
					tableBody.querySelectorAll( '.de-row-check:checked' )
				).map( function ( b ) { return b.value; } );
				if ( ! ids.length ) return;
				if ( ! window.confirm( DE.i18n.confirmDelete ) ) return;
				DE.api( 'digitone_events_session_bulk_delete', { ids: ids } )
					.then( function ( data ) {
						DE.feedback( data.message, 'success' );
						setTimeout( function () { window.location.reload(); }, 400 );
					} )
					.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
			} );
		}
		function updateBulk() {
			if ( ! bulkBtn || ! tableBody ) return;
			bulkBtn.disabled = tableBody.querySelectorAll( '.de-row-check:checked' ).length === 0;
		}
	}

	/* ============================================================ */
	/* SESSION TYPES TAB                                            */
	/* ============================================================ */
	function initTypesTab( page ) {
		const form  = page.querySelector( '[data-de-form="session-type-quick-add"]' );
		const table = page.querySelector( '.de-session-types-table tbody' );

		if ( form ) {
			form.addEventListener( 'submit', function ( ev ) {
				ev.preventDefault();
				const name  = form.elements.name.value.trim();
				const icon  = form.elements.icon.value.trim();
				const color = form.elements.color.value;
				if ( ! name ) return;
				DE.api( 'digitone_events_session_type_save', { name: name, icon: icon, color: color } )
					.then( function ( data ) {
						DE.feedback( data.message, 'success' );
						setTimeout( function () { window.location.reload(); }, 300 );
					} )
					.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
			} );
		}

		if ( ! table ) return;

		table.addEventListener( 'click', function ( ev ) {
			const target = ev.target.closest( '[data-de-action]' );
			if ( ! target ) return;
			const row = target.closest( 'tr' );
			if ( ! row ) return;
			const id = row.getAttribute( 'data-id' );
			if ( ! id ) return;
			const action = target.getAttribute( 'data-de-action' );

			const displayBadge = row.querySelector( '[data-display-badge]' );
			const displaySwatch = row.querySelector( '[data-display-swatch]' );
			const displayIcon = row.querySelector( '[data-display-icon]' );
			const editInput  = row.querySelector( '.de-edit-input' );
			const editColor  = row.querySelector( '.de-edit-color' );
			const editIcon   = row.querySelector( '.de-edit-icon' );
			const editBtn    = row.querySelector( '[data-de-action="edit-inline"]' );
			const saveBtn    = row.querySelector( '.de-save-btn' );
			const cancelBtn  = row.querySelector( '.de-cancel-btn' );

			function setEdit( on ) {
				[ displayBadge, displaySwatch, displayIcon, editBtn ].forEach( function ( el ) { if ( el ) el.hidden = on; } );
				[ editInput, editColor, editIcon, saveBtn, cancelBtn ].forEach( function ( el ) { if ( el ) el.hidden = ! on; } );
				if ( on && editInput ) editInput.focus();
			}

			if ( action === 'edit-inline' ) {
				ev.preventDefault();
				setEdit( true );
			}
			if ( action === 'cancel-inline' ) {
				ev.preventDefault();
				if ( editInput ) editInput.value = row.getAttribute( 'data-name' );
				if ( editColor ) editColor.value = row.getAttribute( 'data-color' ) || '#cccccc';
				if ( editIcon )  editIcon.value  = row.getAttribute( 'data-icon' )  || '';
				setEdit( false );
			}
			if ( action === 'save-inline' ) {
				ev.preventDefault();
				const newName = editInput ? editInput.value.trim() : '';
				if ( ! newName ) { DE.feedback( 'Name cannot be empty.', 'error' ); return; }
				DE.api( 'digitone_events_session_type_save', {
					id:    id,
					name:  newName,
					icon:  editIcon  ? editIcon.value.trim() : '',
					color: editColor ? editColor.value      : '',
				} )
					.then( function () {
						DE.feedback( 'Saved.', 'success' );
						setTimeout( function () { window.location.reload(); }, 300 );
					} )
					.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
			}
			if ( action === 'delete-session-type' ) {
				ev.preventDefault();
				if ( ! window.confirm( DE.i18n.confirmDelete ) ) return;
				DE.api( 'digitone_events_session_type_delete', { id: id } )
					.then( function ( data ) {
						DE.feedback( data.message, 'success' );
						row.remove();
					} )
					.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
			}
		} );
	}
} )();
