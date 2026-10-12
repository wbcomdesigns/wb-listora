---
journey: map-provider-single-engine-and-defaults
plugin: wb-listora
priority: high
roles: [guest, owner, admin]
covers: [map-provider-setting, submission-map-picker, detail-display-map, map-default-coords, map-picker-registry, listing-map, google-maps, leaflet, card-9852373335, card-10328160694]
prerequisites:
  - "Default install: map_provider osm, empty google_maps_key"
  - "A published listing {listing} with coordinates; Add Listing page and a member {owner}"
  - "Steps 6-8: Pro active with the google_maps feature ON (a dummy key is enough; Google shows its own key error)"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/map-provider-honored.md
  - ora:regression/map-google-single-engine.md
extends: pro:customer/12-google-maps-render.md
fails_on: unrecorded
site_profile: required
---

# Every map surface uses the one configured provider and starting coordinates

## Why
The directory map, single-listing map and Add Listing picker all resolve the same `map_provider` and tile source. With Google live only Google may draw the map (no Leaflet beside it), and a stored key with OpenStreetMap selected must not load Google.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `map_default_lat/lng/zoom` live in Settings > Maps "Where maps start" (`#map_default_lat`, `#map_default_lng`, `#map_default_zoom`, `includes/admin/class-map-settings-tab.php:150-164`).
- Note the original provider and key (`wp option get wb_listora_settings --format=json`); restore in Cleanup.

## Steps - provider = osm (default)

### 1. Add Listing picker
- **Action**: log in as {owner}, `$SITE_URL/add-listing/`, choose type {type}, Continue, fill Title, Category, Description, Continue to Details.
- **Expect**: `.listora-submission__map-picker` has `data-provider="osm"` and `data-default-lat/lng/zoom` equal to the saved settings (not a hard-coded city), and is a `.leaflet-container`; `typeof window.wbListoraMapPickers === 'object'`. Before the type is chosen no picker exists in the DOM.
- **Fail**: `includes/submission-field-renderer.php:379-390`, `src/utils/map-picker.js`, `src/blocks/listing-submission/view.js:83` / `:2039`.

### 2. Single-listing detail map
- **Action**: open `/listing/{listing}/` (no tab click: the map is the sidebar "Location" card).
- **Expect**: `#listora-detail-map.listora-detail__map-embed--sidebar` has `data-provider="osm"`, `data-zoom`, `data-lat`, is a `.leaflet-container` with tiles, plus a "Get directions" link; tabs are Overview, the type's field-group tabs, Services, Reviews (no Map tab); `window.wbListoraDetailMaps` is undefined while osm. Leaflet is enqueued only for osm.
- **Fail**: `templates/blocks/listing-detail/sidebar.php:98-125`, `blocks/listing-detail/render.php:196-211`, `src/interactivity/store.js:193` `initDetailMap`.

### 3. Directory map
- **Action**: open the Directory.
- **Expect**: tiles, markers at the listing coordinates, `mapConfig.provider === "osm"` (`wb_listora_map_config` filter, `blocks/listing-map/render.php:199`).

### 4. Coordinate consistency
- **Expect**: the detail map `data-lat/lng`, `_listora_address` lat/lng and the `{prefix}listora_geo` row agree.

### 5. Settings > Maps preview
- **Action**: as {admin} open Settings > Maps.
- **Expect**: the preview picker `.listora-settings-map__picker` renders at the saved start coordinates.

## Steps - provider = google (Pro + key)

### 6. Google live: only Google draws the map
- **Action**: Settings > Maps: Provider = Google (radio disabled until Pro + key), key set, save. Open the Directory logged out.
- **Expect**: `typeof L === 'undefined'`; `.listora-map` class exactly `listora-map` (no `leaflet-container`), zero `.leaflet-pane`, `dataset.googleMapInit === "1"` (Pro `src/maps/google-maps-init.js:61-62`); `mapConfig.provider === "google"`. Free loads Leaflet only for empty/`osm` provider (`blocks/listing-map/render.php:204`; `onMapInit` returns for others).

### 7. Detail map and picker use the same engine
- **Action**: open `/listing/{listing}/` and Add Listing Details.
- **Expect**: `#listora-detail-map` has `data-provider="google"`, no `leaflet-container`, `window.wbListoraDetailMaps` has the key `google`; the submission page registers `window.wbListoraMapPickers.google`. With no key/engine Free falls back to OSM, never blank.
- **Fail**: Pro `includes/features/class-google-maps.php:51-60`, `src/maps/google-submission-picker.js`, `src/maps/google-detail-map.js`.

### 8. OSM control
- **Action**: provider back to OpenStreetMap (key left stored), reload.
- **Expect**: `typeof L === 'object'`, `.leaflet-container` with tiles, no `data-google-map-init`, `data-provider="osm"`, `wbListoraDetailMaps` undefined or without a `google` key, and no Google geocoder script on Add Listing (see `customer/submission-map-picker.md` step 9).

## Role ladder
| Actor | Expect |
|---|---|
| {guest} | steps 2, 3, 6-8 (Add Listing returns 200 with `.listora-submission__login-prompt`, no wizard) |
| {owner} (member) | all surfaces including steps 1, 7 |
| {admin} (control) | same plus Settings > Maps (`manage_listora_settings`) and step 5 |

## Pass criteria
1. Picker, detail and directory maps agree on provider and start coordinates.
2. Google live: no Leaflet anywhere; OSM live: no Google registry.
3. Settings restored.

## Fail diagnostics
| Symptom | File |
|---|---|
| Leaflet classes on the Google map | `blocks/listing-map/render.php:204` enqueues Leaflet without the provider check, or `src/blocks/listing-map/view.js` `onMapInit` lost its guard |
| Blank map on OSM | guard excludes `osm`/empty provider |
| Picker has hard-coded start | `includes/submission-field-renderer.php:379-390` |

## Not verified
Picker marker drag/click updating lat/lng fields (Leaflet drag not scripted; Google half needs a real key). Step 5 added in consolidation.

## Cleanup
Restore provider, key, and start coordinates to the noted values.
