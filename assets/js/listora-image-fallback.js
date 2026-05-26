/**
 * Listora image-fallback delegated handler.
 *
 * Replaces inline `onerror="this.src='…'"` attributes (which the WordPress
 * Interactivity API rejects during hydration — "Component's onerror property
 * should be a function, but got string instead") with a CSP-safe delegated
 * listener attached at the document level.
 *
 * Any <img> carrying `data-listora-fallback-src="…"` will swap to that URL the
 * first time it fires an `error` event. The data-attribute is removed after
 * the swap so a broken placeholder doesn't loop.
 *
 * Basecamp #9927470152.
 */
( function () {
	function handle( event ) {
		var img = event.target;
		if ( ! img || 'IMG' !== img.tagName ) {
			return;
		}
		var fallback = img.getAttribute( 'data-listora-fallback-src' );
		if ( ! fallback ) {
			return;
		}
		img.removeAttribute( 'data-listora-fallback-src' );
		if ( img.src !== fallback ) {
			img.src = fallback;
		}
	}

	// useCapture=true: <img>'s `error` event does not bubble, so a bubbling
	// listener on document never fires. Capture phase walks the tree from
	// root to target and catches the event before it stops.
	document.addEventListener( 'error', handle, true );
} )();
