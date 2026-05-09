---
journey: map-block-no-fatal
plugin: wb-listora
priority: critical
roles: [anonymous]
covers: [listing-map-block-render, update-meta-cache, popup-image]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A page rendering the listora/listing-map block with at least 1 mapped listing"
  - "At least 1 mapped listing has a featured image"
estimated_runtime_minutes: 2
---

# Map block no-fatal regression sentinel

The listing-map block must render without fatal — pre-fix #9871222447 used `update_post_meta_cache()` (does not exist; correct function is `update_meta_cache('post', ids)`). Post-fix also restored map-popup featured image rendering (#9867372176, commit additions to `view.js` for `imageHtml` snippet).

## Setup

- Site: `$SITE_URL`
- Map page URL: `$MAP_URL` — typically homepage or `/listings/` if it embeds the listing-map block
- Baseline debug.log byte count.

## Steps

### 1. Navigate to the map page
- **Action**: `playwright_navigate $MAP_URL`
- **Expect**:
  - HTTP 200
  - DOM contains `.wp-block-listora-listing-map`
  - Map tiles render (Leaflet OR Google per provider)
  - Markers visible for mapped listings

### 2. Verify zero fatals
- **Action**: tail debug.log diff since baseline
- **Expect**: ZERO new entries containing `Call to undefined function`, `update_post_meta_cache`, OR `Fatal`
- **On fail**: regression of #9871222447. See `blocks/listing-map/render.php` — must call `update_meta_cache('post', $listing_ids)`, NOT `update_post_meta_cache(...)`.

### 3. Verify markers carry image data
- **Action**: `browser_evaluate "document.querySelector('.wp-block-listora-listing-map').dataset.markers"` (or read the wp-interactivity-state JSON)
- **Expect**: marker objects include an `image` field (URL or empty string), populated via the prefetched meta cache

### 4. Click a marker with featured image
- **Action**: click a marker that corresponds to a listing with thumbnail
- **Expect**:
  - Popup opens
  - Popup contains `<img class="listora-map__popup-image">` with proper `src`
  - Popup also has title + brief description + link to detail page
- **On fail**: regression of #9867372176. See `src/blocks/listing-map/view.js` — `imageHtml` snippet construction.

### 5. Click a marker without image (graceful omission)
- **Action**: click a marker for a listing without thumbnail
- **Expect**: popup opens, NO image element (graceful — not a broken-image icon)

### 6. Verify image inside popup is responsive + lazy
- **Action**: inspect the popup img element
- **Expect**: `loading="lazy"` attribute (or eager if popup is the only viewport content), proper aspect ratio CSS

## Pass criteria

1. Map block renders without fatal
2. `update_meta_cache` (correct fn) prefetches all listing meta in one call (no N+1)
3. Marker popups include featured image when available
4. Marker popups gracefully omit image when not available
5. Zero new debug.log entries during the walk

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| HTTP 500 on map page | `Call to undefined function update_post_meta_cache` | `blocks/listing-map/render.php` (commit fix) |
| Markers render but popups have no image | `imageHtml` snippet missing | `src/blocks/listing-map/view.js` |
| Popup image broken (404) | `image` field is post ID instead of URL | render.php should call `wp_get_attachment_image_url(get_post_thumbnail_id($id), 'medium')` |
| Map tiles missing | provider key wrong / network blocked | Maps tab provider settings |
