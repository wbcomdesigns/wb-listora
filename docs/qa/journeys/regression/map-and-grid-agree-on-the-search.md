---
journey: map-and-grid-agree-on-the-search
plugin: wb-listora
priority: critical
roles: [anonymous]
covers: [search, map, grid, counter-vs-list, cross-cutting-check-8]
prerequisites:
  - "Directory page carrying BOTH a listing-grid and a listing-map block (demo: /listings/)"
  - "Listings across 2+ types, and some published listings WITHOUT a geo row (demo: 4 of 83)"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The map plots the search the grid ran

Grid and map both build their args through `wb_listora_search_args_from_url()` (`includes/search/search-url-helpers.php:215`) and resolve through `Search_Engine`; the map block adds `has_geo => true` and `per_page = map_max_markers` (`blocks/listing-map/render.php:75-85`) and joins `Geo_Query::markers_for()` (`:111`). A second parser or a second query against `search_index` reintroduces the bug. URL arguments are `listora_`-prefixed (`listora_type`, `listora_keyword`; the bare name is also accepted).

Verified via: Playwright against `/listings/` at 1440 (DOM + the block's `data-wp-context` JSON) plus SQL. 390 not screenshotted in this pass.

## How to read the markers
`document.querySelector('[data-wp-context*="mapConfig"]').getAttribute('data-wp-context')`, `JSON.parse(...).mapConfig.markers` (each has `id,lat,lng,title,type,typeName,color,icon,rating,featured,url,image,imageAlt`).

## Steps

### 1. A filter that matches nothing empties BOTH
- **Action**: `/listings/?listora_type=hotel&listora_keyword=zzzznomatch`
- **Expect**: grid shows the empty state "No listings found" (`.listora-empty`) AND `markers.length === 0`. Fails if markers > 0 beside a visible empty state.

### 2. A filter that matches something agrees (positive control)
- **Action**: `/listings/?listora_type=hotel`
- **Expect**: grid "Showing 1-20 of 20 listings"; every marker `type === "hotel"`; marker count equals `SELECT COUNT(*) FROM wp_listora_search_index si JOIN wp_listora_geo g ON g.listing_id=si.listing_id WHERE si.status='publish' AND si.listing_type='hotel' AND g.lat<>0` (20 on 2026-10-12).

### 3. Unfiltered does not shrink
- **Action**: `/listings/`
- **Expect**: marker count equals `SELECT COUNT(*) FROM wp_listora_geo g JOIN wp_listora_search_index si ON g.listing_id=si.listing_id WHERE si.status='publish' AND g.lat<>0` (79; the grid says 83), capped at `map_max_markers`. Lower than the SQL number means mappable listings were ranked past the cap (`has_geo` regressed).

### 4. A block-pinned type still wins over the URL
- **Action**: a page whose map block has `listingType` set to restaurant, opened with `?listora_type=hotel`.
- **Expect**: markers are all restaurants. (`render.php:90` `if ( $listing_type )`.)

### 5. The map may be a subset, never a superset
- **Expect**: every marker id appears in the grid's result set for the same URL; the 4 geo-less published listings are in the grid with no pin.

### 6. Large-site step (2809 listing rows, 2819 index rows, 83 published)
- **Action**: `SELECT COUNT(*) FROM wp_listora_search_index` (2819) vs `status='publish'` (83). Load `/listings/` and page the grid.
- **Expect**: grid and map totals come from the 83 published rows, never the 2819 index rows (a stale `search_index` row inflating totals was the 1.4.1 orphan bug). Page 2 exists (`Showing 21-40 of 83`), pagination links carry the same `listora_*` args.

### 7. 390px
- **Expect**: no horizontal page scroll; map and grid stack; filter controls >= 40px tall.

## Test-data trap
If every listing had a geo row, step 3 would pass whether or not `has_geo` is honoured. Keep some published listings without a geo row. A single listing type makes step 4 vacuous.

## Fail diagnostics
| Symptom | Look at |
|---|---|
| Markers ignore keyword | `blocks/listing-map/render.php:75` args no longer from `wb_listora_search_args_from_url()` |
| Fewer markers than mappable | `render.php:80` `has_geo` |
| Pin for a listing the grid hides | `Geo_Query::markers_for()` fed IDs not from the engine |
