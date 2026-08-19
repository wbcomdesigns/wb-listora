/**
 * WB Listora — Shared Interactivity API Store
 *
 * Single namespace `listora/directory` shared across all blocks.
 * Search ↔ Grid ↔ Map ↔ Card ↔ Detail communicate through this store.
 *
 * @package WBListora
 */

import { store, getContext, getElement } from '@wordpress/interactivity';
import { t } from '../utils/i18n.js';
import {
	abortableApiFetch,
	isAbortError,
	NETWORK_SLOW_MESSAGE,
} from '../utils/abortable-fetch.js';

/**
 * Registry of provider-specific single-listing detail-map renderers.
 *
 * Extension point for add-ons that render the read-only single-listing detail
 * map (the Map tab on a listing page) with a different map engine than the
 * bundled Leaflet/OpenStreetMap one. Mirrors the Add Listing picker registry
 * (`window.wbListoraMapPickers` in src/blocks/listing-submission/view.js) — same
 * delegate-or-fall-back-to-Leaflet pattern, scoped to the detail map.
 *
 * The provider key matches the admin's Settings → Maps → Provider value (and the
 * `wb_listora_map_provider` filter), surfaced on the map element as
 * `data-provider`. 'osm' is handled natively by Leaflet below; any other
 * provider is delegated to a matching entry registered here.
 *
 * Contract — a registered renderer is called as:
 *
 *     window.wbListoraDetailMaps[ provider ]( el, {
 *         lat,   // number — listing latitude
 *         lng,   // number — listing longitude
 *         zoom,  // number — admin-configured default zoom (data-zoom)
 *     } )
 *
 * The renderer OWNS the element from that point: it must build a read-only
 * display map (centered marker, no editing controls needed), set a truthy guard
 * (e.g. `el.dataset.providerMapInit = '1'`) so it doesn't double-init, and
 * return a truthy value to tell Free the map was handled. Returning falsy (e.g.
 * the engine's JS API isn't loaded yet) lets Free fall back to the Leaflet/OSM
 * engine below.
 *
 * Example (Pro, after its Google Maps JS API loader is ready):
 *
 *     window.wbListoraDetailMaps = window.wbListoraDetailMaps || {};
 *     window.wbListoraDetailMaps.google = function ( el, ctx ) { … return true; };
 *
 * @type {Object.<string, Function>}
 */
if ( typeof window !== 'undefined' && typeof window.wbListoraDetailMaps === 'undefined' ) {
	window.wbListoraDetailMaps = {};
}

/**
 * Submit an owner-message form (Free contact form or Pro lead form).
 *
 * One REST capability, one code path. The two forms differ only in their BEM
 * base class, their per-listing nonce field, and whether they collect a phone
 * number — everything else (validation, submit-button state, response
 * handling, toast, abort/timeout copy) was duplicated line for line, which is
 * how the Free form ended up with an inline-styled response while Pro's used
 * proper BEM state classes.
 *
 * The REST path is never built here: whichever server rendered the form
 * advertises it as `contactPath` in the block context, so a `lead_form` toggle
 * flip cannot strand the request on an unregistered route, and Free carries no
 * knowledge of Pro's REST surface (INV-1).
 *
 * @param {Event}   event            Submit event.
 * @param {Object}  config           Per-form contract.
 * @param {string}  config.base      BEM base class, e.g. `listora-lead-form`.
 * @param {string}  config.nonceField Name of the per-listing nonce input.
 * @param {boolean} config.withPhone Whether the form collects a phone number.
 * @return {Promise<void>} Resolves once the submit cycle completes.
 */
async function submitOwnerMessage( event, { base, nonceField, withPhone } ) {
	event.preventDefault();
	const ctx = getContext();
	const el = getElement();
	const form = el.ref.closest( `.${ base }__form` ) || el.ref;
	const msgDiv = form.querySelector( `.${ base }__message` );
	const submitBtn = form.querySelector( 'button[type="submit"]' );

	const setMessage = ( text, state ) => {
		if ( ! msgDiv ) {
			return;
		}
		msgDiv.hidden = false;
		msgDiv.textContent = text;
		msgDiv.className = `${ base }__message ${ base }__message--${ state }`;
	};

	const name = form.querySelector( 'input[name="name"]' )?.value?.trim();
	const email = form.querySelector( 'input[name="email"]' )?.value?.trim();
	const message = form.querySelector( 'textarea[name="message"]' )?.value?.trim();
	const hp = form.querySelector( 'input[name="hp"]' )?.value || '';
	// Per-listing nonce printed by the rendering PHP (P-01). apiFetch only sets
	// the X-WP-Nonce header for logged-in users, so guests must carry the
	// form-specific nonce in the body.
	const nonce = form.querySelector( `input[name="${ nonceField }"]` )?.value || '';

	if ( ! name || ! email || ! message ) {
		setMessage( listoraI18n.leadRequired, 'error' );
		return;
	}

	if ( submitBtn ) {
		submitBtn.disabled = true;
		submitBtn.textContent = listoraI18n.leadSending;
	}

	const data = { name, email, message, hp, _wpnonce: nonce };
	if ( withPhone ) {
		data.phone = form.querySelector( 'input[name="phone"]' )?.value?.trim() || '';
	}

	try {
		const response = await abortableApiFetch( {
			path: ctx.contactPath,
			method: 'POST',
			data,
		} );
		setMessage( ( response && response.message ) || listoraI18n.leadSent, 'success' );
		if ( window.listoraToast ) {
			window.listoraToast( listoraI18n.leadSent, 'success' );
		}
		form.reset();
	} catch ( error ) {
		const errMsg = isAbortError( error )
			? NETWORK_SLOW_MESSAGE
			: ( error && error.message ? error.message : listoraI18n.leadFailed );
		setMessage( errMsg, 'error' );
		if ( window.listoraToast ) {
			window.listoraToast( errMsg, 'error' );
		}
	} finally {
		if ( submitBtn ) {
			submitBtn.disabled = false;
			submitBtn.textContent = listoraI18n.leadSend;
		}
	}
}

/**
 * Initialise the read-only single-listing detail map inside the given element.
 *
 * Default engine is Leaflet/OpenStreetMap (bundled with Free). When the admin
 * selects a different provider (exposed on the element as `data-provider`) and
 * an add-on has registered a matching renderer in `window.wbListoraDetailMaps`,
 * that renderer takes over the element instead of Leaflet. With no engine
 * registered for the provider, falls back to Leaflet/OSM so the map is never
 * blank.
 *
 * Idempotent: a `_leafletMap` (Leaflet) or `dataset.providerMapInit` (delegated)
 * guard prevents a second init when the Map tab is re-opened.
 *
 * @param {HTMLElement} mapEl The `#listora-detail-map` element.
 */
function initDetailMap( mapEl ) {
	if ( mapEl._leafletMap || mapEl.dataset.providerMapInit ) {
		return;
	}

	const lat = parseFloat( mapEl.dataset.lat );
	const lng = parseFloat( mapEl.dataset.lng );
	if ( ! lat || ! lng ) {
		return;
	}

	const provider = ( mapEl.dataset.provider || 'osm' ).toLowerCase();
	const zoom = parseInt( mapEl.dataset.zoom, 10 ) || 15;

	// Non-OSM provider with a registered renderer → delegate and skip Leaflet.
	if (
		'osm' !== provider &&
		typeof window !== 'undefined' &&
		window.wbListoraDetailMaps &&
		typeof window.wbListoraDetailMaps[ provider ] === 'function'
	) {
		const handled = window.wbListoraDetailMaps[ provider ]( mapEl, {
			lat,
			lng,
			zoom,
		} );
		if ( handled ) {
			mapEl.dataset.providerMapInit = '1';
			return;
		}
	}

	// Default / fallback engine: Leaflet/OSM (bundled with Free). When Leaflet
	// isn't present (e.g. a Pro provider deregistered it) there is nothing to
	// fall back to — bail rather than throw.
	if ( typeof L === 'undefined' ) {
		return;
	}

	const map = L.map( mapEl ).setView( [ lat, lng ], zoom );
	L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
		maxZoom: 19,
	} ).addTo( map );
	L.marker( [ lat, lng ] ).addTo( map );
	mapEl._leafletMap = map;
	setTimeout( () => map.invalidateSize(), 100 );
}

