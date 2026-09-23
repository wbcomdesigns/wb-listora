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
 * Turn the address field into a search box with a list of matches.
 *
 * It used to geocode on a 800ms debounce after every keystroke and silently
 * apply the FIRST result. Two problems: a member typing "12 High Street" got a
 * pin on whichever High Street the geocoder liked, with no way to see that it
 * had chosen or to correct it; and per-keystroke lookups are what OSM's usage
 * policy forbids, with the site's IP as the thing that gets blocked
 * (card 9867200436).
 *
 * Now: type, press Enter (or click Find), pick from what comes back. One
 * request per search. The pattern members already know from booking and
 * delivery forms.
 *
 * Combobox semantics so it is usable without a mouse: the input owns the
 * listbox, Up/Down move the active option, Enter takes it, Escape closes.
 *
 * @param {HTMLInputElement} input  The address field.
 * @param {L.Map}            map    Leaflet map instance.
 * @param {L.Marker}         marker Leaflet marker instance.
 * @param {HTMLElement}      parent The .listora-submission__map-field container.
 */
function initAddressSearch( input, map, marker, parent ) {
	if ( input.dataset.listoraAddressSearch ) return;
	input.dataset.listoraAddressSearch = '1';

	const i18n = ( window.listoraI18n || {} );
	const listId = `listora-address-matches-${ Math.random().toString( 36 ).slice( 2, 9 ) }`;

	const list = document.createElement( 'ul' );
	list.className = 'listora-address-search__matches';
	list.id = listId;
	list.setAttribute( 'role', 'listbox' );
	list.hidden = true;

	const status = document.createElement( 'p' );
	status.className = 'listora-address-search__status';
	status.setAttribute( 'role', 'status' );
	status.setAttribute( 'aria-live', 'polite' );
	status.hidden = true;

	const button = document.createElement( 'button' );
	button.type = 'button'; // Never submit the wizard step.
	button.className = 'listora-btn listora-btn--secondary listora-address-search__btn';
	button.textContent = i18n.findAddress || 'Find';

	input.setAttribute( 'role', 'combobox' );
	input.setAttribute( 'aria-expanded', 'false' );
	input.setAttribute( 'aria-controls', listId );
	input.setAttribute( 'aria-autocomplete', 'list' );
	input.setAttribute( 'autocomplete', 'off' );

	const wrap = document.createElement( 'div' );
	wrap.className = 'listora-address-search';
	input.parentNode.insertBefore( wrap, input );
	wrap.appendChild( input );
	wrap.appendChild( button );
	wrap.appendChild( status );
	wrap.appendChild( list );

	let active = -1;

	function close() {
		list.hidden = true;
		list.innerHTML = '';
		active = -1;
		input.setAttribute( 'aria-expanded', 'false' );
		input.removeAttribute( 'aria-activedescendant' );
	}

	function setActive( next ) {
		const options = [ ...list.querySelectorAll( '[role="option"]' ) ];
		if ( ! options.length ) return;

		active = ( next + options.length ) % options.length;

		options.forEach( ( option, index ) => {
			const isActive = index === active;
			option.classList.toggle( 'is-active', isActive );
			option.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			if ( isActive ) input.setAttribute( 'aria-activedescendant', option.id );
		} );
	}

	function choose( result ) {
		applyGeocodeResult( result, map, marker, parent );
		// The geocoder's own formatting of the place, so what the member sees
		// on screen is what was actually matched.
		if ( result.display_name ) input.value = result.display_name;
		close();
		status.hidden = true;
		input.focus();
	}

	function search() {
		const query = input.value.trim();

		if ( query.length < 3 ) {
			close();
			return;
		}

		status.hidden = false;
		status.textContent = i18n.searchingAddress || 'Searching…';
		button.disabled = true;

		geocodeAddress( query ).then( ( results ) => {
			button.disabled = false;
			close();

			if ( ! results.length ) {
				status.hidden = false;
				status.textContent = i18n.noAddressMatches || 'No matching addresses. Try a different spelling, or drop the pin on the map.';
				return;
			}

			status.hidden = true;

			results.forEach( ( result, index ) => {
				const option = document.createElement( 'li' );
				option.id = `${ listId }-${ index }`;
				option.className = 'listora-address-search__match';
				option.setAttribute( 'role', 'option' );
				option.setAttribute( 'aria-selected', 'false' );
				option.tabIndex = -1;
				option.textContent = result.display_name || '';
				option.addEventListener( 'click', () => choose( result ) );
				list.appendChild( option );
			} );

			list.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
			setActive( 0 );
		} );
	}

	button.addEventListener( 'click', search );

	input.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Enter' ) {
			// Enter in a wizard step would otherwise submit it.
			event.preventDefault();

			const options = [ ...list.querySelectorAll( '[role="option"]' ) ];
			if ( ! list.hidden && active > -1 && options[ active ] ) {
				options[ active ].click();
			} else {
				search();
			}
			return;
		}

		if ( list.hidden ) return;

		if ( event.key === 'ArrowDown' ) {
			event.preventDefault();
			setActive( active + 1 );
		} else if ( event.key === 'ArrowUp' ) {
			event.preventDefault();
			setActive( active - 1 );
		} else if ( event.key === 'Escape' ) {
			close();
		}
	} );

	// Typing again invalidates the list, but must NOT fire a request.
	input.addEventListener( 'input', () => {
		if ( ! list.hidden ) close();
	} );

	document.addEventListener( 'click', ( event ) => {
		if ( ! wrap.contains( event.target ) ) close();
	} );
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
function applyGeocodeResult( result, map, marker, parent ) {
	const lat = parseFloat( result.lat );
	const lng = parseFloat( result.lon );

	if ( Number.isNaN( lat ) || Number.isNaN( lng ) ) return;

	const latlng = L.latLng( lat, lng );

	if ( marker ) marker.setLatLng( latlng );
	if ( map ) map.setView( latlng, 15 );

	if ( ! parent ) return;

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

/**
 * Look an address up and return candidates.
 *
 * Nominatim by default: no API key, no billing, works on every install. A
 * provider with a better index - Pro's Google Places, say - replaces this by
 * registering `window.wbListoraGeocoder`, which must return a Promise for an
 * array of `{ lat, lon, display_name, address }`.
 *
 * Called on ENTER or the Find button, never per keystroke: OSM's usage policy
 * caps Nominatim at roughly one request a second and forbids type-ahead, and
 * the penalty for ignoring it is the site's IP being blocked - which would
 * break geocoding for every member, not just the one typing.
 *
 * @param {string} query Address the member typed.
 * @return {Promise<Array>} Candidate results, best first.
 */
function geocodeAddress( query ) {
	if ( ! query || query.trim().length < 3 ) {
		return Promise.resolve( [] );
	}

	if ( typeof window.wbListoraGeocoder === 'function' ) {
		return Promise.resolve( window.wbListoraGeocoder( query ) ).then( ( r ) => ( Array.isArray( r ) ? r : [] ) );
	}

	const url = `https://nominatim.openstreetmap.org/search?q=${ encodeURIComponent( query ) }&format=json&addressdetails=1&limit=5`;

	return abortableFetch( url, { headers: { Accept: 'application/json' } } )
		.then( ( res ) => res.json() )
		.then( ( results ) => ( Array.isArray( results ) ? results : [] ) )
		.catch( () => [] );
}

/**
 * Kept for the provider contract: geocode and apply the best match directly.
 *
 * Registered map-picker initializers receive this as `forwardGeocode`, so its
 * signature cannot change without breaking them.
 *
 * @param {string}      query  Address string.
 * @param {L.Map}       map    Leaflet map instance.
 * @param {L.Marker}    marker Leaflet marker instance.
 * @param {HTMLElement} parent The .listora-submission__map-field container.
 */
function forwardGeocode( query, map, marker, parent ) {
	geocodeAddress( query ).then( ( results ) => {
		if ( results.length ) {
			applyGeocodeResult( results[ 0 ], map, marker, parent );
		}
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

		/*
		 * Tiles come from the site's configured source, never a literal.
		 *
		 * This used to hardcode OpenStreetMap's public tiles, so an owner who
		 * pointed Settings > Map at their own tile server still had this picker
		 * hitting OSM - and every unconfigured install kept using OSM's public
		 * infrastructure after the display map deliberately stopped doing so
		 * (their usage policy does not allow it at plugin distribution volume).
		 *
		 * An EMPTY url is a real answer, not a missing one: render no raster
		 * layer. The map still works - pan, zoom and the draggable marker are
		 * Leaflet, not the tiles - and an owner sees an unstyled map that
		 * prompts them to configure a source, rather than a working one quietly
		 * borrowing someone else's bandwidth.
		 */
		const tileUrl = ( el.dataset.tileUrl || '' ).trim();

		if ( tileUrl ) {
			L.tileLayer( tileUrl, {
				attribution: el.dataset.tileAttribution || '',
				maxZoom: 19,
			} ).addTo( map );
		}

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

		// On address search: offer the matches and let the member choose.
		if ( parent ) {
			const addressInput = parent.querySelector( '[name$="[address]"]' );
			if ( addressInput ) {
				initAddressSearch( addressInput, map, marker, parent );
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

