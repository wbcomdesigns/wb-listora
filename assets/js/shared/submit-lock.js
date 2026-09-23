/**
 * Submit-lock delegation — shared by admin and frontend.
 *
 * Replaces inline onclick="this.disabled=true;this.textContent='…';this.form.submit()"
 * with a clean `data-listora-submit-lock="Busy label"` attribute on any button.
 *
 * The lock engages on the form's `submit` event, never on the button's click.
 * A submit button's activation behaviour returns early when the button is
 * disabled, so disabling it inside its own click handler cancelled every
 * submission: bulk Apply on Reviews and Claims sat on "Processing..." and
 * posted nothing (found in the 2026-09-23 hooks audit). By `submit` the browser
 * has already committed, so the button can be disabled safely. `submit` only
 * fires once validation passes, so native validation UI still takes over.
 */
( function () {
	'use strict';

	function lock( btn ) {
		var busy = btn.getAttribute( 'data-listora-submit-lock' );
		btn.disabled = true;
		if ( busy ) {
			btn.textContent = busy;
		}
	}

	document.addEventListener( 'submit', function ( event ) {
		var btn = event.submitter;
		if ( btn && btn.hasAttribute( 'data-listora-submit-lock' ) && ! event.defaultPrevented ) {
			lock( btn );
		}
	} );

	// A non-submit button carrying the attribute submits its form itself.
	document.addEventListener( 'click', function ( event ) {
		var btn = event.target && event.target.closest ? event.target.closest( '[data-listora-submit-lock]' ) : null;
		if ( ! btn || 'submit' === btn.type ) {
			return;
		}
		var form = btn.form || btn.closest( 'form' );
		if ( ! form || ( form.checkValidity && ! form.checkValidity() ) ) {
			return;
		}
		lock( btn );
		form.submit();
	} );
}() );