const { state, actions, callbacks } = store( 'listora/directory', {
	state: {
		// ─── Search ───
		// searchQuery, selectedType, selectedLocation, selectedCategory, sortBy
		// are injected by the server via wp_interactivity_state() in
		// blocks/listing-search/render.php so the URL-derived values survive
		// hydration. Declaring defaults here would override the server-provided
		// values (IAPI merges JS state ON TOP OF server state, so JS wins for
		// any key that exists in both) — meaning a fresh page load on
		// /?location=New+York would correctly SSR `value="New York"` on the
		// input, but data-wp-bind--value="state.selectedLocation" would then
		// blank it out post-hydration because the JS '' overwrote server's
		// 'New York'. This was the root cause of the "Search by Location not
		// working" QA report — the search button navigated correctly, but
		// when the user landed on the URL the input was empty so they couldn't
		// see what they had searched for.
		filters: {},
		// currentPage is server-injected by blocks/listing-grid/render.php
		// from $_GET['page']. Don't default here — same IAPI merge hazard
		// as the search-related keys above. A JS default of 1 would
		// silently overwrite the SSR page number, so visiting /?page=3
		// would show page-3 cards in the SSR but render the page-1
		// pagination chip in the toolbar (and "next page" would compute
		// 1+1=2 instead of 3+1=4).

		// ─── Geo ───
		userLat: null,
		userLng: null,
		searchRadius: 5,
		mapBounds: null,

		// ─── Results ───
		// totalResults, totalPages, pageFrom, pageTo are injected by the
		// server via wp_interactivity_state() in listing-grid/render.php.
		// Declaring defaults here would override the server-provided counts
		// and make the toolbar read "Showing 1–0 of 0 listings" under the
		// 20 rendered cards.
		results: [],
		facets: {},
		isLoading: false,
		hasSearched: false,
		searchError: null,

		// ─── Type Config ───
		typeFilters: {},
		typeFieldConfig: {},

		// ─── View ───
		viewMode: 'grid',
		get isGridView() {
			return state.viewMode === 'grid' || ! state.viewMode;
		},
		get isListView() {
			return state.viewMode === 'list';
		},

		// ─── Map ───
		mapReady: false,
		activeMarker: null,
		highlightedCard: null,
		markers: [],

		// ─── User & Favorites (server-provided via wp_interactivity_state) ───
		// isLoggedIn, userId, favorites, perPage, radiusUnit are injected by the server.
		// Do NOT define defaults here — they would override server-injected values.

		// ─── UI Panels ───
		showFiltersPanel: false,
		showSuggestions: false,
		suggestions: [],
		recentSearches: [],

		// ─── Date Filters ───
		dateFilter: '',
		dateFrom: '',
		dateTo: '',

		// ─── Calendar ───
		showEventPopover: false,
		eventPopoverTitle: '',
		eventPopoverDate: '',
		eventPopoverUrl: '',

		// ─── Modals ───
		// `activeModal` is the source of truth ('claim' | 'share' | 'login' | null).
		// The boolean getters below are what directives bind against — IAPI's
		// reactivity tracks property reads, and an inline `===` expression in a
		// `data-wp-class--*` value isn't always re-evaluated when the underlying
		// state mutates (Basecamp 9842877199: clicking Claim mutated state but
		// the modal's `data-wp-class--is-open="state.activeModal === 'claim'"`
		// never flipped). Always bind directives to a property, not an expression.
		activeModal: null,
		get isClaimModalOpen() {
			return state.activeModal === 'claim';
		},
		get isShareModalOpen() {
			return state.activeModal === 'share';
		},
		get isLoginModalOpen() {
			return state.activeModal === 'login';
		},
		get isReportModalOpen() {
			return state.activeModal === 'report';
		},
		get isReportReviewModalOpen() {
			return state.activeModal === 'report-review';
		},

		// ─── Report Review (listing-reviews block) ───
		// The review-report modal targets a SINGLE review at a time. There are
		// many review cards on a page but only one page-level modal, so the
		// clicked card's reviewId is captured into `reportReviewId` when the
		// modal opens and read back at submit time. `reportReviewReason` mirrors
		// the <select> value so the modal stays a controlled IAPI surface.
		reportReviewId: 0,
		reportReviewReason: '',

		// ─── Computed ───
		get hasActiveFilters() {
			return (
				!! state.searchQuery ||
				!! state.selectedCategory ||
				!! state.selectedLocation ||
				!! state.selectedType ||
				!! state.dateFilter ||
				!! state.dateFrom ||
				!! state.dateTo ||
				Object.keys( state.filters ).length > 0
			);
		},
		get activeFilterCount() {
			// Card 9871208081 — previously this only counted entries in
			// `state.filters` (the dynamic per-type registry — checkboxes,
			// range fields, etc.). Dropdown filters (category / location /
			// type) and the date range live in dedicated state keys, so
			// they were silently absent from the badge. Now mirrors the
			// truth-set of `hasActiveFilters` above so the count matches
			// what the user perceives as "active filters."
			let count = 0;
			if ( state.searchQuery ) count++;
			if ( state.selectedCategory ) count++;
			if ( state.selectedLocation ) count++;
			// Card 9932186473 — selecting a listing-TYPE tab is navigation
			// (a pivot between type views), not an applied filter. The "All"
			// tab clears the type and per-type tabs switch the active view, so
			// it must not increment the Filters badge. Category / location /
			// keyword / date remain real filters and still count above/below.
			// Date range collapses to one logical filter regardless of which
			// of the three keys is set (preset, from-only, to-only, both).
			if ( state.dateFilter || state.dateFrom || state.dateTo ) count++;
			for ( const key in state.filters ) {
				const val = state.filters[ key ];
				if ( val === '' || val === null || val === undefined ) continue;
				count += Array.isArray( val ) ? val.length : 1;
			}
			return count;
		},
		get isEventType() {
			return state.selectedType === 'event';
		},
		get isDateFilterToday() {
			return state.dateFilter === 'today';
		},
		get isDateFilterWeekend() {
			return state.dateFilter === 'weekend';
		},
		get isDateFilterHappeningNow() {
			return state.dateFilter === 'happening_now';
		},
		get hasDateFilter() {
			return !! state.dateFilter || !! state.dateFrom || !! state.dateTo;
		},
		get isFavorited() {
			const ctx = getContext();
			return state.favorites.includes( ctx.listingId );
		},
		/**
		 * The count beside the Save button, adjusted for this viewer's toggles.
		 *
		 * The server figure counts everyone including this viewer, so the
		 * client cannot just add one on favourite — it has to know whether the
		 * viewer was already in the total. `favoritedAtRender` carries that.
		 *
		 * This exists because the count was server-rendered with no binding:
		 * the heart filled, the row persisted, and the number beside it stayed
		 * wrong until a reload.
		 */
		get favoriteCountDisplay() {
			const ctx = getContext();
			const base = Number( ctx.favoriteCount ) || 0;
			const now = state.favorites.includes( ctx.listingId );
			const before = !! ctx.favoritedAtRender;
			const delta = ( now ? 1 : 0 ) - ( before ? 1 : 0 );
			return String( Math.max( 0, base + delta ) );
		},
		get hasFavoriteCount() {
			const ctx = getContext();
			const base = Number( ctx.favoriteCount ) || 0;
			const now = state.favorites.includes( ctx.listingId );
			const before = !! ctx.favoritedAtRender;
			return Math.max( 0, base + ( now ? 1 : 0 ) - ( before ? 1 : 0 ) ) > 0;
		},
		get favoriteAriaLabel() {
			const ctx = getContext();
			const favorited = state.favorites.includes( ctx.listingId );
			const i18n = ( typeof window !== 'undefined' && window.listoraI18n ) || {};
			return favorited
				? ( i18n.removeFavorite || 'Remove from favorites' )
				: ( i18n.saveFavorite || 'Save to favorites' );
		},
		get isHighlightedCard() {
			const ctx = getContext();
			return state.highlightedCard === ctx.listingId;
		},
		get isActiveMarker() {
			const ctx = getContext();
			return state.activeMarker === ctx.listingId;
		},
		get isActiveTypeTab() {
			// Card 9932186473 — per-type tab highlight. Each per-type button
			// carries `typeSlug` in its `data-wp-context`; the tab is active
			// when its slug matches the currently selected type. The "All"
			// tab uses `!state.selectedType` directly in the template.
			const ctx = getContext();
			return !! ctx.typeSlug && ctx.typeSlug === state.selectedType;
		},
		get hasResults() {
			return state.results.length > 0;
		},
		get showEmptyState() {
			// Show the empty card when:
			//   (a) the user has run a search/filter that returned 0 rows, OR
			//   (b) the server initially rendered with totalResults === 0
			//       (e.g. type-specific page with no listings of that type yet).
			// Without the (b) clause, the server's "0 results" empty state
			// is hidden by `data-wp-class--is-hidden="!state.showEmptyState"`
			// at hydration because hasSearched defaults to false.
			if ( state.isLoading ) return false;
			if ( state.hasSearched && state.results.length === 0 ) return true;
			if ( ( state.totalResults || 0 ) === 0 && ! state.results.length ) return true;
			return false;
		},
		get showPagination() {
			return ( state.totalPages || 0 ) > 1;
		},
		get resultCountText() {
			if ( state.isLoading ) {
				return '';
			}
			if ( state.totalResults === 0 ) {
				return state.hasSearched ? listoraI18n.noResults : '';
			}
			return state.totalResults === 1
				? '1 ' + listoraI18n.result
				: state.totalResults + ' ' + listoraI18n.results;
		},
	},

	actions: {
		// ─── Search ───
		search() {
			// Debounce — clear any pending timeout.
			if ( state._searchTimeout ) {
				clearTimeout( state._searchTimeout );
			}

			// Cancel any in-flight request from a previous search() call so a
			// stale response can't clobber the latest user query, and so a
			// theme/host that hangs the previous request doesn't keep the
			// loader spinning forever (Basecamp 9833977037 — search REST
			// request hung on Reign + WB Debugging without ever resolving).
			if ( state._searchAbort ) {
				state._searchAbort.abort();
			}

			state._searchTimeout = setTimeout( async () => {
				const controller = new AbortController();
				state._searchAbort = controller;

				// Hard timeout so a never-resolving REST call (theme middleware,
				// proxy, or REST namespace conflict) can't trap the UI in a
				// permanent loading state. 20s matches WordPress's default
				// remote-request budget.
				const timeoutId = setTimeout( () => controller.abort( 'timeout' ), 20000 );

				state.isLoading = true;
				state.searchError = null;

				try {
					const url = actions.buildSearchURL();
					const response = await window.wp.apiFetch( {
						path: url,
						signal: controller.signal,
					} );

					state.results = response.listings;

					/*
					 * The map is driven by the SAME response as the grid.
					 *
					 * This action re-rendered the grid from `response.listings`
					 * and never touched `state.markers`, so on the Directory —
					 * where the search, map and grid sit on one page — typing a
					 * keyword narrowed the cards while the map kept every pin
					 * from the initial server render. The two halves then
					 * described different result sets side by side
					 * (BC 10213017602).
					 *
					 * Server-rendered loads were already consistent because the
					 * map block resolves through the same search args as the
					 * grid; only this client path diverged.
					 *
					 * Listings without coordinates are dropped rather than
					 * plotted at 0,0 — a marker in the Gulf of Guinea is worse
					 * than no marker.
					 */
					state.markers = ( response.listings || [] )
						.map( ( l ) => {
							/*
							 * Coordinates live on `geo`, NOT at the top level.
							 *
							 * The first version of this filtered `l.lat` /
							 * `l.lng`, which /search does not return — so every
							 * marker was dropped and the map went from stale
							 * pins to NO pins beside a grid showing results.
							 * Read the payload, do not assume its shape.
							 *
							 * The `??` fallbacks cover a caller that flattens
							 * the coordinates without breaking this again.
							 */
							const lat = Number( l?.geo?.lat ?? l?.lat );
							const lng = Number( l?.geo?.lng ?? l?.lng );

							if ( ! Number.isFinite( lat ) || ! Number.isFinite( lng ) || ( ! lat && ! lng ) ) {
								return null;
							}

							// Field names match the server-rendered marker
							// shape in blocks/listing-map/render.php, so a pin
							// drawn from a search looks identical to one drawn
							// on page load.
							return {
								id: l.id,
								title: l.title,
								lat,
								lng,
								type: l.listing_type || '',
								rating: Number( l?.rating?.average ?? 0 ),
								featured: !! l.is_featured,
								url: l.link || l.url || '',
								image: l?.featured_image?.thumbnail || l?.featured_image?.medium || '',
								imageAlt: l.title || '',
							};
						} )
						.filter( Boolean );

					state.totalResults = response.total;
					state.totalPages = response.pages;
					state.pageFrom = response.total > 0 ? ( state.currentPage - 1 ) * state.perPage + 1 : 0;
					state.pageTo = response.total > 0 ? Math.min( state.currentPage * state.perPage, response.total ) : 0;
					state.facets = response.facets || {};
					state.hasSearched = true;

					// Update URL params for shareability.
					actions.syncURLParams();
				} catch ( error ) {
					// AbortError fires both when a newer search supersedes us
					// (intentional — discard silently) and when our hard
					// timeout fires (surface a clear error so the UI doesn't
					// look broken).
					const isAbort = error?.name === 'AbortError';
					const isTimeout = controller.signal.reason === 'timeout';

					if ( isAbort && ! isTimeout ) {
						// Superseded — let the newer call drive the UI.
						return;
					}

					state.searchError = isTimeout
						? ( ( window.listoraI18n && window.listoraI18n.searchTimeoutError ) || 'Search took too long. Please try again.' )
						: ( error?.message || ( window.listoraI18n && window.listoraI18n.searchError ) || 'Search failed. Please try again.' );
					state.results = [];
					// No results means no pins — leaving the previous set on
					// screen is the same divergence, just with an empty grid.
					state.markers = [];
					state.totalResults = 0;
					state.totalPages = 0;
					state.pageFrom = 0;
					state.pageTo = 0;
					state.hasSearched = true;
				} finally {
					clearTimeout( timeoutId );
					if ( state._searchAbort === controller ) {
						state._searchAbort = null;
					}
					state.isLoading = false;
				}
			}, 300 );
		},

		searchImmediate() {
			if ( state._searchTimeout ) {
				clearTimeout( state._searchTimeout );
			}
			state.currentPage = 1;

			// Progressive enhancement: navigate via URL so the server re-renders
			// with filtered results. This ensures server-rendered cards match the query.
			const params = new URLSearchParams();
			if ( state.searchQuery ) params.set( 'keyword', state.searchQuery );
			if ( state.selectedType ) params.set( 'type', state.selectedType );
			if ( state.selectedCategory ) params.set( 'category', state.selectedCategory );
			if ( state.selectedLocation ) params.set( 'location', state.selectedLocation );
			if ( state.sortBy && state.sortBy !== 'featured' ) params.set( 'sort', state.sortBy );
			if ( state.dateFilter ) params.set( 'date_filter', state.dateFilter );
			if ( state.dateFrom ) params.set( 'date_from', state.dateFrom );
			if ( state.dateTo ) params.set( 'date_to', state.dateTo );
			for ( const [ key, value ] of Object.entries( state.filters ) ) {
				if ( Array.isArray( value ) && value.length > 0 ) {
					params.set( key, value.join( ',' ) );
				} else if ( value ) {
					params.set( key, value );
				}
			}

			// Carry the map viewport ("Search this area") through the navigation
			// so the server re-renders both the grid AND the map markers within
			// the drawn bounds. Without this the bounds were dropped on the
			// full-page reload and the map reset to the initial unfiltered view
			// (Basecamp 9909608502). Only present after searchMapArea() ran.
			if ( state.mapBounds ) {
				params.set( 'bounds[ne_lat]', state.mapBounds.ne_lat );
				params.set( 'bounds[ne_lng]', state.mapBounds.ne_lng );
				params.set( 'bounds[sw_lat]', state.mapBounds.sw_lat );
				params.set( 'bounds[sw_lng]', state.mapBounds.sw_lng );
			}

			const url = window.location.pathname + ( params.toString() ? '?' + params.toString() : '' );
			window.location.href = url;
		},

		// Apply the filters the user has selected in the panel. The individual
		// panel setters below only update state (no navigation) so a visitor
		// can tick several options — category, features, price, etc. — and then
		// apply them together via the "Apply Filters" button, the pattern every
		// major directory / shop filter uses. Clearing a filter still applies
		// immediately (see clearFilter / clearAllFilters).
		applyFilters() {
			state.currentPage = 1;
			actions.searchImmediate();
		},

		buildSearchURL() {
			const params = new URLSearchParams();

			if ( state.searchQuery ) params.set( 'keyword', state.searchQuery );
			if ( state.selectedType ) params.set( 'type', state.selectedType );
			if ( state.selectedCategory ) params.set( 'category', state.selectedCategory );
			if ( state.selectedLocation ) params.set( 'location', state.selectedLocation );
			if ( state.sortBy ) params.set( 'sort', state.sortBy );

			params.set( 'page', state.currentPage );
			params.set( 'per_page', state.perPage );
			params.set( 'facets', 'true' );

			// Date filter params.
			if ( state.dateFilter ) params.set( 'date_filter', state.dateFilter );
			if ( state.dateFrom ) params.set( 'date_from', state.dateFrom );
			if ( state.dateTo ) params.set( 'date_to', state.dateTo );

			// Geo params.
			if ( state.userLat && state.userLng ) {
				params.set( 'lat', state.userLat );
				params.set( 'lng', state.userLng );
				if ( state.searchRadius > 0 ) {
					params.set( 'radius', state.searchRadius );
					params.set( 'radius_unit', state.radiusUnit );
				}
			}

			if ( state.mapBounds ) {
				params.set( 'bounds[ne_lat]', state.mapBounds.ne_lat );
				params.set( 'bounds[ne_lng]', state.mapBounds.ne_lng );
				params.set( 'bounds[sw_lat]', state.mapBounds.sw_lat );
				params.set( 'bounds[sw_lng]', state.mapBounds.sw_lng );
			}

			// Custom field filters.
			for ( const [ key, value ] of Object.entries( state.filters ) ) {
				if ( Array.isArray( value ) ) {
					params.set( key, value.join( ',' ) );
				} else if ( typeof value === 'object' && value.min !== undefined ) {
					if ( value.min !== '' ) params.set( key + '_min', value.min );
					if ( value.max !== '' ) params.set( key + '_max', value.max );
				} else {
					params.set( key, value );
				}
			}

			return '/listora/v1/search?' + params.toString();
		},

		// ─── Filter Actions ───
		setSearchQuery( event ) {
			state.searchQuery = event.target.value;
			state.currentPage = 1;
			actions.search();
			actions.fetchSuggestions();
		},

		setLocation( event ) {
			state.selectedLocation = event.target.value;
			state.currentPage = 1;
			actions.search();
		},

		setFilter() {
			const ctx = getContext();
			const { filterKey, filterValue } = ctx;

			if ( ! filterKey ) return;

			const current = state.filters[ filterKey ];

			if ( Array.isArray( current ) ) {
				// Toggle in array.
				const idx = current.indexOf( filterValue );
				if ( idx > -1 ) {
					state.filters[ filterKey ] = current.filter(
						( v ) => v !== filterValue
					);
					if ( state.filters[ filterKey ].length === 0 ) {
						delete state.filters[ filterKey ];
					}
				} else {
					state.filters[ filterKey ] = [ ...current, filterValue ];
				}
			} else {
				state.filters = {
					...state.filters,
					[ filterKey ]: filterValue,
				};
			}

			// Deferred — applied together via the Apply Filters button.
		},

		setFilterCheckbox( event ) {
			const ctx = getContext();
			const { filterKey, filterValue } = ctx;
			const checked = event.target.checked;

			const current = state.filters[ filterKey ] || [];
			if ( checked ) {
				state.filters = {
					...state.filters,
					[ filterKey ]: [ ...current, filterValue ],
				};
			} else {
				const filtered = current.filter( ( v ) => v !== filterValue );
				if ( filtered.length === 0 ) {
					const { [ filterKey ]: _, ...rest } = state.filters;
					state.filters = rest;
				} else {
					state.filters = { ...state.filters, [ filterKey ]: filtered };
				}
			}

			// Deferred — applied together via the Apply Filters button.
		},

		toggleFeatureFilter( event ) {
			const ctx = getContext();
			const slug = ctx && ctx.featureSlug ? ctx.featureSlug : '';
			if ( ! slug ) {
				return;
			}
			const checked = event.target.checked;
			const current = Array.isArray( state.filters.features ) ? state.filters.features : [];
			if ( checked ) {
				state.filters = {
					...state.filters,
					features: current.includes( slug ) ? current : [ ...current, slug ],
				};
			} else {
				const next = current.filter( ( v ) => v !== slug );
				if ( next.length === 0 ) {
					const { features: _omit, ...rest } = state.filters;
					state.filters = rest;
				} else {
					state.filters = { ...state.filters, features: next };
				}
			}

			// Deferred — applied together via the Apply Filters button.
		},

		setFilterSelect( event ) {
			const ctx = getContext();
			const { filterKey } = ctx;
			const value = event.target.value;

			if ( value === '' || value === 'all' ) {
				const { [ filterKey ]: _, ...rest } = state.filters;
				state.filters = rest;
			} else {
				state.filters = { ...state.filters, [ filterKey ]: value };
			}

			// `searchImmediate()` builds the next URL from a small set of
			// top-level state keys (`state.selectedCategory`,
			// `state.selectedLocation`, `state.selectedType`) PLUS
			// `state.filters[*]`. Updating only `state.filters[filterKey]`
			// leaves the matching `selected*` key stale, which then wins
			// the URL build — picking "All Categories" silently navigates
			// back to the same `?category=...` URL the page came from
			// (QA card 9838055062 reopen 2: "All Categories" reset
			// doesn't reset).
			if ( 'category' === filterKey ) {
				state.selectedCategory = ( '' === value || 'all' === value ) ? '' : value;
			} else if ( 'location' === filterKey ) {
				state.selectedLocation = ( '' === value || 'all' === value ) ? '' : value;
			} else if ( 'type' === filterKey ) {
				state.selectedType = ( '' === value || 'all' === value ) ? '' : value;
			}

			// Deferred — applied together via the Apply Filters button.
		},

		setFilterToggle( event ) {
			const ctx = getContext();
			const { filterKey } = ctx;
			const checked = event.target.checked;

			if ( checked ) {
				state.filters = { ...state.filters, [ filterKey ]: '1' };
			} else {
				const { [ filterKey ]: _, ...rest } = state.filters;
				state.filters = rest;
			}

			// Deferred — applied together via the Apply Filters button.
		},

		// ─── Date Filters ───
		setDateFilter() {
			const ctx = getContext();
			const value = ctx.dateFilterValue || '';

			// Toggle: if already active, clear it.
			if ( state.dateFilter === value ) {
				state.dateFilter = '';
			} else {
				state.dateFilter = value;
				// Clear custom date range when using a preset.
				state.dateFrom = '';
				state.dateTo = '';
			}

			// Deferred — applied together via the Apply Filters button.
		},

		setDateFrom( event ) {
			state.dateFrom = event.target.value;
			// Clear preset when using custom range.
			state.dateFilter = '';
			// Deferred — applied together via the Apply Filters button.
		},

		setDateTo( event ) {
			state.dateTo = event.target.value;
			// Clear preset when using custom range.
			state.dateFilter = '';
			// Deferred — applied together via the Apply Filters button.
		},

		clearFilter() {
			const ctx = getContext();
			const { filterKey } = ctx;
			const { [ filterKey ]: _, ...rest } = state.filters;
			state.filters = rest;
			state.currentPage = 1;
			actions.searchImmediate();
		},

		clearAllFilters() {
			state.searchQuery = '';
			state.selectedCategory = '';
			state.selectedLocation = '';
			state.filters = {};
			state.dateFilter = '';
			state.dateFrom = '';
			state.dateTo = '';
			state.currentPage = 1;
			actions.searchImmediate();
		},

		// ─── Type Selection ───
		async selectType( event ) {
			// Card 9895504466 — the Type <select> in search-bar.php is wired
			// with a STATIC `data-wp-context='{"typeSlug": ""}'`, so reading
			// `getContext().typeSlug` here always returned an empty string
			// regardless of which option the user picked — the filter never
			// updated. Tile-style type buttons elsewhere DO pass `typeSlug`
			// in their context; we keep that path working as a fallback.
			//
			// Resolution order: explicit context value (from tile buttons)
			// → event.target.value (from <select> change events) → empty.
			const ctx = getContext();
			let slug = ctx && ctx.typeSlug ? ctx.typeSlug : '';
			if ( ! slug && event && event.target && 'value' in event.target ) {
				slug = event.target.value || '';
			}

			state.selectedType = slug;
			state.filters = {};
			state.currentPage = 1;

			// Load filter config for this type if not cached.
			if ( slug && ! state.typeFilters[ slug ] ) {
				try {
					const config = await abortableApiFetch( {
						path: `/listora/v1/listing-types/${ slug }/fields`,
					} );
					state.typeFilters = {
						...state.typeFilters,
						[ slug ]: config.filters,
					};
					state.typeFieldConfig = {
						...state.typeFieldConfig,
						[ slug ]: config.field_groups,
					};
				} catch ( e ) {
					// Silently fail — filters will be empty. Abort/timeout
					// is treated the same: filter UI remains usable albeit
					// without the type-specific fields.
				}
			}

			actions.searchImmediate();
		},

		// ─── Sort ───
		setSort( event ) {
			state.sortBy = event.target.value;
			state.currentPage = 1;
			actions.searchImmediate();
		},

		// ─── Pagination ───
		setPage() {
			const ctx = getContext();
			state.currentPage = ctx.page;
			actions.searchImmediate();

			// Scroll to top of results.
			const resultsEl = document.querySelector(
				'.listora-grid__results'
			);
			if ( resultsEl ) {
				resultsEl.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		},

		nextPage() {
			if ( state.currentPage < state.totalPages ) {
				state.currentPage++;
				actions.searchImmediate();
			}
		},

		prevPage() {
			if ( state.currentPage > 1 ) {
				state.currentPage--;
				actions.searchImmediate();
			}
		},

		// ─── View Mode ───
		setViewMode() {
			const ctx = getContext();
			state.viewMode = ctx.mode;
		},

		// ─── Geolocation ───
		async nearMe() {
			if ( ! navigator.geolocation ) {
				state.searchError = listoraI18n.geoNotSupported;
				return;
			}

			try {
				const pos = await new Promise( ( resolve, reject ) => {
					navigator.geolocation.getCurrentPosition( resolve, reject, {
						enableHighAccuracy: false,
						timeout: 10000,
					} );
				} );
				state.userLat = pos.coords.latitude;
				state.userLng = pos.coords.longitude;
				state.sortBy = 'distance';
				state.currentPage = 1;
				actions.searchImmediate();
			} catch ( error ) {
				state.searchError = listoraI18n.geoDenied;
			}
		},

		// ─── Map ↔ Card Sync ───
		highlightMarker() {
			const ctx = getContext();
			state.activeMarker = ctx.listingId;
		},

		unhighlightMarker() {
			state.activeMarker = null;
		},

		highlightCard() {
			const ctx = getContext();
			state.highlightedCard = ctx.listingId;
		},

		unhighlightCard() {
			state.highlightedCard = null;
		},

		updateMapBounds() {
			const ctx = getContext();
			state.mapBounds = ctx.bounds;
			state.currentPage = 1;
			actions.search();
		},

		// ─── Search Suggestions ───
		showSuggestions() {
			if ( state.searchQuery.length >= 2 ) {
				state.showSuggestions = true;
			}
		},

		hideSuggestions() {
			setTimeout( () => {
				state.showSuggestions = false;
			}, 200 );
		},

		clearSearchQuery() {
			state.searchQuery = '';
			state.showSuggestions = false;
			state.currentPage = 1;
			actions.searchImmediate();
		},

		async fetchSuggestions() {
			if ( state.searchQuery.length < 2 ) {
				state.showSuggestions = false;
				return;
			}

			try {
				const response = await abortableApiFetch( {
					path: `/listora/v1/search/suggest?q=${ encodeURIComponent( state.searchQuery ) }&type=${ encodeURIComponent( state.selectedType ) }`,
				} );
				// REST controller (class-search-controller.php:694) wraps
				// the array in { suggestions: [...] }. Reading state.suggestions
				// must be the inner array — assigning the whole envelope made
				// IAPI's <ul data-wp-each> iterate over `{ suggestions, ... }`
				// keys and render nothing.
				state.suggestions = Array.isArray( response?.suggestions ) ? response.suggestions : [];
				state.showSuggestions = state.suggestions.length > 0;
			} catch ( e ) {
				// Hide suggestions on any error — including timeout. The
				// user can keep typing; suggestions are a nice-to-have.
				state.showSuggestions = false;
			}
		},

		handleSuggestionKeydown( event ) {
			if ( event.key === 'Escape' ) {
				state.showSuggestions = false;
			} else if ( event.key === 'ArrowDown' || event.key === 'ArrowUp' ) {
				event.preventDefault();
				const items = event.target.closest( '.listora-search__field' )?.querySelectorAll( '.listora-search__suggestion-item' );
				if ( ! items?.length ) return;

				const current = event.target.closest( '.listora-search__field' )?.querySelector( '.listora-search__suggestion-item.is-highlighted' );
				let idx = current ? Array.from( items ).indexOf( current ) : -1;

				current?.classList.remove( 'is-highlighted' );

				if ( event.key === 'ArrowDown' ) {
					idx = Math.min( idx + 1, items.length - 1 );
				} else {
					idx = Math.max( idx - 1, 0 );
				}

				items[ idx ]?.classList.add( 'is-highlighted' );
				items[ idx ]?.scrollIntoView( { block: 'nearest' } );
			} else if ( event.key === 'Enter' ) {
				const highlighted = event.target.closest( '.listora-search__field' )?.querySelector( '.listora-search__suggestion-item.is-highlighted' );
				if ( highlighted ) {
					event.preventDefault();
					highlighted.click();
				}
			}
		},

		// ─── Favorites ───
		async toggleFavorite( event ) {
			event.preventDefault();
			event.stopPropagation();

			if ( ! state.isLoggedIn ) {
				actions.openModal( 'login' );
				return;
			}

			const ctx = getContext();
			const listingId = ctx.listingId;
			const idx = state.favorites.indexOf( listingId );

			// Optimistic update.
			if ( idx > -1 ) {
				state.favorites = state.favorites.filter(
					( id ) => id !== listingId
				);
			} else {
				state.favorites = [ ...state.favorites, listingId ];
			}

			try {
				if ( idx > -1 ) {
					await abortableApiFetch( {
						path: `/listora/v1/favorites/${ listingId }`,
						method: 'DELETE',
					} );
				} else {
					await abortableApiFetch( {
						path: '/listora/v1/favorites',
						method: 'POST',
						data: { listing_id: listingId },
					} );
				}
			} catch ( error ) {
				// Revert on failure (network error OR abort/timeout).
				if ( idx > -1 ) {
					state.favorites = [ ...state.favorites, listingId ];
				} else {
					state.favorites = state.favorites.filter(
						( id ) => id !== listingId
					);
				}
				if ( isAbortError( error ) && window.listoraToast ) {
					window.listoraToast( NETWORK_SLOW_MESSAGE, 'error' );
				}
			}
		},

		// ─── Feature Listing (owner) ───
		async featureListing( event ) {
			event.preventDefault();
			event.stopPropagation();

			if ( ! state.isLoggedIn ) {
				actions.openModal( 'login' );
				return;
			}

			const btn = event.currentTarget;
			if ( ! btn || btn.dataset.listoraFeatureInflight === '1' ) {
				return;
			}

			const listingId = parseInt( btn.dataset.listoraListingId || '0', 10 );
			if ( ! listingId ) {
				return;
			}

			btn.dataset.listoraFeatureInflight = '1';
			btn.setAttribute( 'disabled', 'disabled' );
			btn.classList.add( 'is-loading' );

			const unlock = () => {
				btn.removeAttribute( 'disabled' );
				btn.classList.remove( 'is-loading' );
				btn.dataset.listoraFeatureInflight = '0';
			};

			try {
				const data = await abortableApiFetch( {
					path: `/listora/v1/listings/${ listingId }/feature`,
					method: 'POST',
				} );

				if ( window.listoraToast ) {
					window.listoraToast(
						( data && data.message ) || listoraI18n.featureSuccess || 'Listing featured.',
						'success'
					);
				}
				// Reload so the badge, detail status, and credit balance update.
				window.setTimeout( () => window.location.reload(), 600 );
			} catch ( error ) {
				const message = isAbortError( error )
					? NETWORK_SLOW_MESSAGE
					: ( error && error.message
						? error.message
						: listoraI18n.featureFailed || 'Unable to feature this listing.' );
				if ( window.listoraToast ) {
					window.listoraToast( message, 'error' );
				}
				unlock();
			}
		},

		// ─── Owner: Deactivate Listing ───
		/**
		 * Activate a listing paused on credits, when the balance already covers it.
		 *
		 * Resuming used to be triggered ONLY by a top-up, so a member who
		 * already held enough credits had no way to finish: the dashboard told
		 * them to buy credits they did not need. This is that missing action.
		 *
		 * The server re-checks ownership, the paused status, the plan and the
		 * balance - this is a convenience trigger, not the authority.
		 */
		async activatePausedListing( event ) {
			event.preventDefault();
			event.stopPropagation();

			const btn = event.currentTarget;
			const note = btn && btn.closest( '.listora-dashboard__paused-note' );
			const listingId = note ? parseInt( note.dataset.listingId, 10 ) : 0;

			if ( ! listingId || ( btn && btn.dataset.listoraActivateInflight === '1' ) ) {
				return;
			}

			if ( btn ) {
				btn.dataset.listoraActivateInflight = '1';
				btn.setAttribute( 'disabled', 'disabled' );
			}

			const i18n = ( typeof window !== 'undefined' && window.listoraI18n ) || {};

			try {
				await abortableApiFetch( {
					path: `/listora/v1/listings/${ listingId }/activate-plan`,
					method: 'POST',
				} );

				if ( window.listoraToast ) {
					window.listoraToast(
						i18n.listingActivated || 'Listing activated.',
						'success'
					);
				}

				// Let the toast register before the page swaps under the user.
				setTimeout( () => {
					window.location.reload();
				}, 1200 );
			} catch ( error ) {
				if ( btn ) {
					delete btn.dataset.listoraActivateInflight;
					btn.removeAttribute( 'disabled' );
				}
				const errMsg = isAbortError( error )
					? NETWORK_SLOW_MESSAGE
					: ( ( error && error.message ) ||
						i18n.listingActivateFailed ||
						'Could not activate this listing. Please contact the site owner.' );

				if ( window.listoraToast ) {
					window.listoraToast( errMsg, 'error' );
				}
			}
		},

		async deactivateListing( event ) {
			event.preventDefault();
			event.stopPropagation();

			const ctx = getContext();
			const listingId = ctx && ctx.listingId ? parseInt( ctx.listingId, 10 ) : 0;
			if ( ! listingId ) {
				return;
			}

			const btn = event.currentTarget;
			if ( btn && btn.dataset.listoraDeactivateInflight === '1' ) {
				return;
			}

			// Use the design-system modal (Promise-returning) instead of native
			// confirm — keyboard-trapped, focus-managed, screen-reader-friendly.
			// listora-confirm assets are guaranteed to be enqueued on every
			// surface that renders the user-dashboard / detail blocks.
			const confirmMsg =
				( window.listoraI18n && window.listoraI18n.confirmDeactivate ) ||
				'Deactivate this listing? It will be hidden from the public directory until you reactivate it.';
			const confirmed = await window.listoraConfirm( {
				title: ( window.listoraI18n && window.listoraI18n.confirmDeactivateTitle ) || 'Deactivate listing?',
				message: confirmMsg,
				confirmLabel: ( window.listoraI18n && window.listoraI18n.deactivate ) || 'Deactivate',
				cancelLabel: ( window.listoraI18n && window.listoraI18n.cancel ) || 'Cancel',
				tone: 'danger',
			} );
			if ( ! confirmed ) {
				return;
			}

			if ( btn ) {
				btn.dataset.listoraDeactivateInflight = '1';
				btn.setAttribute( 'disabled', 'disabled' );
			}

			try {
				await abortableApiFetch( {
					path: `/listora/v1/listings/${ listingId }/deactivate`,
					method: 'POST',
				} );

				if ( window.listoraToast ) {
					window.listoraToast(
						( window.listoraI18n && window.listoraI18n.deactivateSuccess ) ||
							'Listing deactivated.',
						'success'
					);
				}
				window.setTimeout( () => window.location.reload(), 600 );
			} catch ( error ) {
				const message = isAbortError( error )
					? NETWORK_SLOW_MESSAGE
					: ( error && error.message
						? error.message
						: ( window.listoraI18n && window.listoraI18n.deactivateFailed ) ||
								'Unable to deactivate listing.' );
				if ( window.listoraToast ) {
					window.listoraToast( message, 'error' );
				}
				if ( btn ) {
					btn.removeAttribute( 'disabled' );
					btn.dataset.listoraDeactivateInflight = '0';
				}
			}
		},

		async reactivateListing( event ) {
			event.preventDefault();
			event.stopPropagation();

			const ctx = getContext();
			const listingId = ctx && ctx.listingId ? parseInt( ctx.listingId, 10 ) : 0;
			if ( ! listingId ) {
				return;
			}

			const btn = event.currentTarget;
			if ( btn && btn.dataset.listoraReactivateInflight === '1' ) {
				return;
			}

			const confirmMsg =
				( window.listoraI18n && window.listoraI18n.confirmReactivate ) ||
				'Reactivate this listing? It will reappear in the public directory.';
			const confirmed = await window.listoraConfirm( {
				title: ( window.listoraI18n && window.listoraI18n.confirmReactivateTitle ) || 'Reactivate listing?',
				message: confirmMsg,
				confirmLabel: ( window.listoraI18n && window.listoraI18n.reactivate ) || 'Reactivate',
				cancelLabel: ( window.listoraI18n && window.listoraI18n.cancel ) || 'Cancel',
				tone: 'primary',
			} );
			if ( ! confirmed ) {
				return;
			}

			if ( btn ) {
				btn.dataset.listoraReactivateInflight = '1';
				btn.setAttribute( 'disabled', 'disabled' );
			}

			try {
				await abortableApiFetch( {
					path: `/listora/v1/listings/${ listingId }/reactivate`,
					method: 'POST',
				} );

				if ( window.listoraToast ) {
					window.listoraToast(
						( window.listoraI18n && window.listoraI18n.reactivateSuccess ) ||
							'Listing reactivated.',
						'success'
					);
				}
				window.setTimeout( () => window.location.reload(), 600 );
			} catch ( error ) {
				const message = isAbortError( error )
					? NETWORK_SLOW_MESSAGE
					: ( error && error.message
						? error.message
						: ( window.listoraI18n && window.listoraI18n.reactivateFailed ) ||
								'Unable to reactivate listing.' );
				if ( window.listoraToast ) {
					window.listoraToast( message, 'error' );
				}
				if ( btn ) {
					btn.removeAttribute( 'disabled' );
					btn.dataset.listoraReactivateInflight = '0';
				}
			}
		},

		// ─── Profile ───
		async saveProfile( event ) {
			event.preventDefault();

			const form = event.currentTarget;
			if ( ! form || form.dataset.listoraProfileInflight === '1' ) {
				return;
			}

			form.dataset.listoraProfileInflight = '1';
			const submitBtn = form.querySelector( '[type="submit"]' );
			if ( submitBtn ) {
				submitBtn.setAttribute( 'disabled', 'disabled' );
				submitBtn.classList.add( 'is-loading' );
			}

			const fd = new FormData( form );
			const payload = {
				display_name: fd.get( 'display_name' ) || '',
				email: fd.get( 'email' ) || '',
				first_name: fd.get( 'first_name' ) || '',
				last_name: fd.get( 'last_name' ) || '',
				phone: fd.get( 'phone' ) || '',
				website: fd.get( 'website' ) || '',
				description: fd.get( 'description' ) || '',
			};

			// Notification preferences live under notification_prefs[event_key].
			// Social links live under social_links[platform_slug]. Walk the
			// FormData once and route each into the right sub-object.
			const prefs = {};
			const socials = {};
			for ( const [ key, value ] of fd.entries() ) {
				const prefMatch = key.match( /^notification_prefs\[([^\]]+)\]$/ );
				if ( prefMatch ) {
					prefs[ prefMatch[ 1 ] ] = value;
					continue;
				}
				const socialMatch = key.match( /^social_links\[([^\]]+)\]$/ );
				if ( socialMatch ) {
					socials[ socialMatch[ 1 ] ] = value;
				}
			}
			payload.notification_prefs = prefs;
			payload.social_links = socials;

			try {
				await abortableApiFetch( {
					path: '/listora/v1/dashboard/profile',
					method: 'PUT',
					data: payload,
				} );

				if ( window.listoraToast ) {
					window.listoraToast(
						( window.listoraI18n && window.listoraI18n.profileSaved ) ||
							'Profile saved.',
						'success'
					);
				}
			} catch ( error ) {
				const message = isAbortError( error )
					? NETWORK_SLOW_MESSAGE
					: ( error && error.message
						? error.message
						: ( window.listoraI18n && window.listoraI18n.profileFailed ) ||
								'Unable to save profile.' );
				if ( window.listoraToast ) {
					window.listoraToast( message, 'error' );
				}
			} finally {
				form.dataset.listoraProfileInflight = '0';
				if ( submitBtn ) {
					submitBtn.removeAttribute( 'disabled' );
					submitBtn.classList.remove( 'is-loading' );
				}
			}
		},

		// ─── Modals ───
		shareDialog( event ) {
			event.preventDefault();
			const ctx = getContext();

			if ( navigator.share ) {
				navigator.share( {
					title: ctx.listingTitle,
					url: ctx.listingUrl,
				} );
			} else {
				actions.openModal( 'share' );
			}
		},

		showClaimModal( event ) {
			event.preventDefault();
			actions.openModal( 'claim' );
		},

		showLoginModal( event ) {
			event.preventDefault();
			actions.openModal( 'login' );
		},

		// Open a modal with proper a11y: remember the trigger, move focus into the
		// dialog, and lock background scroll. Every claim/share/login/report open
		// path routes through here so keyboard + screen-reader users land inside
		// the dialog and the page behind it can't scroll (QA a11y, 1.3.0).
		openModal( name ) {
			state.activeModal = name;
			if ( typeof document === 'undefined' ) {
				return;
			}
			state._modalTrigger = document.activeElement || null;
			document.body.style.overflow = 'hidden';

			// Trap Tab inside the dialog.
			//
			// Escape-to-close, backdrop-click and focus-return were all already
			// handled, so the modals looked complete: a keyboard user could open
			// one, and every visible affordance worked. But Tab walked straight
			// out of the dialog into the page behind it while the modal stayed
			// open and blocking - the listing detail modals (claim, report,
			// login) never adopted the trap that assets/js/shared/confirm.js has
			// implemented all along. WAI-ARIA APG requires it for aria-modal.
			if ( ! state._modalKeydown ) {
				state._modalKeydown = ( event ) => {
					if ( 'Tab' !== event.key || ! state.activeModal ) {
						return;
					}
					const dialog = document.querySelector(
						'.listora-detail__modal.is-open [role="dialog"]'
					);
					if ( ! dialog ) {
						return;
					}
					const nodes = Array.from(
						dialog.querySelectorAll(
							'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
						)
					).filter(
						( el ) => ! el.disabled && el.offsetParent !== null
					);
					if ( ! nodes.length ) {
						return;
					}
					const first = nodes[ 0 ];
					const last = nodes[ nodes.length - 1 ];
					// Focus can sit on the dialog itself (it is given
					// tabindex="-1" below), which is neither first nor last -
					// without this the first Tab escapes.
					if ( ! dialog.contains( document.activeElement ) ) {
						event.preventDefault();
						first.focus();
						return;
					}
					if ( event.shiftKey && document.activeElement === first ) {
						event.preventDefault();
						last.focus();
					} else if (
						! event.shiftKey &&
						document.activeElement === last
					) {
						event.preventDefault();
						first.focus();
					}
				};
				document.addEventListener( 'keydown', state._modalKeydown );
			}
			if ( typeof window !== 'undefined' ) {
				window.requestAnimationFrame( () => {
					const dialog = document.querySelector(
						'.listora-detail__modal.is-open [role="dialog"]'
					);
					if ( dialog ) {
						if ( ! dialog.hasAttribute( 'tabindex' ) ) {
							dialog.setAttribute( 'tabindex', '-1' );
						}
						dialog.focus();
					}
				} );
			}
		},

		closeModal() {
			state.activeModal = null;
			if ( typeof document === 'undefined' ) {
				return;
			}
			document.body.style.overflow = '';
			if ( state._modalKeydown ) {
				document.removeEventListener( 'keydown', state._modalKeydown );
				state._modalKeydown = null;
			}
			const trigger = state._modalTrigger;
			state._modalTrigger = null;
			if ( trigger && typeof trigger.focus === 'function' ) {
				trigger.focus();
			}
		},

		// Open the report modal — guests are routed to the login modal first
		// (the REST endpoint requires auth). Mirrors the favorite/claim pattern.
		openReportModal( event ) {
			if ( event ) {
				event.preventDefault();
			}
			if ( ! state.isLoggedIn ) {
				actions.openModal( 'login' );
				return;
			}
			actions.openModal( 'report' );
		},

		async submitReport( event ) {
			event.preventDefault();
			const ctx = getContext();
			const form = event.target;
			const btn = form.querySelector( 'button[type="submit"]' );
			const msgEl = form.querySelector( '.listora-detail__report-message' );
			const reasonEl = form.querySelector( '[name="reason"]' );
			const detailsEl = form.querySelector( '[name="details"]' );
			const reason = reasonEl ? reasonEl.value : '';
			const details = detailsEl ? detailsEl.value.trim() : '';

			if ( ! reason ) {
				return;
			}

			btn.disabled = true;
			btn.textContent = listoraI18n.submitting;

			try {
				await abortableApiFetch( {
					path: `/listora/v1/listings/${ ctx.listingId }/report`,
					method: 'POST',
					data: { reason, details },
				} );

				// Swap the form body for a success state.
				const body = form.querySelector( '.listora-detail__report-body' );
				if ( body ) {
					body.hidden = true;
				}
				if ( msgEl ) {
					msgEl.hidden = false;
					msgEl.className = 'listora-detail__report-message listora-detail__report-message--success';
					msgEl.textContent = listoraI18n.reportSubmitted;
				}
				if ( window.listoraToast ) {
					window.listoraToast( listoraI18n.reportSubmitted, 'success' );
				}
			} catch ( error ) {
				const errMsg = isAbortError( error )
					? NETWORK_SLOW_MESSAGE
					: ( error.message || listoraI18n.reportFailed );
				if ( msgEl ) {
					msgEl.hidden = false;
					msgEl.textContent = errMsg;
					msgEl.className = 'listora-detail__report-message listora-detail__report-message--error';
				}
				if ( window.listoraToast ) {
					window.listoraToast( errMsg, 'error' );
				}
				btn.disabled = false;
				btn.textContent = listoraI18n.submitReport;
			}
		},

		// ─── Report a Review (listing-reviews block) ───
		// Replaces the former native prompt() in
		// src/blocks/listing-reviews/view.js — prompt() is inaccessible to
		// screen readers and blocked under strict Content-Security-Policy.
		// Opens the page-level review-report dialog (reviews.php) reusing the
		// .listora-detail__modal family. Guests are routed to the login modal
		// first because POST /reviews/{id}/report requires auth.
		// Block the author of a review, from the review card itself.
		//
		// The REST surface (/me/blocks) and the read-side filtering both already
		// existed; the feature was app-only because nothing on the web called
		// them (BC 10185681658). The card renders the trigger only when
		// blocking is actually possible, so this action does not re-derive
		// those conditions — it validates the id and posts.
		//
		// A reload is the honest way to reflect the result: blocking is
		// symmetric and changes BOTH the review list and the headline count,
		// and the count is computed server-side. Patching the DOM here would
		// leave the summary stale, which is the exact mismatch fixed in
		// BC 10185680640.
		async blockReviewAuthor( event ) {
			if ( event ) {
				event.preventDefault();
			}

			const ctx = getContext();
			const targetId = ctx && ctx.blockUserId ? parseInt( ctx.blockUserId, 10 ) : 0;
			if ( ! targetId ) {
				return;
			}

			if ( ! state.isLoggedIn ) {
				actions.openModal( 'login' );
				return;
			}

			const btn = event && event.target ? event.target.closest( 'button' ) : null;
			if ( btn ) {
				btn.disabled = true;
			}

			try {
				await abortableApiFetch( {
					path: '/listora/v1/me/blocks',
					method: 'POST',
					data: { user_id: targetId },
				} );

				if ( window.listoraToast ) {
					window.listoraToast( listoraI18n.memberBlocked, 'success' );
				}

				// Let the toast register before the page swaps under the user.
				setTimeout( () => {
					window.location.reload();
				}, 1200 );
			} catch ( error ) {
				const errMsg = isAbortError( error )
					? NETWORK_SLOW_MESSAGE
					: ( error.message || listoraI18n.memberBlockFailed );
				if ( window.listoraToast ) {
					window.listoraToast( errMsg, 'error' );
				}
				if ( btn ) {
					btn.disabled = false;
				}
			}
		},

		// Undo a block, from the dashboard's Blocked Members list.
		// Pairs with blockReviewAuthor above: blocking was reachable from a
		// review card while unblocking existed only over REST, so the action
		// was one-way on the web (BC 10192062770).
		async unblockMember( event ) {
			if ( event ) {
				event.preventDefault();
			}

			const ctx = getContext();
			const targetId = ctx && ctx.unblockUserId ? parseInt( ctx.unblockUserId, 10 ) : 0;
			if ( ! targetId ) {
				return;
			}

			const btn = event && event.target ? event.target.closest( 'button' ) : null;
			const row = btn ? btn.closest( '.listora-dashboard__blocked-row' ) : null;
			if ( btn ) {
				btn.disabled = true;
			}

			try {
				await abortableApiFetch( {
					path: `/listora/v1/me/blocks/${ targetId }`,
					method: 'DELETE',
				} );

				// Removing the row is safe here, unlike blocking: nothing else on
				// this screen is derived from the block set, so there is no
				// server-computed figure left to go stale.
				if ( row ) {
					const list = row.parentElement;
					row.remove();

					// Unblocking the LAST member leaves an empty <ul> until the
					// next reload — the server renders the empty state, but only
					// on a fresh request. Swap it in here so the section never
					// reads as broken.
					if ( list && ! list.children.length ) {
						const note = document.createElement( 'p' );
						note.className = 'listora-dashboard__blocked-empty';
						note.textContent = t(
							'jsNoBlockedMembers',
							'You have not blocked anyone. You can block a member from any review they have written.'
						);
						list.replaceWith( note );
					}
				}
				if ( window.listoraToast ) {
					window.listoraToast( listoraI18n.memberUnblocked, 'success' );
				}
			} catch ( error ) {
				const errMsg = isAbortError( error )
					? NETWORK_SLOW_MESSAGE
					: ( error.message || listoraI18n.memberUnblockFailed );
				if ( window.listoraToast ) {
					window.listoraToast( errMsg, 'error' );
				}
				if ( btn ) {
					btn.disabled = false;
				}
			}
		},

		showReportModal( event ) {
			if ( event ) {
				event.preventDefault();
			}
			const ctx = getContext();
			const reviewId = ctx && ctx.reviewId ? parseInt( ctx.reviewId, 10 ) : 0;
			if ( ! reviewId ) {
				return;
			}

			if ( ! state.isLoggedIn ) {
				actions.openModal( 'login' );
				return;
			}

			state.reportReviewId = reviewId;
			state.reportReviewReason = '';
			state.activeModal = 'report-review';
			if ( typeof document !== 'undefined' ) {
				document.body.style.overflow = 'hidden';
			}

			// Move focus into the dialog so keyboard + screen-reader users
			// land inside the modal, not back at the page top. The dialog
			// element carries tabindex="-1" for this. Restore focus on close
			// is handled by closeReportReviewModal().
			state._reportReviewTrigger =
				( typeof document !== 'undefined' && document.activeElement ) || null;
			if ( typeof window !== 'undefined' ) {
				window.requestAnimationFrame( () => {
					const dialog = document.getElementById( 'listora-report-review-dialog' );
					if ( dialog ) {
						dialog.focus();
					}
				} );
			}
		},

		setReportReviewReason( event ) {
			state.reportReviewReason = event.target.value;
		},

		handleReportReviewKeydown( event ) {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				actions.closeReportReviewModal();
			}
		},

		closeReportReviewModal() {
			state.activeModal = null;
			state.reportReviewId = 0;
			state.reportReviewReason = '';
			if ( typeof document !== 'undefined' ) {
				document.body.style.overflow = '';
			}
			// Restore focus to the Report button that opened the dialog.
			const trigger = state._reportReviewTrigger;
			state._reportReviewTrigger = null;
			if ( trigger && typeof trigger.focus === 'function' ) {
				trigger.focus();
			}
		},

		async submitReviewReport( event ) {
			event.preventDefault();
			const form = event.target;
			const reviewId = state.reportReviewId;
			const reason = state.reportReviewReason;
			if ( ! reviewId || ! reason ) {
				return;
			}

			const btn = form.querySelector( 'button[type="submit"]' );
			if ( btn ) {
				btn.disabled = true;
				btn.textContent = listoraI18n.submitting;
			}

			try {
				await abortableApiFetch( {
					path: `/listora/v1/reviews/${ reviewId }/report`,
					method: 'POST',
					data: { reason, details: '' },
				} );

				actions.closeReportReviewModal();
				if ( window.listoraToast ) {
					window.listoraToast(
						( window.listoraI18n && listoraI18n.reportSubmitted ) ||
							'Report submitted. Thank you.',
						'success'
					);
				}
			} catch ( error ) {
				const errMsg = isAbortError( error )
					? NETWORK_SLOW_MESSAGE
					: ( error.message || listoraI18n.reportFailed );
				if ( window.listoraToast ) {
					window.listoraToast( errMsg, 'error' );
				}
				if ( btn ) {
					btn.disabled = false;
					btn.textContent = listoraI18n.submitReport;
				}
			}
		},

		// ─── Helpful vote on a review ───
		// Used by listing-reviews block (review-card.php), listing-detail
		// Reviews tab (tabs.php), and any future review surface — they
		// all live in the listora/directory namespace and bind the same
		// action (Basecamp 9842891993).
		async voteReviewHelpful( event ) {
			const ctx = getContext();
			const el = getElement();
			const btn = el.ref;
			const reviewId = parseInt( ctx.reviewId, 10 );
			if ( ! reviewId || btn.disabled ) {
				return;
			}

			btn.disabled = true;

			try {
				const response = await abortableApiFetch( {
					path: `/listora/v1/reviews/${ reviewId }/helpful`,
					method: 'POST',
				} );

				// Reflect the new count without a full re-render. Try a
				// generic count selector first (matches both block variants),
				// fall back to creating one if absent.
				let countSpan =
					btn.querySelector( '.listora-detail__review-helpful-count' )
					|| btn.querySelector( '.listora-reviews__helpful-count' );
				if ( countSpan ) {
					countSpan.textContent = `(${ response.helpful_count })`;
				} else if ( typeof response.helpful_count === 'number' ) {
					countSpan = document.createElement( 'span' );
					countSpan.className = 'listora-detail__review-helpful-count';
					countSpan.textContent = `(${ response.helpful_count })`;
					btn.appendChild( countSpan );
				}
				btn.classList.add( 'is-voted' );
			} catch ( error ) {
				// Distinguish the three failure modes the REST handler can
				// return so the user sees an honest status instead of a
				// generic "error" CSS state. Basecamp 9842891993 round 2:
				// QA reported the error styling kicking in for "already voted"
				// (a normal, expected outcome) and for the same-page second
				// vote attempt (anonymous → 401 from the auth gate).
				if ( isAbortError( error ) ) {
					// Network timeout — re-enable so the user can retry.
					btn.disabled = false;
					if ( window.listoraToast ) {
						window.listoraToast( NETWORK_SLOW_MESSAGE, 'error' );
					}
					return;
				}
				const code = error && error.code;
				if ( 'listora_already_voted' === code ) {
					// Same user clicked again — treat as success.
					btn.classList.add( 'is-voted' );
					if ( window.listoraToast ) {
						window.listoraToast(
							( listoraI18n && listoraI18n.alreadyVoted ) || error.message,
							'info'
						);
					}
					return;
				}
				if ( 'listora_own_review' === code ) {
					// Author tried to vote on their own review.
					btn.classList.add( 'is-disabled' );
					if ( window.listoraToast ) {
						window.listoraToast(
							( listoraI18n && listoraI18n.ownReview ) || error.message,
							'info'
						);
					}
					return;
				}
				if ( 'rest_forbidden' === code || 'listora_unauthorized' === code || ( error && error.data && 401 === error.data.status ) ) {
					// Anonymous / not logged in. Re-enable the button so the
					// next visitor (post-login) can still vote — locking it
					// here is what produced the "other user can't vote"
					// symptom on shared / cached pages.
					btn.disabled = false;
					btn.classList.add( 'is-needs-login' );
					if ( window.listoraToast ) {
						window.listoraToast(
							( listoraI18n && listoraI18n.loginRequired ) || error.message,
							'info'
						);
					}
					return;
				}
				// Genuine REST/network error — keep the disabled+error state.
				btn.classList.add( 'is-error' );
			}
		},

		// ─── Dashboard: Owner reply to review ───
		// Wired to the Reply button on each row in My Listings → Reviews
		// (templates/blocks/user-dashboard/tab-reviews.php). Each row carries
		// a per-review IAPI context with `reviewId`, `replyOpen`, `replyText`,
		// `replySubmitting`, `replyError` so the inline form is fully scoped
		// and submitting on one row never disturbs another.
		openReplyForm( event ) {
			event.preventDefault();
			const ctx = getContext();
			ctx.replyOpen      = true;
			ctx.replyError     = '';
		},

		cancelReply( event ) {
			event.preventDefault();
			const ctx = getContext();
			ctx.replyOpen  = false;
			ctx.replyError = '';
		},

		updateReplyText( event ) {
			const ctx = getContext();
			ctx.replyText = event.target.value;
		},

		async submitReply( event ) {
			event.preventDefault();
			const ctx = getContext();
			const reviewId = parseInt( ctx.reviewId, 10 );
			const text     = ( ctx.replyText || '' ).trim();

			if ( ! reviewId || ! text ) {
				ctx.replyError = ( window.listoraI18n && window.listoraI18n.replyEmpty ) || 'Please write a reply.';
				return;
			}

			ctx.replySubmitting = true;
			ctx.replyError      = '';

			try {
				await abortableApiFetch( {
					path: `/listora/v1/reviews/${ reviewId }/reply`,
					method: 'POST',
					data: { content: text },
				} );

				// Reload the dashboard so the persisted reply renders
				// authoritatively from the server rather than re-rendering
				// the row in JS (avoids client/server drift on truncation,
				// link parsing, owner display name, etc.).
				//
				// Use a `?tab=reviews` query param (not just `#reviews`)
				// so the server-side renderer in blocks/user-dashboard/render.php
				// picks the right tab during SSR, before any JS runs. The
				// hash-only approach worked in theory — onDashboardInit
				// reads it post-hydration and synthesises a click — but
				// any failure or delay between SSR and hydration left the
				// user staring at the default Listings tab. QA card
				// 9842842463 round 2 was the symptom of exactly that
				// race; switching to a query param + matching SSR
				// branch removes the race entirely.
				if ( typeof window !== 'undefined' ) {
					const reloadUrl = new URL( window.location.href );
					reloadUrl.searchParams.set( 'tab', 'reviews' );
					reloadUrl.hash = 'reviews';
					window.location.replace( reloadUrl.toString() );
				} else {
					window.location.reload();
				}
			} catch ( error ) {
				ctx.replyError = isAbortError( error )
					? NETWORK_SLOW_MESSAGE
					: ( error?.message
						|| ( window.listoraI18n && window.listoraI18n.replyFailed )
						|| 'Reply could not be saved. Please try again.' );
				ctx.replySubmitting = false;
			}
		},

		async submitClaim( event ) {
			event.preventDefault();
			const ctx = getContext();
			const form = event.target;
			const btn = form.querySelector( 'button[type="submit"]' );
			const msgEl = form.querySelector( '.listora-detail__claim-message' );
			const proofText = form.querySelector( '[name="proof_text"]' ).value.trim();

			if ( ! proofText ) {
				return;
			}

			btn.disabled = true;
			btn.textContent = btn.dataset.loadingText || listoraI18n.submitting;

			try {
				const formData = new FormData();
				formData.append( 'listing_id', ctx.listingId );
				formData.append( 'proof_text', proofText );

				const fileInput = form.querySelector( '[name="proof_file"]' );
				if ( fileInput && fileInput.files.length > 0 ) {
					formData.append( 'proof_file', fileInput.files[ 0 ] );
				}

				await abortableApiFetch( {
					path: '/listora/v1/claims',
					method: 'POST',
					body: formData,
				} );

				// Replace form body with a success state so the user has a clear next step.
				if ( msgEl ) {
					const dashUrl = listoraI18n.dashboardUrl
						? `${ listoraI18n.dashboardUrl.replace( /#.*$/, '' ) }#claims`
						: '';
					msgEl.hidden = false;
					msgEl.className = 'listora-detail__claim-message listora-detail__claim-message--success';
					// Clear then append only DOM-constructed nodes (no innerHTML).
					while ( msgEl.firstChild ) {
						msgEl.removeChild( msgEl.firstChild );
					}
					const p = document.createElement( 'p' );
					p.textContent = listoraI18n.claimSubmitted;
					msgEl.appendChild( p );
					if ( dashUrl ) {
						const a = document.createElement( 'a' );
						a.href = dashUrl;
						a.textContent = listoraI18n.viewMyClaims;
						a.className = 'listora-btn listora-btn--primary listora-btn--sm';
						msgEl.appendChild( a );
					}
				}

				// Hide the form body so only the success state is visible.
				const body = form.querySelector( '.listora-detail__claim-body' );
				if ( body ) {
					body.hidden = true;
				}
				if ( btn ) {
					btn.hidden = true;
				}

				if ( window.listoraToast ) {
					window.listoraToast( listoraI18n.claimSubmitted, 'success' );
				}
			} catch ( error ) {
				const errMsg = isAbortError( error )
					? NETWORK_SLOW_MESSAGE
					: ( error.message || listoraI18n.claimFailed );
				if ( msgEl ) {
					msgEl.hidden = false;
					msgEl.textContent = errMsg;
					msgEl.className = 'listora-detail__claim-message listora-detail__claim-message--error';
				}
				if ( window.listoraToast ) {
					window.listoraToast( errMsg, 'error' );
				}
				btn.disabled = false;
				btn.textContent = listoraI18n.submitClaim;
			}
		},

		// ─── Filters Panel ───
		toggleFiltersPanel() {
			state.showFiltersPanel = ! state.showFiltersPanel;
		},

		// ─── Featured Carousel ───
		scrollFeaturedNext() {
			const el = getElement();
			const track = el.ref.closest( '.listora-featured' )?.querySelector( '.listora-featured__track' );
			if ( track ) {
				const scrollAmount = track.firstElementChild?.offsetWidth + parseFloat( getComputedStyle( track ).gap ) || 300;
				track.scrollBy( { left: scrollAmount * 2, behavior: 'smooth' } );
			}
		},

		scrollFeaturedPrev() {
			const el = getElement();
			const track = el.ref.closest( '.listora-featured' )?.querySelector( '.listora-featured__track' );
			if ( track ) {
				const scrollAmount = track.firstElementChild?.offsetWidth + parseFloat( getComputedStyle( track ).gap ) || 300;
				track.scrollBy( { left: -scrollAmount * 2, behavior: 'smooth' } );
			}
		},

		// ─── Calendar ───
		async navigateMonth() {
			const ctx = getContext();
			const el = getElement();
			const calendar = el.ref.closest( '.listora-calendar' );
			if ( ! calendar ) return;

			let month = ctx.calendarMonth;
			let year = ctx.calendarYear;

			if ( ctx.direction === 'prev' ) {
				month--;
				if ( month < 1 ) { month = 12; year--; }
			} else {
				month++;
				if ( month > 12 ) { month = 1; year++; }
			}

			// Update URL and reload (fallback for initial implementation).
			const url = new URL( window.location );
			url.searchParams.set( 'cal_year', year );
			url.searchParams.set( 'cal_month', month );
			window.location.href = url.toString();
		},

		showEventPopover() {
			const ctx = getContext();
			state.showEventPopover = true;
			state.eventPopoverTitle = ctx.eventTitle;
			state.eventPopoverDate = ctx.eventDate;
			state.eventPopoverUrl = ctx.eventUrl;

			// Close on outside click.
			setTimeout( () => {
				const handler = () => {
					state.showEventPopover = false;
					document.removeEventListener( 'click', handler );
				};
				document.addEventListener( 'click', handler );
			}, 0 );
		},

		scrollFeaturedToPage() {
			const ctx = getContext();
			const el = getElement();
			const track = el.ref.closest( '.listora-featured' )?.querySelector( '.listora-featured__track' );
			if ( track ) {
				const scrollAmount = track.firstElementChild?.offsetWidth + parseFloat( getComputedStyle( track ).gap ) || 300;
				track.scrollTo( { left: ctx.dotIndex * scrollAmount * 2, behavior: 'smooth' } );

				// Update active dot.
				const dots = el.ref.closest( '.listora-featured__dots' )?.querySelectorAll( '.listora-featured__dot' );
				dots?.forEach( ( dot, i ) => {
					dot.classList.toggle( 'is-active', i === ctx.dotIndex );
				} );
			}
		},

		// ─── Detail: Tabs & Gallery ───
		switchTab() {
			const ctx = getContext();
			const tabId = ctx.tabId;
			const el = getElement();
			const detail = el.ref.closest( '.listora-detail' );
			if ( ! detail ) return;

			detail.querySelectorAll( '.listora-detail__tab' ).forEach( ( tab ) => {
				tab.classList.remove( 'is-active' );
				tab.setAttribute( 'aria-selected', 'false' );
			} );
			detail.querySelectorAll( '.listora-detail__panel' ).forEach( ( panel ) => {
				panel.hidden = true;
			} );

			const tab = detail.querySelector( `#tab-${ tabId }` );
			const panel = detail.querySelector( `#panel-${ tabId }` );
			if ( tab ) { tab.classList.add( 'is-active' ); tab.setAttribute( 'aria-selected', 'true' ); }
			if ( panel ) { panel.hidden = false; }

			// Initialise the detail map whenever its host tab becomes visible.
			// Pre-1.0.5 the map lived in a dedicated #panel-map; now it may
			// be embedded inside the Location field-group panel. Detect by
			// "does the activated panel contain #listora-detail-map?" so this
			// works for either layout without hard-coding the tab id.
			if ( panel ) {
				const mapEl = panel.querySelector( '#listora-detail-map' );
				if ( mapEl ) {
					initDetailMap( mapEl );
				}
			}

			if ( typeof window !== 'undefined' ) {
				window.history.replaceState( null, '', `#${ tabId }` );
			}
		},

		/**
		 * Show gallery image N, syncing every control that points at it.
		 *
		 * The thumbnail strip, the arrows and the dots all move through this,
		 * so they cannot disagree about which photo is showing — the failure
		 * mode when a carousel keeps its own index alongside an existing one.
		 *
		 * @param {HTMLElement} detail The `.listora-detail` root.
		 * @param {number}      index  Zero-based image index.
		 */
		showGalleryImage( detail, index ) {
			if ( ! detail ) return;

			const thumbs = Array.from(
				detail.querySelectorAll( '.listora-detail__gallery-thumb' )
			);
			if ( ! thumbs.length ) return;

			// Wrap, so Next on the last photo returns to the first rather than
			// dead-ending — the whole complaint was photos being unreachable.
			const total = thumbs.length;
			const target = ( ( index % total ) + total ) % total;

			const thumb = thumbs[ target ];

			/*
			 * Resolve the large source from the thumb, then fall back.
			 *
			 * `data-gallery-large` is new in 1.6.0. A theme that overrode
			 * `gallery.php` before then emits thumbnails without it, and
			 * reading only that attribute left `src` undefined — so on an
			 * overridden template clicking a thumbnail toggled the active
			 * class and never changed the photo. The `data-wp-context` on each
			 * thumb has always carried `imageSrc`, so read that next, and the
			 * thumbnail's own `<img>` last (the strip is a smaller crop of the
			 * right image, so it is a poor but correct answer — better than a
			 * control that does nothing).
			 */
			let src = thumb ? thumb.dataset.galleryLarge : '';

			if ( ! src && thumb ) {
				try {
					const ctxAttr = thumb.getAttribute( 'data-wp-context' );
					if ( ctxAttr ) src = JSON.parse( ctxAttr ).imageSrc || '';
				} catch ( e ) {
					// Malformed override context — fall through to the img.
				}
			}

			if ( ! src && thumb ) {
				const thumbImg = thumb.querySelector( 'img' );
				if ( thumbImg ) src = thumbImg.src;
			}

			const mainImg = detail.querySelector( '.listora-detail__gallery-image' );
			if ( mainImg && src ) {
				mainImg.src = src;
			}

			thumbs.forEach( ( t, i ) => t.classList.toggle( 'is-active', i === target ) );

			detail
				.querySelectorAll( '.listora-detail__gallery-dot' )
				.forEach( ( dot, i ) => {
					dot.classList.toggle( 'is-active', i === target );

					// `aria-current`, not `aria-selected` — the dots are a
					// labelled group, not a tablist, and there is no tabpanel
					// for a tab to control. Removed rather than set to
					// "false": aria-current has no false state, and the
					// attribute's absence IS the not-current state.
					if ( i === target ) {
						dot.setAttribute( 'aria-current', 'true' );
					} else {
						dot.removeAttribute( 'aria-current' );
					}
				} );
		},

		/**
		 * Index of the photo currently showing.
		 *
		 * @param {HTMLElement} detail The `.listora-detail` root.
		 * @return {number} Zero-based index, 0 when nothing is marked active.
		 */
		currentGalleryIndex( detail ) {
			const thumbs = Array.from(
				detail.querySelectorAll( '.listora-detail__gallery-thumb' )
			);
			const idx = thumbs.findIndex( ( t ) => t.classList.contains( 'is-active' ) );
			return idx < 0 ? 0 : idx;
		},

		switchGalleryImage() {
			const ctx = getContext();
			const el = getElement();
			const detail = el.ref.closest( '.listora-detail' );
			if ( ! detail ) return;

			/*
			 * The dots and the thumbnails both carry imageIndex; fall back to
			 * the element's own position for any override still on the old
			 * context shape.
			 *
			 * There is no `index < 0` case left after this: `indexOf` on an
			 * element's own parent always finds it. The direct-src branch that
			 * used to sit below this was therefore unreachable, and the
			 * override compatibility it was written for did not exist — the
			 * fallbacks now live in `showGalleryImage()`, which is the one
			 * place that resolves a source, so every caller gets them.
			 */
			let index = typeof ctx.imageIndex === 'number' ? ctx.imageIndex : -1;
			if ( index < 0 ) {
				const siblings = Array.from( el.ref.parentElement ? el.ref.parentElement.children : [] );
				index = Math.max( 0, siblings.indexOf( el.ref ) );
			}

			actions.showGalleryImage( detail, index );
		},

		prevGalleryImage() {
			const detail = getElement().ref.closest( '.listora-detail' );
			if ( ! detail ) return;
			actions.showGalleryImage( detail, actions.currentGalleryIndex( detail ) - 1 );
		},

		nextGalleryImage() {
			const detail = getElement().ref.closest( '.listora-detail' );
			if ( ! detail ) return;
			actions.showGalleryImage( detail, actions.currentGalleryIndex( detail ) + 1 );
		},

		/**
		 * Swipe support.
		 *
		 * Recorded on the element rather than in store state: a page can carry
		 * more than one gallery (the related-listings strip renders cards), and
		 * a shared slot would let a swipe on one move another.
		 */
		galleryTouchStart( event ) {
			const el = getElement().ref;
			const touch = event.changedTouches && event.changedTouches[ 0 ];
			if ( ! touch ) return;
			el.dataset.touchStartX = String( touch.clientX );
			el.dataset.touchStartY = String( touch.clientY );
		},

		galleryTouchEnd( event ) {
			const el = getElement().ref;
			const touch = event.changedTouches && event.changedTouches[ 0 ];
			if ( ! touch || ! el.dataset.touchStartX ) return;

			const dx = touch.clientX - parseFloat( el.dataset.touchStartX );
			const dy = touch.clientY - parseFloat( el.dataset.touchStartY || '0' );

			delete el.dataset.touchStartX;
			delete el.dataset.touchStartY;

			// Ignore anything that is really a vertical scroll, and anything
			// too short to be deliberate — otherwise a tap steals the photo.
			if ( Math.abs( dx ) < 40 || Math.abs( dx ) < Math.abs( dy ) ) return;

			const detail = el.closest( '.listora-detail' );
			if ( ! detail ) return;

			actions.showGalleryImage(
				detail,
				actions.currentGalleryIndex( detail ) + ( dx < 0 ? 1 : -1 )
			);
		},

		toggleDetailReviewForm() {
			const el = getElement();
			const detail = el.ref.closest( '.listora-detail' );
			const form = detail?.querySelector( '#listora-detail-review-form' );
			if ( form ) {
				form.hidden = ! form.hidden;
				if ( ! form.hidden ) {
					const firstInput = form.querySelector( 'input[type="radio"], input[type="text"]' );
					if ( firstInput ) firstInput.focus();
				}
			}
		},

		async submitDetailReviewForm( event ) {
			event.preventDefault();
			const ctx = getContext();
			const el = getElement();
			const form = el.ref.closest( '.listora-reviews__form' ) || el.ref;
			const rating = form.querySelector( 'input[name="overall_rating"]:checked' )?.value;
			const title = form.querySelector( 'input[name="title"]' )?.value;
			const content = form.querySelector( 'textarea[name="content"]' )?.value;
			if ( ! rating || ! title || ! content ) return;

			const criteriaRatings = {};
			form.querySelectorAll( 'input[name^="criteria_ratings["]:checked' ).forEach( ( input ) => {
				const match = input.name.match( /^criteria_ratings\[([^\]]+)\]$/ );
				if ( match ) criteriaRatings[ match[ 1 ] ] = parseInt( input.value, 10 );
			} );

			const submitBtn = form.querySelector( 'button[type="submit"]' );
			const msgDiv = form.querySelector( '.listora-reviews__form-message' );
			if ( submitBtn ) { submitBtn.disabled = true; submitBtn.textContent = t( 'jsSubmitting', 'Submitting...' ); }

			const requestData = { listing_id: ctx.listingId, overall_rating: parseInt( rating, 10 ), title, content };
			if ( Object.keys( criteriaRatings ).length > 0 ) requestData.criteria_ratings = criteriaRatings;

			try {
				const response = await abortableApiFetch( { path: `/listora/v1/listings/${ ctx.listingId }/reviews`, method: 'POST', data: requestData } );
				if ( msgDiv ) { msgDiv.hidden = false; msgDiv.textContent = response.message || t( 'jsReviewSubmitted', 'Review submitted!' ); msgDiv.style.color = 'var(--listora-success)'; }

				/*
				 * Only reload when the reload has something to show.
				 *
				 * A pending review is not rendered in the list, so reloading
				 * destroyed the success message AND put nothing in its place:
				 * the member watched the form clear, saw the count unchanged,
				 * and had no way to tell the review had saved. That is
				 * D.admin-save-confirms-success on the member surface — the
				 * same defect this release fixed for the new-listing-type save
				 * and Reset Settings, which also confirmed and then navigated
				 * the confirmation away.
				 *
				 * Approved reviews still reload, because there the reloaded
				 * list IS the durable confirmation. The submit button stays
				 * disabled on the pending path: one review per user per listing
				 * is enforced server-side (409 listora_already_reviewed), so a
				 * re-enabled button would only earn the member an error.
				 */
				if ( 'approved' === response.status ) {
					setTimeout( () => { window.location.reload(); }, 2000 );
				} else if ( submitBtn ) {
					submitBtn.textContent = t( 'jsReviewPending', 'Awaiting approval' );
				}
			} catch ( error ) {
				const errMsg = isAbortError( error )
					? NETWORK_SLOW_MESSAGE
					: ( error.message || 'Failed to submit review.' );
				if ( msgDiv ) { msgDiv.hidden = false; msgDiv.textContent = errMsg; msgDiv.style.color = 'var(--listora-danger)'; }
				if ( submitBtn ) { submitBtn.disabled = false; submitBtn.textContent = t( 'jsSubmitReview', 'Submit Review' ); }
			}
		},

		async submitLeadForm( event ) {
			return submitOwnerMessage( event, {
				base: 'listora-lead-form',
				nonceField: '_listora_lead_nonce',
				withPhone: true,
			} );
		},

		/**
		 * Free contact form (Contact_Form::render_form) — renders when Pro's
		 * lead_form toggle is off. Same flow as submitLeadForm, different DOM,
		 * nonce and REST route; both go through submitOwnerMessage().
		 */
		async submitContactForm( event ) {
			return submitOwnerMessage( event, {
				base: 'listora-contact-form',
				nonceField: '_listora_contact_nonce',
				withPhone: false,
			} );
		},

		// ─── Inline review-form validation ───
		// Lightweight blur validator referenced by templates/blocks/listing-reviews/review-form.php.
		// Adds the standard `is-invalid` class when the input is empty or
		// fails its own pattern/required attribute, removes it once valid.
		// No-op IAPI handler before this addition — every blur threw on
		// `actions.validateFieldOnBlur is not a function`.
		validateFieldOnBlur( event ) {
			const el = ( event && event.target ) || ( getElement() && getElement().ref );
			if ( ! el || typeof el.checkValidity !== 'function' ) return;
			el.classList.toggle( 'is-invalid', ! el.checkValidity() );
		},

		// ─── User dashboard: Services management ───
		// Invocation surface lives in templates/blocks/user-dashboard/tab-listings.php
		// (toggleDashServices opens the panel; toggleServiceForm opens add/edit;
		// saveService / editService / deleteService manipulate rows). The full
		// REST flow is tracked separately — these handlers prevent the
		// "actions.X is not a function" throw the moment a user clicks any
		// services button on the dashboard.
		toggleDashServices( event ) {
			// Card 9932329169 — the services panel is NOT a child of the
			// listing row. tab-listings.php renders each
			// `.listora-dashboard__services-panel` (id `services-panel-{ID}`)
			// in a SEPARATE sibling foreach after all the rows, so the old
			// `row.querySelector('.listora-dashboard__services')` (wrong class
			// AND wrong subtree) always matched nothing and the gear was dead.
			// BC #9976599203 — simply unhiding that distant sibling dropped
			// the owner thousands of pixels below the row they clicked. The
			// panel now presents as a modal overlay (dialog markup + backdrop
			// + close button live in tab-listings.php); this action opens or
			// closes it with Esc / focus-return handled by the module-level
			// helpers below the store definition.
			const ctx = getContext();
			const listingId = ctx && ctx.servicesListingId ? ctx.servicesListingId : 0;
			let panel = null;
			if ( listingId && typeof document !== 'undefined' ) {
				panel = document.getElementById( 'services-panel-' + listingId );
			}
			// Defensive fallback for theme overrides that nest the panel in the
			// row under the legacy class names.
			if ( ! panel && event && event.target ) {
				const root = event.target.closest( '.listora-dashboard__listing-row, .listora-dashboard__listings-row' );
				panel = root ? root.querySelector( '.listora-dashboard__services-panel, .listora-dashboard__services' ) : null;
			}
			if ( ! panel ) {
				return;
			}
			if ( panel.hidden ) {
				listoraOpenServicesModal(
					panel,
					event && event.target && event.target.closest ? event.target.closest( 'button' ) : null
				);
			} else {
				listoraCloseServicesModal( panel );
			}
		},
		closeDashServices( event ) {
			// Close affordances inside the services modal — the backdrop and
			// the X button both carry data-wp-on--click="actions.closeDashServices".
			const panel = event && event.target && event.target.closest
				? event.target.closest( '.listora-dashboard__services-panel' )
				: null;
			listoraCloseServicesModal( panel );
		},
		toggleServiceForm( event ) {
			const ctx = getContext();
			const listingId = ctx && ctx.serviceListingId ? ctx.serviceListingId : 0;
			let form = null;
			if ( listingId && typeof document !== 'undefined' ) {
				const panel = document.getElementById( 'services-panel-' + listingId );
				form = panel ? panel.querySelector( '.listora-dashboard__service-form' ) : null;
			}
			if ( ! form && event && event.target ) {
				const root = event.target.closest( '.listora-dashboard__services-panel, .listora-dashboard__listing-row, .listora-dashboard__listings-row, .listora-dashboard__services' );
				form = root ? root.querySelector( '.listora-dashboard__service-form' ) : null;
			}
			if ( ! form ) return;

			/*
			 * "Add Service" means CREATE, whatever the form was doing before.
			 *
			 * `editService` marks the form with the service it loaded so the
			 * save updates that row instead of duplicating it. Nothing cleared
			 * the mark, and this one handler is bound to BOTH "Add Service"
			 * and "Cancel" — so after editing once, Add Service reopened a
			 * form still carrying the previous values and still flagged as an
			 * edit, and saving it OVERWROTE the service edited earlier instead
			 * of creating a new one. Silent, and destructive.
			 *
			 * Add therefore always opens clean rather than toggling: a member
			 * who is mid-edit and clicks Add wants a blank form, not the panel
			 * to shut. Cancel still closes, and also resets so the next open
			 * cannot inherit anything either.
			 *
			 * `editService` re-opens the form itself, so it never routes
			 * through here and its mark survives.
			 */
			const isAdd = !! (
				event &&
				event.target &&
				event.target.closest( '.listora-dashboard__add-service-btn' )
			);

			form.hidden = isAdd ? false : ! form.hidden;
			actions.resetServiceForm( form );
		},

		/**
		 * Return a services form to its empty create state.
		 *
		 * @param {HTMLElement} form The `.listora-dashboard__service-form`.
		 */
		resetServiceForm( form ) {
			if ( ! form ) return;

			delete form.dataset.editingServiceId;

			form
				.querySelectorAll( 'input, textarea, select' )
				.forEach( ( field ) => {
					if ( field.tagName === 'SELECT' ) {
						field.selectedIndex = 0;
					} else {
						field.value = '';
					}
					field.classList.remove( 'is-invalid' );
				} );
		},

		toggleServiceDesc( event ) {
			// Card 9872013428 — the original selectors drifted from the
			// actual template markup. The detail-tab template emits
			// `.listora-detail__service-card` (not `__service`) and
			// `.listora-detail__service-desc` (not `.listora-service__desc`).
			// Toggle the `--collapsed` modifier (CSS in style.css collapses
			// to a 2-line clamp when present, full text when removed).
			// Legacy selectors retained as fallbacks for any theme override
			// still on the old shape.
			const root = ( event && event.target ) ? event.target.closest( '.listora-detail__service-card, .listora-detail__service, .listora-card__service' ) : null;
			const desc = root ? root.querySelector( '.listora-detail__service-desc, .listora-service__desc, .listora-service__description' ) : null;
			if ( desc ) {
				desc.classList.toggle( 'listora-detail__service-desc--collapsed' );
			}
		},
		/**
		 * Resolve the services panel + form a service action was fired from.
		 *
		 * Every service action starts from a button inside one listing's panel,
		 * so the listing ID is read from the DOM rather than carried in state —
		 * the dashboard renders many panels at once and a single state slot
		 * would apply the last-opened listing's ID to every one of them.
		 */
		serviceContext( event ) {
			const target = event && event.target ? event.target : null;
			if ( ! target ) return null;

			const form = target.closest( '.listora-dashboard__service-form' );
			const panel = target.closest(
				'.listora-dashboard__services-panel, .listora-dashboard__services, .listora-dashboard__listing-row, .listora-dashboard__listings-row'
			);
			const scope = form || panel;
			if ( ! scope ) return null;

			const formEl = form || ( panel ? panel.querySelector( '.listora-dashboard__service-form' ) : null );
			const listingId = parseInt(
				( formEl && formEl.dataset.listingId ) ||
					( panel && panel.dataset.listingId ) ||
					0,
				10
			);

			const row = target.closest( '.listora-dashboard__service-row' );
			const serviceId = row ? parseInt( row.dataset.serviceId || 0, 10 ) : 0;

			return { form: formEl, panel, row, listingId, serviceId };
		},

		/**
		 * Create or update a service from the dashboard panel.
		 *
		 * These three actions were deliberate stubs that fired a "coming in a
		 * future update" toast, while the customer docs said dashboard service
		 * management worked. The Services_Controller create/update/delete
		 * routes have existed and been journey-verified the whole time — this
		 * is the wiring, not new capability (BC 10199116630).
		 */
		async saveService( event ) {
			if ( event && event.preventDefault ) event.preventDefault();

			const ctx = actions.serviceContext( event );
			if ( ! ctx || ! ctx.form || ! ctx.listingId ) return;

			const val = ( name ) => {
				const el = ctx.form.querySelector( '[name="' + name + '"]' );
				return el ? el.value.trim() : '';
			};

			const title = val( 'service_title' );
			if ( ! title ) {
				// Mirrors the submission form: mark the field rather than
				// firing a toast the user has to read and dismiss.
				const titleEl = ctx.form.querySelector( '[name="service_title"]' );
				if ( titleEl ) {
					titleEl.classList.add( 'is-invalid' );
					titleEl.addEventListener(
						'input',
						() => titleEl.classList.remove( 'is-invalid' ),
						{ once: true }
					);
					titleEl.focus();
				}
				return;
			}

			// An editing form carries the service it was opened for; absent
			// means create.
			const editingId = parseInt( ctx.form.dataset.editingServiceId || 0, 10 );

			const payload = {
				listing_id: ctx.listingId,
				title,
				description: val( 'service_description' ),
				price_type: val( 'service_price_type' ),
			};

			/*
			 * Price and duration are OMITTED when blank, never sent as ''.
			 *
			 * Both are optional inputs on the form and both are declared with a
			 * numeric type on the route (`price` number, `duration_minutes`
			 * integer). WordPress validates a declared arg the moment it is
			 * present, and '' satisfies neither type — so sending the key empty
			 * failed the whole request with "price is not of type number." A
			 * member adding a service without a price, which is the common
			 * case, could not save at all.
			 *
			 * Omitting is also the correct UPDATE semantics: the route treats
			 * an absent field as "leave it alone", so clearing the input no
			 * longer silently zeroes a price that was already set. Sending an
			 * explicit null would be the way to clear one; the panel has no
			 * affordance for that yet.
			 */
			const price = val( 'service_price' );
			if ( '' !== price ) {
				payload.price = parseFloat( price );
			}

			const duration = val( 'service_duration' );
			if ( '' !== duration ) {
				payload.duration_minutes = parseInt( duration, 10 );
			}

			const category = val( 'service_category' );
			if ( category ) {
				payload.categories = [ parseInt( category, 10 ) ];
			}

			try {
				await abortableApiFetch( {
					path: editingId
						? '/listora/v1/services/' + editingId
						: '/listora/v1/listings/' + ctx.listingId + '/services',
					method: 'POST',
					data: payload,
				} );

				// Re-render from the server rather than splicing the row in by
				// hand: price formatting, duration rounding and category
				// labels are all resolved server-side, and a hand-built row
				// drifts from them the first time any of that changes.
				window.location.reload();
			} catch ( error ) {
				if ( window.listoraToast ) {
					window.listoraToast(
						( error && error.message ) ||
							t( 'serviceSaveFailed', 'Could not save the service. Please try again.' ),
						'error'
					);
				}
			}
		},

		/**
		 * Load an existing service back into the panel form for editing.
		 */
		async editService( event ) {
			if ( event && event.preventDefault ) event.preventDefault();

			const ctx = actions.serviceContext( event );
			if ( ! ctx || ! ctx.form || ! ctx.serviceId ) return;

			try {
				const service = await abortableApiFetch( {
					path: '/listora/v1/services/' + ctx.serviceId,
					method: 'GET',
				} );

				const set = ( name, value ) => {
					const el = ctx.form.querySelector( '[name="' + name + '"]' );
					if ( el ) el.value = value === null || value === undefined ? '' : value;
				};

				set( 'service_title', service.title );
				set( 'service_description', service.description );
				set( 'service_price', service.price );
				set( 'service_price_type', service.price_type );
				set( 'service_duration', service.duration_minutes );

				// Marks the form as an EDIT. Without it a save would create a
				// duplicate instead of updating the row the user opened.
				ctx.form.dataset.editingServiceId = String( ctx.serviceId );
				ctx.form.hidden = false;
				ctx.form.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
			} catch ( error ) {
				if ( window.listoraToast ) {
					window.listoraToast(
						( error && error.message ) ||
							t( 'serviceLoadFailed', 'Could not load that service.' ),
						'error'
					);
				}
			}
		},

		/**
		 * Delete a service after confirmation.
		 */
		async deleteService( event ) {
			if ( event && event.preventDefault ) event.preventDefault();

			const ctx = actions.serviceContext( event );
			if ( ! ctx || ! ctx.serviceId ) return;

			/*
			 * Design-system modal, never native confirm() — wppqa Rule 10.
			 *
			 * Fails CLOSED. The guard used to SKIP the prompt when the modal
			 * had not loaded, so a script-load race turned an irreversible
			 * delete into a single unconfirmed click. An unavailable
			 * confirmation is a reason not to proceed, never a reason to
			 * proceed silently — `listora-confirm` is a hard dependency of the
			 * dashboard bundle, so this branch means something is wrong.
			 */
			if ( typeof window.listoraConfirm !== 'function' ) {
				if ( window.listoraToast ) {
					window.listoraToast(
						t(
							'confirmUnavailable',
							'Could not open the confirmation dialog. Please reload the page and try again.'
						),
						'error'
					);
				}
				return;
			}

			const confirmed = await window.listoraConfirm(
				t( 'confirmDeleteService', 'Delete this service? This cannot be undone.' )
			);
			if ( ! confirmed ) return;

			try {
				await abortableApiFetch( {
					path: '/listora/v1/services/' + ctx.serviceId,
					method: 'DELETE',
				} );

				if ( ctx.row ) ctx.row.remove();
			} catch ( error ) {
				if ( window.listoraToast ) {
					window.listoraToast(
						( error && error.message ) ||
							t( 'serviceDeleteFailed', 'Could not delete the service.' ),
						'error'
					);
				}
			}
		},

		// ─── URL State ───
		syncURLParams() {
			if ( typeof window === 'undefined' ) return;

			const params = new URLSearchParams();

			if ( state.searchQuery ) params.set( 'keyword', state.searchQuery );
			if ( state.selectedType ) params.set( 'type', state.selectedType );
			if ( state.selectedCategory ) params.set( 'category', state.selectedCategory );
			if ( state.sortBy && state.sortBy !== 'featured' ) params.set( 'sort', state.sortBy );
			if ( state.currentPage > 1 ) params.set( 'page', state.currentPage );

			// Date filter params.
			if ( state.dateFilter ) params.set( 'date_filter', state.dateFilter );
			if ( state.dateFrom ) params.set( 'date_from', state.dateFrom );
			if ( state.dateTo ) params.set( 'date_to', state.dateTo );

			for ( const [ key, value ] of Object.entries( state.filters ) ) {
				if ( Array.isArray( value ) && value.length > 0 ) {
					params.set( key, value.join( ',' ) );
				} else if ( value ) {
					params.set( key, value );
				}
			}

			const newUrl =
				window.location.pathname +
				( params.toString() ? '?' + params.toString() : '' );
			window.history.replaceState( null, '', newUrl );
		},
	},

	callbacks: {
		// Called when search block initializes — restore state from URL.
		onSearchBlockInit() {
			if ( typeof window === 'undefined' ) return;

			const params = new URLSearchParams( window.location.search );

			if ( params.get( 'keyword' ) )
				state.searchQuery = params.get( 'keyword' );
			if ( params.get( 'type' ) )
				state.selectedType = params.get( 'type' );
			if ( params.get( 'category' ) )
				state.selectedCategory = params.get( 'category' );
			if ( params.get( 'sort' ) ) state.sortBy = params.get( 'sort' );
			if ( params.get( 'page' ) )
				state.currentPage = parseInt( params.get( 'page' ), 10 );

			// Restore date filters from URL.
			if ( params.get( 'date_filter' ) )
				state.dateFilter = params.get( 'date_filter' );
			if ( params.get( 'date_from' ) )
				state.dateFrom = params.get( 'date_from' );
			if ( params.get( 'date_to' ) )
				state.dateTo = params.get( 'date_to' );

			// Restore field filters.
			const ctx = getContext();
			if ( ctx.typeFilters ) {
				state.typeFilters = ctx.typeFilters;
			}

			// Restore field filter values from URL.
			if ( state.selectedType && state.typeFilters[ state.selectedType ] ) {
				const typeFilterKeys = state.typeFilters[ state.selectedType ].map( ( f ) => f.key );
				for ( const key of typeFilterKeys ) {
					const val = params.get( key );
					if ( val ) {
						if ( val.includes( ',' ) ) {
							state.filters[ key ] = val.split( ',' );
						} else {
							state.filters[ key ] = val;
						}
					}
				}
			}

			// NOTE: do NOT call actions.searchImmediate() here.
			//
			// searchImmediate() navigates to the current URL with the same
			// params we just read from it, which makes the page reload,
			// which re-runs this init, which re-navigates — an infinite
			// flicker loop. The server (listing-grid render.php) already
			// reads these params from $_GET and renders the filtered
			// results, so there is nothing more to do on init beyond
			// seeding the state for the input bindings above.
		},

		// onMapInit is defined in listing-map/view.js — do not duplicate here.

		onDetailInit() {
			if ( typeof window === 'undefined' ) return;
			const hash = window.location.hash.replace( '#', '' );
			if ( hash ) {
				const el = getElement();
				const detail = el.ref.closest( '.listora-detail' );
				const tab = detail?.querySelector( `#tab-${ hash }` );
				if ( tab ) tab.click();
			}
		},
	},
} );

