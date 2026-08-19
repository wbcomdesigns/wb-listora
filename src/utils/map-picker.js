/**
 * Location map picker — shared by the submission wizard and wp-admin.
 *
 * Extracted from `src/blocks/listing-submission/view.js` in 1.6.0 so the
 * wp-admin listing editor can offer the same picker without a second map
 * implementation. The admin metabox reuses the frontend field renderer, which
 * prints the picker div — but the div only becomes a map when Leaflet and this
 * initialiser are both present, and neither was ever enqueued in wp-admin
 * (BC 10198832114).
 *
 * Nothing here assumes the wizard: `initMapPickers()` takes any root element
 * and initialises every `.listora-submission__map-picker` inside it.
 *
 * @package WBListora
 */

import { abortableFetch } from './abortable-fetch.js';

/**
 * Registry of provider-specific map-picker initializers.
 *
 * Extension point for add-ons that render the Add Listing location picker with
 * a different map engine than the bundled Leaflet/OpenStreetMap one. The
 * provider key matches the admin's Settings → Maps → Provider value (and the
 * `wb_listora_map_provider` filter), surfaced on each picker element as
 * `data-provider`. 'osm' is handled natively below; any other provider is
 * delegated here.
 *
 * Contract — a registered initializer is called as:
 *
 *     window.wbListoraMapPickers[ provider ]( el, {
 *         parent,        // .listora-submission__map-field wrapper (or null)
 *         initialLat,    // number — resolved start latitude
 *         initialLng,    // number — resolved start longitude
 *         initialZoom,   // number — resolved start zoom
 *         hasExisting,   // boolean — true in edit mode (coords pre-filled)
 *         updateLatLngFields, // fn( {lat,lng}, parent ) — writes hidden lat/lng inputs
 *         reverseGeocode,     // fn( lat, lng, parent ) — fills address fields from coords
 *         forwardGeocode,     // fn( addressString, mapRef, markerRef, parent ) — Leaflet-only; pass own geocoder
 *     } )
 *
 * The initializer OWNS the element from that point: it must build the map,
 * wire marker drag / map click → updateLatLngFields + (its own) reverse
 * geocode, wire the address field → forward geocode, and set a truthy guard
 * (e.g. `el.dataset.providerMapInit = '1'`) so it doesn't double-init. Returning
 * a truthy value tells Free the picker was handled; returning falsy lets Free
 * fall back to the Leaflet/OSM engine below.
 *
 * Example (Pro, after its Google Maps JS API loader is ready):
 *
 *     window.wbListoraMapPickers = window.wbListoraMapPickers || {};
 *     window.wbListoraMapPickers.google = function ( el, ctx ) { … return true; };
 *
 * @type {Object.<string, Function>}
 */
if ( typeof window.wbListoraMapPickers === 'undefined' ) {
	window.wbListoraMapPickers = {};
}

/**
 * Update lat/lng hidden fields from marker position.
 *
 * @param {L.LatLng}     pos    Marker position.
 * @param {HTMLElement}  parent The .listora-submission__map-field container.
 */
export function updateLatLngFields( pos, parent ) {
	if ( ! parent ) return;
	const latInput = parent.querySelector( '[name$="[lat]"]' );
	const lngInput = parent.querySelector( '[name$="[lng]"]' );
	if ( latInput ) latInput.value = pos.lat.toFixed( 7 );
	if ( lngInput ) lngInput.value = pos.lng.toFixed( 7 );
}

/**
 * Reverse-geocode coordinates via Nominatim and populate address fields.
 *
 * @param {number}      lat    Latitude.
 * @param {number}      lng    Longitude.
 * @param {HTMLElement} parent The .listora-submission__map-field container.
 */
function reverseGeocode( lat, lng, parent ) {
	if ( ! parent ) return;

	const url = `https://nominatim.openstreetmap.org/reverse?lat=${ lat }&lon=${ lng }&format=json&addressdetails=1`;

	abortableFetch( url, { headers: { Accept: 'application/json' } } )
		.then( ( res ) => res.json() )
		.then( ( data ) => {
			if ( ! data || data.error ) return;

			const addr = data.address || {};
			const addressInput = parent.querySelector( '[name$="[address]"]' );
			if ( addressInput && data.display_name ) {
				addressInput.value = data.display_name;
			}

			const cityInput = parent.querySelector( '[name$="[city]"]' );
			if ( cityInput ) {
				cityInput.value = addr.city || addr.town || addr.village || addr.municipality || '';
			}

			const stateInput = parent.querySelector( '[name$="[state]"]' );
			if ( stateInput ) {
				stateInput.value = addr.state || '';
			}

			const countryInput = parent.querySelector( '[name$="[country]"]' );
			if ( countryInput ) {
				countryInput.value = addr.country || '';
			}

			const postalInput = parent.querySelector( '[name$="[postal_code]"]' );
			if ( postalInput ) {
				postalInput.value = addr.postcode || '';
			}
		} )
		.catch( () => {
			// Silently fail — user can still type the address manually.
		} );
}

