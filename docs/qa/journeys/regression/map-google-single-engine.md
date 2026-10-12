---
journey: map-google-single-engine
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [listing-map, google-maps, leaflet]
prerequisites:
  - "Pro active with the google_maps feature ON"
  - "The Directory page (/listings/) and any listing page"
estimated_runtime_minutes: 5
covers_card: 10328160694
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# With Google live, only Google draws the map

Free's listing-map block loads Leaflet only when the resolved `mapConfig.provider` is empty or `osm` (`blocks/listing-map/render.php:204`), and `onMapInit` returns for any other provider. Pro's `src/maps/google-maps-init.js:61-62` marks the element `data-google-map-init="1"`.

Verified via: Playwright with option flip by `wp eval` (provider `google`, dummy key `QA-J1-dummy`) and restore. Dummy key means Google shows its own key error; expected.

## Steps

### 1. Google live
- **Action**: Settings > Maps: Provider = Google (radio is disabled until Pro + key), key set, save. Open `/listings/` logged out.
- **Expect**: `typeof L === 'undefined'`; `.listora-map` has class exactly `listora-map` (no `leaflet-container`), zero `.leaflet-pane`, `dataset.googleMapInit === "1"`; block context `mapConfig.provider === "google"`.

### 2. Single listing map uses the same engine
- **Action**: open `/listing/the-golden-fork/`.
- **Expect**: `#listora-detail-map` has `data-provider="google"`, no `leaflet-container`, `window.wbListoraDetailMaps` has the key `google`. (The detail map is the "Location" card in the sidebar since 2.0.0; there is no "Map" tab.)

### 3. OSM control
- **Action**: provider back to OpenStreetMap, reload.
- **Expect**: `typeof L === 'object'`, `.leaflet-container` with tiles, no `data-google-map-init`, `#listora-detail-map` carries `data-provider="osm"` and `window.wbListoraDetailMaps` is undefined or has no `google` key (Pro registers it only for Google).

### 4. Restore the owner's settings
Provider and key back to the values noted before step 1 (demo: `osm`, empty key).

### 5. Role ladder
Public surface; same for logged-out and logged-in. Provider choice is admin-only (Settings > Maps requires `manage_listora_settings`).

## Fail diagnostics
- Leaflet classes on the Google map: `blocks/listing-map/render.php:204` enqueues Leaflet without the provider check, or `src/blocks/listing-map/view.js` `onMapInit` lost its provider guard.
- Blank map on OSM: the guard excludes `osm`/empty provider.
- Pro registration: `wb-listora-pro/includes/features/class-google-maps.php:51-60`.
