/**
 * Block editor: warn, before Publish, that a listing needs a type.
 *
 * The server keeps a typeless listing as a draft, but the block editor does not
 * print admin_notices, so the reason was never seen. The warning stays on screen
 * while the Listing Type box reads "No type set" and goes away once one is chosen.
 */
( function ( wp ) {
	const { __ } = wp.i18n;
	const NOTICE_ID = 'wb-listora-type-required';
	const SELECT = '#wb-listora-listing-type';

	function sync() {
		const select = document.querySelector( SELECT );
		if ( ! select ) {
			return;
		}
		const notices = wp.data.dispatch( 'core/notices' );
		if ( '' === select.value ) {
			notices.createWarningNotice( __( 'Choose a listing type in the Listing Type box before publishing, or the listing is saved as a draft.', 'wb-listora' ), { id: NOTICE_ID, isDismissible: false } );
		} else {
			notices.removeNotice( NOTICE_ID );
		}
	}

	wp.domReady( function () {
		document.addEventListener( 'change', function ( event ) {
			if ( event.target && event.target.matches && event.target.matches( SELECT ) ) {
				sync();
			}
		} );
		// Meta boxes render after the editor shell.
		[ 500, 2000 ].forEach( function ( delay ) {
			window.setTimeout( sync, delay );
		} );
	} );
}( window.wp ) );
