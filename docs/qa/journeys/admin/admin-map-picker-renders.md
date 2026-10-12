---
journey: admin-map-picker-renders
plugin: wb-listora
priority: normal
roles: [admin, editor, owner, guest]
covers: [map_location, listing-fields-metabox, initMapPickers, leaflet-admin-enqueue, BC-10198832114]
prerequisites:
  - "Site reachable at $SITE_URL; wb-listora active"
  - "A listing {listing} whose type includes a `map_location` field"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
supersedes: []
site_profile: required
---

# The map picker works in wp-admin and is the same picker the Add Listing wizard uses

## Why
Editors got an empty box where the map should be because wp-admin never loaded Leaflet or the picker script. Saving always worked (hidden lat/lng inputs, `Field::sanitize_map_location()`), so it read as a UX gap, not data loss. The 0x0 size in the original report was the metabox being `display:none` when measured.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). The metabox reuses the frontend renderer, so a `map_location` field prints `.listora-submission__map-picker`. The block editor already enqueues `blocks/listing-submission/style.css`.
- Roles: `{admin}`, `{editor}` (`edit_others_listora_listings`), `{owner}` member, `{guest}`.

## Steps

### 1. The assets are there, in the right order
- **Action**: as `{admin}` open `$SITE_URL/wp-admin/post.php?post={listing}&action=edit`.
- **Expect** (page source): `assets/vendor/leaflet.css?ver=<v>` in the head; then `assets/vendor/leaflet.js?ver=<v>` and AFTER it `build/admin/listing-map-picker.js` (its dependency list includes `leaflet`; `includes/admin/class-listing-fields-metabox.php:451-480`).

### 2. It is a map, not a div
- **Action**: scroll the metabox into view first (the block editor mounts metaboxes after first paint), then in the page run:
  ```js
  typeof window.L !== 'undefined'
  document.querySelectorAll( '.leaflet-container' ).length > 0
  document.querySelector( '.listora-submission__map-picker' ).children.length > 0
  ```
- **Expect**: all `true`. An empty `.listora-submission__map-picker` with zero children is the original bug. The initialiser skips pickers marked `data-provider-map-init` (a Pro provider owns that map).

### 3. The marker drives the fields
- **Action**: drag the marker (or click the map).
- **Expect**: hidden `[name$="[lat]"]` / `[name$="[lng]"]` update and the address field reverse-geocodes.
- **Action**: type an address, press Enter or Find (typing alone searches nothing; see `address-search-picker`), pick a match.
- **Expect**: the marker moves. Save, reload: the marker is where it was left.
- **Read-back**: `wp post meta get {listing} _listora_address` has the new lat/lng; boundary: drag to lat 90 / lng 180 and the poles saves valid numbers, clearing the field saves empty without error.

### 4. There is exactly one picker implementation
- **Action**: `grep -rn "L.map(" src/ | grep -v "utils/map-picker.js"` in the Free plugin.
- **Expect**: exactly two hits, both display maps: `src/blocks/listing-map/view.js:72` and `src/interactivity/store.js:232` (`initDetailMap`). The picker (`L.map` at `src/utils/map-picker.js:587`) is the only editable map; `grep -rln initMapPickers src` lists only `src/blocks/listing-submission/view.js` and `src/admin/listing-map-picker.js`. A third `L.map(` is the regression.

### 5. Frontend unchanged
- **Action**: Add Listing > Location step.
- **Expect**: the same OSM picker with the same behaviour (extraction moved the code; it must not change it).

### 6. Role ladder
- `{admin}` and `{editor}`: same metabox and map. `{owner}`: edits the location from the frontend wizard (step 5), not wp-admin. Subscriber / `{guest}`: `post.php` not reachable (redirect/403).

### 7. 390px
- **Expect**: picker stays inside the metabox; no horizontal page scroll; marker draggable by touch.

## Role ladder
See step 6.

## Pass criteria
1. Leaflet and the picker script load in wp-admin in order, and a map mounts.
2. Marker and search drive lat/lng/address and persist.
3. One picker implementation; frontend unchanged.

## Fail diagnostics
| Symptom | File |
|---|---|
| Empty picker div | enqueue missing: `includes/admin/class-listing-fields-metabox.php:451-480` |
| Script before Leaflet | `array_merge( $asset['dependencies'], array( 'leaflet' ) )` dropped |
| Second implementation | any `L.map(` outside `src/utils/map-picker.js` that is a picker |

## Not verified
The map actually mounting, marker drag, step 3 read-back and 390px were not run in a browser; asset order was verified from page source as administrator.

## Cleanup
Only step 3 writes: restore the listing's original coordinates.
