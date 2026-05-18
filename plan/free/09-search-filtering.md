# 09 — Search & Filtering

## Scope

| | Free | Pro |
|---|---|---|
| Keyword search (FULLTEXT) | Yes | Yes |
| Category filter | Yes | Yes |
| Location filter | Yes | Yes |
| Listing type filter | Yes | Yes |
| Custom field filters | Yes | Yes |
| "Open Now" filter | Yes | Yes |
| Faceted counts | Yes | Yes |
| Sort options | Yes | Yes |
| Autocomplete suggestions | Yes | Yes (enhanced) |
| Radius slider | — | Yes |
| Price range slider | — | Yes |
| Multi-field range filters | — | Yes |
| Saved searches with alerts | — | Yes |
| "Near me" (browser geolocation) | Yes | Yes |

---

## Overview

Search is the core UX of any directory. The search block, grid block, and map block are connected — they share state via the Interactivity API `listora/directory` store.

**Key principle:** Search must work instantly after setup wizard. No configuration needed. Type-specific filters appear automatically based on the listing type's field configuration.

---

## Search Block: `listora/listing-search`

### Default Layout (Horizontal Bar)
```
┌──────────────────────────────────────────────────────────────────┐
│ [ 🔍 Search listings...  ] [ 📍 Location...  ] [ Type ▾ ] [Search] │
└──────────────────────────────────────────────────────────────────┘
  [More Filters ▾]
```

### Expanded Filters (Click "More Filters")
```
┌──────────────────────────────────────────────────────────────────┐
│ [ 🔍 Search listings...  ] [ 📍 Location...  ] [ Type ▾ ] [Search] │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│ Category: [ All Categories ▾ ]     Rating: [ Any ▾ ]            │
│                                                                  │
│ Cuisine:  ☑ Italian  ☐ Chinese  ☐ Japanese  ☑ Mexican          │
│                                                                  │
│ Price:    ☐ $  ☑ $$  ☑ $$$  ☐ $$$$                             │
│                                                                  │
│ ☑ Open Now    ☐ Has Reviews    ☐ Verified Only                  │
│                                                                  │
│ Features: ☐ WiFi  ☐ Parking  ☐ Outdoor  ☐ Delivery            │
│                                                                  │
│ [Clear All Filters]                          [Apply Filters]     │
└──────────────────────────────────────────────────────────────────┘
```

### Sidebar Layout (Alternative)
```
┌────────────────┐  ┌─────────────────────────────────┐
│ Search         │  │                                 │
│ [            ] │  │                                 │
│                │  │         Grid Results            │
│ Location       │  │                                 │
│ [            ] │  │                                 │
│                │  │                                 │
│ Category       │  │                                 │
│ [  ▾         ] │  │                                 │
│                │  │                                 │
│ Cuisine        │  │                                 │
│ ☑ Italian     │  │                                 │
│ ☐ Chinese     │  │                                 │
│ ☐ Japanese    │  │                                 │
│                │  │                                 │
│ Price Range    │  │                                 │
│ ☐ $ ☑ $$ ☑$$$│  │                                 │
│                │  │                                 │
│ ☑ Open Now    │  │                                 │
│                │  │                                 │
│ [Clear All]    │  │                                 │
└────────────────┘  └─────────────────────────────────┘
```

---

## Filter Types

### Common Filters (Always Available)
| Filter | UI | Source |
|--------|-----|--------|
| Keyword | Text input with autocomplete | FULLTEXT on search_index |
| Location | Text input with geocoding | listora_geo table |
| Listing Type | Tabs or dropdown | listora_listing_type taxonomy |
| Category | Dropdown (hierarchical) | listora_listing_cat taxonomy (scoped to type) |
| Rating | Star buttons or min-rating dropdown | search_index.avg_rating |
| Open Now | Toggle switch | listora_hours table |
| Features | Multi-checkbox | listora_listing_feature taxonomy |
| Sort By | Dropdown | Various columns |

### Type-Specific Filters (Dynamic)
Loaded from listing type's `_listora_search_filters` meta. Examples:

**Restaurant:**
- Cuisine (multiselect → multi-checkbox)
- Price Range (select → pill buttons)
- Delivery (checkbox → toggle)
- Reservations (select → dropdown)

**Real Estate:**
- Price (price → number inputs, Pro: range slider)
- Bedrooms (number → dropdown 1-5+)
- Bathrooms (number → dropdown 1-5+)
- Area (number → number inputs, Pro: range slider)
- Property Type (select → pill buttons)
- For Sale/Rent (select → tabs)

