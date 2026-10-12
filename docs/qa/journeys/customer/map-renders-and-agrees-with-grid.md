---
journey: map-renders-and-agrees-with-grid
plugin: wb-listora
priority: critical
roles: [guest, owner, other, editor, admin]
covers: [listing-map-block-render, update-meta-cache, popup-image, search, map, grid, counter-vs-list, cross-cutting-check-8, map-marker-cap-notice, bc-10350135718, search-on-drag, viewport-bounds, card-9909608502, card-10393313839]
prerequisites:
  - "Directory page carrying BOTH a listing-grid and a listing-map block"
  - "Listings across 2+ types; some PUBLISHED listings WITHOUT a geo row (else the has_geo step is vacuous); one mappable listing with a featured image and one without"
  - "More than 50 mappable published listings and a category {cat} with fewer; listings in distinct regions for the bounds step"
  - "map_search_on_drag enabled (Settings > Maps > Search on drag, default on)"
estimated_runtime_minutes: 20
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/map-fatal.md
  - ora:regression/map-and-grid-agree-on-the-search.md
  - ora:regression/map-notice-only-when-capped.md
  - ora:regression/map-search-this-area-bounds.md
fails_on: 46ba223e
site_profile: required
---

# The map renders without fatals and plots exactly the search the grid ran

## Why
The map block once fataled (`update_post_meta_cache()` does not exist), popups lacked images, and a second query path let the map show pins the grid hid. Grid and map both build args through `wb_listora_search_args_from_url()` (`includes/search/search-url-helpers.php:215`); the map adds `has_geo => true` and `per_page = map_max_markers` (`blocks/listing-map/render.php:75-85`).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Directory URL `$MAP_URL = $SITE_URL/{directory-path}/`; note the byte size of `$WP_PATH/wp-content/debug.log`.
- Read markers: `JSON.parse(document.querySelector('[data-wp-context*="mapConfig"]').getAttribute('data-wp-context')).mapConfig.markers` (each has `id,lat,lng,title,type,typeName,color,icon,rating,featured,url,image,imageAlt`).
- URL args are `listora_`-prefixed (`listora_type`, `listora_keyword`, `listora_bounds[ne_lat|ne_lng|sw_lat|sw_lng]`; bare names also accepted).
- `G` = mappable published = `SELECT COUNT(*) FROM {prefix}listora_geo g JOIN {prefix}listora_search_index si ON g.listing_id=si.listing_id WHERE si.status='publish' AND g.lat<>0`; `T` = grid total (`GET /search` `total`); `T > G` when geo-less listings exist.

## Steps

### 1. Page and map render, no fatals
- **Action**: open `$MAP_URL` logged out.
- **Expect**: HTTP 200; `.wp-block-listora-listing-map`; `.listora-map.leaflet-container` with tiles and `.leaflet-marker-icon`s; `debug.log` diff has no `Call to undefined function`, `update_post_meta_cache` or `Fatal` (`blocks/listing-map/render.php:122`).

### 2. Markers carry image data
- **Expect**: each marker has `image` (URL string or `""`) and `imageAlt`; non-empty for listings with a featured image, empty for those without.

### 3. Popup with an image (positive control)
- **Action**: click a non-cluster `.leaflet-marker-icon:not(.marker-cluster)`.
- **Expect**: `.listora-map__popup` shows `img.listora-map__popup-image` (`loading="lazy"`, `alt` = listing title), the title link, a `.listora-badge--type` with the type name, a "View Details" link (`.listora-map__popup-link`) and `button.listora-map__popup-close[aria-label="Close"]`.

### 4. Popup without an image (graceful omission)
- **Expect**: popup opens with no `.listora-map__popup-image` element (not a broken-image icon).

### 5. A filter that matches nothing empties BOTH
- **Action**: `$MAP_URL?listora_type={type}&listora_keyword={nomatch}`.
- **Expect**: grid shows `.listora-empty` "No listings found" AND `markers.length === 0`. Fails if markers > 0 beside a visible empty state.

### 6. A filter that matches something agrees (positive control)
- **Action**: `$MAP_URL?listora_type={type}`.
- **Expect**: grid "Showing 1-N of N listings"; every marker `type === "{type}"`; marker count equals `SELECT COUNT(*) FROM {prefix}listora_search_index si JOIN {prefix}listora_geo g ON g.listing_id=si.listing_id WHERE si.status='publish' AND si.listing_type='{type}' AND g.lat<>0`.

### 7. Unfiltered does not shrink
- **Expect**: marker count equals `G` (capped at `map_max_markers`); fewer means mappable listings were ranked past the cap (`has_geo` regressed).

### 8. A block-pinned type still wins over the URL
- **Action**: a page whose map block has `listingType` = `{type}`, opened with `?listora_type={other-type}`.
- **Expect**: markers are all `{type}` (`render.php:90`).

