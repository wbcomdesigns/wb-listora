---
journey: map-block-no-fatal
plugin: wb-listora
priority: critical
roles: [anonymous]
covers: [listing-map-block-render, update-meta-cache, popup-image]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A page rendering the listora/listing-map block with mapped listings (demo: /listings/)"
  - "At least one mapped listing with a featured image and one without (demo: 78 with, 1 without)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Map block no-fatal regression sentinel

The map block once called `update_post_meta_cache()` (does not exist; the core function is `update_meta_cache( 'post', $ids )`, now at `blocks/listing-map/render.php:122`) and its popups had no featured image (`imageHtml`, `src/blocks/listing-map/view.js:229`).

Verified via: Playwright on `/listings/` at 1440 and 390 (popup measured), source read. `debug.log` diff not taken in this pass.

## Setup
- `$MAP_URL` = `http://wb-listora.local/listings/`. Note the byte size of `wp-content/debug.log` before the walk.

## Steps

### 1. Page and map render
- **Action**: open `$MAP_URL` logged out.
- **Expect**: HTTP 200; `.wp-block-listora-listing-map` present; `.listora-map.leaflet-container` with tiles and `.leaflet-marker-icon`s.

### 2. Zero fatals
- **Action**: diff `debug.log` since the baseline.
- **Expect**: no new `Call to undefined function`, `update_post_meta_cache` or `Fatal`.

### 3. Markers carry image data
- **Action**: read `JSON.parse( document.querySelector( '[data-wp-context*="mapConfig"]' ).getAttribute( 'data-wp-context' ) ).mapConfig.markers`.
- **Expect**: each marker has `image` (URL string or `""`) and `imageAlt`; 78 non-empty and 1 empty on demo data.

### 4. Click a marker with an image (positive control)
- **Action**: click a non-cluster `.leaflet-marker-icon:not(.marker-cluster)`.
- **Expect**: `.listora-map__popup` shows `img.listora-map__popup-image` with `loading="lazy"` and `alt` = the listing title, the title link, a `.listora-badge--type` with the type name (observed "Hotel"), a "View Details →" link (`.listora-map__popup-link`) and `button.listora-map__popup-close[aria-label="Close"]`.

### 5. Click a marker without an image (graceful omission)
- **Expect**: popup opens, no `.listora-map__popup-image` element (not a broken-image icon).

### 6. Phone
- **Action**: 390 viewport, open a popup.
- **Expect**: close button 40 x 40 px (observed); no horizontal page scroll.

### 7. Role ladder
Public surface, identical for anonymous, member, moderator and admin: spot-check one logged-in member that markers and popup are unchanged.

## Fail diagnostics
| Symptom | Look at |
|---|---|
| HTTP 500 on the map page | `blocks/listing-map/render.php:122` |
| Popups have no image | `src/blocks/listing-map/view.js:229` `imageHtml`; marker `image` built in `render.php` marker loop |
| Image 404 | `render.php` must resolve `get_the_post_thumbnail_url( $id, 'medium' )` |
| Tiles missing | Settings > Maps tile URL / provider (`includes/class-template-helpers.php:1634`) |
