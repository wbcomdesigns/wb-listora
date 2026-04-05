/**
 * WB Listora — Shared Interactivity API Store
 *
 * Single namespace `listora/directory` shared across all blocks.
 * Search ↔ Grid ↔ Map ↔ Card ↔ Detail communicate through this store.
 *
 * @package WBListora
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

const { state, actions, callbacks } = store( 'listora/directory', {
	state: {
		// ─── Search ───
		searchQuery: '',
		selectedType: '',
		selectedLocation: '',
		selectedCategory: '',
		filters: {},
		sortBy: 'featured',
		currentPage: 1,
		perPage: 20,

		// ─── Geo ───
		userLat: null,
		userLng: null,
		searchRadius: 5,
		radiusUnit: 'km',
		mapBounds: null,

		// ─── Results ───
		results: [],
		totalResults: 0,
		totalPages: 0,
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

		// ─── Favorites ───
		favorites: [],

		// ─── User ───
		isLoggedIn: false,
		userId: 0,

		// ─── UI Panels ───
		showFiltersPanel: false,
		showSuggestions: false,
		suggestions: [],
		recentSearches: [],

		// ─── Calendar ───
		showEventPopover: false,
		eventPopoverTitle: '',
		eventPopoverDate: '',
		eventPopoverUrl: '',

		// ─── Modals ───
		activeModal: null,

		// ─── Computed ───
		get hasActiveFilters() {
			return (
				!! state.searchQuery ||
				!! state.selectedCategory ||
				Object.keys( state.filters ).length > 0
			);
		},
		get activeFilterCount() {
			let count = 0;
			for ( const key in state.filters ) {
				const val = state.filters[ key ];
				count += Array.isArray( val ) ? val.length : 1;
			}
			return count;
		},
		get isFavorited() {
			const ctx = getContext();
			return state.favorites.includes( ctx.listingId );
		},
		get isHighlightedCard() {
			const ctx = getContext();
			return state.highlightedCard === ctx.listingId;
		},
		get isActiveMarker() {
			const ctx = getContext();
			return state.activeMarker === ctx.listingId;
		},
		get hasResults() {
			return state.results.length > 0;
		},
		get showEmptyState() {
			return state.hasSearched && ! state.isLoading && state.results.length === 0;
		},
		get showPagination() {
			return state.totalPages > 1;
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

			state._searchTimeout = setTimeout( async () => {
				state.isLoading = true;
				state.searchError = null;

				try {
					const url = actions.buildSearchURL();
					const response = await window.wp.apiFetch( {
						path: url,
					} );

					state.results = response.listings;
					state.totalResults = response.total;
					state.totalPages = response.pages;
					state.facets = response.facets || {};
					state.hasSearched = true;

					// Update URL params for shareability.
					actions.syncURLParams();
				} catch ( error ) {
					state.searchError =
						error.message || listoraI18n.searchError;
					state.results = [];
					state.totalResults = 0;
					state.totalPages = 0;
				} finally {
					state.isLoading = false;
				}
			}, 300 );
		},

		searchImmediate() {
			if ( state._searchTimeout ) {
				clearTimeout( state._searchTimeout );
			}
			state.currentPage = 1;
			// Reset timeout and search immediately with 0 delay.
			state._searchTimeout = setTimeout( () => actions.search(), 0 );
			actions.search();
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

			state.currentPage = 1;
			actions.searchImmediate();
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

			state.currentPage = 1;
			actions.searchImmediate();
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

			state.currentPage = 1;
			actions.searchImmediate();
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

			state.currentPage = 1;
			actions.searchImmediate();
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
			state.currentPage = 1;
			actions.searchImmediate();
		},

		// ─── Type Selection ───
		async selectType() {
			const ctx = getContext();
			const slug = ctx.typeSlug || '';

			state.selectedType = slug;
			state.filters = {};
			state.currentPage = 1;

			// Load filter config for this type if not cached.
			if ( slug && ! state.typeFilters[ slug ] ) {
				try {
					const config = await window.wp.apiFetch( {
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
					// Silently fail — filters will be empty.
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
				const response = await window.wp.apiFetch( {
					path: `/listora/v1/search/suggest?keyword=${ encodeURIComponent( state.searchQuery ) }&type=${ state.selectedType }`,
				} );
				state.suggestions = response;
				state.showSuggestions = true;
			} catch ( e ) {
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
				state.activeModal = 'login';
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
					await window.wp.apiFetch( {
						path: `/listora/v1/favorites/${ listingId }`,
						method: 'DELETE',
					} );
				} else {
					await window.wp.apiFetch( {
						path: '/listora/v1/favorites',
						method: 'POST',
						data: { listing_id: listingId },
					} );
				}
			} catch ( error ) {
				// Revert on failure.
				if ( idx > -1 ) {
					state.favorites = [ ...state.favorites, listingId ];
				} else {
					state.favorites = state.favorites.filter(
						( id ) => id !== listingId
					);
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
				state.activeModal = 'share';
			}
		},

		showClaimModal( event ) {
			event.preventDefault();
			state.activeModal = 'claim';
		},

		showLoginModal( event ) {
			event.preventDefault();
			state.activeModal = 'login';
		},

		closeModal() {
			state.activeModal = null;
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
			btn.textContent = btn.dataset.loadingText || 'Submitting...';

			try {
				const formData = new FormData();
				formData.append( 'listing_id', ctx.listingId );
				formData.append( 'proof_text', proofText );

				const fileInput = form.querySelector( '[name="proof_file"]' );
				if ( fileInput && fileInput.files.length > 0 ) {
					formData.append( 'proof_file', fileInput.files[ 0 ] );
				}

				const response = await wp.apiFetch( {
					path: '/listora/v1/claims',
					method: 'POST',
					body: formData,
				} );

				if ( msgEl ) {
					msgEl.hidden = false;
					msgEl.textContent = response.message || 'Claim submitted! We will review it shortly.';
					msgEl.className = 'listora-detail__claim-message listora-detail__claim-message--success';
				}

				setTimeout( () => {
					state.activeModal = null;
				}, 2000 );
			} catch ( error ) {
				if ( msgEl ) {
					msgEl.hidden = false;
					msgEl.textContent = error.message || 'Failed to submit claim. Please try again.';
					msgEl.className = 'listora-detail__claim-message listora-detail__claim-message--error';
				}
				btn.disabled = false;
				btn.textContent = 'Submit Claim';
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

		// ─── URL State ───
		syncURLParams() {
			if ( typeof window === 'undefined' ) return;

			const params = new URLSearchParams();

			if ( state.searchQuery ) params.set( 'keyword', state.searchQuery );
			if ( state.selectedType ) params.set( 'type', state.selectedType );
			if ( state.selectedCategory ) params.set( 'category', state.selectedCategory );
			if ( state.sortBy && state.sortBy !== 'featured' ) params.set( 'sort', state.sortBy );
			if ( state.currentPage > 1 ) params.set( 'page', state.currentPage );

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

			// Auto-search if URL has params.
			if ( state.searchQuery || state.selectedCategory || Object.keys( state.filters ).length > 0 ) {
				actions.searchImmediate();
			}
		},

		onMapInit() {
			state.mapReady = true;
		},
	},
} );

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
};