/**
 * Dashboard services modal mechanics (BC #9976599203).
 *
 * The per-listing services panel in tab-listings.php presents as a fixed
 * overlay. These helpers own the open/close invariants: only one panel open
 * at a time, Esc closes, and focus returns to the triggering gear button on
 * close. Backdrop / X-button clicks route through actions.closeDashServices.
 */
let listoraServicesReturnFocus = null;

function listoraServicesEscHandler( e ) {
	if ( e.key !== 'Escape' ) {
		return;
	}
	const open = document.querySelector( '.listora-dashboard__services-panel:not([hidden])' );
	if ( open ) {
		listoraCloseServicesModal( open );
	}
}

function listoraOpenServicesModal( panel, trigger ) {
	if ( typeof document === 'undefined' ) {
		return;
	}
	document.querySelectorAll( '.listora-dashboard__services-panel:not([hidden])' ).forEach( ( other ) => {
		if ( other !== panel ) {
			other.hidden = true;
		}
	} );
	panel.hidden = false;
	listoraServicesReturnFocus = trigger || null;
	const closeBtn = panel.querySelector( '.listora-dashboard__services-close' );
	if ( closeBtn ) {
		closeBtn.focus();
	}
	document.addEventListener( 'keydown', listoraServicesEscHandler );
}

function listoraCloseServicesModal( panel ) {
	if ( ! panel || panel.hidden ) {
		return;
	}
	panel.hidden = true;
	document.removeEventListener( 'keydown', listoraServicesEscHandler );
	if ( listoraServicesReturnFocus && typeof listoraServicesReturnFocus.focus === 'function' ) {
		listoraServicesReturnFocus.focus();
	}
	listoraServicesReturnFocus = null;
}

/**
 * i18n strings — injected by PHP via wp_interactivity_state or wp_localize_script.
 */
const listoraI18n = window.listoraI18n || {
	noResults: 'No listings found',
	result: 'result',
	results: 'results',
	searchError: 'Search failed. Please try again.',
	geoNotSupported: 'Geolocation is not supported by your browser.',
	geoDenied: 'Location access denied. Use the location search instead.',
	submitting: 'Submitting\u2026',
	submitClaim: 'Submit Claim',
	claimSubmitted: 'Claim submitted! We will review it shortly.',
	claimFailed: 'Failed to submit claim. Please try again.',
};
