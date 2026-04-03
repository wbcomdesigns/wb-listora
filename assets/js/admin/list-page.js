/**
 * WB Listora — List Page JS
 *
 * Select-all checkbox and delete confirmation for Pattern B list pages.
 *
 * @package WBListora
 */
( function () {
	'use strict';

	// Select all checkbox.
	document.querySelectorAll( '.listora-table__select-all' ).forEach( function ( el ) {
		el.addEventListener( 'change', function () {
			var table = this.closest( '.listora-table' );
			table.querySelectorAll( 'input[type="checkbox"][name="ids[]"]' ).forEach( function ( cb ) {
				cb.checked = el.checked;
			} );
		} );
	} );

	// Delete confirmation.
	document.querySelectorAll( '.listora-action-link--danger' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function ( e ) {
			if ( ! confirm( 'Are you sure you want to delete this item?' ) ) {
				e.preventDefault();
			}
		} );
	} );
} )();
