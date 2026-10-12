---
journey: map-provider-single-engine-and-defaults
plugin: wb-listora
priority: high
roles: [guest, owner, admin]
covers: [map-provider-setting, submission-map-picker, detail-display-map, map-default-coords, map-picker-registry, listing-map, google-maps, leaflet, card-9852373335, card-10328160694]
prerequisites:
  - "Default install: map_provider osm, empty google_maps_key"
  - "A published listing {listing} with coordinates; Add Listing page and a member {owner}"
  - "Step 6 only references the Pro journey; Pro with a Google key is needed there, not here"
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

## Steps

#### Provider = osm (default)

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
- **Expect**: the detail map `data-lat/lng`, `_listora_address` lat/lng and the `${PREFIX}listora_geo` row agree.

### 5. Settings > Maps preview
- **Action**: as {admin} open Settings > Maps.
- **Expect**: the preview picker `.listora-settings-map__picker` renders at the saved start coordinates.

#### Provider = google (Pro + key)

### 6. Provider = google
- **Action**: none restated here. The Google half (only Google draws the map, the detail map and the Add Listing picker use the same engine, the OSM control after switching back) is owned by Pro: `pro:customer/12-google-maps-render.md` steps 4 and 7.
- **Expect**: this journey's OSM steps 1-5 are the control for it; the picker must register `window.wbListoraMapPickers.osm` only (no Google geocoder script, `customer/submission-map-picker.md` step 9).

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
