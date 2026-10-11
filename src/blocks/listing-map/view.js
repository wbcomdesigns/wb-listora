/**
 * Listing Map Block — Leaflet integration via Interactivity API.
 *
 * Initializes the map, adds markers, handles clustering,
 * syncs with search results, and manages card↔marker hover.
 *
 * @package WBListora
 */

import { store, getContext, getElement } from '@wordpress/interactivity';
import '../../interactivity/store.js';
import { t } from '../../utils/i18n.js';

/** @type {L.Map|null} */
let map = null;

/** @type {L.MarkerClusterGroup|L.LayerGroup|null} */
let markerLayer = null;

/** @type {Object<number, L.Marker>} Marker lookup by listing ID */
const markerMap = {};

/** @type {boolean} Prevent search-on-drag loop */
let isDragging = false;
// Moves before this time come from code (fit, popup pan), not the visitor.
let ignoreMovesUntil = 0;

const { state, actions } = store( 'listora/directory', {
	actions: {
		/**
		 * Search listings within the current map viewport.
		 */
		searchMapArea() {
			if ( ! map ) return;

			const bounds = map.getBounds();
			state.mapBounds = {
				ne_lat: bounds.getNorthEast().lat,
				ne_lng: bounds.getNorthEast().lng,
				sw_lat: bounds.getSouthWest().lat,
				sw_lng: bounds.getSouthWest().lng,
			};
			state.currentPage = 1;
			actions.searchImmediate();

			// Hide the "Search this area" button.
			const btn = document.querySelector( '.listora-map__search-area-btn' );
			if ( btn ) btn.style.display = 'none';
		},
	},

	callbacks: {
		/**
		 * Initialize the Leaflet map when the block mounts.
		 */
		onMapInit() {
			const ctx = getContext();
			const config = ctx.mapConfig;

			if ( ! config || typeof L === 'undefined' ) return;

			// Another engine (Pro's Google Maps) owns this container — two
			// engines on one element corrupt the map.
			if ( config.provider && 'osm' !== config.provider ) return;

			const el = getElement();
			const mapContainer = el.ref;

			if ( ! mapContainer || map ) return;

			// Initialize map.
			map = L.map( mapContainer, {
				center: [ config.centerLat, config.centerLng ],
				zoom: config.zoom,
				scrollWheelZoom: true,
				zoomControl: true,
			} );

			// Add tile layer (OSM).
			L.tileLayer( config.tileUrl, {
				attribution: config.tileAttribution,
				maxZoom: 19,
			} ).addTo( map );

			// Create marker layer (with or without clustering).
			if ( config.clustering && typeof L.markerClusterGroup === 'function' ) {
				markerLayer = L.markerClusterGroup( {
					maxClusterRadius: 50,
					spiderfyOnMaxZoom: true,
					showCoverageOnHover: false,
					zoomToBoundsOnClick: true,
				} );
			} else {
				// Must be a featureGroup (NOT a plain
				// layerGroup): featureGroup implements getBounds(), which
				// fitMarkersInView() calls. L.layerGroup() has no getBounds,
				// so the non-clustering branch threw
				// "markerLayer.getBounds is not a function" and the map never
				// fit to its markers.
				markerLayer = L.featureGroup();
			}

			map.addLayer( markerLayer );

			// Add initial markers.
			if ( config.markers && config.markers.length > 0 ) {
				addMarkers( config.markers );
				fitMarkersInView( config.markers );
			}

			// Search on drag (viewport search).
			if ( config.searchOnDrag ) {
				map.on( 'moveend', onMapMoveEnd );
				// Opening a popup pans the map; that is not the visitor moving it.
				map.on( 'autopanstart', () => {
					ignoreMovesUntil = Date.now() + 1000;
				} );
			}

			// Update state.
			state.mapReady = true;
			state.markers = config.markers || [];

			// Watch for active marker changes (card hover → marker bounce).
			watchActiveMarker();

			// Redraw when a client-side search replaces the result set.
			watchMarkerSet();
		},
	},
} );

/**
 * Add markers to the map.
 *
 * @param {Array} markers Marker data array.
 */
function addMarkers( markers ) {
	if ( ! markerLayer ) return;

	markerLayer.clearLayers();
	Object.keys( markerMap ).forEach( ( k ) => delete markerMap[ k ] );

	markers.forEach( ( data ) => {
		const marker = createMarker( data );
		markerLayer.addLayer( marker );
		markerMap[ data.id ] = marker;
	} );
}

/**
 * Create a single Leaflet marker.
 *
 * @param {Object} data Marker data.
 * @return {L.Marker}
 */
