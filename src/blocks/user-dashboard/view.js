/**
 * User Dashboard — Interactivity API view module.
 *
 * Handles tab switching, URL hash sync, keyboard nav, and listing menus.
 *
 * @package WBListora
 */

import { store, getContext, getElement } from '@wordpress/interactivity';
import '../../interactivity/store.js';

store( 'listora/directory', {
	actions: {
		/**
		 * Switch dashboard tab.
		 */
		switchDashTab() {
			const ctx = getContext();
			const tabId = ctx.tabId;
			const el = getElement();
			const dashboard = el.ref.closest( '.listora-dashboard' );
			if ( ! dashboard ) return;

			// Deactivate all tabs and panels.
			dashboard.querySelectorAll( '.listora-dashboard__nav-item, .listora-dashboard__tab' ).forEach( ( tab ) => {
				tab.classList.remove( 'is-active' );
				tab.setAttribute( 'aria-selected', 'false' );
			} );
			dashboard.querySelectorAll( '.listora-dashboard__panel' ).forEach( ( panel ) => {
				panel.hidden = true;
			} );

			// Activate clicked tab and panel.
			const tab = dashboard.querySelector( `#dash-tab-${ tabId }` );
			const panel = dashboard.querySelector( `#dash-panel-${ tabId }` );

			if ( tab ) {
				tab.classList.add( 'is-active' );
				tab.setAttribute( 'aria-selected', 'true' );
			}
			if ( panel ) {
				panel.hidden = false;
			}

			// Update URL hash.
			if ( typeof window !== 'undefined' ) {
				window.history.replaceState( null, '', `#${ tabId }` );
			}
		},

		/**
		 * Toggle listing three-dot menu.
		 */
		toggleListingMenu() {
			const el = getElement();
			const dropdown = el.ref.closest( '.listora-dashboard__menu-wrap' )?.querySelector( '.listora-dashboard__menu-dropdown' );
			if ( ! dropdown ) return;

			const isOpen = ! dropdown.hidden;

			// Close all other open menus first.
			document.querySelectorAll( '.listora-dashboard__menu-dropdown' ).forEach( ( d ) => {
				d.hidden = true;
			} );

			dropdown.hidden = isOpen;

			// Close on outside click.
			if ( ! isOpen ) {
				const closeHandler = ( e ) => {
					if ( ! dropdown.contains( e.target ) && ! el.ref.contains( e.target ) ) {
						dropdown.hidden = true;
						document.removeEventListener( 'click', closeHandler );
					}
				};
				setTimeout( () => document.addEventListener( 'click', closeHandler ), 0 );
			}
		},
	},

	callbacks: {
		/**
		 * On dashboard init — restore tab from URL hash, setup keyboard nav.
		 */
		onDashboardInit() {
			if ( typeof window === 'undefined' ) return;

			const dashboard = document.querySelector( '.listora-dashboard' );
			if ( ! dashboard ) return;

			// Restore tab from URL hash.
			const hash = window.location.hash.replace( '#', '' );
			if ( hash ) {
				const tab = dashboard.querySelector( `#dash-tab-${ hash }` );
				if ( tab ) {
					tab.click();
				}
			}

			// Keyboard navigation for sidebar items.
			const sidebar = dashboard.querySelector( '.listora-dashboard__sidebar' );
			if ( sidebar ) {
				sidebar.addEventListener( 'keydown', ( e ) => {
					if ( e.key !== 'ArrowDown' && e.key !== 'ArrowUp' ) return;
					e.preventDefault();

					const items = Array.from( sidebar.querySelectorAll( '.listora-dashboard__nav-item' ) );
					const current = document.activeElement;
					const idx = items.indexOf( current );

					let next;
					if ( e.key === 'ArrowDown' ) {
						next = items[ Math.min( idx + 1, items.length - 1 ) ];
					} else {
						next = items[ Math.max( idx - 1, 0 ) ];
					}

					if ( next ) {
						next.focus();
					}
				} );
			}
		},
	},
} );
