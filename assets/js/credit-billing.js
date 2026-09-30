/**
 * WB Listora - billing details for a Stripe / PayPal credit purchase.
 *
 * The form is rendered by wb_listora_render_credit_billing_form() only when the
 * member's required billing fields are missing. Both buy surfaces (the
 * dashboard Credits tab and Pro's Credit Purchase block) call collect() before
 * they start a checkout, and reveal() when the server answers billing_incomplete.
 */
( function () {
	'use strict';

	function form() {
		return document.querySelector( '[data-listora-credit-billing]' );
	}

	function showError( el, message ) {
		var error = el.querySelector( '.listora-credit-billing__error' );
		if ( ! error ) {
			return;
		}
		error.textContent = message || '';
		error.hidden = ! message;
	}

	function reveal( message ) {
		var el = form();
		if ( ! el ) {
			return false;
		}
		el.hidden = false;
		showError( el, message || '' );
		var first = el.querySelector( '[required]:not(:disabled)' );
		var empty = Array.prototype.filter.call( el.querySelectorAll( '[name]' ), function ( field ) {
			return field.required && ! field.value.trim();
		} )[ 0 ];
		( empty || first || el ).focus();
		el.scrollIntoView( { block: 'center', behavior: 'smooth' } );
		return true;
	}

	/**
	 * @return {{ok: boolean, billing?: Object}} ok:false means "stop, the member has something to fill in".
	 */
	function collect() {
		var el = form();
		if ( ! el ) {
			return { ok: true };
		}
		if ( el.hidden ) {
			reveal();
			return { ok: false };
		}

		var billing = {};
		var firstInvalid = null;
		Array.prototype.forEach.call( el.querySelectorAll( '[name]' ), function ( field ) {
			var value = field.value.trim();
			var invalid = ( field.required && ! value ) || ( value && ! field.checkValidity() );
			field.setAttribute( 'aria-invalid', invalid ? 'true' : 'false' );
			if ( invalid && ! firstInvalid ) {
				firstInvalid = field;
			}
			if ( value ) {
				billing[ field.name ] = value;
			}
		} );

		if ( firstInvalid ) {
			showError( el, el.getAttribute( 'data-required-text' ) || '' );
			firstInvalid.focus();
			return { ok: false };
		}

		showError( el, '' );
		return { ok: true, billing: billing };
	}

	window.listoraCreditBilling = { collect: collect, reveal: reveal };
} )();