**Job:**
- Salary Range (price → number inputs)
- Employment Type (select → pill buttons)
- Remote (select → tabs: All / Remote / On-site / Hybrid)
- Experience Level (select → dropdown)

### Filter UI Mapping
| Field Type | Default Filter UI | Pro Filter UI |
|-----------|-------------------|---------------|
| select | Dropdown | Pill buttons |
| multiselect | Multi-checkbox (max 8, then scrollable) | Expandable checkbox list |
| checkbox | Toggle switch | Toggle switch |
| number | Min/Max inputs | Range slider |
| price | Min/Max inputs | Range slider with histogram |
| radio | Radio group | Pill buttons |
| date | Date picker | Date range picker |
| business_hours | "Open Now" toggle | + "Open at [time]" |
| map_location | Location input | + Radius slider (Pro) |

---

## Search Behavior

### Type Selection Changes Filters
1. User on `/listings/` page → sees type tabs: All | Restaurant | Real Estate | Hotel | ...
2. Clicks "Restaurant" → type-specific filters appear (cuisine, price range, delivery)
3. Clicks "Real Estate" → filters change (bedrooms, price, area)
4. Clicks "All" → only common filters shown

This is handled by the Interactivity API store:
```
actions.selectType(slug):
  1. Update state.selectedType
  2. Fetch type's filter config from API (or cached in initial state)
  3. Re-render filter panel with type-specific fields
  4. Clear type-specific filter values
  5. Trigger new search
```

### Instant Search (No Page Reload)
All filter changes trigger search via REST API:
```
1. User changes a filter
2. Debounce 300ms (keyboard) or immediate (click)
3. Interactivity API action fires
4. Fetch results from REST API: GET /listora/v1/search?keyword=...&type=...&filters=...
5. Update state.results → grid re-renders
6. Update state.facets → filter counts update
7. Update URL params (pushState) for shareability
8. Update map markers
```

### URL State
All search params reflected in URL for shareability and back-button:
```
/restaurants/?keyword=pizza&price_range=$$,$$$&open_now=1&sort=rating
```

Interactivity API reads URL params on load to restore search state.

---

## Autocomplete / Suggestions

### Keyword Autocomplete
```
User types: "piz"
                    ┌──────────────────────────┐
                    │ 🔍 Pizza Palace          │
                    │ 🔍 Pizza Hut             │
                    │ 🔍 Pizzeria Roma         │
                    │ 📁 Pizza (category)      │
                    │ 📍 Pizza Street, NYC     │
                    └──────────────────────────┘
```

**Implementation:**
- REST endpoint: `GET /listora/v1/search/suggest?q=piz`
- Searches: listing titles (prefix match), category names, location names
- Returns max 8 suggestions grouped by type (listings, categories, locations)
- Debounced at 200ms
- Cached per prefix (transient, 15 min)

### Location Autocomplete
```
User types: "Man"
                    ┌──────────────────────────┐
                    │ 📍 Manhattan, NY         │
                    │ 📍 Manchester, UK        │
                    │ 📍 Manila, Philippines   │
                    └──────────────────────────┘
```

**Free:** Searches location taxonomy terms (already in database)
**Pro:** Google Places autocomplete (more comprehensive, worldwide)

---

## Faceted Counts

Show how many listings match each filter value:
```
Cuisine:
☐ Italian (23)    ☐ Chinese (15)    ☐ Japanese (8)

Price Range:
☐ $ (12)   ☐ $$ (34)   ☐ $$$ (18)   ☐ $$$$ (5)
```

**Implementation:**
- Calculated in search query as additional aggregation
- Counts update when other filters change (true faceted search)
- Cached as transients (30 min TTL)
- Returned in REST response under `facets` key

**Performance:**
- For sites with < 50K listings: compute in real-time (fast enough)
- For larger sites: use cached facets, update on next cache miss

---

## Sort Options

| Sort | Free | Pro | SQL |
|------|:----:|:---:|-----|
| Relevance (default for keyword search) | Yes | Yes | FULLTEXT score |
| Newest | Yes | Yes | `created_at DESC` |
| Rating | Yes | Yes | `avg_rating DESC` |
| Distance | Yes | Yes | Haversine calculation |
| Price (Low to High) | Yes | Yes | `price_value ASC` |
| Price (High to Low) | Yes | Yes | `price_value DESC` |
| Most Reviewed | Yes | Yes | `review_count DESC` |
| Featured First | Yes | Yes | `is_featured DESC, avg_rating DESC` |
| Alphabetical | Yes | Yes | `title ASC` |

