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

			// Restore tab from URL — `?tab=...` query first (the
			// post-reply submitReply redirect path, the share-a-link
			// path), falling back to `#hash` for legacy bookmarks.
			// Without the query lookup the dashboard panels render
			// with their template-level `hidden` defaults (Reviews,
			// Favorites, Profile all start hidden) and stay hidden
			// after a `?tab=reviews` reload because no JS event ever
			// flips them — that was the regression behind QA card
			// 9842842463 round 4.
			const params  = new URLSearchParams( window.location.search );
			const queryTab = ( params.get( 'tab' ) || '' ).trim();
			const hashTab  = window.location.hash.replace( '#', '' );
			const targetTab = queryTab || hashTab;
			if ( targetTab ) {
				const tab = dashboard.querySelector( `#dash-tab-${ targetTab }` );
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

/**
 * Renewal flow — confirm modal, REST POST, toast feedback.
 *
 * Flow:
 *   1. User clicks .listora-dashboard__renew-btn (or "Renew" menu item).
 *   2. We GET /listings/{id}/renewal-quote and populate the modal.
 *   3. User clicks "Confirm renewal" → POST /listings/{id}/renew.
 *   4. On 200: update DOM, hide button, success toast.
 *   5. On 402: show "Buy more credits" link inside modal.
 */
function initRenewalFlow() {
	const root = document.querySelector( '.listora-dashboard' );
	if ( ! root ) return;
	if ( root.dataset.listoraRenewBound === '1' ) return;
	root.dataset.listoraRenewBound = '1';

	const modal = root.querySelector( '[data-listora-renew-modal]' );
	if ( ! modal ) return;

	const titleEl = modal.querySelector( '.listora-dashboard__renew-modal-listing' );
	const planEl = modal.querySelector( '[data-listora-renew-plan]' );
	const costEl = modal.querySelector( '[data-listora-renew-cost]' );
	const durEl = modal.querySelector( '[data-listora-renew-duration]' );
	const balEl = modal.querySelector( '[data-listora-renew-balance]' );
	const errEl = modal.querySelector( '[data-listora-renew-error]' );
	const confirmBtn = modal.querySelector( '[data-listora-renew-confirm]' );
	const buyBtn = modal.querySelector( '[data-listora-renew-buy]' );
	const toastStack = root.querySelector( '[data-listora-toast-stack]' );

	let activeListingId = 0;
	let activeQuote = null;

	const apiFetch = ( opts ) => {
		if ( window.wp && window.wp.apiFetch ) {
			return window.wp.apiFetch( opts );
		}
		// Minimal fallback.
		return fetch( ( opts.path.startsWith( '/' ) ? '/wp-json' + opts.path : opts.path ), {
			method: opts.method || 'GET',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.wpApiSettings ? window.wpApiSettings.nonce : '' },
			credentials: 'same-origin',
			body: opts.data ? JSON.stringify( opts.data ) : undefined,
		} ).then( async ( res ) => {
			const body = await res.json().catch( () => ( {} ) );
			if ( ! res.ok ) {
				const err = new Error( body.message || res.statusText );
				err.code = body.code;
				err.data = Object.assign( { status: res.status }, body.data || {} );
				throw err;
			}
			return body;
		} );
	};

	const showToast = ( message, variant = 'success' ) => {
		if ( ! toastStack ) return;
		const toast = document.createElement( 'div' );
		toast.className = 'listora-dashboard__toast listora-dashboard__toast--' + variant;
		toast.textContent = message;
		toastStack.appendChild( toast );
		setTimeout( () => {
			toast.classList.add( 'is-fading' );
			setTimeout( () => toast.remove(), 320 );
		}, 4000 );
	};

	const closeModal = () => {
		modal.hidden = true;
		errEl.hidden = true;
		errEl.textContent = '';
		buyBtn.hidden = true;
		confirmBtn.disabled = false;
		confirmBtn.textContent = confirmBtn.dataset.originalText || 'Confirm renewal';
		activeListingId = 0;
		activeQuote = null;
	};

	const openModal = async ( listingId, listingTitle ) => {
		activeListingId = parseInt( listingId, 10 );
		if ( ! activeListingId ) return;

		titleEl.textContent = listingTitle || '';
		planEl.textContent = '…';
		costEl.textContent = '…';
		durEl.textContent = '…';
		balEl.textContent = '…';
		errEl.hidden = true;
		errEl.textContent = '';
		buyBtn.hidden = true;
		confirmBtn.disabled = true;
		if ( ! confirmBtn.dataset.originalText ) {
			confirmBtn.dataset.originalText = confirmBtn.textContent;
		}
		modal.hidden = false;

		try {
			const quote = await apiFetch( { path: '/listora/v1/listings/' + activeListingId + '/renewal-quote' } );
			activeQuote = quote;
			planEl.textContent = quote.plan_name ? quote.plan_name : 'Default';
			costEl.textContent = ( quote.cost > 0 ) ? ( quote.cost + ' credits' ) : 'Free';
			durEl.textContent = quote.duration_days + ' days';
			balEl.textContent = quote.balance + ' credits';

			if ( ! quote.can_renew_now ) {
				errEl.hidden = false;
				errEl.textContent = quote.reason || 'Listing not ready to renew yet.';
				confirmBtn.disabled = true;
				return;
			}

			if ( quote.cost > 0 && quote.balance < quote.cost ) {
				errEl.hidden = false;
				errEl.textContent = 'You need ' + quote.cost + ' credits to renew (you have ' + quote.balance + ').';
				confirmBtn.disabled = true;
				if ( quote.purchase_url ) {
					buyBtn.href = quote.purchase_url;
					buyBtn.hidden = false;
				}
				return;
			}

			confirmBtn.disabled = false;
		} catch ( err ) {
			errEl.hidden = false;
			errEl.textContent = ( err && err.message ) ? err.message : 'Could not load renewal pricing.';
		}
	};

	const doRenew = async () => {
		if ( ! activeListingId ) return;
		confirmBtn.disabled = true;
		confirmBtn.textContent = 'Renewing…';
		errEl.hidden = true;
		errEl.textContent = '';
		buyBtn.hidden = true;

		try {
			const res = await apiFetch( {
				path: '/listora/v1/listings/' + activeListingId + '/renew',
				method: 'POST',
				data: {},
			} );

			if ( res && res.renewed ) {
				showToast( res.message || ( 'Renewed until ' + res.new_expiry_human ) );

				// Update DOM: remove "Renew Now" buttons + warning pill on this row,
				// flip the row state to active, replace status pill if expired.
				const row = root.querySelector( '.listora-dashboard__listing-row[data-listora-listing-id="' + activeListingId + '"]' );
				if ( row ) {
					row.dataset.listoraState = 'active';
					row.querySelectorAll( '[data-listora-renew-listing]' ).forEach( ( el ) => el.remove() );
					const expiringPill = row.querySelector( '.listora-dashboard__status--expiring' );
					if ( expiringPill ) expiringPill.remove();
					const expiredPill = row.querySelector( '.listora-dashboard__status--expired' );
					if ( expiredPill ) {
						expiredPill.classList.remove( 'listora-dashboard__status--expired' );
						expiredPill.classList.add( 'listora-dashboard__status--publish' );
						expiredPill.textContent = 'Published';
					}
				}

				closeModal();
				return;
			}

			errEl.hidden = false;
			errEl.textContent = ( res && res.message ) ? res.message : 'Renewal failed.';
			confirmBtn.disabled = false;
			confirmBtn.textContent = confirmBtn.dataset.originalText;
		} catch ( err ) {
			const data = ( err && err.data ) || {};
			confirmBtn.disabled = false;
			confirmBtn.textContent = confirmBtn.dataset.originalText;

			if ( err && err.code === 'insufficient_credits' ) {
				errEl.hidden = false;
				errEl.textContent = err.message || 'Insufficient credits.';
				if ( data.balance !== undefined && balEl ) {
					balEl.textContent = data.balance + ' credits';
				}
				if ( data.purchase_url ) {
					buyBtn.href = data.purchase_url;
					buyBtn.hidden = false;
				}
				return;
			}

			errEl.hidden = false;
			errEl.textContent = ( err && err.message ) ? err.message : 'Renewal failed. Try again.';
			showToast( errEl.textContent, 'error' );
		}
	};

	// Wire renew triggers (delegated so dynamic rows still work).
	root.addEventListener( 'click', ( e ) => {
		const trigger = e.target.closest( '[data-listora-renew-listing]' );
		if ( trigger ) {
			e.preventDefault();
			openModal( trigger.getAttribute( 'data-listora-renew-listing' ), trigger.getAttribute( 'data-listing-title' ) );
			return;
		}
		const closer = e.target.closest( '[data-listora-renew-close]' );
		if ( closer ) {
			e.preventDefault();
			closeModal();
			return;
		}
		if ( e.target.closest( '[data-listora-renew-confirm]' ) ) {
			e.preventDefault();
			doRenew();
		}
	} );

	// Esc to close modal.
	document.addEventListener( 'keydown', ( e ) => {
		if ( ! modal.hidden && e.key === 'Escape' ) {
			closeModal();
		}
	} );

	// Filter dropdown.
	const filter = root.querySelector( '[data-listora-listing-filter]' );
	if ( filter ) {
		filter.addEventListener( 'change', () => {
			const value = filter.value;
			root.querySelectorAll( '.listora-dashboard__listing-row' ).forEach( ( row ) => {
				const state = row.dataset.listoraState || 'active';
				row.style.display = ( value === 'all' || value === state ) ? '' : 'none';
			} );
		} );
	}

	// Auto-open modal when arriving via ?renew={id} (e.g. from email CTA).
	const params = new URLSearchParams( window.location.search );
	const autoRenew = parseInt( params.get( 'renew' ) || '0', 10 );
	if ( autoRenew > 0 ) {
		const row = root.querySelector( '.listora-dashboard__listing-row[data-listora-listing-id="' + autoRenew + '"]' );
		if ( row ) {
			const trigger = row.querySelector( '[data-listora-renew-listing]' );
			const title = trigger ? trigger.getAttribute( 'data-listing-title' ) : '';
			openModal( autoRenew, title );
		}
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initRenewalFlow );
} else {
	initRenewalFlow();
}
