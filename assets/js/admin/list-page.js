/**
 * WB Listora — List Page JS
 *
 * Behaviour for the shared admin list table (Admin_Table) and the older
 * list screens still being moved onto it: select-all, confirm dialogs, the
 * row menu, and the detail drawer. Everything works without this file —
 * links still navigate, menus still open — it adds the confirm step and
 * the polish.
 *
 * @package WBListora
 */
( function () {
	'use strict';

	// Select all checkbox.
	document.addEventListener( 'change', function ( e ) {
		var all = e.target.closest( '.listora-table__select-all' );
		if ( ! all ) {
			return;
		}
		all.closest( '.listora-table' ).querySelectorAll( 'input[type="checkbox"][name="ids[]"]' ).forEach( function ( cb ) {
			cb.checked = all.checked;
		} );
	} );

	// Confirm before a destructive or irreversible action. Any link that
	// names its consequence in data-confirm-message asks first; a danger link
	// without one gets the generic "cannot be undone". Links with a more
	// specific handler (the type editor's delete, Pro's data-listora-confirm-
	// delete) are skipped so no one sees two dialogs.
	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest( 'a.listora-action-link--danger, a[data-confirm-message]' );
		if ( ! link || link.matches( '.listora-delete-type, [data-listora-confirm-delete]' ) || ! window.listoraConfirm ) {
			return;
		}
		e.preventDefault();
		var danger = link.classList.contains( 'listora-action-link--danger' );
		window.listoraConfirm( {
			title: link.dataset.confirmTitle || 'Delete item?',
			message: link.dataset.confirmMessage || 'This cannot be undone.',
			confirmLabel: link.dataset.confirmLabel || 'Delete',
			tone: danger ? 'danger' : 'primary',
		} ).then( function ( ok ) {
			if ( ok ) {
				window.location.href = link.href;
			}
		} );
	} );

	// Row menu: one open at a time; closes on an outside click or Escape.
	document.addEventListener( 'click', function ( e ) {
		document.querySelectorAll( '.listora-row-menu[open]' ).forEach( function ( menu ) {
			if ( ! menu.contains( e.target ) ) {
				menu.removeAttribute( 'open' );
			}
		} );
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' !== e.key ) {
			return;
		}
		var menu = e.target.closest && e.target.closest( '.listora-row-menu[open]' );
		if ( menu ) {
			menu.removeAttribute( 'open' );
			menu.querySelector( 'summary' ).focus();
		}
	} );

	// Detail drawer: a native <dialog>, so focus trapping, Escape and the
	// backdrop come from the browser. A click on the backdrop closes it.
	document.addEventListener( 'click', function ( e ) {
		var opener = e.target.closest( '[data-listora-drawer]' );
		if ( opener ) {
			var drawer = document.getElementById( opener.dataset.listoraDrawer );
			if ( drawer && drawer.showModal ) {
				drawer.showModal();
			}
			return;
		}
		if ( e.target.closest( '[data-listora-drawer-close]' ) ) {
			e.target.closest( 'dialog' ).close();
			return;
		}
		if ( e.target.matches && e.target.matches( 'dialog.listora-drawer' ) ) {
			e.target.close();
		}
	} );
} )();
