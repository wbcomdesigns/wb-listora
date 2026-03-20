# 40 — REST API, Abilities API & Interactivity API Unified Architecture

## Scope: Free + Pro (Uniform)

---

## Design Principle

**Every feature has three layers, all in the Free plugin:**

1. **REST API** — data access, CRUD, search (app-ready, headless-ready)
2. **Abilities API** — capability/permission declarations for clients
3. **Interactivity API** — frontend reactivity with server-rendered HTML

Pro adds features by hooking into all three layers via filters/actions. The architecture is uniform — Pro never creates a parallel system.

---

## REST API — Complete Endpoint Map

### Public Endpoints (No Auth)

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/listora/v1/listings` | List/filter listings |
| `GET` | `/listora/v1/listings/{id}` | Single listing with all meta |
| `GET` | `/listora/v1/listings/{id}/related` | Related listings |
| `GET` | `/listora/v1/search` | Full search (keyword + geo + filters + facets) |
| `GET` | `/listora/v1/search/suggest` | Autocomplete suggestions |
| `GET` | `/listora/v1/listing-types` | All types with field definitions |
| `GET` | `/listora/v1/listing-types/{slug}` | Single type schema |
| `GET` | `/listora/v1/listing-types/{slug}/fields` | Fields for type |
| `GET` | `/listora/v1/listing-types/{slug}/categories` | Scoped categories |
| `GET` | `/listora/v1/listings/{id}/reviews` | Reviews for listing |
| `GET` | `/listora/v1/categories` | All categories |
| `GET` | `/listora/v1/locations` | Location hierarchy |
| `GET` | `/listora/v1/features` | All features/amenities |
| `GET` | `/listora/v1/plans` | Pricing plans (Pro, public info only) |
| `GET` | `/listora/v1/settings/maps` | Public map config (provider, public API key) |

### Authenticated Endpoints

| Method | Endpoint | Capability Required | Purpose |
|--------|----------|--------------------:|---------|
| `POST` | `/listings` | `submit_listora_listing` | Create listing |
| `PUT` | `/listings/{id}` | Author or `edit_others` | Update listing |
| `DELETE` | `/listings/{id}` | Author or `delete_others` | Trash listing |
| `POST` | `/submit` | `submit_listora_listing` | Frontend submission (handles media) |
| `PUT` | `/submit/{id}` | Author | Edit own submission |
| `POST` | `/submit/{id}/media` | Author | Upload media for listing |
| `POST` | `/submit/{id}/renew` | Author | Renew expired listing |
| `POST` | `/listings/{id}/reviews` | Authenticated | Submit review |
| `PUT` | `/reviews/{id}` | Author | Edit own review |
| `DELETE` | `/reviews/{id}` | Author or `moderate` | Delete review |
| `POST` | `/reviews/{id}/helpful` | Authenticated | Vote helpful |
| `POST` | `/reviews/{id}/reply` | Listing Author | Owner reply |
| `POST` | `/reviews/{id}/report` | Authenticated | Report review |
| `GET` | `/favorites` | Authenticated | Get user favorites |
| `POST` | `/favorites` | Authenticated | Add favorite |
| `DELETE` | `/favorites/{id}` | Authenticated | Remove favorite |
| `POST` | `/claims` | Authenticated | Submit claim |
| `GET` | `/dashboard/listings` | Authenticated | User's listings |
| `GET` | `/dashboard/reviews` | Authenticated | User's reviews |
| `GET` | `/dashboard/stats` | Authenticated | Summary counts |
| `PUT` | `/dashboard/profile` | Authenticated | Update profile |

### Admin-Only Endpoints

| Method | Endpoint | Capability | Purpose |
|--------|----------|-----------|---------|
| `GET` | `/claims` | `manage_claims` | List all claims |
| `PUT` | `/claims/{id}` | `manage_claims` | Approve/reject claim |
| `GET` | `/settings` | `manage_settings` | Get all settings |
| `PUT` | `/settings` | `manage_settings` | Update settings |
| `POST` | `/reindex` | `manage_settings` | Trigger reindex |

### Pro Endpoints (Added via hooks)

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/analytics/listing/{id}` | Listing analytics |
| `GET` | `/analytics/overview` | Site-wide analytics |
| `POST` | `/payments/checkout` | Create checkout session |
| `GET` | `/payments` | Payment history |
| `GET` | `/payments/{id}/invoice` | Download invoice |
| `POST` | `/payments/{id}/refund` | Issue refund |
| `POST` | `/coupons/validate` | Validate coupon code |
| `GET` | `/applications/{listing_id}` | Job applications |
| `POST` | `/applications` | Submit application |
| `POST` | `/webhooks/stripe` | Stripe webhook |
| `POST` | `/webhooks/paypal` | PayPal webhook |
| `GET` | `/saved-searches` | User's saved searches |
| `POST` | `/saved-searches` | Create saved search |

