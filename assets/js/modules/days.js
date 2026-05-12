/**
 * DigitOne Events — Days module JS.
 * List with create/edit modal, bulk delete, drag-to-reorder.
 */
( function () {
	'use strict';

	const DE = window.DigitoneEvents;
	if ( ! DE || typeof DE.api !== 'function' ) return;

	document.addEventListener( 'DOMContentLoaded', function () {
		const page = document.querySelector( '.digitone-events-page[data-page="days"]' );
		if ( ! page ) return;

		const activeEvent = page.getAttribute( 'data-active-event' );
		if ( ! activeEvent ) return;

		const modal     = document.getElementById( 'de-day-modal' );
		const form      = modal ? modal.querySelector( 'form' ) : null;
		const titleEl   = modal ? modal.querySelector( '.de-modal-title' ) : null;
		const closeBtn  = modal ? modal.querySelector( '.de-modal-close' ) : null;
		const cancelBtn = modal ? modal.querySelector( '[data-de-action="cancel"]' ) : null;
		const tableBody = page.querySelector( '.de-days-tbody' );
		const checkAll  = page.querySelector( '.de-check-all' );
		const bulkBtn   = page.querySelector( '[data-de-action="bulk-delete"]' );

		function openModal( day ) {
			if ( ! modal || ! form ) return;
			form.reset();
			form.elements.id.value         = day ? day.id            : '';
			form.elements.day_date.value   = day ? ( day.day_date    || '' ) : '';
			form.elements.start_time.value = day ? trimSec( day.start_time ) : '';
			form.elements.end_time.value   = day ? trimSec( day.end_time   ) : '';
			form.elements.label.value      = day ? ( day.label       || '' ) : '';
			titleEl.textContent = day ? 'Edit day' : 'New day';
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

		const createBtn = page.querySelector( '[data-de-action="open-create"]' );
		if ( createBtn ) createBtn.addEventListener( 'click', function () { openModal( null ); } );
		if ( closeBtn )  closeBtn.addEventListener( 'click', closeModal );
		if ( cancelBtn ) cancelBtn.addEventListener( 'click', closeModal );

		if ( form ) {
			form.addEventListener( 'submit', function ( ev ) {
				ev.preventDefault();
				const payload = {
					id:         form.elements.id.value,
					event_id:   activeEvent,
					day_date:   form.elements.day_date.value,
					start_time: form.elements.start_time.value,
					end_time:   form.elements.end_time.value,
					label:      form.elements.label.value.trim(),
				};
				if ( ! payload.day_date ) {
					DE.feedback( 'Date is required.', 'error' );
					return;
				}
				DE.api( 'digitone_events_day_save', payload )
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
					DE.api( 'digitone_events_day_get', { id: id } )
						.then( function ( data ) { openModal( data.day ); } )
						.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
				}
				if ( action === 'delete' ) {
					ev.preventDefault();
					if ( ! window.confirm( DE.i18n.confirmDelete ) ) return;
					DE.api( 'digitone_events_day_delete', { id: id } )
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
				DE.api( 'digitone_events_day_bulk_delete', { ids: ids } )
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

		// Drag-to-reorder (HTML5 drag API)
		if ( tableBody ) {
			let dragRow = null;
			tableBody.querySelectorAll( 'tr[data-id]' ).forEach( function ( row ) {
				row.setAttribute( 'draggable', 'true' );
				row.addEventListener( 'dragstart', function ( ev ) {
					dragRow = row;
					row.classList.add( 'is-dragging' );
					ev.dataTransfer.effectAllowed = 'move';
				} );
				row.addEventListener( 'dragend', function () {
					if ( dragRow ) dragRow.classList.remove( 'is-dragging' );
					tableBody.querySelectorAll( '.de-drop-target' ).forEach( function ( r ) { r.classList.remove( 'de-drop-target' ); } );
					dragRow = null;
					persistOrder();
				} );
				row.addEventListener( 'dragover', function ( ev ) {
					ev.preventDefault();
					if ( ! dragRow || dragRow === row ) return;
					const rect = row.getBoundingClientRect();
					const after = ( ev.clientY - rect.top ) > ( rect.height / 2 );
					if ( after ) row.parentNode.insertBefore( dragRow, row.nextSibling );
					else        row.parentNode.insertBefore( dragRow, row );
				} );
			} );
			function persistOrder() {
				const ids = Array.prototype.slice.call(
					tableBody.querySelectorAll( 'tr[data-id]' )
				).map( function ( r ) { return r.getAttribute( 'data-id' ); } );
				if ( ! ids.length ) return;
				DE.api( 'digitone_events_day_reorder', { ids: ids } )
					.then( function () { DE.feedback( 'Order saved.', 'success' ); } )
					.catch( function ( err ) { DE.feedback( err.message, 'error' ); } );
			}
		}
	} );
} )();
