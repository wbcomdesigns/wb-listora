/**
 * Email Templates editor (AUD-F8) — progressive enhancement.
 *
 * Vanilla JS, no jQuery, no inline handlers. Two enhancements:
 *  1. Click a placeholder chip to copy `{token}` to the clipboard.
 *  2. When "Reset to default" is checked, dim + disable that event's fields so
 *     it is obvious the typed value will be discarded on save.
 *
 * The form works fully without this script — it only adds convenience.
 */
( function () {
	'use strict';

	var root = document.getElementById( 'listora-email-templates' );

	if ( ! root ) {
		return;
	}

	/**
	 * Copy a placeholder token to the clipboard on click.
	 */
	root.addEventListener( 'click', function ( event ) {
		var chip = event.target.closest( '.listora-email-templates__placeholder' );

		if ( ! chip ) {
			return;
		}

		var token = chip.textContent.trim();

		if ( ! navigator.clipboard || ! navigator.clipboard.writeText ) {
			return;
		}

		navigator.clipboard.writeText( token ).then( function () {
			chip.classList.add( 'is-copied' );
			window.setTimeout( function () {
				chip.classList.remove( 'is-copied' );
			}, 1200 );
		} );
	} );

	/**
	 * Toggle the disabled state of an event's inputs when its reset box flips.
	 *
	 * @param {HTMLInputElement} checkbox The reset checkbox.
	 */
	function syncResetState( checkbox ) {
		var details = checkbox.closest( '.listora-email-templates__event' );

		if ( ! details ) {
			return;
		}

		var fields = details.querySelectorAll(
			'.listora-email-templates__subject, .listora-email-templates__textarea'
		);

		Array.prototype.forEach.call( fields, function ( field ) {
			field.disabled = checkbox.checked;
		} );
	}

	root.addEventListener( 'change', function ( event ) {
		var checkbox = event.target;

		if (
			'INPUT' !== checkbox.tagName ||
			'checkbox' !== checkbox.type ||
			! checkbox.name ||
			0 !== checkbox.name.indexOf( 'email_templates_reset[' )
		) {
			return;
		}

		syncResetState( checkbox );
	} );
}() );
