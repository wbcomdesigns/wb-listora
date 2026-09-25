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

	// Map tile presets (Settings > Maps, Setup Wizard): fill the tile URL and
	// credit inputs from the chosen source. "My own provider" leaves them to the owner.
	document.addEventListener( 'change', function ( event ) {
		var select = event.target;
		if ( ! select || ! select.matches || ! select.matches( '[data-listora-tile-preset]' ) ) {
			return;
		}
		var opt   = select.options[ select.selectedIndex ];
		var note  = select.parentNode.parentNode.querySelector( '[data-listora-tile-note]' );
		var url   = document.getElementById( select.getAttribute( 'data-url-input' ) );
		var attr  = document.getElementById( select.getAttribute( 'data-attribution-input' ) );
		if ( note ) {
			note.textContent = opt.getAttribute( 'data-note' ) || '';
		}
		if ( ! opt.getAttribute( 'data-url' ) ) {
			if ( 'custom' === select.value && url ) {
				url.focus();
			}
			return;
		}
		[ [ url, opt.getAttribute( 'data-url' ) ], [ attr, opt.getAttribute( 'data-attribution' ) ] ].forEach( function ( pair ) {
			if ( pair[ 0 ] ) {
				pair[ 0 ].value = pair[ 1 ];
				// Let the Settings unsaved-changes guard see the edit.
				pair[ 0 ].dispatchEvent( new Event( 'input', { bubbles: true } ) );
			}
		} );
		// A keyed provider: select the placeholder so the owner pastes over it.
		var keyAt = url ? url.value.indexOf( 'YOUR_KEY' ) : -1;
		if ( keyAt > -1 ) {
			url.focus();
			url.setSelectionRange( keyAt, keyAt + 'YOUR_KEY'.length );
		}
	} );
}() );
