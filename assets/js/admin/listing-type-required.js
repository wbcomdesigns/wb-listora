/**
 * Block editor: a listing needs a type before it can be saved.
 *
 * The server keeps a typeless listing as a draft, but the block editor does not
 * print admin_notices and still announces "Listing published.", so the owner
 * left believing it was live. While the Listing Type box reads "No type set"
 * saving is locked and a notice says why; choosing a type lifts both.
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
		const editor = wp.data.dispatch( 'core/editor' );
		if ( '' === select.value ) {
			editor.lockPostSaving( NOTICE_ID );
			notices.createWarningNotice( __( 'Choose a listing type in the Listing Type box to save or publish this listing.', 'wb-listora' ), { id: NOTICE_ID, isDismissible: false } );
		} else {
			editor.unlockPostSaving( NOTICE_ID );
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
