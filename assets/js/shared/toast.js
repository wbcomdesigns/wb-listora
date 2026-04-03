/**
 * Toast notification system for WB Listora.
 *
 * Usage: listoraToast( 'Message text', 'success' );
 * Types: success, error, info, warning
 *
 * @package WBListora
 */
( function() {
	var container;

	function init() {
		if ( container ) {
			return;
		}
		container = document.createElement( 'div' );
		container.className = 'listora-toast-container';
		document.body.appendChild( container );
	}

	window.listoraToast = function( message, type ) {
		init();
		type = type || 'info';

		var toast = document.createElement( 'div' );
		toast.className = 'listora-toast listora-toast--' + type;
		toast.textContent = message;
		container.appendChild( toast );

		setTimeout( function() {
			toast.classList.add( 'is-visible' );
		}, 10 );

		setTimeout( function() {
			toast.classList.remove( 'is-visible' );
			setTimeout( function() {
				container.removeChild( toast );
			}, 300 );
		}, 4000 );
	};
} )();
