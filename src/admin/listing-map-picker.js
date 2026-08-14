/**
 * Map picker for the wp-admin listing editor.
 *
 * The Listing Fields metabox reuses the frontend submission field renderer, so
 * a `map_location` field prints the same `.listora-submission__map-picker` div
 * the Add Listing wizard uses. On the frontend that div becomes a Leaflet map
 * because the block enqueues Leaflet and the wizard's view module calls
 * `initMapPickers()`. In wp-admin neither ever loaded, so editors got an empty
 * box: no map, no marker, nothing to drag (BC 10198832114).
 *
 * This is the missing half. It imports the same `initMapPickers()` the wizard
 * uses rather than re-implementing a picker — the address input, the hidden
 * lat/lng fields and the geocoding behaviour are all identical by
 * construction.
 *
 * @package WBListora
 */

import { initMapPickers } from '../utils/map-picker.js';

/**
 * Initialise every picker on the screen.
 *
 * `initMapPickers()` is idempotent — it skips any element that already carries
 * a map — so running it more than once is safe. That matters here because the
 * block editor mounts metaboxes asynchronously: the div frequently does not
 * exist yet at DOMContentLoaded, and it can be re-mounted when the editor
 * re-renders the metabox area.
 *
 * @return {void}
 */
function initAdminMapPickers() {
	if ( typeof window.L === 'undefined' ) {
		// Leaflet did not load. Better to leave the empty box than to throw on
		// every editor screen — the enqueue is what would be broken, and the
		// PHP side is where that gets fixed.
		return;
	}

	initMapPickers( document );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initAdminMapPickers );
} else {
	initAdminMapPickers();
}

/*
 * The block editor renders metaboxes into `#metabox-area` after the initial
 * paint, and re-renders them on some state changes. A one-shot DOMContentLoaded
 * call therefore misses the picker on a cold load often enough to look random.
 * Watching the metabox container catches every mount, and the idempotence guard
 * inside initMapPickers() makes the repeat calls free.
 */
if ( typeof window.MutationObserver !== 'undefined' ) {
	const target =
		document.getElementById( 'poststuff' ) || document.body;

	if ( target ) {
		const observer = new window.MutationObserver( () => {
			if ( document.querySelector( '.listora-submission__map-picker:not([data-provider-map-init])' ) ) {
				initAdminMapPickers();
			}
		} );

		observer.observe( target, { childList: true, subtree: true } );
	}
}
