/**
 * DigitOne Events — Events module JS.
 * Handles list interactions: create/edit modal, delete, bulk delete,
 * search filter, status filter, set-active.
 */
( function () {
	'use strict';

	const DE = window.DigitoneEvents;
	if ( ! DE || typeof DE.api !== 'function' ) {
		return;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		const page = document.querySelector( '.digitone-events-page[data-page="events"]' );
		if ( ! page ) {
			return;
		}

		const modal     = page.parentNode.querySelector( '#de-event-modal' );
		const form      = modal ? modal.querySelector( 'form' ) : null;
		const titleEl   = modal ? modal.querySelector( '.de-modal-title' ) : null;
		const closeBtn  = modal ? modal.querySelector( '.de-modal-close' ) : null;
		const cancelBtn = modal ? modal.querySelector( '[data-de-action="cancel"]' ) : null;
		const tableBody = page.querySelector( '.de-events-table tbody' );
		const searchEl  = page.querySelector( '.de-search' );
		const statusEl  = page.querySelector( '.de-filter-status' );
		const checkAll  = page.querySelector( '.de-check-all' );
		const bulkBtn   = page.querySelector( '[data-de-action="bulk-delete"]' );

		function openModal( eventData ) {
			if ( ! modal || ! form ) {
				return;
			}
			form.reset();
			form.elements.id.value           = eventData ? eventData.id          : '';
			form.elements.name.value         = eventData ? eventData.name        : '';
			form.elements.slug.value         = eventData ? eventData.slug        : '';
			form.elements.start_date.value   = eventData ? ( eventData.start_date || '' ) : '';
			form.elements.end_date.value     = eventData ? ( eventData.end_date   || '' ) : '';
			form.elements.status.value       = eventData ? eventData.status      : 'draft';
			form.elements.description.value  = eventData ? ( eventData.description || '' ) : '';
			titleEl.textContent = eventData ? 'Edit event' : 'New event';
			if ( typeof modal.showModal === 'function' ) {
				modal.showModal();
			} else {
				modal.setAttribute( 'open', '' );
			}
		}

		function closeModal() {
			if ( ! modal ) return;
			if ( typeof modal.close === 'function' ) {
				modal.close();
			} else {
				modal.removeAttribute( 'open' );
			}
		}

		// Open Create
		const createBtn = page.querySelector( '[data-de-action="open-create"]' );
		if ( createBtn ) {
			createBtn.addEventListener( 'click', function () {
				openModal( null );
			} );
		}

		if ( closeBtn )  closeBtn.addEventListener( 'click', closeModal );
		if ( cancelBtn ) cancelBtn.addEventListener( 'click', closeModal );

		// Submit
		if ( form ) {
			form.addEventListener( 'submit', function ( ev ) {
				ev.preventDefault();
				const payload = {
					id:          form.elements.id.value,
					name:        form.elements.name.value.trim(),
					slug:        form.elements.slug.value.trim(),
					start_date:  form.elements.start_date.value,
					end_date:    form.elements.end_date.value,
					status:      form.elements.status.value,
					description: form.elements.description.value,
				};
				if ( ! payload.name ) {
					DE.feedback( 'Name is required.', 'error' );
					return;
				}
				DE.api( 'digitone_events_event_save', payload )
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

		// Row actions (event delegation)
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
					DE.api( 'digitone_events_event_get', { id: id } )
						.then( function ( data ) { openModal( data.event ); } )
						.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
				}

				if ( action === 'delete' ) {
					ev.preventDefault();
					if ( ! window.confirm( DE.i18n.confirmDelete ) ) return;
					DE.api( 'digitone_events_event_delete', { id: id } )
						.then( function ( data ) {
							DE.feedback( data.message, 'success' );
							row.remove();
						} )
						.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
				}

				if ( action === 'set-active' ) {
					ev.preventDefault();
					DE.api( 'digitone_events_event_set_active', { id: id } )
						.then( function () {
							DE.feedback( 'Active event updated.', 'success' );
							setTimeout( function () { window.location.reload(); }, 400 );
						} )
						.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
				}
			} );
		}

		// Check-all + bulk delete
		if ( checkAll && tableBody ) {
			checkAll.addEventListener( 'change', function () {
				const boxes = tableBody.querySelectorAll( '.de-row-check' );
				boxes.forEach( function ( b ) { b.checked = checkAll.checked; } );
				updateBulkButton();
			} );
		}
		if ( tableBody ) {
			tableBody.addEventListener( 'change', function ( ev ) {
				if ( ev.target.classList.contains( 'de-row-check' ) ) {
					updateBulkButton();
				}
			} );
		}
		if ( bulkBtn ) {
			bulkBtn.addEventListener( 'click', function () {
				const checked = Array.prototype.slice.call(
					tableBody.querySelectorAll( '.de-row-check:checked' )
				).map( function ( b ) { return b.value; } );
				if ( ! checked.length ) return;
				if ( ! window.confirm( DE.i18n.confirmDelete ) ) return;
				DE.api( 'digitone_events_event_bulk_delete', { ids: checked } )
					.then( function ( data ) {
						DE.feedback( data.message, 'success' );
						setTimeout( function () { window.location.reload(); }, 400 );
					} )
					.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
			} );
		}

		function updateBulkButton() {
			if ( ! bulkBtn || ! tableBody ) return;
			const count = tableBody.querySelectorAll( '.de-row-check:checked' ).length;
			bulkBtn.disabled = count === 0;
		}

		// Live search + status filter (client-side)
		function applyFilters() {
			if ( ! tableBody ) return;
			const q       = ( searchEl ? searchEl.value : '' ).trim().toLowerCase();
			const status  = statusEl ? statusEl.value : '';
			const rows    = tableBody.querySelectorAll( 'tr[data-id]' );
			rows.forEach( function ( row ) {
				let show = true;
				if ( status && row.getAttribute( 'data-status' ) !== status ) show = false;
				if ( q ) {
					const text = row.textContent.toLowerCase();
					if ( text.indexOf( q ) === -1 ) show = false;
				}
				row.style.display = show ? '' : 'none';
			} );
		}
		if ( searchEl ) searchEl.addEventListener( 'input', applyFilters );
		if ( statusEl ) statusEl.addEventListener( 'change', applyFilters );
	} );
} )();
