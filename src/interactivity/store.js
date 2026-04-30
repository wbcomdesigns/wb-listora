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
		activeModal: null,

		// ─── Computed ───
		get hasActiveFilters() {
			return (
				!! state.searchQuery ||
				!! state.selectedCategory ||
				!! state.dateFilter ||
				!! state.dateFrom ||
				!! state.dateTo ||
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
			const url = window.location.pathname + ( params.toString() ? '?' + params.toString() : '' );
			window.location.href = url;
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

			state.currentPage = 1;
			actions.searchImmediate();
		},

		setDateFrom( event ) {
			state.dateFrom = event.target.value;
			// Clear preset when using custom range.
			state.dateFilter = '';
			state.currentPage = 1;
			actions.searchImmediate();
		},

		setDateTo( event ) {
			state.dateTo = event.target.value;
			// Clear preset when using custom range.
			state.dateFilter = '';
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
			state.dateFilter = '';
			state.dateFrom = '';
			state.dateTo = '';
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

		// ─── Feature Listing (owner) ───
		async featureListing( event ) {
			event.preventDefault();
			event.stopPropagation();

			if ( ! state.isLoggedIn ) {
				state.activeModal = 'login';
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
				const data = await wp.apiFetch( {
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
				const message =
					error && error.message
						? error.message
						: listoraI18n.featureFailed || 'Unable to feature this listing.';
				if ( window.listoraToast ) {
					window.listoraToast( message, 'error' );
				}
				unlock();
			}
		},

		// ─── Owner: Deactivate Listing ───
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

			const confirmMsg =
				( window.listoraI18n && window.listoraI18n.confirmDeactivate ) ||
				'Deactivate this listing? It will be hidden from the public directory until you reactivate it.';
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( confirmMsg ) ) {
				return;
			}

			if ( btn ) {
				btn.dataset.listoraDeactivateInflight = '1';
				btn.setAttribute( 'disabled', 'disabled' );
			}

			try {
				await window.wp.apiFetch( {
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
				const message =
					error && error.message
						? error.message
						: ( window.listoraI18n && window.listoraI18n.deactivateFailed ) ||
								'Unable to deactivate listing.';
				if ( window.listoraToast ) {
					window.listoraToast( message, 'error' );
				}
				if ( btn ) {
					btn.removeAttribute( 'disabled' );
					btn.dataset.listoraDeactivateInflight = '0';
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
				description: fd.get( 'description' ) || '',
			};

			// Notification preferences live under notification_prefs[event_key].
			const prefs = {};
			for ( const [ key, value ] of fd.entries() ) {
				const match = key.match( /^notification_prefs\[([^\]]+)\]$/ );
				if ( match ) {
					prefs[ match[ 1 ] ] = value;
				}
			}
			payload.notification_prefs = prefs;

			try {
				await window.wp.apiFetch( {
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
				const message =
					error && error.message
						? error.message
						: ( window.listoraI18n && window.listoraI18n.profileFailed ) ||
								'Unable to save profile.';
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
			btn.textContent = btn.dataset.loadingText || listoraI18n.submitting;

			try {
				const formData = new FormData();
				formData.append( 'listing_id', ctx.listingId );
				formData.append( 'proof_text', proofText );

				const fileInput = form.querySelector( '[name="proof_file"]' );
				if ( fileInput && fileInput.files.length > 0 ) {
					formData.append( 'proof_file', fileInput.files[ 0 ] );
				}

				await wp.apiFetch( {
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
				if ( msgEl ) {
					msgEl.hidden = false;
					msgEl.textContent = error.message || listoraI18n.claimFailed;
					msgEl.className = 'listora-detail__claim-message listora-detail__claim-message--error';
				}
				if ( window.listoraToast ) {
					window.listoraToast( error.message || listoraI18n.claimFailed, 'error' );
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

			if ( tabId === 'map' ) {
				const mapEl = detail.querySelector( '#listora-detail-map' );
				if ( mapEl && ! mapEl._leafletMap && typeof L !== 'undefined' ) {
					const lat = parseFloat( mapEl.dataset.lat );
					const lng = parseFloat( mapEl.dataset.lng );
					if ( lat && lng ) {
						const map = L.map( mapEl ).setView( [ lat, lng ], 15 );
						L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
							attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
							maxZoom: 19,
						} ).addTo( map );
						L.marker( [ lat, lng ] ).addTo( map );
						mapEl._leafletMap = map;
						setTimeout( () => map.invalidateSize(), 100 );
					}
				}
			}

			if ( typeof window !== 'undefined' ) {
				window.history.replaceState( null, '', `#${ tabId }` );
			}
		},

		switchGalleryImage() {
			const ctx = getContext();
			const el = getElement();
			const detail = el.ref.closest( '.listora-detail' );
			if ( ! detail ) return;
			const mainImg = detail.querySelector( '.listora-detail__gallery-image' );
			if ( mainImg && ctx.imageSrc ) { mainImg.src = ctx.imageSrc; }
			detail.querySelectorAll( '.listora-detail__gallery-thumb' ).forEach( ( thumb ) => {
				thumb.classList.remove( 'is-active' );
			} );
			el.ref.classList.add( 'is-active' );
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
			if ( submitBtn ) { submitBtn.disabled = true; submitBtn.textContent = 'Submitting...'; }

			const requestData = { listing_id: ctx.listingId, overall_rating: parseInt( rating, 10 ), title, content };
			if ( Object.keys( criteriaRatings ).length > 0 ) requestData.criteria_ratings = criteriaRatings;

			try {
				const response = await window.wp.apiFetch( { path: `/listora/v1/listings/${ ctx.listingId }/reviews`, method: 'POST', data: requestData } );
				if ( msgDiv ) { msgDiv.hidden = false; msgDiv.textContent = response.message || 'Review submitted!'; msgDiv.style.color = 'var(--listora-success)'; }
				setTimeout( () => { window.location.reload(); }, 2000 );
			} catch ( error ) {
				if ( msgDiv ) { msgDiv.hidden = false; msgDiv.textContent = error.message || 'Failed to submit review.'; msgDiv.style.color = 'var(--listora-error)'; }
				if ( submitBtn ) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Review'; }
			}
		},

		async submitLeadForm( event ) {
			event.preventDefault();
			const ctx = getContext();
			const el = getElement();
			const form = el.ref.closest( '.listora-lead-form__form' ) || el.ref;
			const msgDiv = form.querySelector( '.listora-lead-form__message' );
			const submitBtn = form.querySelector( 'button[type="submit"]' );
			const name = form.querySelector( 'input[name="name"]' )?.value?.trim();
			const email = form.querySelector( 'input[name="email"]' )?.value?.trim();
			const phone = form.querySelector( 'input[name="phone"]' )?.value?.trim() || '';
			const message = form.querySelector( 'textarea[name="message"]' )?.value?.trim();
			const hp = form.querySelector( 'input[name="hp"]' )?.value || '';

			if ( ! name || ! email || ! message ) {
				if ( msgDiv ) {
					msgDiv.hidden = false;
					msgDiv.textContent = listoraI18n.leadRequired;
					msgDiv.className = 'listora-lead-form__message listora-lead-form__message--error';
				}
				return;
			}
			if ( submitBtn ) {
				submitBtn.disabled = true;
				submitBtn.textContent = listoraI18n.leadSending;
			}

			try {
				const response = await window.wp.apiFetch( {
					path: `/listora/v1/listings/${ ctx.listingId }/contact`,
					method: 'POST',
					data: { name, email, phone, message, hp },
				} );
				if ( msgDiv ) {
					msgDiv.hidden = false;
					msgDiv.textContent = ( response && response.message ) || listoraI18n.leadSent;
					msgDiv.className = 'listora-lead-form__message listora-lead-form__message--success';
				}
				if ( window.listoraToast ) {
					window.listoraToast( listoraI18n.leadSent, 'success' );
				}
				form.reset();
			} catch ( error ) {
				const errMsg =
					error && error.message ? error.message : listoraI18n.leadFailed;
				if ( msgDiv ) {
					msgDiv.hidden = false;
					msgDiv.textContent = errMsg;
					msgDiv.className = 'listora-lead-form__message listora-lead-form__message--error';
				}
				if ( window.listoraToast ) {
					window.listoraToast( errMsg, 'error' );
				}
			} finally {
				if ( submitBtn ) {
					submitBtn.disabled = false;
					submitBtn.textContent = listoraI18n.leadSend;
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
