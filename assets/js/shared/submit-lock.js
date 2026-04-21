/**
 * Submit-lock delegation — shared by admin and frontend.
 *
 * Replaces inline onclick="this.disabled=true;this.textContent='…';this.form.submit()"
 * with a clean `data-listora-submit-lock="Busy label"` attribute on any button.
 *
 * Form validity is respected: if checkValidity() fails, the lock does not engage
 * so the browser's native validation UI can take over.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( ! target || ! target.closest ) {
			return;
		}
		var btn = target.closest( '[data-listora-submit-lock]' );
		if ( ! btn ) {
			return;
		}
		var form = btn.form || btn.closest( 'form' );
		if ( ! form ) {
			return;
		}
		if ( form.checkValidity && ! form.checkValidity() ) {
			return;
		}
		var busy = btn.getAttribute( 'data-listora-submit-lock' );
		btn.disabled = true;
		if ( busy ) {
			btn.textContent = busy;
		}
		if ( btn.type !== 'submit' ) {
			form.submit();
		}
	} );
}() );
