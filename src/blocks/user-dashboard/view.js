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

/**
 * Resend verification email — wires the dashboard "Resend verification email"
 * buttons rendered next to listings stuck in pending_verification.
 */
function initVerifyResend() {
	document.querySelectorAll( '.listora-dashboard__verify-resend' ).forEach( ( btn ) => {
		if ( btn.dataset.listoraVerifyBound === '1' ) return;
		btn.dataset.listoraVerifyBound = '1';

		btn.addEventListener( 'click', () => {
			const listingId = parseInt( btn.dataset.listingId || '0', 10 );
			if ( ! listingId ) return;

			const wrap = btn.closest( '.listora-dashboard__verify-note' );
			const status = wrap ? wrap.querySelector( '.listora-dashboard__verify-status' ) : null;
			const original = btn.textContent;

			btn.disabled = true;
			btn.textContent = 'Sending…';
			if ( status ) status.hidden = true;

			window.wp.apiFetch( {
				path: '/listora/v1/submission/resend-verification',
				method: 'POST',
				data: { listing_id: listingId },
			} ).then( ( res ) => {
				if ( res && res.sent ) {
					if ( status ) {
						status.hidden = false;
						status.textContent = ' ✓ A fresh verification email is on its way.';
					}
					btn.textContent = 'Sent';
					setTimeout( () => {
						btn.disabled = false;
						btn.textContent = original;
					}, 60000 );
				} else if ( res && res.error === 'rate_limited' ) {
					if ( status ) {
						status.hidden = false;
						status.textContent = ' Please wait ' + ( res.retry_after || 60 ) + 's before resending.';
					}
					btn.textContent = original;
					setTimeout( () => {
						btn.disabled = false;
					}, ( res.retry_after || 60 ) * 1000 );
				} else {
					if ( status ) {
						status.hidden = false;
						status.textContent = ' Could not send the email — try again later.';
					}
					btn.disabled = false;
					btn.textContent = original;
				}
			} ).catch( ( err ) => {
				const data = err && err.data;
				if ( data && data.error === 'rate_limited' ) {
					if ( status ) {
						status.hidden = false;
						status.textContent = ' Please wait ' + ( data.retry_after || 60 ) + 's before resending.';
					}
				} else if ( status ) {
					status.hidden = false;
					status.textContent = ' Could not send the email — try again later.';
				}
				btn.disabled = false;
				btn.textContent = original;
			} );
		} );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initVerifyResend );
} else {
	initVerifyResend();
}
