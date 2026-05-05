/**
 * Lucide icon initialization for WB Listora admin pages.
 *
 * @package WBListora
 */
( function () {
	'use strict';

	function initLucide() {
		if ( window.lucide && typeof window.lucide.createIcons === 'function' ) {
			window.lucide.createIcons();
			return true;
		}
		return false;
	}

	// Cover three timing cases:
	//   1. Lucide already attached — render now.
	//   2. DOM still parsing — wait for DOMContentLoaded.
	//   3. DOM ready but Lucide deferred / cache-missed — poll up to
	//      5s so the icons paint without a manual refresh.
	if ( ! initLucide() ) {
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', initLucide );
		}
		var attempts = 0;
		var poller   = setInterval( function () {
			if ( initLucide() || ++attempts > 50 ) {
				clearInterval( poller );
			}
		}, 100 );
	}
} )();