/**
 * Forward-geocode an address string via Nominatim and move the marker.
 *
 * @param {string}       query  Address string.
 * @param {L.Map}        map    Leaflet map instance.
 * @param {L.Marker}     marker Leaflet marker instance.
 * @param {HTMLElement}  parent The .listora-submission__map-field container.
 */
function forwardGeocode( query, map, marker, parent ) {
	if ( ! query || query.length < 3 ) return;

	const url = `https://nominatim.openstreetmap.org/search?q=${ encodeURIComponent( query ) }&format=json&addressdetails=1&limit=1`;

	abortableFetch( url, { headers: { Accept: 'application/json' } } )
		.then( ( res ) => res.json() )
		.then( ( results ) => {
			if ( ! results || ! results.length ) return;

			const result = results[ 0 ];
			const lat = parseFloat( result.lat );
			const lng = parseFloat( result.lon );
			const latlng = L.latLng( lat, lng );

			marker.setLatLng( latlng );
			map.setView( latlng, 15 );

			if ( parent ) {
				const latInput = parent.querySelector( '[name$="[lat]"]' );
				const lngInput = parent.querySelector( '[name$="[lng]"]' );
				if ( latInput ) latInput.value = lat.toFixed( 7 );
				if ( lngInput ) lngInput.value = lng.toFixed( 7 );

				const addr = result.address || {};
				const cityInput = parent.querySelector( '[name$="[city]"]' );
				if ( cityInput ) cityInput.value = addr.city || addr.town || addr.village || addr.municipality || '';

				const stateInput = parent.querySelector( '[name$="[state]"]' );
				if ( stateInput ) stateInput.value = addr.state || '';

				const countryInput = parent.querySelector( '[name$="[country]"]' );
				if ( countryInput ) countryInput.value = addr.country || '';

				const postalInput = parent.querySelector( '[name$="[postal_code]"]' );
				if ( postalInput ) postalInput.value = addr.postcode || '';
			}
		} )
		.catch( () => {
			// Silently fail — geocoding is best-effort.
		} );
}

/*
 * NOTE: this function moved here from
 * `src/blocks/listing-submission/view.js` when `initMapPickers()` was
 * extracted into this module. The extraction left it behind, so the call
 * below referenced an identifier that does not exist in this scope and
 * every picker threw `ReferenceError: recalcMapWhenVisible is not defined`
 * the moment its map was created — on the Add Listing wizard AND on the
 * wp-admin editor, which is the surface the extraction existed to serve.
 * The throw also took the 0-height tile fix (card 9932290292) with it.
 */
/**
 * Robustly recalc a Leaflet map's size once its container is laid out.
 *
 * Replaces the fragile single `setTimeout(() => invalidateSize(), 200)` that
 * raced the CSS step-reveal transition. Strategy:
 *   1. requestAnimationFrame loop — fire invalidateSize() as soon as the
 *      container reports a non-zero height (bounded retry count so we never
 *      spin forever if the step stays hidden).
 *   2. ResizeObserver — keep the map honest if the container changes size
 *      later (responsive reflow, late font load, sidebar toggle).
 *
 * @param {Object}      map Leaflet map instance.
 * @param {HTMLElement} el  Map container element.
 */
function recalcMapWhenVisible( map, el ) {
	let frames = 0;
	const maxFrames = 60; // ~1s at 60fps — generous backstop, then stop.

	const tick = () => {
		if ( ! el.isConnected ) return;
		if ( el.offsetHeight > 0 ) {
			map.invalidateSize();
			return;
		}
		if ( frames++ < maxFrames ) {
			( window.requestAnimationFrame || window.setTimeout )( tick );
		}
	};
	( window.requestAnimationFrame || window.setTimeout )( tick );

	if ( typeof window.ResizeObserver === 'function' && ! el._leafletResizeObserver ) {
		const ro = new window.ResizeObserver( () => {
			if ( el.offsetHeight > 0 ) {
				map.invalidateSize();
			}
		} );
		ro.observe( el );
		el._leafletResizeObserver = ro;
	}
}

/**
 * Init the location map pickers in the details step.
 *
 * Default engine is Leaflet/OpenStreetMap (bundled with Free). When the admin
 * selects a different provider (exposed per element as `data-provider`) and an
 * add-on has registered a matching initializer in `window.wbListoraMapPickers`,
 * that initializer takes over the element instead of Leaflet.
 *
 * Leaflet path creates a draggable marker that syncs with address fields:
 * - Drag marker or click map: reverse-geocodes to fill address fields.
 * - Type address: forward-geocodes to move the marker.
 * - On init: uses existing lat/lng values, or attempts browser geolocation.
 */