### 9. The map may be a subset, never a superset
- **Expect**: every marker id is in the grid's result set for the same URL; the geo-less published listings are in the grid with no pin.

### 10. Bounds in the URL constrain markers and grid
- **Action**: open `$MAP_URL?listora_bounds[ne_lat]=A&listora_bounds[ne_lng]=B&listora_bounds[sw_lat]=C&listora_bounds[sw_lng]=D` (percent-encode the brackets) for a box around a cluster of listings.
- **Expect**: `mapConfig.markers` holds only listings inside the box (compare with the unbounded count, positive control); grid says "Showing 1-N of M listings" with M = `GET /search?bounds[ne_lat]=A&bounds[ne_lng]=B&bounds[sw_lat]=C&bounds[sw_lng]=D&per_page=100` `total`; the bare `bounds[...]` form in the page URL gives the same.

### 11. Search this area carries bounds
- **Action**: pan the map, click "Search this area" (or drag with search-on-drag on).
- **Expect**: the navigation URL contains the four `listora_bounds[...]` params (`searchImmediate()` `store.js:837-885`) and the reloaded page stays scoped. A partial set (only `ne_lat`) is ignored, not applied (`wb_listora_search_bounds_from_url()` `search-url-helpers.php:294`). Drag path not scripted.

### 12. Cap notice appears only when markers are held back
- **Action**: open `/listing-category/{cat}/` logged out (grid total below the cap).
- **Expect**: `.listora-map__notice` has `hidden === true` and empty `textContent`: no "Showing N of N" text anywhere in the DOM.
- **Action**: Settings > Maps > Max markers = 50 (minimum), open `$MAP_URL`.
- **Expect**: notice visible with exactly "Showing 50 of G on the map, zoom in to see more" (G = mappable, not grid total).
- **Action**: Max markers 50, open `$MAP_URL?listora_type={type}` (fewer than 50 mappable).
- **Expect**: notice `hidden` and empty.
- **Action**: Max markers 50 with a bounded URL covering > 50 listings.
- **Expect**: marker count <= 50 and the notice shown. Restore Max markers (default 500).

### 13. 390px
- **Action**: viewport 390x844 with Max markers 50.
- **Expect**: the notice is whole and inside the map (`r.left >= m.left`, `r.right <= m.right`, `n.scrollWidth <= n.clientWidth`, wraps to 2 lines, last word inside `r`); popup close button >= 40x40; "Search this area" >= 40px; no horizontal scroll (`document.documentElement.scrollWidth <= 390`); map and grid stack. Negative control: at 1440 the notice is one line. **fails_on**: 46ba223e (parent of 66994034, the wrap fix). Not verified live.

### 14. Large-site step
- **Expect**: `SELECT COUNT(*) FROM {prefix}listora_search_index` vs `status='publish'`: grid and map totals come from the published rows, never the orphan index rows (the 1.4.1 stale-row bug); page 2 exists ("Showing 21-40 of T") and pagination links carry the same `listora_*` args.

## Role ladder
| Actor | Expect |
|---|---|
| {guest}, {other}, {owner}, {editor}, {admin} | identical markers, popup and counts (public surface); spot-check one logged-in member |
| {admin} | only role that can change Max markers (control) |

## Pass criteria
1. HTTP 200 and no new fatal; markers have image data; popups degrade without an image.
2. Map markers are a subset of the grid set for every URL; unfiltered marker count equals G.
3. Bounds constrain both grid and map; partial bounds ignored.
4. Cap notice only when markers are held back, whole at 390.

## Fail diagnostics
| Symptom | Look at |
|---|---|
| HTTP 500 on the map page | `blocks/listing-map/render.php:122` |
| Popups have no image / 404 | `src/blocks/listing-map/view.js:229` `imageHtml`; `render.php` marker loop must resolve `get_the_post_thumbnail_url( $id, 'medium' )` |
| Tiles missing | Settings > Maps tile URL / provider (`includes/class-template-helpers.php:1634`) |
| Markers ignore keyword | `render.php:75` args no longer from `wb_listora_search_args_from_url()` |
| Fewer markers than mappable | `render.php:80` `has_geo` |
| Pin for a listing the grid hides | `Geo_Query::markers_for()` fed IDs not from the engine (`:111`) |
| Notice shows "Showing N of N" / clipped | `src/blocks/listing-map/view.js:330` `updateMapNotice`, `templates/blocks/listing-map/map.php`; bounds `includes/admin/class-settings-page.php:138` (50-5000) |
| Bounds ignored | `store.js:837-885`, `search-url-helpers.php:294`, `blocks/listing-grid/render.php` |

## Not verified
390px for steps 1-12, the drag + click path of step 11, and `debug.log` diff in the original run. Test-data trap: if every listing had a geo row, step 7 passes whether or not `has_geo` is honoured; a single listing type makes step 8 vacuous.

## Cleanup
Restore Max markers (default 500) and any changed map settings.
