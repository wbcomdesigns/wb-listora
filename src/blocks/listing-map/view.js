/**
 * Listing Map Block — Leaflet integration via Interactivity API.
 *
 * Initializes the map, adds markers, handles clustering,
 * syncs with search results, and manages card↔marker hover.
 *
 * @package WBListora
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

/** @type {L.Map|null} */
let map = null;

/** @type {L.MarkerClusterGroup|L.LayerGroup|null} */
let markerLayer = null;

/** @type {Object<number, L.Marker>} Marker lookup by listing ID */
const markerMap = {};

/** @type {boolean} Prevent search-on-drag loop */
let isDragging = false;

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
				markerLayer = L.layerGroup();
			}

			map.addLayer( markerLayer );

			// Add initial markers.
			if ( config.markers && config.markers.length > 0 ) {
				addMarkers( config.markers );
				fitMarkersInView();
			}

			// Search on drag (viewport search).
			if ( config.searchOnDrag ) {
				map.on( 'moveend', onMapMoveEnd );
			}

			// Update state.
			state.mapReady = true;
			state.markers = config.markers || [];

			// Watch for active marker changes (card hover → marker bounce).
			watchActiveMarker();
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

	const icon = L.divIcon( {
		className: 'listora-marker',
		html: iconHtml,
		iconSize: [ 28, 36 ],
		iconAnchor: [ 14, 36 ],
		popupAnchor: [ 0, -36 ],
	} );

	const marker = L.marker( [ data.lat, data.lng ], { icon } );

	// Popup with compact card.
	const ratingHtml = data.rating > 0
		? `<span class="listora-map__popup-rating">★ ${ data.rating.toFixed( 1 ) }</span>`
		: '';

	const featuredHtml = data.featured
		? '<span class="listora-badge listora-badge--featured" style="font-size:0.65rem">Featured</span>'
		: '';

	const popupHtml = `
		<div class="listora-map__popup">
			${ featuredHtml }
			<strong class="listora-map__popup-title">
				<a href="${ data.url }">${ escHtml( data.title ) }</a>
			</strong>
			<div class="listora-map__popup-meta">
				${ ratingHtml }
				<span class="listora-badge listora-badge--type" style="--listora-type-color:${ color }">${ data.type }</span>
			</div>
			<a href="${ data.url }" class="listora-btn listora-btn--primary listora-map__popup-link" style="font-size:0.75rem;padding:0.3em 0.8em;margin-top:0.5em">
				View Details →
			</a>
		</div>
	`;

	marker.bindPopup( popupHtml, {
		maxWidth: 240,
		className: 'listora-map__popup-container',
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
 * Fit map view to show all markers.
 */
function fitMarkersInView() {
	if ( ! map || ! markerLayer ) return;

	const bounds = markerLayer.getBounds();
	if ( bounds.isValid() ) {
		map.fitBounds( bounds, { padding: [ 30, 30 ], maxZoom: 15 } );
	}
}

/**
 * Handle map move end — show "Search this area" button.
 */
function onMapMoveEnd() {
	if ( isDragging ) return;

	const btn = document.querySelector( '.listora-map__search-area-btn' );
	if ( btn ) {
		btn.style.display = 'inline-flex';
	}
}

/**
 * Watch state.activeMarker and bounce the corresponding map marker.
 */
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