export function initMapPickers( step ) {
	step.querySelectorAll( '.listora-submission__map-picker' ).forEach( ( el ) => {
		if ( el._leafletMap || el.dataset.providerMapInit ) return;

		const parent = el.closest( '.listora-submission__map-field' );

		// Resolve provider — 'osm' (Leaflet) is the bundled default.
		const provider = ( el.dataset.provider || 'osm' ).toLowerCase();

		// Pre-compute the shared start position so every engine centers the same
		// way (edit-mode coords → admin default → NYC legacy fallback).
		const preLat = parent ? parseFloat( parent.querySelector( '[name$="[lat]"]' )?.value ) : NaN;
		const preLng = parent ? parseFloat( parent.querySelector( '[name$="[lng]"]' )?.value ) : NaN;
		const preHasExisting = ! isNaN( preLat ) && ! isNaN( preLng ) && preLat !== 0 && preLng !== 0;
		const dfLat = parseFloat( el.dataset.defaultLat );
		const dfLng = parseFloat( el.dataset.defaultLng );
		const dfZoom = parseInt( el.dataset.defaultZoom, 10 );
		const startLat = preHasExisting ? preLat : ( ! isNaN( dfLat ) ? dfLat : 40.7128 );
		const startLng = preHasExisting ? preLng : ( ! isNaN( dfLng ) ? dfLng : -74.006 );
		const startZoom = preHasExisting ? 15 : ( ! isNaN( dfZoom ) && dfZoom > 0 ? dfZoom : 12 );

		// Non-OSM provider with a registered initializer → delegate and skip Leaflet.
		if (
			'osm' !== provider &&
			typeof window.wbListoraMapPickers[ provider ] === 'function'
		) {
			const handled = window.wbListoraMapPickers[ provider ]( el, {
				parent,
				initialLat: startLat,
				initialLng: startLng,
				initialZoom: startZoom,
				hasExisting: preHasExisting,
				updateLatLngFields,
				reverseGeocode,
				forwardGeocode,
			} );
			if ( handled ) {
				el.dataset.providerMapInit = '1';
				return;
			}
		}

		// No registered engine for this provider: fall back to Leaflet/OSM. When
		// Leaflet isn't present (e.g. a Pro provider deregistered it) the add-on
		// initializes the element off its own DOM scan — mirror the display map's
		// "skip itself when L is undefined" idiom rather than rendering OSM.
		if ( typeof L === 'undefined' ) return;

		// Default coords come from admin's Settings → Maps → Default location
		// (exposed by the renderer as data-default-* attributes), resolved into
		// startLat / startLng / startZoom above. Fall back to NYC only when those
		// attrs are missing — keeps the legacy out-of-the-box behaviour for
		// installs that haven't customised the map defaults.
		const hasExisting = preHasExisting;
		const initialLat = startLat;
		const initialLng = startLng;
		const initialZoom = startZoom;

		const map = L.map( el ).setView( [ initialLat, initialLng ], initialZoom );
		L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; OpenStreetMap',
			maxZoom: 19,
		} ).addTo( map );

		const marker = L.marker( [ initialLat, initialLng ], { draggable: true } ).addTo( map );

		// On marker drag: update coords and reverse-geocode.
		marker.on( 'dragend', () => {
			const pos = marker.getLatLng();
			updateLatLngFields( pos, parent );
			reverseGeocode( pos.lat, pos.lng, parent );
		} );

		// On map click: move marker, update coords, reverse-geocode.
		map.on( 'click', ( e ) => {
			marker.setLatLng( e.latlng );
			updateLatLngFields( e.latlng, parent );
			reverseGeocode( e.latlng.lat, e.latlng.lng, parent );
		} );

		// On address field change: forward-geocode and move marker (debounced).
		if ( parent ) {
			const addressInput = parent.querySelector( '[name$="[address]"]' );
			if ( addressInput ) {
				let geocodeTimeout = null;
				addressInput.addEventListener( 'input', () => {
					if ( geocodeTimeout ) clearTimeout( geocodeTimeout );
					geocodeTimeout = setTimeout( () => {
						forwardGeocode( addressInput.value.trim(), map, marker, parent );
					}, 800 );
				} );
			}
		}

		// If no existing coords, try browser geolocation.
		if ( ! hasExisting && 'geolocation' in navigator ) {
			navigator.geolocation.getCurrentPosition(
				( position ) => {
					const lat = position.coords.latitude;
					const lng = position.coords.longitude;
					const latlng = L.latLng( lat, lng );

					marker.setLatLng( latlng );
					map.setView( latlng, 14 );
					updateLatLngFields( latlng, parent );
				},
				() => {
					// Geolocation denied or unavailable — keep defaults.
				},
				{ timeout: 5000 }
			);
		}

		el._leafletMap = map;

		// Recalc tile geometry once the container is actually laid out. The
		// map is created the moment the Details step is revealed, but the
		// CSS step transition can leave the container at 0 height for a few
		// frames — a single fixed 200ms timeout (the old approach) raced that
		// transition and computed tiles against a 0-height box, so the map
		// only appeared after a resize / extra Continue click (card
		// 9932290292). Recalc via rAF once the box has height, keep a short
		// retry loop as a backstop, and observe later size changes with a
		// ResizeObserver. All paths are idempotent — invalidateSize() is safe
		// to call repeatedly.
		recalcMapWhenVisible( map, el );
	} );
}