### How Pro Adds Endpoints
```php
// Pro hooks into free's REST init
add_action('wb_listora_rest_api_init', function() {
    $analytics = new Pro_Analytics_Controller();
    $analytics->register_routes();

    $payments = new Pro_Payments_Controller();
    $payments->register_routes();
});
```

---

## Abilities API Integration

### What is the Abilities API?
WordPress Abilities API (introduced in WP 6.x) allows plugins to declare capabilities that clients can query. This tells block editor, REST API consumers, and mobile apps what the current user can do.

### Ability Declarations
```php
// Register abilities on init
add_action('init', function() {
    if (!function_exists('wp_register_ability')) return; // WP version check

    wp_register_ability('listora_submit_listing', [
        'label'       => __('Submit a listing', 'wb-listora'),
        'description' => __('Create and submit new directory listings', 'wb-listora'),
        'category'    => 'listora',
        'callback'    => function() {
            return current_user_can('submit_listora_listing');
        },
    ]);

    wp_register_ability('listora_edit_own_listing', [
        'label'       => __('Edit own listings', 'wb-listora'),
        'category'    => 'listora',
        'callback'    => function() {
            return current_user_can('edit_listora_listing');
        },
    ]);

    wp_register_ability('listora_write_review', [
        'label'       => __('Write reviews', 'wb-listora'),
        'category'    => 'listora',
        'callback'    => function() {
            return is_user_logged_in();
        },
    ]);

    wp_register_ability('listora_manage_directory', [
        'label'       => __('Manage directory settings', 'wb-listora'),
        'category'    => 'listora',
        'callback'    => function() {
            return current_user_can('manage_listora_settings');
        },
    ]);

    wp_register_ability('listora_moderate', [
        'label'       => __('Moderate listings and reviews', 'wb-listora'),
        'category'    => 'listora',
        'callback'    => function() {
            return current_user_can('moderate_listora_reviews');
        },
    ]);

    // Pro adds more abilities
    do_action('wb_listora_register_abilities');
});

// Register ability category
wp_register_ability_category('listora', [
    'label' => __('WB Listora', 'wb-listora'),
]);
```

### Pro Abilities
```php
// Pro hooks in to register additional abilities
add_action('wb_listora_register_abilities', function() {
    wp_register_ability('listora_view_analytics', [
        'label'    => __('View listing analytics', 'wb-listora'),
        'category' => 'listora',
        'callback' => function() {
            return current_user_can('edit_listora_listing') && wb_listora_is_pro_active();
        },
    ]);

    wp_register_ability('listora_manage_payments', [
        'label'    => __('Manage payments and plans', 'wb-listora'),
        'category' => 'listora',
        'callback' => function() {
            return current_user_can('manage_listora_settings') && wb_listora_is_pro_active();
        },
    ]);
});
```

### REST API Exposes Abilities
```
GET /wp-json/wp-abilities/v1/abilities?category=listora

Response:
{
  "listora_submit_listing": true,
  "listora_edit_own_listing": true,
  "listora_write_review": true,
  "listora_manage_directory": false,
  "listora_moderate": false,
  "listora_view_analytics": false,
  "listora_manage_payments": false
}
```

**Why this matters for apps:** A mobile app or headless frontend can query abilities to know what UI to show for the current user — without hardcoding capability checks.

---

## Interactivity API — Unified Store Architecture

### Single Namespace
All interactive blocks share: `listora/directory`

This allows search ↔ grid ↔ map ↔ card ↔ detail to communicate via shared state.

### Complete Store Definition