function createMarker( data ) {
	// Custom colored icon using SVG.
	const color = data.color || '#0073aa';
	const iconHtml = `
		<svg width="28" height="36" viewBox="0 0 28 36" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path d="M14 0C6.268 0 0 6.268 0 14c0 10.5 14 22 14 22s14-11.5 14-22C28 6.268 21.732 0 14 0z" fill="${ color }"/>
			<circle cx="14" cy="14" r="6" fill="white" opacity="0.9"/>
		</svg>
	`;

	/*
	 * The pin STAYS 28x36 visually; the hit box is 40x40.
	 *
	 * Leaflet sizes the marker element from `iconSize`, so the 28x36 pin was
	 * also the entire touch target — below the 40px floor, and the hardest
	 * kind of target to hit accurately because a map is pannable: a missed tap
	 * drags the map instead.
	 *
	 * Widening the pin itself would have made the map look cluttered at
	 * density, so the marker box is padded to 40x40 with the pin centred
	 * inside by .listora-marker in the block stylesheet. iconAnchor moves to
	 * the pin's TIP inside that padded box — (20, 38) — so markers still point
	 * at their exact coordinate rather than sitting 2px off.
	 */
	const icon = L.divIcon( {
		className: 'listora-marker',
		html: iconHtml,
		iconSize: [ 40, 40 ],
		iconAnchor: [ 20, 38 ],
		popupAnchor: [ 0, -38 ],
	} );

	const marker = L.marker( [ data.lat, data.lng ], {
		icon,
		// Kept for the img-icon path and for Leaflet's own bookkeeping, but it
		// is NOT what names this marker — see below.
		alt: data.title || '',
	} );

	/*
	 * Name the marker on its element, not through the `alt` option.
	 *
	 * Leaflet makes markers keyboard-focusable with role="button", so an
	 * unnamed one announces as just "button" and a screen-reader user has no
	 * idea which listing they are on. The obvious fix is the
	 * `alt` marker option — and it does nothing here, because `alt` only
	 * applies to L.icon, which renders an <img>. These are L.divIcon, which
	 * renders a <div>, and a div has no alt attribute. That is why the first
	 * attempt at this measured as still-unnamed.
	 *
	 * Setting aria-label on the element once Leaflet has added it to the map
	 * works for divIcon and for img icons alike.
	 */
	marker.on( 'add', () => {
		const el = marker.getElement();
		if ( el && data.title ) {
			el.setAttribute( 'aria-label', data.title );
		}
	} );

	// Popup with compact card.
	const ratingHtml = data.rating > 0
		? `<span class="listora-map__popup-rating">★ ${ data.rating.toFixed( 1 ) }</span>`
		: '';

	const featuredHtml = data.featured
		? `<span class="listora-badge listora-badge--featured" style="font-size:0.65rem">${ escHtml( t( 'featured', 'Featured' ) ) }</span>`
		: '';

	// Featured image — server-side render.php now exposes the listing's
	// thumbnail URL in `data.image`. CSS for `.listora-map__popup-image`
	// has been in style.css since launch but was unused.
	const imageHtml = data.image
		? `<img class="listora-map__popup-image" src="${ data.image }" alt="${ escHtml( data.title ) }" loading="lazy" />`
		: '';

	const popupHtml = `
		<div class="listora-map__popup">
			<button type="button" class="listora-map__popup-close" aria-label="${ escHtml( t( 'close', 'Close' ) ) }">&times;</button>
			${ imageHtml }
			${ featuredHtml }
			<div class="listora-map__popup-body">
				<strong class="listora-map__popup-title">
					<a href="${ data.url }">${ escHtml( data.title ) }</a>
				</strong>
				<div class="listora-map__popup-meta">
					${ ratingHtml }
					<span class="listora-badge listora-badge--type listora-type--${ escHtml( data.type ) }">${ escHtml( data.typeName || data.type ) }</span>
				</div>
				<a href="${ data.url }" class="listora-btn listora-btn--primary listora-btn--sm listora-map__popup-link">
					${ escHtml( t( 'viewDetails', 'View Details' ) ) } →
				</a>
			</div>
		</div>
	`;

	marker.bindPopup( popupHtml, {
		maxWidth: 240,
		className: 'listora-map__popup-container',
		// Our own close button: Leaflet's is styled by its unlayered vendor sheet, which our layered CSS cannot size.
		closeButton: false,
		// Keep the popup below the Near Me / Search this area buttons at the top of the map.
		autoPanPaddingTopLeft: [ 16, 72 ],
		autoPanPaddingBottomRight: [ 16, 16 ],
	} );

	marker.on( 'popupopen', ( event ) => {
		const close = event.popup.getElement()?.querySelector( '.listora-map__popup-close' );
		if ( close ) {
			close.addEventListener( 'click', () => marker.closePopup() );
		}
	} );

	// Hover: highlight corresponding card.
	marker.on( 'mouseover', () => {
		state.highlightedCard = data.id;
	} );

	marker.on( 'mouseout', () => {
		state.highlightedCard = null;
	} );

	return marker;
}