---

## Search Architecture (Two-Phase Query)

This is what makes WB Listora search fundamentally faster than every competitor. No plugin except GeoDirectory uses custom tables for search — and even GeoDirectory doesn't have a dedicated filter index.

### Phase 1: Candidate Selection (search_index table)

Purpose: Narrow down from 100K+ listings to ~500 candidates using indexed columns.

Query hits `listora_search_index` only:
- FULLTEXT match on `title`, `content_text`, `meta_text` for keyword search
- WHERE `listing_type` = filter
- WHERE `status` = 'publish'
- WHERE `lat/lng` within bounding box (geo pre-filter)
- WHERE `avg_rating` >= min_rating
- WHERE `is_featured` = 1 (if filtering)
- Returns: array of listing IDs + FULLTEXT relevance scores

This single-table query with proper indexes runs in < 50ms even at 100K rows.

### Phase 2: Custom Field Filtering (field_index table)

Purpose: Apply type-specific filters (bedrooms >= 3, cuisine = Italian, price range = $$) on the candidate set.

Query hits `listora_field_index`:
```sql
-- Example: Filter by cuisine = 'Italian' AND bedrooms >= 3
SELECT listing_id FROM listora_field_index
WHERE listing_id IN (...candidate_ids_from_phase_1...)
  AND (
    (field_key = 'cuisine' AND field_value = 'Italian')
    OR (field_key = 'bedrooms' AND numeric_value >= 3)
  )
GROUP BY listing_id
HAVING COUNT(DISTINCT field_key) = 2  -- must match ALL filter conditions
```

For multiselect filters (e.g., cuisine IN ['Italian', 'Chinese']):
```sql
AND field_key = 'cuisine' AND field_value IN ('Italian', 'Chinese')
```

This works because multiselect values are stored as separate rows in field_index.

### Phase 3: Hydration (WP object cache)

Purpose: Fetch full listing data for the final result set.

1. Take filtered listing IDs (typically 20 per page)
2. `WP_Query(['post__in' => $ids, 'orderby' => 'post__in'])` — uses WP object cache
3. `update_meta_cache('post', $ids)` — batch meta fetch
4. Return complete listing objects

### Phase 4: Facet Calculation (Parallel)

Purpose: Count how many listings match each filter value for dynamic filter counts.

```sql
SELECT field_value, COUNT(DISTINCT listing_id)
FROM listora_field_index
WHERE listing_id IN (...candidate_ids_from_phase_1...)
  AND field_key = 'cuisine'
GROUP BY field_value
```

Run one query per faceted filter field. Results cached as transients.

### Performance at Scale

| Phase | 1K listings | 10K listings | 100K listings |
|-------|-------------|--------------|---------------|
| Phase 1 (search_index) | < 10ms | < 30ms | < 100ms |
| Phase 2 (field_index) | < 5ms | < 15ms | < 50ms |
| Phase 3 (hydration) | < 20ms | < 20ms | < 20ms |
| Phase 4 (facets) | < 10ms | < 30ms | < 100ms |
| **Total** | **< 45ms** | **< 95ms** | **< 270ms** |

### Why This Beats Every Competitor

| Plugin | How They Filter | At 100K |
|--------|----------------|---------|
| Directorist | JOIN wp_postmeta per filter field | 3-10 seconds |
| HivePress | JOIN wp_postmeta per filter field | 3-10 seconds |
| Business Dir | JOIN wp_postmeta per filter field | 3-10 seconds |
| GeoDirectory | Custom table per CPT (single table scan) | < 500ms |
| **WB Listora** | **search_index + field_index (two indexed queries)** | **< 300ms** |

### Open Now Integration

"Open Now" filter uses Phase 1.5 — after candidate selection, before field filtering:

```sql
SELECT listing_id FROM listora_hours
WHERE listing_id IN (...candidate_ids...)
  AND day_of_week = ?
  AND is_closed = 0
  AND (is_24h = 1 OR (open_time <= ? AND close_time >= ?))
```

Day/time calculated using the listing's stored timezone.

### Taxonomy Filters (Category, Location, Features)

Category, location, and feature filters use standard WordPress term_relationships table — this is already optimized by WP core with proper indexes. These are applied as additional WHERE conditions on candidate IDs using `wp_get_object_terms()` batch query.

