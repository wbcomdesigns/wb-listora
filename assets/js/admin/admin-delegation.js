/**
 * Admin UI event delegation — replaces inline onclick handlers.
 *
 * Patterns handled:
 *
 *   .listora-copy-field__input   Select the input text on click (for copy fields).
 *
 *   [data-listora-action]        Dispatches to a global function defined in the
 *                                enclosing admin page (tools tab):
 *                                  reset-defaults   → window.listoraResetDefaults()
 *                                  export-settings  → window.listoraExportSettings()
 *                                  import-settings  → window.listoraImportSettings()
 *
 * The submit-lock pattern lives in assets/js/shared/submit-lock.js so it works on
 * both admin and frontend templates.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( ! target || ! target.closest ) {
			return;
		}

		var copyField = target.closest( '.listora-copy-field__input' );
		if ( copyField && typeof copyField.select === 'function' ) {
			copyField.select();
			return;
		}

		var actionEl = target.closest( '[data-listora-action]' );
		if ( ! actionEl ) {
			return;
		}

		var action = actionEl.getAttribute( 'data-listora-action' );
		var map = {
			'reset-defaults':  'listoraResetDefaults',
			'export-settings': 'listoraExportSettings',
			'import-settings': 'listoraImportSettings',
		};
		var fnName = map[ action ];
		if ( fnName && typeof window[ fnName ] === 'function' ) {
			event.preventDefault();
			window[ fnName ]();
		}
	} );
}() );
