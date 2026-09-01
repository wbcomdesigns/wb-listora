/**
 * User Dashboard — Interactivity API view module.
 *
 * Handles tab switching, URL hash sync, keyboard nav, and listing menus.
 *
 * @package WBListora
 */

import { store, getContext, getElement } from '@wordpress/interactivity';
import '../../interactivity/store.js';
import { t, tf } from '../../utils/i18n.js';
import {
	abortableApiFetch,
	abortableFetch,
	isAbortError,
	NETWORK_SLOW_MESSAGE,
} from '../../utils/abortable-fetch.js';

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

			/*
			 * Rename the document to the tab the member is actually on.
			 *
			 * The dashboard lives on a page called "My Listings", so the
			 * browser tab, the history entry and the screen-reader page
			 * announcement all still said "My Listings" while the member was
			 * reading Credits or Claims (BC 10208510032). Tabs switch client
			 * side, so nothing ever re-titled the document.
			 *
			 * The theme's own <h1 class="entry-title"> also says "My Listings"
			 * and is rendered from the page title — that one is the theme's to
			 * draw and cannot be corrected from here. This fixes the parts the
			 * plugin owns and marks the active panel for assistive tech.
			 */
			if ( typeof document !== 'undefined' && tab ) {
				// The clean label, NOT textContent — the button also carries a
				// count badge, so textContent titled the page "Credits 48.00".
				// Server-stamped from the same map the document title uses.
				const tabLabel = (
					tab.dataset.listoraTabLabel ||
					( tab.textContent || '' ).replace( /\s+/g, ' ' ).trim()
				).trim();
				if ( tabLabel ) {
					if ( ! dashboard.dataset.listoraBaseTitle ) {
						// Everything after the first separator is the site name.
						dashboard.dataset.listoraBaseTitle =
							document.title.split( /\s[–|-]\s/ ).slice( 1 ).join( ' – ' ) || document.title;
					}
					const suffix = dashboard.dataset.listoraBaseTitle;
					document.title = suffix ? `${ tabLabel } – ${ suffix }` : tabLabel;

					// The VISIBLE heading too. Updating only document.title
					// left the member looking at a heading naming a different
					// section than the panel below it (BC 10208510032).
					const heading = dashboard.querySelector( '[data-listora-dash-heading]' );
					if ( heading ) {
						heading.textContent = tabLabel;
					}
				}
			}

			if ( panel ) {
				// Move focus intent to the panel without stealing it: a screen
				// reader user tabbing on lands inside the content they chose.
				panel.setAttribute( 'tabindex', '-1' );
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
			// Hash can legally contain `?` / `&` (e.g. `#credits?autologin=1`
			// from a post-redirect chain). querySelector would barf on the
			// raw string, so isolate just the tab-slug shape.
			const hashRaw  = window.location.hash.replace( '#', '' );
			const hashTab  = ( hashRaw.match( /^[a-z][a-z0-9_-]*/ ) || [ '' ] )[ 0 ];
			const targetTab = ( queryTab || hashTab ).replace( /[^a-z0-9_-]/gi, '' );
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
			btn.textContent = t( 'jsSending', 'Sending...' );
			if ( status ) status.hidden = true;

			abortableApiFetch( {
				path: '/listora/v1/submission/resend-verification',
				method: 'POST',
				data: { listing_id: listingId },
			} ).then( ( res ) => {
				if ( res && res.sent ) {
					if ( status ) {
						status.hidden = false;
						status.textContent = ' ✓ A fresh verification email is on its way.';
					}
					btn.textContent = t( 'jsSent', 'Sent' );
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
				if ( isAbortError( err ) ) {
					if ( status ) {
						status.hidden = false;
						status.textContent = ' ' + NETWORK_SLOW_MESSAGE;
					}
				} else if ( data && data.error === 'rate_limited' ) {
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
			return abortableApiFetch( opts );
		}
		// Minimal fallback — uses abortableFetch so a hung host doesn't
		// trap the renewal modal indefinitely.
		return abortableFetch(
			( opts.path.startsWith( '/' ) ? '/wp-json' + opts.path : opts.path ),
			{
				method: opts.method || 'GET',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.wpApiSettings ? window.wpApiSettings.nonce : '' },
				credentials: 'same-origin',
				body: opts.data ? JSON.stringify( opts.data ) : undefined,
			}
		).then( async ( res ) => {
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
				errEl.textContent = tf( 'jsNeedCredits', 'You need %1$s credits to renew (you have %2$s).', quote.cost, quote.balance );
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
			errEl.textContent = isAbortError( err )
				? NETWORK_SLOW_MESSAGE
				: ( ( err && err.message ) ? err.message : 'Could not load renewal pricing.' );
		}
	};

	const doRenew = async () => {
		if ( ! activeListingId ) return;
		confirmBtn.disabled = true;
		confirmBtn.textContent = t( 'jsRenewing', 'Renewing...' );
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
						expiredPill.textContent = t( 'jsPublished', 'Published' );
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

			if ( isAbortError( err ) ) {
				errEl.hidden = false;
				errEl.textContent = NETWORK_SLOW_MESSAGE;
				showToast( NETWORK_SLOW_MESSAGE, 'error' );
				return;
			}

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

/**
 * Direct-payment-gateway checkout — Stripe / PayPal "Buy with X" buttons in
 * the credits tab pack cards. Click → POST /checkout/{gateway} → redirect.
 *
 * Out of band of the IAPI store because checkout is a one-shot navigation,
 * not a stateful interaction.
 */
const CHECKOUT_TIMEOUT_MS = 10000;

function handleDirectCheckoutClick( event ) {
	const button = event.target.closest( '[data-listora-credits-checkout]' );
	if ( ! button ) {
		return;
	}
	event.preventDefault();
	if ( button.disabled ) {
		return;
	}

	const gateway      = button.getAttribute( 'data-gateway' ) || '';
	const credits      = parseInt( button.getAttribute( 'data-credits' ) || '0', 10 );
	const priceCents   = parseInt( button.getAttribute( 'data-price-cents' ) || '0', 10 );
	const currency     = button.getAttribute( 'data-currency' ) || 'USD';
	const checkoutBase = button.getAttribute( 'data-checkout-base' ) || '';
	const returnUrl    = button.getAttribute( 'data-return-url' ) || '';
	const restNonce    = button.getAttribute( 'data-rest-nonce' ) || '';

	if ( ! gateway || ! checkoutBase || ! credits || ! priceCents ) {
		showCheckoutError( button, 'Invalid pack configuration. Please contact the site administrator.' );
		return;
	}

	const originalLabel = button.textContent;
	button.disabled = true;
	button.classList.add( 'is-busy' );
	button.textContent = t( 'jsRedirecting', 'Redirecting...' );

	const controller = new AbortController();
	const timer = setTimeout( () => controller.abort(), CHECKOUT_TIMEOUT_MS );

	fetch( checkoutBase + encodeURIComponent( gateway ), {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': restNonce,
		},
		body: JSON.stringify( {
			credits,
			price_cents: priceCents,
			currency,
			return_url: returnUrl,
		} ),
		signal: controller.signal,
	} )
		.then( async ( response ) => {
			const data = await response.json().catch( () => null );
			if ( ! response.ok || ! data || ! data.url ) {
				const message = ( data && data.message ) || 'Could not start checkout. Please try again.';
				throw new Error( message );
			}
			window.location.href = data.url;
		} )
		.catch( ( err ) => {
			clearTimeout( timer );
			button.disabled = false;
			button.classList.remove( 'is-busy' );
			button.textContent = originalLabel;
			const message = err && err.name === 'AbortError'
				? 'Network is slow — please try again.'
				: ( err.message || 'Could not start checkout. Please try again.' );
			showCheckoutError( button, message );
		} )
		.finally( () => {
			clearTimeout( timer );
		} );
}

function showCheckoutError( button, message ) {
	const card = button.closest( '.listora-dashboard__credit-pack' );
	const host = card || button.parentElement;
	if ( ! host ) {
		return;
	}
	let target = host.querySelector( '.listora-dashboard__credit-pack-error' );
	if ( ! target ) {
		target = document.createElement( 'p' );
		target.className = 'listora-dashboard__credit-pack-error';
		target.setAttribute( 'role', 'alert' );
		host.appendChild( target );
	}
	target.textContent = String( message );
}

document.addEventListener( 'click', handleDirectCheckoutClick );

/**
 * Post-checkout balance refresh — when the credits tab loads with a
 * `?wbcom_credits=success` banner, fetch the latest balance from the
 * SDK's REST endpoint. The webhook may have already credited the user
 * (we hide the "Updating your balance…" hint and replace it with the new
 * total) or may still be in-flight (we poll every 3s for up to 30s).
 */
async function refreshCreditsBalanceAfterCheckout() {
	const banner = document.querySelector( '[data-listora-credits-banner][data-status="success"]' );
	if ( ! banner ) {
		return;
	}

	const balanceLabel = document.querySelector( '[data-listora-credits-balance-status]' );
	const balanceEl    = document.querySelector( '.listora-dashboard__balance-number' );
	if ( ! balanceLabel || ! balanceEl ) {
		return;
	}

	const startBalance = parseInt( ( balanceEl.textContent || '0' ).replace( /[^0-9-]/g, '' ), 10 );
	const expected     = parseInt( banner.getAttribute( 'data-credits' ) || '0', 10 );

	/*
	 * Claim the payment on return, instead of only waiting for the webhook.
	 *
	 * Stripe sends the member back with `session_id=cs_…` in the URL. The SDK
	 * exposes `POST /claim/{gateway}` for exactly this: it verifies the session
	 * with the gateway and credits the account, idempotently, so calling it when
	 * the webhook has already landed is a no-op rather than a double-credit.
	 *
	 * Without it the page only POLLED the balance for 30 seconds. On any site
	 * where the webhook is slow, misconfigured, or unreachable — a local install,
	 * a firewalled host, a webhook the owner never set up — the member had paid
	 * and the credits simply never arrived. Claiming makes the return path
	 * self-sufficient and leaves the webhook as the backup, not the only route.
	 */
	const params   = new URLSearchParams( window.location.search );
	const sessionId = params.get( 'session_id' ) || '';
	const gateway   = banner.getAttribute( 'data-gateway' ) || params.get( 'gateway' ) || 'stripe';

	const claimOnce = async () => {
		if ( ! sessionId ) {
			return;
		}

		try {
			await fetch( `/wp-json/wbcom-credits/v1/wb-listora/claim/${ gateway }`, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					// Same nonce the rest of the dashboard uses; the route is
					// authenticated and must not be callable cross-site.
					'X-WP-Nonce': ( window.listoraDashboard && window.listoraDashboard.restNonce ) || '',
				},
				body: JSON.stringify( { session_id: sessionId } ),
			} );
		} catch ( e ) {
			// Deliberately silent: the webhook is still a valid path to the
			// same result, and the poll below reports the outcome either way.
			// A failed claim is not something to alarm a paying member about.
		}
	};

	await claimOnce();

	const tryFetch = async () => {
		try {
			const r = await fetch( '/wp-json/wbcom-credits/v1/wb-listora', { credentials: 'same-origin' } );
			if ( ! r.ok ) return null;
			const j = await r.json();
			return typeof j.balance === 'number' ? j.balance : null;
		} catch ( e ) {
			return null;
		}
	};

	const settle = ( newBalance ) => {
		if ( typeof newBalance !== 'number' ) return false;
		if ( newBalance > startBalance ) {
			balanceEl.textContent = String( newBalance );
			/*
			 * Confirm, rather than blanking the line. The banner above now says
			 * "Adding N credits…", so clearing this left the member on a
			 * half-finished sentence with no statement that it worked.
			 */
			balanceLabel.textContent = banner.getAttribute( 'data-confirmed-text' ) || '';
			return true;
		}
		return false;
	};

	// Immediate try.
	const first = await tryFetch();
	if ( settle( first ) ) return;

	// Poll up to 10 attempts, 3 seconds apart (~30s total).
	let attempts = 0;
	const interval = setInterval( async () => {
		attempts += 1;
		const next = await tryFetch();
		if ( settle( next ) || attempts >= 10 ) {
			clearInterval( interval );
			if ( attempts >= 10 ) {
				balanceLabel.textContent = 'Your credits will appear shortly — refresh in a moment if not.';
			}
		}
	}, 3000 );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', refreshCreditsBalanceAfterCheckout );
} else {
	refreshCreditsBalanceAfterCheckout();
}
