---
journey: admin-map-picker-renders
plugin: wb-listora
roles: [administrator, editor]
priority: normal
covers: [map_location, listing-fields-metabox, initMapPickers, leaflet-admin-enqueue, BC-10198832114]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora active"
  - "A listing whose type includes a `map_location` field (Business listing 551 on the reference site)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
changed: true
---

# The map picker works in wp-admin, using the same picker as the wizard

The Listing Fields metabox reuses the frontend submission field renderer, so a `map_location` field prints the same `.listora-submission__map-picker` div the Add Listing wizard uses. On the frontend that div becomes a Leaflet map because the block enqueues Leaflet and the wizard's view module calls `initMapPickers()`. In wp-admin neither used to load, so editors got an empty box.

Saving always worked (hidden lat/lng inputs and `Field::sanitize_map_location()`), which is why this read as a UX gap rather than data loss. The 0x0 box seen in the original report was the metabox container being `display: none` when measured, not missing CSS (the block editor already enqueues `blocks/listing-submission/style.css`).

## Steps

### 1. The assets are there, in the right order
- **Action**: as administrator open `$SITE_URL/wp-admin/post.php?post=<id>&action=edit` for a listing whose type has a `map_location` field.
- **Expect** (page source, verified live on listing 551): `assets/vendor/leaflet.css?ver=1.9.4` in the head; then `assets/vendor/leaflet.js?ver=1.9.4` and AFTER it `build/admin/listing-map-picker.js` (its dependency list includes `leaflet`; enqueue code at `includes/admin/class-listing-fields-metabox.php:451-480`).

### 2. It is a map, not a div
Presence of the script proves nothing on its own. In the page:

```js
typeof window.L !== 'undefined'                                       // Leaflet booted
document.querySelectorAll( '.leaflet-container' ).length > 0          // a map mounted
document.querySelector( '.listora-submission__map-picker' ).children.length > 0
```

An empty `.listora-submission__map-picker` with zero children is the original bug.
- **Note**: scroll the metabox into view first. The block editor mounts metaboxes after the initial paint, so an early measurement reports 0x0 on a picker that is fine. The initialiser skips pickers already marked `data-provider-map-init` (a Pro provider can own the map).

### 3. The marker drives the fields
- **Action**: drag the marker (or click the map).
- **Expect**: the hidden `[name$="[lat]"]` / `[name$="[lng]"]` inputs update and the address field reverse-geocodes.
- **Action**: type an address and press Enter or click Find (see `address-search-picker.md`; typing alone searches nothing) and pick a match.
- **Expect**: the marker moves. Save, reload: the marker is where it was left.

### 4. There is exactly one picker implementation
```bash
grep -rn "L.map(" wb-listora/src/ | grep -v "utils/map-picker.js"
```
- **Expect** exactly two hits and no more, both display maps and not pickers: `src/blocks/listing-map/view.js:72` (the Map block) and `src/interactivity/store.js:232` (`initDetailMap`, the read-only listing-detail map). The picker (`L.map` at `src/utils/map-picker.js:587`) is the only editable map; `initMapPickers()` is imported by `src/blocks/listing-submission/view.js` and `src/admin/listing-map-picker.js` only (`grep -rln initMapPickers src`). A third `L.map(` is the regression this step exists to catch.

### 5. Frontend unchanged
- **Action**: Add Listing > Location step.
- **Expect**: the same OSM picker with the same behaviour (extraction moved the code; it must not change it).

### 6. Role ladder
- **Administrator and editor** (qa_editor, `edit_others_listora_listings`): same metabox and map.
- **Listing owner (member)**: edits the location from the frontend wizard (step 5), not wp-admin.
- **Subscriber / logged out**: `post.php` is not reachable.

### 7. 390px
- **Expect**: the picker stays inside the metabox; no horizontal page scroll; marker draggable by touch (Leaflet default).

## Cleanup
Only step 3 writes. Restore the listing's original coordinates if you saved.

## Fail diagnostics
| Symptom | File |
|---|---|
| Empty picker div | enqueue missing: `includes/admin/class-listing-fields-metabox.php:451-480` |
| Script before Leaflet | `array_merge( $asset['dependencies'], array( 'leaflet' ) )` dependency dropped |
| Second implementation | any `L.map(` outside `src/utils/map-picker.js` that is a picker |

Verified via: curl/page source as administrator on listing 551 (asset order and presence of `.listora-submission__map-picker`); source for the grep step. The map actually mounting (`.leaflet-container`), marker drag and 390px were not run in a browser in this pass.
