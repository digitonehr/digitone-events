/**
 * DigitOne Events — Venues module JS.
 * List with create/edit modal, sub-venue support, bulk delete.
 */
( function () {
	'use strict';

	const DE = window.DigitoneEvents;
	if ( ! DE || typeof DE.api !== 'function' ) return;

	document.addEventListener( 'DOMContentLoaded', function () {
		const page = document.querySelector( '.digitone-events-page[data-page="venues"]' );
		if ( ! page ) return;

		const activeEvent = page.getAttribute( 'data-active-event' );
		if ( ! activeEvent ) return;

		const modal       = document.getElementById( 'de-venue-modal' );
		const form        = modal ? modal.querySelector( 'form' ) : null;
		const titleEl     = modal ? modal.querySelector( '.de-modal-title' ) : null;
		const closeBtn    = modal ? modal.querySelector( '.de-modal-close' ) : null;
		const cancelBtn   = modal ? modal.querySelector( '[data-de-action="cancel"]' ) : null;
		const typeSelect  = modal ? modal.querySelector( '.de-venue-type-select' ) : null;
		const parentField = modal ? modal.querySelector( '.de-parent-field' ) : null;
		const tableBody   = page.querySelector( '.de-venues-table tbody' );
		const checkAll    = page.querySelector( '.de-check-all' );
		const bulkBtn     = page.querySelector( '[data-de-action="bulk-delete"]' );

		function toggleParentField() {
			if ( ! typeSelect || ! parentField ) return;
			parentField.style.display = typeSelect.value === 'sub-venue' ? '' : 'none';
		}
		if ( typeSelect ) typeSelect.addEventListener( 'change', toggleParentField );

		function openModal( venue, forceSubParent ) {
			if ( ! modal || ! form ) return;
			form.reset();
			form.elements.id.value         = venue ? venue.id   : '';
			form.elements.name.value       = venue ? venue.name : '';
			form.elements.address.value    = venue ? ( venue.address || '' ) : '';
			form.elements.venue_type.value = venue ? venue.venue_type : ( forceSubParent ? 'sub-venue' : 'primary' );
			form.elements.parent_id.value  = venue ? ( venue.parent_id || '' ) : ( forceSubParent || '' );
			toggleParentField();
			titleEl.textContent = venue ? 'Edit venue' : ( forceSubParent ? 'New sub-venue' : 'New venue' );
			if ( typeof modal.showModal === 'function' ) modal.showModal();
			else modal.setAttribute( 'open', '' );
		}
		function closeModal() {
			if ( ! modal ) return;
			if ( typeof modal.close === 'function' ) modal.close();
			else modal.removeAttribute( 'open' );
		}

		const createBtn = page.querySelector( '[data-de-action="open-create"]' );
		if ( createBtn ) createBtn.addEventListener( 'click', function () { openModal( null, null ); } );
		if ( closeBtn )  closeBtn.addEventListener( 'click', closeModal );
		if ( cancelBtn ) cancelBtn.addEventListener( 'click', closeModal );

		if ( form ) {
			form.addEventListener( 'submit', function ( ev ) {
				ev.preventDefault();
				const payload = {
					id:         form.elements.id.value,
					event_id:   activeEvent,
					name:       form.elements.name.value.trim(),
					address:    form.elements.address.value,
					venue_type: form.elements.venue_type.value,
					parent_id:  form.elements.parent_id.value,
				};
				if ( ! payload.name ) {
					DE.feedback( 'Name is required.', 'error' );
					return;
				}
				if ( payload.venue_type === 'sub-venue' && ! payload.parent_id ) {
					DE.feedback( 'Sub-venue requires a parent.', 'error' );
					return;
				}
				DE.api( 'digitone_events_venue_save', payload )
					.then( function ( data ) {
						DE.feedback( data.message || DE.i18n.saved, 'success' );
						closeModal();
						setTimeout( function () { window.location.reload(); }, 400 );
					} )
					.catch( function ( err ) {
						DE.feedback( err.message || DE.i18n.error, 'error' );
					} );
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

				if ( action === 'edit' ) {
					ev.preventDefault();
					DE.api( 'digitone_events_venue_get', { id: id } )
						.then( function ( data ) { openModal( data.venue, null ); } )
						.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
				}
				if ( action === 'delete' ) {
					ev.preventDefault();
					const isPrimary = row.getAttribute( 'data-type' ) === 'primary';
					const msg = isPrimary ? 'Delete this venue AND all its sub-venues?' : DE.i18n.confirmDelete;
					if ( ! window.confirm( msg ) ) return;
					DE.api( 'digitone_events_venue_delete', { id: id } )
						.then( function ( data ) {
							DE.feedback( data.message, 'success' );
							setTimeout( function () { window.location.reload(); }, 400 );
						} )
						.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
				}
				if ( action === 'add-sub' ) {
					ev.preventDefault();
					openModal( null, target.getAttribute( 'data-parent' ) );
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
				DE.api( 'digitone_events_venue_bulk_delete', { ids: ids } )
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
	} );
} )();
