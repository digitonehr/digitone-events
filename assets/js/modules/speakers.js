/**
 * DigitOne Events — Speakers tab JS.
 * Handles three tabs: Speakers (full CRUD + photo picker + roles), Titles (inline CRUD), Roles (inline CRUD + color).
 */
( function () {
	'use strict';

	const DE = window.DigitoneEvents;
	if ( ! DE || typeof DE.api !== 'function' ) return;

	document.addEventListener( 'DOMContentLoaded', function () {
		const page = document.querySelector( '.digitone-events-page[data-page="speakers"]' );
		if ( ! page ) return;
		const activeEvent = page.getAttribute( 'data-active-event' );
		if ( ! activeEvent ) return;
		const currentTab = page.getAttribute( 'data-tab' );

		if ( currentTab === 'speakers' ) initSpeakersTab( page, activeEvent );
		if ( currentTab === 'titles' )   initTitlesTab( page );
		if ( currentTab === 'roles' )    initRolesTab( page );
	} );

	/* ============================================================ */
	/* SPEAKERS TAB                                                 */
	/* ============================================================ */
	function initSpeakersTab( page, activeEvent ) {
		const modal       = document.getElementById( 'de-speaker-modal' );
		const form        = modal ? modal.querySelector( 'form' ) : null;
		const titleEl     = modal ? modal.querySelector( '.de-modal-title' ) : null;
		const closeBtn    = modal ? modal.querySelector( '.de-modal-close' ) : null;
		const cancelBtn   = modal ? modal.querySelector( '[data-de-action="cancel-speaker"]' ) : null;
		const photoUrl    = modal ? modal.querySelector( '.de-photo-url' ) : null;
		const photoPick   = modal ? modal.querySelector( '[data-de-action="pick-photo"]' ) : null;
		const photoClear  = modal ? modal.querySelector( '[data-de-action="clear-photo"]' ) : null;
		const photoPreview= modal ? modal.querySelector( '.de-photo-preview' ) : null;
		const tableBody   = page.querySelector( '.de-speakers-table tbody' );
		const searchEl    = page.querySelector( '.de-search' );
		const checkAll    = page.querySelector( '.de-check-all' );
		const bulkBtn     = page.querySelector( '[data-de-action="bulk-delete-speakers"]' );

		function updatePhotoPreview() {
			if ( ! photoPreview ) return;
			photoPreview.innerHTML = '';
			const url = photoUrl.value.trim();
			if ( url ) {
				const img = document.createElement( 'img' );
				img.src = url;
				img.alt = '';
				photoPreview.appendChild( img );
			}
		}
		if ( photoUrl ) photoUrl.addEventListener( 'input', updatePhotoPreview );
		if ( photoClear ) photoClear.addEventListener( 'click', function () { photoUrl.value = ''; updatePhotoPreview(); } );

		// WP media library
		if ( photoPick && typeof wp !== 'undefined' && wp.media ) {
			let frame = null;
			photoPick.addEventListener( 'click', function () {
				if ( ! frame ) {
					frame = wp.media( {
						title: 'Choose speaker photo',
						button: { text: 'Use this image' },
						multiple: false,
						library: { type: 'image' },
					} );
					frame.on( 'select', function () {
						const attachment = frame.state().get( 'selection' ).first().toJSON();
						photoUrl.value = attachment.url;
						updatePhotoPreview();
					} );
				}
				frame.open();
			} );
		}

		function openModal( speaker ) {
			if ( ! modal || ! form ) return;
			form.reset();
			form.elements.id.value         = speaker ? speaker.id : '';
			form.elements.title_id.value   = speaker ? ( speaker.title_id   || '' ) : '';
			form.elements.first_name.value = speaker ? ( speaker.first_name || '' ) : '';
			form.elements.last_name.value  = speaker ? ( speaker.last_name  || '' ) : '';
			form.elements.email.value      = speaker ? ( speaker.email      || '' ) : '';
			form.elements.photo_url.value  = speaker ? ( speaker.photo_url  || '' ) : '';
			form.elements.bio.value        = speaker ? ( speaker.bio        || '' ) : '';
			updatePhotoPreview();

			// Reset role checkboxes
			const checkboxes = modal.querySelectorAll( 'input[name="role_ids[]"]' );
			const selected   = speaker && speaker.role_ids ? speaker.role_ids : [];
			checkboxes.forEach( function ( cb ) { cb.checked = selected.indexOf( cb.value ) !== -1; } );

			titleEl.textContent = speaker ? 'Edit speaker' : 'New speaker';
			if ( typeof modal.showModal === 'function' ) modal.showModal();
			else modal.setAttribute( 'open', '' );
		}
		function closeModal() {
			if ( ! modal ) return;
			if ( typeof modal.close === 'function' ) modal.close();
			else modal.removeAttribute( 'open' );
		}

		const createBtn = page.querySelector( '[data-de-action="open-create-speaker"]' );
		if ( createBtn ) createBtn.addEventListener( 'click', function () { openModal( null ); } );
		if ( closeBtn )  closeBtn.addEventListener( 'click', closeModal );
		if ( cancelBtn ) cancelBtn.addEventListener( 'click', closeModal );

		if ( form ) {
			form.addEventListener( 'submit', function ( ev ) {
				ev.preventDefault();
				const role_ids = Array.prototype.slice.call(
					modal.querySelectorAll( 'input[name="role_ids[]"]:checked' )
				).map( function ( cb ) { return cb.value; } );

				const payload = {
					id:         form.elements.id.value,
					event_id:   activeEvent,
					title_id:   form.elements.title_id.value,
					first_name: form.elements.first_name.value.trim(),
					last_name:  form.elements.last_name.value.trim(),
					email:      form.elements.email.value.trim(),
					photo_url:  form.elements.photo_url.value.trim(),
					bio:        form.elements.bio.value,
					role_ids:   role_ids,
				};
				if ( ! payload.first_name && ! payload.last_name ) {
					DE.feedback( 'At least a first or last name is required.', 'error' );
					return;
				}
				DE.api( 'digitone_events_speaker_save', payload )
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

				if ( action === 'edit-speaker' ) {
					ev.preventDefault();
					DE.api( 'digitone_events_speaker_get', { id: id } )
						.then( function ( data ) { openModal( data.speaker ); } )
						.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
				}
				if ( action === 'delete-speaker' ) {
					ev.preventDefault();
					if ( ! window.confirm( DE.i18n.confirmDelete ) ) return;
					DE.api( 'digitone_events_speaker_delete', { id: id } )
						.then( function ( data ) {
							DE.feedback( data.message, 'success' );
							row.remove();
						} )
						.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
				}
			} );
		}

		// Bulk + search
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
				DE.api( 'digitone_events_speaker_bulk_delete', { ids: ids } )
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
		if ( searchEl ) {
			searchEl.addEventListener( 'input', function () {
				const q = searchEl.value.trim().toLowerCase();
				tableBody.querySelectorAll( 'tr[data-id]' ).forEach( function ( row ) {
					const text = row.textContent.toLowerCase();
					row.style.display = ( ! q || text.indexOf( q ) !== -1 ) ? '' : 'none';
				} );
			} );
		}
	}

	/* ============================================================ */
	/* TITLES TAB                                                   */
	/* ============================================================ */
	function initTitlesTab( page ) {
		const form  = page.querySelector( '[data-de-form="title-quick-add"]' );
		const table = page.querySelector( '.de-titles-table tbody' );

		if ( form ) {
			form.addEventListener( 'submit', function ( ev ) {
				ev.preventDefault();
				const name = form.elements.name.value.trim();
				if ( ! name ) return;
				DE.api( 'digitone_events_title_save', { name: name } )
					.then( function ( data ) {
						DE.feedback( data.message, 'success' );
						setTimeout( function () { window.location.reload(); }, 300 );
					} )
					.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
			} );
		}

		if ( table ) initInlineEditTable( table, 'title' );
	}

	/* ============================================================ */
	/* ROLES TAB                                                    */
	/* ============================================================ */
	function initRolesTab( page ) {
		const form  = page.querySelector( '[data-de-form="role-quick-add"]' );
		const table = page.querySelector( '.de-roles-table tbody' );

		if ( form ) {
			form.addEventListener( 'submit', function ( ev ) {
				ev.preventDefault();
				const name  = form.elements.name.value.trim();
				const color = form.elements.color.value;
				if ( ! name ) return;
				DE.api( 'digitone_events_role_save', { name: name, color: color } )
					.then( function ( data ) {
						DE.feedback( data.message, 'success' );
						setTimeout( function () { window.location.reload(); }, 300 );
					} )
					.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
			} );
		}

		if ( table ) initInlineEditTable( table, 'role' );
	}

	/* ============================================================ */
	/* Shared inline-edit handler for titles and roles tables       */
	/* ============================================================ */
	function initInlineEditTable( tbody, entity ) {
		tbody.addEventListener( 'click', function ( ev ) {
			const target = ev.target.closest( '[data-de-action]' );
			if ( ! target ) return;
			const row = target.closest( 'tr' );
			if ( ! row ) return;
			const id = row.getAttribute( 'data-id' );
			if ( ! id ) return;
			const action = target.getAttribute( 'data-de-action' );

			const displayName = row.querySelector( '.de-display-name, [data-display-badge]' );
			const displaySwatch = row.querySelector( '[data-display-swatch]' );
			const editInput   = row.querySelector( '.de-edit-input' );
			const editColor   = row.querySelector( '.de-edit-color' );
			const editBtn     = row.querySelector( '[data-de-action="edit-inline"]' );
			const saveBtn     = row.querySelector( '.de-save-btn' );
			const cancelBtn   = row.querySelector( '.de-cancel-btn' );

			function enterEdit() {
				if ( displayName ) displayName.hidden = true;
				if ( displaySwatch ) displaySwatch.hidden = true;
				if ( editInput ) editInput.hidden = false;
				if ( editColor ) editColor.hidden = false;
				if ( editBtn ) editBtn.hidden = true;
				if ( saveBtn ) saveBtn.hidden = false;
				if ( cancelBtn ) cancelBtn.hidden = false;
				if ( editInput ) editInput.focus();
			}
			function leaveEdit() {
				if ( displayName ) displayName.hidden = false;
				if ( displaySwatch ) displaySwatch.hidden = false;
				if ( editInput ) editInput.hidden = true;
				if ( editColor ) editColor.hidden = true;
				if ( editBtn ) editBtn.hidden = false;
				if ( saveBtn ) saveBtn.hidden = true;
				if ( cancelBtn ) cancelBtn.hidden = true;
			}

			if ( action === 'edit-inline' ) {
				ev.preventDefault();
				enterEdit();
			}
			if ( action === 'cancel-inline' ) {
				ev.preventDefault();
				if ( editInput ) editInput.value = row.getAttribute( 'data-name' );
				if ( editColor ) editColor.value = row.getAttribute( 'data-color' ) || '#cccccc';
				leaveEdit();
			}
			if ( action === 'save-inline' ) {
				ev.preventDefault();
				const newName = editInput ? editInput.value.trim() : '';
				if ( ! newName ) {
					DE.feedback( 'Name cannot be empty.', 'error' );
					return;
				}
				const payload = { id: id, name: newName };
				if ( editColor ) payload.color = editColor.value;

				const action_name = entity === 'role' ? 'digitone_events_role_save' : 'digitone_events_title_save';
				DE.api( action_name, payload )
					.then( function () {
						DE.feedback( 'Saved.', 'success' );
						setTimeout( function () { window.location.reload(); }, 300 );
					} )
					.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
			}
			if ( action === 'delete-' + entity ) {
				ev.preventDefault();
				if ( ! window.confirm( DE.i18n.confirmDelete ) ) return;
				const action_name = entity === 'role' ? 'digitone_events_role_delete' : 'digitone_events_title_delete';
				DE.api( action_name, { id: id } )
					.then( function ( data ) {
						DE.feedback( data.message, 'success' );
						row.remove();
					} )
					.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
			}
		} );
	}
} )();