### Error Handling

```javascript
actions: {
  search: async () => {
    state.isLoading = true;
    state.searchError = null;
    try {
      const response = await apiFetch({
        path: buildSearchURL(),
      });
      state.results = response.listings;
      state.totalResults = response.total;
      state.totalPages = response.pages;
      state.facets = response.facets || {};
      state.hasSearched = true;
    } catch (error) {
      state.searchError = error.message || 'Search failed. Please try again.';
      state.results = [];
    } finally {
      state.isLoading = false;
    }
  }
}
```

Error state shows:
```
┌────────────────────────────────────┐
│  ⚠ Something went wrong           │
│  Could not load search results.   │
│  [Try Again]                       │
└────────────────────────────────────┘
```

---

## "Near Me" Search

1. User clicks "Near Me" button or browser auto-detects location
2. Browser Geolocation API → get user's lat/lng
3. Set default radius (5km/5mi, configurable)
4. Search with geo filter: listings within radius
5. Sort by distance by default
6. Show distance on each card: "0.3 mi away"

**Privacy:** Geolocation is opt-in (browser permission). If denied, location filter falls back to text input.

---

## Block Attributes

```json
{
  "attributes": {
    "layout": {
      "type": "string",
      "default": "horizontal",
      "enum": ["horizontal", "sidebar", "stacked"]
    },
    "listingType": {
      "type": "string",
      "default": ""
    },
    "showKeyword": { "type": "boolean", "default": true },
    "showLocation": { "type": "boolean", "default": true },
    "showTypeFilter": { "type": "boolean", "default": true },
    "showCategoryFilter": { "type": "boolean", "default": true },
    "showMoreFilters": { "type": "boolean", "default": true },
    "showSortBy": { "type": "boolean", "default": true },
    "placeholder": { "type": "string", "default": "" },
    "defaultSort": {
      "type": "string",
      "default": "featured",
      "enum": ["featured", "newest", "rating", "distance", "relevance"]
    }
  }
}
```

**`listingType` attribute:** When set (e.g., on type-specific page), the search is pre-filtered to that type. Type selector is hidden. Only that type's filters are shown.

---

## Interactivity API Store (Search State)

```javascript
// Shared state in listora/directory namespace
const { state } = store('listora/directory', {
  state: {
    // Search inputs
    searchQuery: '',
    selectedType: '',
    selectedLocation: '',
    selectedCategory: '',
    filters: {},           // { cuisine: ['Italian'], price_range: ['$$', '$$$'] }
    sortBy: 'featured',
    currentPage: 1,
    perPage: 20,

    // Geo
    userLat: null,
    userLng: null,
    searchRadius: 5,
    radiusUnit: 'km',
    mapBounds: null,       // { ne_lat, ne_lng, sw_lat, sw_lng }

    // Results
    results: [],
    totalResults: 0,
    totalPages: 0,
    facets: {},
    isLoading: false,
    hasSearched: false,

    // Error
    searchError: null,

    // Type config (loaded once per type)
    typeFilters: {},       // { restaurant: [{key:'cuisine', type:'multiselect', options:[...]}, ...] }

    // Map sync
    activeMarker: null,    // listing ID of highlighted marker
    highlightedCard: null, // listing ID of highlighted card

    // Favorites (loaded for logged-in user)
    favorites: [],         // array of listing IDs

    get isFavorited() {
      return state.favorites.includes(getContext().listingId);
    },

    // View
    viewMode: 'grid',      // grid | list | map | split

    // Computed
    get hasActiveFilters() {
      return this.searchQuery || this.selectedCategory ||
        Object.keys(this.filters).length > 0;
    },
    get activeFilterCount() {
      return Object.values(this.filters).flat().length;
    }
  },

  actions: {
    search: async () => { /* debounced REST API call */ },
    setFilter: (key, value) => { /* update filter, trigger search */ },
    clearFilter: (key) => { /* remove filter, trigger search */ },
    clearAllFilters: () => { /* reset all, trigger search */ },
    selectType: (slug) => { /* change type, update filters, trigger search */ },
    setSort: (sort) => { /* change sort, trigger search */ },
    setPage: (page) => { /* pagination, trigger search */ },
    setViewMode: (mode) => { /* grid/list/map/split */ },
    nearMe: () => { /* browser geolocation, trigger search */ },

    // Map ↔ Card sync
    highlightMarker: () => { state.activeMarker = getContext().listingId; },
    unhighlightMarker: () => { state.activeMarker = null; },
    highlightCard: () => { state.highlightedCard = getContext().listingId; },
    unhighlightCard: () => { state.highlightedCard = null; },

    // Favorites
    toggleFavorite: async () => { /* optimistic toggle + REST call */ },

    // Share
    shareDialog: () => { /* Web Share API or custom modal */ },

    // Claim
    showClaimModal: () => { /* open claim form modal */ },
  }
});
```

