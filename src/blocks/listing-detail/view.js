/**
 * Listing Detail — Interactivity API view module.
 *
 * Handles tab switching, gallery image switching, and detail map.
 *
 * @package WBListora
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'listora/directory', {
	actions: {
		/**
		 * Switch active tab.
		 */
		switchTab() {
			const ctx = getContext();
			const tabId = ctx.tabId;
			const el = getElement();

			// Find parent detail block.
			const detail = el.ref.closest( '.listora-detail' );
			if ( ! detail ) return;

			// Deactivate all tabs and panels.
			detail.querySelectorAll( '.listora-detail__tab' ).forEach( ( tab ) => {
				tab.classList.remove( 'is-active' );
				tab.setAttribute( 'aria-selected', 'false' );
			} );

			detail.querySelectorAll( '.listora-detail__panel' ).forEach( ( panel ) => {
				panel.hidden = true;
			} );

			// Activate clicked tab and panel.
			const tab = detail.querySelector( `#tab-${ tabId }` );
			const panel = detail.querySelector( `#panel-${ tabId }` );

			if ( tab ) {
				tab.classList.add( 'is-active' );
				tab.setAttribute( 'aria-selected', 'true' );
			}
			if ( panel ) {
				panel.hidden = false;
			}

			// Initialize map if map tab clicked.
			if ( tabId === 'map' ) {
				initDetailMap( detail );
			}

			// Update URL hash for direct linking.
			if ( typeof window !== 'undefined' ) {
				window.history.replaceState( null, '', `#${ tabId }` );
			}
		},

		/**
		 * Switch gallery main image.
		 */
		switchGalleryImage() {
			const ctx = getContext();
			const el = getElement();
			const detail = el.ref.closest( '.listora-detail' );
			if ( ! detail ) return;

			// Update main image.
			const mainImg = detail.querySelector( '.listora-detail__gallery-image' );
			if ( mainImg && ctx.imageSrc ) {
				mainImg.src = ctx.imageSrc;
			}

			// Update active thumb.
			detail.querySelectorAll( '.listora-detail__gallery-thumb' ).forEach( ( thumb ) => {
				thumb.classList.remove( 'is-active' );
			} );
			el.ref.classList.add( 'is-active' );
		},
	},

	callbacks: {
		/**
		 * On detail block init — check URL hash for active tab.
		 */
		onDetailInit() {
			if ( typeof window === 'undefined' ) return;

			const hash = window.location.hash.replace( '#', '' );
			if ( hash ) {
				const el = getElement();
				const detail = el.ref.closest( '.listora-detail' );
				const tab = detail?.querySelector( `#tab-${ hash }` );
				if ( tab ) {
					tab.click();
				}
			}
		},
	},
} );

/**
 * Initialize Leaflet map in detail view.
 *
 * @param {HTMLElement} detail The detail block element.
 */
function initDetailMap( detail ) {
	const mapEl = detail.querySelector( '#listora-detail-map' );
	if ( ! mapEl || mapEl._leafletMap || typeof L === 'undefined' ) return;

	const lat = parseFloat( mapEl.dataset.lat );
	const lng = parseFloat( mapEl.dataset.lng );

	if ( ! lat || ! lng ) return;

	const map = L.map( mapEl ).setView( [ lat, lng ], 15 );

	L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
		maxZoom: 19,
	} ).addTo( map );

	L.marker( [ lat, lng ] ).addTo( map );

	mapEl._leafletMap = map;

	// Fix map container size after tab becomes visible.
	setTimeout( () => map.invalidateSize(), 100 );
}
