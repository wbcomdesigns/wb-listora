/**
 * WB Listora — Pro Promotion JS
 *
 * Powers the inline Pro-feature modal triggered from .listora-pro-badge chips,
 * the license-validation form on the Upgrade page, the smooth-scroll to
 * feature anchors, and the dismiss-CTA cookie tracking on both admin and
 * frontend surfaces.
 *
 * Vanilla JS, no jQuery. Runs on both admin and frontend pages — guard with
 * presence checks so a missing global is never a hard error. All inserted
 * content uses textContent / DOM methods — never innerHTML with user data.
 *
 * @package WBListora
 */

( function () {
	'use strict';

	var COOKIE_PREFIX = 'wb_listora_promo_';
	var COOKIE_DAYS = 3;

	/* ──────────────────────────────────────────────────────────────────
	 * Cookie helpers (client-side dismiss persistence)
	 * ────────────────────────────────────────────────────────────────── */

	function setCookie( name, value, days ) {
		var expires = '';
		if ( days ) {
			var date = new Date();
			date.setTime( date.getTime() + ( days * 24 * 60 * 60 * 1000 ) );
			expires = '; expires=' + date.toUTCString();
		}
		document.cookie = name + '=' + encodeURIComponent( value ) + expires + '; path=/; SameSite=Lax';
	}

	function clearChildren( el ) {
		while ( el.firstChild ) {
			el.removeChild( el.firstChild );
		}
	}

	function dismissSurface( surface ) {
		if ( ! surface ) {
			return;
		}
		setCookie( COOKIE_PREFIX + surface, '1', COOKIE_DAYS );

		// Also notify server so HTTP cache (or strict-cookie browsers) reflects it.
		if ( window.wbListoraPromo && window.wbListoraPromo.ajaxUrl && window.wbListoraPromo.nonce ) {
			var body = new URLSearchParams();
			body.set( 'action', 'wb_listora_dismiss_promo' );
			body.set( 'nonce', window.wbListoraPromo.nonce );
			body.set( 'surface', surface );
			fetch( window.wbListoraPromo.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			} ).catch( function () { /* non-fatal */ } );
		}

		// Hide the closest CTA element.
		var nodes = document.querySelectorAll( '[data-promo-surface="' + surface + '"]' );
		Array.prototype.forEach.call( nodes, function ( el ) {
			el.style.transition = 'opacity 0.25s, max-height 0.3s';
			el.style.opacity = '0';
			setTimeout( function () {
				if ( el.parentNode ) {
					el.parentNode.removeChild( el );
				}
			}, 280 );
		} );

		// Special case: WP dashboard widget root.
		var widgetRoot = document.getElementById( 'wb-listora-upgrade-widget' );
		if ( surface === 'wp_dashboard_widget' && widgetRoot ) {
			widgetRoot.style.transition = 'opacity 0.25s';
			widgetRoot.style.opacity = '0';
			setTimeout( function () {
				if ( widgetRoot.parentNode ) {
					widgetRoot.parentNode.removeChild( widgetRoot );
				}
			}, 280 );
		}
	}

	/* ──────────────────────────────────────────────────────────────────
	 * Dismiss-button delegation (works on admin + frontend)
	 * ────────────────────────────────────────────────────────────────── */

	document.addEventListener( 'click', function ( e ) {
		var target = e.target;
		while ( target && target !== document.body ) {
			if ( target.dataset && target.dataset.promoDismiss ) {
				e.preventDefault();
				dismissSurface( target.dataset.promoDismiss );
				return;
			}
			target = target.parentNode;
		}
	} );

	/* ──────────────────────────────────────────────────────────────────
	 * Modal — only runs on admin pages where the modal root + globals exist
	 * ────────────────────────────────────────────────────────────────── */

	var modal = document.getElementById( 'listora-promo-modal' );
	var promoData = window.wbListoraPromo || null;

	if ( modal && promoData ) {
		var titleEl = modal.querySelector( '.listora-promo-modal__title' );
		var descEl = modal.querySelector( '.listora-promo-modal__desc' );
		var learnEl = modal.querySelector( '[data-promo-learn]' );
		var upgradeEl = modal.querySelector( '[data-promo-upgrade]' );
		var lastFocused = null;

		function openModal( feature ) {
			var info = ( promoData.features && promoData.features[ feature ] ) || null;
			if ( ! info ) {
				return;
			}

			lastFocused = document.activeElement;

			titleEl.textContent = info.title;
			descEl.textContent = info.description;
			if ( learnEl ) {
				learnEl.href = ( promoData.upgradePageUrl || '#' ) + '#' + ( info.anchor || '' );
			}
			if ( upgradeEl ) {
				upgradeEl.href = promoData.upgradeUrl || '#';
			}

			modal.hidden = false;
			document.body.style.overflow = 'hidden';

			// Focus the close button so ESC handlers work immediately.
			var closeBtn = modal.querySelector( '.listora-promo-modal__close' );
			if ( closeBtn ) {
				closeBtn.focus();
			}
		}

		function closeModal() {
			modal.hidden = true;
			document.body.style.overflow = '';
			if ( lastFocused && typeof lastFocused.focus === 'function' ) {
				lastFocused.focus();
			}
		}

		// Close handlers — backdrop, close button, ESC.
		modal.addEventListener( 'click', function ( e ) {
			var t = e.target;
			while ( t && t !== modal ) {
				if ( t.dataset && t.dataset.promoClose !== undefined ) {
					e.preventDefault();
					closeModal();
					return;
				}
				t = t.parentNode;
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( modal.hidden ) {
				return;
			}
			if ( e.key === 'Escape' || e.keyCode === 27 ) {
				closeModal();
				return;
			}
			if ( e.key === 'Tab' ) {
				// Simple focus trap — keep focus inside dialog.
				var focusables = modal.querySelectorAll(
					'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
				);
				if ( ! focusables.length ) {
					return;
				}
				var first = focusables[ 0 ];
				var last = focusables[ focusables.length - 1 ];
				if ( e.shiftKey && document.activeElement === first ) {
					e.preventDefault();
					last.focus();
				} else if ( ! e.shiftKey && document.activeElement === last ) {
					e.preventDefault();
					first.focus();
				}
			}
		} );

		// Trigger modal from any .listora-pro-badge[data-pro-feature].
		document.addEventListener( 'click', function ( e ) {
			var target = e.target;
			while ( target && target !== document.body ) {
				if (
					target.classList &&
					target.classList.contains( 'listora-pro-badge' ) &&
					target.dataset &&
					target.dataset.proFeature
				) {
					e.preventDefault();
					openModal( target.dataset.proFeature );
					return;
				}
				target = target.parentNode;
			}
		} );

		// Make Pro badges keyboard accessible — Enter/Space to open.
		document.addEventListener( 'keydown', function ( e ) {
			if ( ( e.key !== 'Enter' && e.key !== ' ' && e.keyCode !== 13 && e.keyCode !== 32 ) ) {
				return;
			}
			var t = document.activeElement;
			if (
				t &&
				t.classList &&
				t.classList.contains( 'listora-pro-badge' ) &&
				t.dataset &&
				t.dataset.proFeature
			) {
				e.preventDefault();
				openModal( t.dataset.proFeature );
			}
		} );

		// Add tabindex + role to badges so they participate in focus order.
		var badges = document.querySelectorAll( '.listora-pro-badge[data-pro-feature]' );
		Array.prototype.forEach.call( badges, function ( badge ) {
			if ( ! badge.hasAttribute( 'tabindex' ) ) {
				badge.setAttribute( 'tabindex', '0' );
			}
			if ( ! badge.hasAttribute( 'role' ) ) {
				badge.setAttribute( 'role', 'button' );
			}
			if ( promoData.i18n && promoData.i18n.requiresPro ) {
				badge.setAttribute( 'aria-label', promoData.i18n.requiresPro + ': ' + ( badge.dataset.proFeature || '' ) );
			}
		} );
	}

	/* ──────────────────────────────────────────────────────────────────
	 * License-validation form (Upgrade page)
	 * ────────────────────────────────────────────────────────────────── */

	var licenseForm = document.getElementById( 'listora-promo-license-form' );
	if ( licenseForm && promoData ) {
		var statusEl = document.getElementById( 'listora-promo-license-status' );
		var submitBtn = licenseForm.querySelector( 'button[type="submit"]' );

		licenseForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			var input = document.getElementById( 'listora-promo-license-key' );
			var key = input ? input.value.trim() : '';

			if ( ! key ) {
				statusEl.className = 'listora-promo-activation__status is-error';
				statusEl.textContent = ( promoData.i18n && promoData.i18n.enterKey ) || 'Please enter a license key.';
				return;
			}

			statusEl.className = 'listora-promo-activation__status is-loading';
			statusEl.textContent = ( promoData.i18n && promoData.i18n.validating ) || 'Validating…';
			if ( submitBtn ) {
				submitBtn.disabled = true;
			}

			var body = new URLSearchParams();
			body.set( 'action', 'wb_listora_validate_license' );
			body.set( 'nonce', promoData.nonce );
			body.set( 'license_key', key );

			fetch( promoData.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			} )
				.then( function ( res ) {
					return res.json().then( function ( data ) {
						return { ok: res.ok, data: data };
					} );
				} )
				.then( function ( result ) {
					if ( submitBtn ) {
						submitBtn.disabled = false;
					}

					clearChildren( statusEl );

					if ( result.ok && result.data && result.data.success ) {
						statusEl.className = 'listora-promo-activation__status is-success';
						var msg = ( result.data.data && result.data.data.message ) ||
							( promoData.i18n && promoData.i18n.licenseValid ) ||
							'License valid.';
						var dlUrl = result.data.data && result.data.data.downloadUrl;
						var span = document.createElement( 'span' );
						span.textContent = msg + ' ';
						statusEl.appendChild( span );
						if ( dlUrl ) {
							var link = document.createElement( 'a' );
							link.href = dlUrl;
							link.target = '_blank';
							link.rel = 'noopener';
							link.textContent = 'Open download page →';
							statusEl.appendChild( link );
						}
						return;
					}

					statusEl.className = 'listora-promo-activation__status is-error';
					var errMsg =
						( result.data && result.data.data && result.data.data.message ) ||
						( promoData.i18n && promoData.i18n.licenseError ) ||
						'License could not be validated.';
					statusEl.textContent = errMsg;
				} )
				.catch( function () {
					if ( submitBtn ) {
						submitBtn.disabled = false;
					}
					statusEl.className = 'listora-promo-activation__status is-error';
					statusEl.textContent = ( promoData.i18n && promoData.i18n.networkError ) || 'Network error.';
				} );
		} );
	}

	/* ──────────────────────────────────────────────────────────────────
	 * Smooth scroll for in-page anchors on the Upgrade page
	 * ────────────────────────────────────────────────────────────────── */

	document.addEventListener( 'click', function ( e ) {
		var t = e.target;
		while ( t && t !== document.body ) {
			if ( t.tagName === 'A' && t.getAttribute( 'href' ) && t.getAttribute( 'href' ).charAt( 0 ) === '#' ) {
				var hash = t.getAttribute( 'href' );
				if ( hash.length > 1 ) {
					var dest = document.getElementById( hash.substring( 1 ) );
					if ( dest && document.querySelector( '.listora-promo-page' ) ) {
						e.preventDefault();
						dest.scrollIntoView( { behavior: 'smooth', block: 'start' } );
						// Also update the URL fragment without jumping.
						if ( history.replaceState ) {
							history.replaceState( null, '', hash );
						}
						return;
					}
				}
			}
			t = t.parentNode;
		}
	} );

	/* ──────────────────────────────────────────────────────────────────
	 * On load: if URL has a #feature-* anchor, scroll into view smoothly.
	 * ────────────────────────────────────────────────────────────────── */

	if ( window.location.hash && document.querySelector( '.listora-promo-page' ) ) {
		setTimeout( function () {
			var dest = document.querySelector( window.location.hash );
			if ( dest ) {
				dest.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		}, 50 );
	}

}() );