---

## Theme Adaptive UI

### Filter Controls
All filter inputs use WordPress form patterns:
```css
.listora-search__input {
  /* Inherit theme form styles */
  font-family: inherit;
  font-size: var(--wp--preset--font-size--small, 0.9rem);
  padding: var(--wp--preset--spacing--10, 0.5rem) var(--wp--preset--spacing--20, 1rem);
  border: 1px solid var(--wp--preset--color--contrast-3, #ccc);
  border-radius: var(--wp--custom--border-radius, 4px);
  background: var(--wp--preset--color--base, #fff);
  color: var(--wp--preset--color--contrast, #333);
}

.listora-search__button {
  /* Use theme's button styles via wp-element-button class */
}
```

### Active Filter Pills
```html
<div class="listora-search__active-filters">
  <span class="listora-filter-pill">
    Italian <button aria-label="Remove Italian filter">×</button>
  </span>
  <span class="listora-filter-pill">
    $$$ <button aria-label="Remove $$$ filter">×</button>
  </span>
  <button class="listora-search__clear-all">Clear all</button>
</div>
```

### Mobile Responsive
```
Desktop: Horizontal bar + inline filters
Tablet:  Horizontal bar + "Filters" button → slide-out panel
Mobile:  Stacked search/location + "Filters" button → full-screen modal
```

Mobile filter modal:
```
┌─────────────────────────────┐
│ Filters                  [×]│
│─────────────────────────────│
│                             │
│ Category                    │
│ [ All Categories        ▾ ] │
│                             │
│ Cuisine                     │
│ ☑ Italian  ☐ Chinese      │
│ ☐ Japanese ☑ Mexican      │
│                             │
│ Price Range                 │
│ ☐ $ ☑ $$ ☑ $$$ ☐ $$$$    │
│                             │
│ ☑ Open Now                 │
│                             │
│ [Clear All]  [Show 23 results] │
└─────────────────────────────┘
```

---

## Accessibility

| Element | A11y Feature |
|---------|-------------|
| Search form | `role="search"`, `aria-label="Search listings"` |
| Filter group | `role="group"`, `aria-labelledby` linked to heading |
| Checkboxes | Standard `<input type="checkbox">` with `<label>` |
| Dropdowns | `<select>` with `<label>`, or custom with `role="listbox"` |
| Active filters | `aria-live="polite"` region for screen reader announcements |
| Result count | `aria-live="polite"`: "Showing 23 results for Italian restaurants" |
| Loading state | `aria-busy="true"` on results container |
| Clear filter button | `aria-label="Remove [filter name] filter"` |

---

## REST API: Search Endpoint

```
GET /listora/v1/search

Query Parameters:
  keyword         string    FULLTEXT search term
  type            string    listing type slug
  category        int|string category term ID or slug
  location        string    location text (geocoded server-side)
  lat             float     center latitude
  lng             float     center longitude
  radius          int       radius in km/mi
  radius_unit     string    "km" or "mi" (default: "km")
  bounds[ne_lat]  float     map viewport NE latitude
  bounds[ne_lng]  float     map viewport NE longitude
  bounds[sw_lat]  float     map viewport SW latitude
  bounds[sw_lng]  float     map viewport SW longitude
  features[]      int[]     feature term IDs
  open_now        boolean   filter to currently open
  min_rating      float     minimum average rating
  sort            string    relevance|newest|rating|distance|price_asc|price_desc
  page            int       page number (default: 1)
  per_page        int       items per page (default: 20, max: 100)
  facets          boolean   include facet counts (default: false)
  {field_key}     mixed     any filterable custom field

Response:
{
  "listings": [...],         // array of listing objects
  "total": 156,              // total matching count
  "pages": 8,                // total pages
  "facets": {                // only if facets=true
    "cuisine": {"Italian": 23, "Chinese": 15, ...},
    "price_range": {"$": 12, "$$": 34, ...}
  }
}
```
