---
journey: map-search-this-area-bounds
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [listing-map, search-on-drag, viewport-bounds]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A page with the listing-map + listing-grid blocks (the Directory page)"
  - "Listings with geo rows in distinct regions (capture an IN-box and an OUT-of-box one)"
  - "map_search_on_drag enabled"
estimated_runtime_minutes: 5
covers_card: 9909608502
---

# "Search this area" constrains the map + grid to the drawn viewport

Regression sentinel for the search-on-drag bug: clicking "Search this area"
called `searchImmediate()`, which built its navigation URL from a fixed param
set that OMITTED `state.mapBounds` — so the bounds were dropped on the
full-page reload and both the map and grid reset to the initial unfiltered
view. The server map render also never read bounds, and `map_max_markers` was
only applied on the initial render.

Fix (navigation-preserving, consistent with searchImmediate's
"navigate + server re-render" design):
- `searchImmediate()` serializes `state.mapBounds` into the navigation URL
  (`bounds[ne_lat]` etc.) when present.
- `blocks/listing-grid/render.php` reads `bounds[]` from `$_GET` and passes it
  to the search engine (which already supports a `bounds` arg).
- `blocks/listing-map/render.php` adds a `g.lat/g.lng BETWEEN` clause from the
  same `$_GET` bounds, still under the `map_max_markers` LIMIT.

## Steps

### 1. Bounds in the URL constrain the server-rendered markers
- **Action**: load the Directory page with explicit bounds covering only
  region A: `?bounds[ne_lat]=..&bounds[ne_lng]=..&bounds[sw_lat]=..&bounds[sw_lng]=..`.
- **Expect**: the map block's context markers include the IN-box listing and
  EXCLUDE the OUT-of-box listing. Loading the page WITHOUT bounds includes
  both. (Verified via the `mapConfig.markers` data-wp-context array.)

### 2. The grid honors the same bounds
- **Action**: same bounded URL; inspect the server-rendered grid cards (or
  `GET /listora/v1/search?bounds[...]`).
- **Expect**: only IN-box listings render; OUT-of-box excluded. Without bounds,
  both appear.

### 3. searchImmediate carries bounds
- **Action**: with search-on-drag on, pan the map, click "Search this area".
- **Expect**: the resulting navigation URL contains the four `bounds[...]`
  params (so the reloaded page stays scoped to the drawn viewport). The built
  `store.js` serializes `state.mapBounds` in `searchImmediate()`.

### 4. max_markers still applies
- **Action**: bounded render with more than `map_max_markers` in-box listings.
- **Expect**: marker count caps at `map_max_markers` (the LIMIT is on the same
  server render path).