```javascript
import { store, getContext } from '@wordpress/interactivity';

const { state, actions } = store('listora/directory', {
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

    // ─── View ───
    viewMode: 'grid',

    // ─── Type Config ───
    typeFilters: {},
    typeFieldConfig: {},

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
    userAbilities: {},

    // ─── Modals ───
    activeModal: null,

    // ─── Computed ───
    get hasActiveFilters() {
      return !!state.searchQuery || !!state.selectedCategory ||
        Object.keys(state.filters).length > 0;
    },
    get activeFilterCount() {
      return Object.values(state.filters).flat().length;
    },
    get isFavorited() {
      return state.favorites.includes(getContext().listingId);
    },
    get canSubmit() {
      return state.userAbilities.listora_submit_listing === true;
    },
    get canReview() {
      return state.userAbilities.listora_write_review === true;
    },
  },

  actions: {
    // ─── Search ───
    search: async () => {
      state.isLoading = true;
      state.searchError = null;
      try {
        const url = buildSearchURL(state);
        const response = await apiFetch({ path: url });
        state.results = response.listings;
        state.totalResults = response.total;
        state.totalPages = response.pages;
        state.facets = response.facets || {};
        state.hasSearched = true;
        updateURLParams(state);
      } catch (error) {
        state.searchError = error.message || 'Search failed.';
        state.results = [];
      } finally {
        state.isLoading = false;
      }
    },

    setFilter: (key, value) => {
      state.filters = { ...state.filters, [key]: value };
      state.currentPage = 1;
      actions.search();
    },

    clearFilter: (key) => {
      const { [key]: _, ...rest } = state.filters;
      state.filters = rest;
      state.currentPage = 1;
      actions.search();
    },

    clearAllFilters: () => {
      state.searchQuery = '';
      state.selectedCategory = '';
      state.selectedLocation = '';
      state.filters = {};
      state.currentPage = 1;
      actions.search();
    },

    selectType: async (slug) => {
      state.selectedType = slug;
      state.filters = {};
      state.currentPage = 1;
      if (slug && !state.typeFilters[slug]) {
        const config = await apiFetch({ path: `/listora/v1/listing-types/${slug}/fields` });
        state.typeFilters[slug] = config.filters;
        state.typeFieldConfig[slug] = config.fields;
      }
      actions.search();
    },

    setSort: (sort) => { state.sortBy = sort; actions.search(); },
    setPage: (page) => { state.currentPage = page; actions.search(); },
    setViewMode: (mode) => { state.viewMode = mode; },

    nearMe: async () => {
      try {
        const pos = await new Promise((resolve, reject) =>
          navigator.geolocation.getCurrentPosition(resolve, reject)
        );
        state.userLat = pos.coords.latitude;
        state.userLng = pos.coords.longitude;
        state.sortBy = 'distance';
        actions.search();
      } catch {
        state.searchError = 'Location access denied. Use the location search instead.';
      }
    },

    // ─── Map ↔ Card Sync ───
    highlightMarker: () => { state.activeMarker = getContext().listingId; },
    unhighlightMarker: () => { state.activeMarker = null; },
    highlightCard: () => { state.highlightedCard = getContext().listingId; },
    unhighlightCard: () => { state.highlightedCard = null; },

    updateMapBounds: (bounds) => {
      state.mapBounds = bounds;
      actions.search();
    },

    // ─── Favorites ───
    toggleFavorite: async () => {
      if (!state.isLoggedIn) {
        state.activeModal = 'login';
        return;
      }
      const { listingId } = getContext();
      const idx = state.favorites.indexOf(listingId);
      if (idx > -1) {
        state.favorites = state.favorites.filter(id => id !== listingId);
        await apiFetch({ path: `/listora/v1/favorites/${listingId}`, method: 'DELETE' });
      } else {
        state.favorites = [...state.favorites, listingId];
        await apiFetch({ path: '/listora/v1/favorites', method: 'POST', data: { listing_id: listingId } });
      }
    },

    // ─── Modals ───
    shareDialog: () => {
      if (navigator.share) {
        navigator.share({ title: getContext().listingTitle, url: getContext().listingUrl });
      } else {
        state.activeModal = 'share';
      }
    },
    showClaimModal: () => { state.activeModal = 'claim'; },
    showLoginModal: () => { state.activeModal = 'login'; },
    closeModal: () => { state.activeModal = null; },

    // ─── Review ───
    submitReview: async () => { /* validate + POST to reviews endpoint */ },
    voteHelpful: async () => { /* POST to reviews/{id}/helpful */ },
    reportReview: async () => { /* POST to reviews/{id}/report */ },
  },

  callbacks: {
    // Called when block initializes
    onSearchBlockInit: () => {
      // Read URL params and restore search state
      const params = new URLSearchParams(window.location.search);
      if (params.get('keyword')) state.searchQuery = params.get('keyword');
      if (params.get('type')) actions.selectType(params.get('type'));
      if (params.get('category')) state.selectedCategory = params.get('category');
      // ... restore all filters from URL
      if (state.hasActiveFilters) actions.search();
    },

    onMapInit: () => {
      state.mapReady = true;
    },
  }
});
```

