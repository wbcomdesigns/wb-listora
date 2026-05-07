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

	// Inline AbortController + 10s timeout helper. Mirrors
	// src/utils/abortable-fetch.js but kept inline because this file is
	// a plain ES5 IIFE outside the wp-scripts module pipeline.
	function abortableFetch( url, opts, ms ) {
		var ctrl = new AbortController();
		var id = setTimeout( function () { ctrl.abort(); }, ms || 10000 );
		opts = opts || {};
		opts.signal = ctrl.signal;
		return fetch( url, opts ).finally( function () { clearTimeout( id ); } );
	}
	function isAbortError( e ) {
		return Boolean( e && ( e.name === 'AbortError' || e.code === 20 ) );
	}

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

			abortableFetch( endpoint, {
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
				.catch( function ( err ) {
					if ( msg ) {
						msg.textContent = isAbortError( err )
							? ( btn.dataset.msgFailed || 'Network slow — please try again.' )
							: ( btn.dataset.msgFailed || 'Could not send the email. Please try again later.' );
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
