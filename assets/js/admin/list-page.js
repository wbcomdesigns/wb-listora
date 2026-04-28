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

	// Delete confirmation. Skip elements that carry a more specific
	// confirm handler (e.g. .listora-delete-type in type-editor.js)
	// so the user does not see two stacked confirmation dialogs.
	document.querySelectorAll( '.listora-action-link--danger' ).forEach( function ( btn ) {
		if ( btn.matches( '.listora-delete-type' ) ) {
			return;
		}
		btn.addEventListener( 'click', function ( e ) {
			if ( btn.dataset.listoraConfirmed === '1' ) {
				return;
			}
			e.preventDefault();
			var href = btn.getAttribute( 'href' );
			window.listoraConfirm( {
				title: btn.dataset.confirmTitle || 'Delete item?',
				message: btn.dataset.confirmMessage || 'This cannot be undone.',
				confirmLabel: 'Delete',
				tone: 'danger',
			} ).then( function ( ok ) {
				if ( ! ok ) {
					return;
				}
				btn.dataset.listoraConfirmed = '1';
				if ( href ) {
					window.location.href = href;
				} else {
					btn.click();
				}
			} );
		} );
	} );
} )();
