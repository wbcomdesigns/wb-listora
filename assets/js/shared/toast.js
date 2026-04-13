/**
 * Toast notification system for WB Listora.
 *
 * Usage:
 *   listoraToast( 'Message', 'success' );
 *   listoraToast( 'Message', { type: 'error', duration: 3000 } );
 *
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

	window.listoraToast = function( message, opts ) {
		init();

		// Accept both string and object: listoraToast('msg', 'error') or listoraToast('msg', {type:'error'})
		var type = 'info';
		var duration = 4000;
		if ( typeof opts === 'string' ) {
			type = opts;
		} else if ( opts && typeof opts === 'object' ) {
			type = opts.type || 'info';
			duration = opts.duration || 4000;
		}

		var toast = document.createElement( 'div' );
		toast.className = 'listora-toast listora-toast--' + type;
		toast.setAttribute( 'role', 'status' );
		toast.setAttribute( 'aria-live', 'polite' );
		toast.textContent = message;
		container.appendChild( toast );

		setTimeout( function() {
			toast.classList.add( 'is-visible' );
		}, 10 );

		setTimeout( function() {
			toast.classList.remove( 'is-visible' );
			setTimeout( function() {
				if ( toast.parentNode ) {
					toast.parentNode.removeChild( toast );
				}
			}, 300 );
		}, duration );
	};
} )();