### How Blocks Use the Store

**Search Block (`render.php`):**
```html
<div
  data-wp-interactive="listora/directory"
  data-wp-init="callbacks.onSearchBlockInit"
  data-wp-class--is-loading="state.isLoading"
>
  <input
    type="search"
    data-wp-bind--value="state.searchQuery"
    data-wp-on--input="actions.updateSearchQuery"
    placeholder="Search listings..."
  />
  <!-- filters rendered here -->
</div>
```

**Card Block (`render.php`):**
```html
<article
  data-wp-interactive="listora/directory"
  data-wp-context='{"listingId": 123, "listingTitle": "Pizza Palace", "listingUrl": "/listing/pizza-palace/"}'
  data-wp-on--mouseenter="actions.highlightMarker"
  data-wp-on--mouseleave="actions.unhighlightMarker"
  data-wp-class--is-highlighted="state.highlightedCard === context.listingId"
>
  <button
    data-wp-on--click="actions.toggleFavorite"
    data-wp-class--is-favorited="state.isFavorited"
    data-wp-bind--aria-pressed="state.isFavorited"
  >♡</button>
</article>
```

### Server-Side Initial State

The first page load is server-rendered (SEO). The Interactivity API hydrates on top:

```php
// In render.php, provide initial state
wp_interactivity_state('listora/directory', [
    'results'      => $initial_listings,
    'totalResults' => $total,
    'favorites'    => $user_favorites,
    'isLoggedIn'   => is_user_logged_in(),
    'userId'       => get_current_user_id(),
    'userAbilities'=> wb_listora_get_user_abilities(),
    'selectedType' => $block_listing_type,
]);
```

This means:
- First render = full HTML, no JS needed (great for SEO, great for performance)
- JS enhances: search, filtering, map interaction, favorites, modals
- If JS fails = site still works (progressive enhancement)

---

## How Pro Extends All Three Layers

### Example: Analytics Feature

**REST API (Pro adds endpoints):**
```php
add_action('wb_listora_rest_api_init', function() {
    register_rest_route('listora/v1', '/analytics/listing/(?P<id>\d+)', [...]);
});
```

**Abilities API (Pro adds ability):**
```php
add_action('wb_listora_register_abilities', function() {
    wp_register_ability('listora_view_analytics', [...]);
});
```

**Interactivity API (Pro adds state + actions):**
```php
// Pro extends the shared store
add_action('wp_interactivity_state_listora/directory', function(&$state) {
    $state['analyticsData'] = null;
    $state['analyticsLoading'] = false;
});
```

```javascript
// Pro's view.js adds actions to the existing store
const { state, actions } = store('listora/directory', {
  actions: {
    loadAnalytics: async () => {
      state.analyticsLoading = true;
      const data = await apiFetch({ path: `/listora/v1/analytics/listing/${getContext().listingId}` });
      state.analyticsData = data;
      state.analyticsLoading = false;
    },
  }
});
```

**Key:** Pro uses the SAME `listora/directory` namespace. It extends, never replaces.

---

## API-First Design Checklist

| Feature | REST API | Abilities | Interactivity | Status |
|---------|:--------:|:---------:|:-------------:|:------:|
| Search | `/search` | — | `actions.search` | Free |
| Listings CRUD | `/listings` | `submit`, `edit_own` | form stores | Free |
| Reviews | `/reviews` | `write_review` | `actions.submitReview` | Free |
| Favorites | `/favorites` | — | `actions.toggleFavorite` | Free |
| Claims | `/claims` | — | `actions.showClaimModal` | Free |
| Maps | `/settings/maps` | — | map callbacks | Free |
| Dashboard | `/dashboard/*` | `edit_own` | dashboard store | Free |
| Types/Fields | `/listing-types` | — | `actions.selectType` | Free |
| Analytics | `/analytics/*` | `view_analytics` | `actions.loadAnalytics` | Pro |
| Payments | `/payments/*` | `manage_payments` | checkout redirect | Pro |
| Applications | `/applications` | `submit`, `edit_own` | apply form | Pro |
| Saved Searches | `/saved-searches` | — | `actions.saveSearch` | Pro |

Every feature accessible via REST API = mobile app ready. Every feature declared via Abilities = client-discoverable. Every feature interactive via Interactivity API = no page reloads.
