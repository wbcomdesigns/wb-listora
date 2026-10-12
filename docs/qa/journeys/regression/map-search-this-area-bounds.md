---
journey: map-search-this-area-bounds
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [listing-map, search-on-drag, viewport-bounds]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A page with the listing-map + listing-grid blocks (/listings/)"
  - "Listings with geo rows in distinct regions (demo: NYC cluster plus listings up to lat 28.6 and 42.4)"
  - "map_search_on_drag enabled (Settings > Maps > Search on drag, default on)"
estimated_runtime_minutes: 6
covers_card: 9909608502
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# "Search this area" constrains the map and the grid to the viewport

`searchImmediate()` (`src/interactivity/store.js:837`) serializes `state.mapBounds` into the navigation URL (`:876-879`) as `listora_bounds[ne_lat]`, `listora_bounds[ne_lng]`, `listora_bounds[sw_lat]`, `listora_bounds[sw_lng]`. The server reads them with `wb_listora_search_bounds_from_url()` (`includes/search/search-url-helpers.php:294`, all four required; the bare `bounds[...]` form is also accepted); the grid and the map both honour them through the shared search args. `map_max_markers` still caps the markers.

Verified via: Playwright page loads with explicit bounds (prefixed, bare) and `GET /listora/v1/search`. The drag + click path (step 3) was not scripted.

## Steps

### 1. Bounds in the URL constrain the markers
- **Action**: open `/listings/?listora_bounds[ne_lat]=40.80&listora_bounds[ne_lng]=-73.90&listora_bounds[sw_lat]=40.65&listora_bounds[sw_lng]=-74.10` (percent-encode the brackets).
- **Expect**: `mapConfig.markers` has 65 entries, all with lat between 40.65 and 40.80. Without bounds: 79 markers spanning lat 28.63-42.36 (positive control).

### 2. The grid honours the same bounds
- **Expect**: grid says "Showing 1-20 of 65 listings" (83 without bounds). `GET /listora/v1/search?bounds[ne_lat]=40.80&bounds[ne_lng]=-73.90&bounds[sw_lat]=40.65&bounds[sw_lng]=-74.10&per_page=100` returns `total` 65. Bare `bounds[...]` in the page URL gives the same 65.

### 3. searchImmediate carries bounds
- **Action**: pan the map, click "Search this area" (or drag with search-on-drag on).
- **Expect**: the navigation URL contains the four `listora_bounds[...]` params and the reloaded page stays scoped. A partial set (e.g. only `ne_lat`) is ignored, not applied.

### 4. max_markers still applies
- **Action**: Max markers = 50 (min allowed), bounded URL covering > 50 listings (use the whole country box).
- **Expect**: marker count <= 50 and the notice "Showing 50 of N on the map, zoom in to see more". Restore 500.

### 5. Role ladder
Public. Identical for logged-out, member, moderator, admin.

### 6. 390px
- **Expect**: "Search this area" control reachable (>= 40px), no horizontal page scroll.

## Fail diagnostics
`src/interactivity/store.js:837-885` (`searchImmediate`, bounds), `includes/search/search-url-helpers.php:294`, `blocks/listing-map/render.php:75`, `blocks/listing-grid/render.php` (shared args).