/**
 * Fit the first view to where the listings are.
 *
 * Fitting every marker let two far-away pins zoom the map out until the
 * city everyone else is in became one unreadable blob.
 * With ten or more markers the view fits the 5th to 95th percentile of
 * latitude and longitude; the trimmed pins are still on the map, one pan
 * away.
 *
 * @param {Array} markers Marker data array.
 */
function fitMarkersInView( markers ) {
	if ( ! map || ! markerLayer ) return;

	// The fit is not the visitor moving the map: its moves must not surface "Search this area".
	ignoreMovesUntil = Date.now() + 1500;

	const list = Array.isArray( markers ) ? markers.filter( ( m ) => m && isFinite( m.lat ) && isFinite( m.lng ) ) : [];

	if ( list.length >= 10 ) {
		const pick = ( key ) => {
			const sorted = list.map( ( m ) => Number( m[ key ] ) ).sort( ( a, b ) => a - b );
			return [ sorted[ Math.floor( sorted.length * 0.05 ) ], sorted[ Math.ceil( sorted.length * 0.95 ) - 1 ] ];
		};
		const [ south, north ] = pick( 'lat' );
		const [ west, east ] = pick( 'lng' );
		map.fitBounds( [ [ south, west ], [ north, east ] ], { padding: [ 30, 30 ], maxZoom: 15 } );
		return;
	}

	// Defensive guard. A plain L.layerGroup (or any custom
	// layer a theme/Pro swaps in) has no getBounds(); only featureGroup /
	// markerClusterGroup do. Bail quietly instead of throwing.
	if ( typeof markerLayer.getBounds !== 'function' ) return;

	const bounds = markerLayer.getBounds();
	if ( bounds.isValid() ) {
		map.fitBounds( bounds, { padding: [ 30, 30 ], maxZoom: 15 } );
	}
}

/**
 * Keep the marker-cap notice honest after a client-side search replaces
 * the pins: it reads "Showing <drawn> of <total>" from the template the
 * server rendered, and hides when nothing is held back.
 *
 * @param {number} drawn Markers on the map.
 */
function updateMapNotice( drawn ) {
	const notice = document.querySelector( '.listora-map__notice' );
	if ( ! notice ) return;

	const total = Number( state.totalResults ) || 0;
	if ( drawn >= total ) {
		notice.hidden = true;
		notice.textContent = '';
		return;
	}

	notice.textContent = ( notice.dataset.template || '%1$s / %2$s' )
		.replace( '%1$s', drawn.toLocaleString() )
		.replace( '%2$s', total.toLocaleString() );
	notice.hidden = false;
}

/**
 * Handle map move end — show "Search this area" button.
 */
function onMapMoveEnd() {
	if ( Date.now() < ignoreMovesUntil ) {
		return;
	}
	if ( isDragging ) return;

	const btn = document.querySelector( '.listora-map__search-area-btn' );
	if ( btn ) {
		btn.style.display = 'inline-flex';
	}
}

/**
 * Watch state.activeMarker and bounce the corresponding map marker.
 */
/**
 * Redraw the pins when the search result set changes.
 *
 * The map drew `config.markers` once at init and never again, while the
 * debounced client search replaced the grid from its own response — so on the
 * Directory a keyword narrowed the cards and left every original pin in place.
 * Both halves now come from one response.
 *
 * Polls for the same reason watchActiveMarker() does: the Interactivity API
 * gives no external watch primitive, and comparing identity is cheap. The
 * signature is length + id order, so a genuine result change redraws while a
 * re-render of the same set does not clear and re-add every pin.
 */
function watchMarkerSet() {
	const signature = ( list ) =>
		Array.isArray( list ) ? list.length + ':' + list.map( ( m ) => m && m.id ).join( ',' ) : '';

	let previous = signature( state.markers );

	setInterval( () => {
		const current = signature( state.markers );

		if ( current === previous ) {
			return;
		}

		previous = current;
		const list = Array.isArray( state.markers ) ? state.markers : [];
		addMarkers( list );
		updateMapNotice( list.length );
	}, 400 );
}

function watchActiveMarker() {
	let previousActive = null;

	// Poll for changes (Interactivity API doesn't have external watch).
	setInterval( () => {
		const current = state.activeMarker;

		if ( current !== previousActive ) {
			// Un-bounce previous.
			if ( previousActive && markerMap[ previousActive ] ) {
				markerMap[ previousActive ].setZIndexOffset( 0 );
			}

			// Bounce current.
			if ( current && markerMap[ current ] ) {
				markerMap[ current ].setZIndexOffset( 1000 );
				markerMap[ current ].openPopup();
			}

			previousActive = current;
		}
	}, 100 );
}

/**
 * Escape HTML for popup content.
 *
 * @param {string} str Raw string.
 * @return {string} Escaped string.
 */
function escHtml( str ) {
	const div = document.createElement( 'div' );
	div.textContent = str;
	return div.innerHTML;
}
