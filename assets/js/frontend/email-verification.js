/**
 * Listora — Email-verification landing page resend handler.
 *
 * Replaces the inline <script> previously emitted from
 * includes/workflow/class-email-verification.php (Rule 11).
 *
 * The button carries its REST endpoint, nonce, listing ID, and the four
 * status strings as data-* attributes — set by the renderer so this
 * script needs no global config object.
 *
 * @package WBListora
 */
( function () {
	'use strict';

	function init() {
		var btn = document.getElementById( 'listora-verify-resend' );
		var msg = document.getElementById( 'listora-verify-resend-msg' );
		if ( ! btn ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			if ( msg ) {
				msg.hidden      = false;
				msg.textContent = btn.dataset.msgSending || 'Sending…';
			}

			var endpoint  = btn.dataset.endpoint || '';
			var nonce     = btn.dataset.nonce || '';
			var listingId = parseInt( btn.dataset.listingId, 10 ) || 0;

			fetch( endpoint, {
				method:  'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   nonce,
				},
				body: JSON.stringify( { listing_id: listingId } ),
			} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( d ) {
					if ( d && d.sent ) {
						if ( msg ) {
							msg.textContent = btn.dataset.msgSent || 'A fresh verification email is on the way.';
						}
					} else if ( d && d.error === 'rate_limited' ) {
						if ( msg ) {
							msg.textContent = btn.dataset.msgRateLimited || 'Please wait a moment before requesting another email.';
						}
						btn.disabled = false;
					} else {
						if ( msg ) {
							msg.textContent = btn.dataset.msgFailed || 'Could not send the email. Please try again later.';
						}
						btn.disabled = false;
					}
				} )
				.catch( function () {
					if ( msg ) {
						msg.textContent = btn.dataset.msgFailed || 'Could not send the email. Please try again later.';
					}
					btn.disabled = false;
				} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
