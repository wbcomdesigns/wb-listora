---
journey: map-and-grid-agree-on-the-search
plugin: wb-listora
priority: critical
roles: [anonymous]
covers: [search, map, grid, counter-vs-list, cross-cutting-check-8]
prerequisites:
  - "Directory page carrying BOTH a listing-grid and a listing-map block"
  - "Listings across 2+ types, and some listings WITHOUT geo rows"
estimated_runtime_minutes: 6
---

# The map plots the search the grid ran

Found by cross-cutting check 8 on its first smoke run. The grid parsed eleven
URL filters and resolved them through `Search_Engine`; the map parsed exactly
one (`bounds`) and hand-rolled its own SQL against `search_index`. They render
side by side, so `?keyword=cafe` produced a grid reading "No listings found"
next to a map still showing pins for every listing that did not match.

Each half was correct alone. Only together were they obviously wrong, which is
why every prior walk passed them.

Both now build args through `wb_listora_search_args_from_url()` and resolve
through the engine. A second parser or a second query against `search_index`
reintroduces this, and it will not be visible on the surface you are testing.

## Steps

### 1. A filter that matches nothing empties BOTH
Load `?type=<real type>&keyword=zzzznomatch`.
- Grid empty state is **computed-visible**.
- `mapConfig.markers.length === 0`.
- **Fails if** markers > 0 beside a visible empty state. That pairing is the bug.

### 2. A filter that matches something agrees
Load `?type=hotel`.
- Every marker's `type` is `hotel`.
- Marker count equals the mappable hotels:
  `SELECT COUNT(*) FROM search_index WHERE status='publish' AND listing_type='hotel' AND lat != 0`.

### 3. Unfiltered does not shrink
Load the Directory with no query string. Marker count must equal
`SELECT COUNT(*) FROM geo g JOIN search_index si ON g.listing_id=si.listing_id
 WHERE si.status='publish' AND g.lat != 0` (capped at `map_max_markers`).
- **Fails if** it is lower. Resolving IDs first and filtering for coordinates
  afterwards caps *candidates* rather than markers: mappable listings ranked
  past the cap vanish silently. `has_geo => true` is what prevents this - it
  was 73 instead of 99 before that arg existed.

### 4. A block-pinned type still wins over the URL
A map pinned to "Restaurants" must not become a hotel map via `?type=hotel`.

### 5. The map may be a subset, never a superset
A listing with no geo row appears in the grid and has no pin. That is correct.
- **Fails if** any marker's listing is absent from the grid's result set.

## Test-data trap

If every listing has a geo row, step 3 passes whether or not `has_geo` is
honoured, because candidate order stops mattering. Ensure some published
listings have NO geo row before trusting it. Likewise, a directory with one
listing type makes step 4 vacuous.
