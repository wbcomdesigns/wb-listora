---
journey: map-provider-honored
plugin: wb-listora
priority: high
roles: [administrator, subscriber]
covers: [map-provider-setting, submission-map-picker, detail-display-map, map-default-coords, map-picker-registry]
prerequisites:
  - "Site reachable at $SITE_URL; default install (map_provider osm, empty google_maps_key)"
  - "A published listing with coordinates (demo: /listing/the-golden-fork/, lat 40.721)"
  - "Add Listing page (/add-listing/) and a member login (author1)"
estimated_runtime_minutes: 8
covers_card: 9852373335
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Map surfaces honour the configured provider and default coordinates

All three map surfaces resolve the same `map_provider` and tile source. `map_default_lat/lng/zoom` live in Settings > Maps ("Where maps start"; fields `#map_default_lat`, `#map_default_lng`, `#map_default_zoom`, class `includes/admin/class-map-settings-tab.php:150-164`).

Verified via: Playwright with author1 (wizard to the Details step) and logged out (listing page); provider flip to Google by option (see `map-google-single-engine`).

## Steps - provider = osm (default)

### 1. Add Listing picker
- **Action**: autologin author1, `/add-listing/`, choose a type (e.g. Restaurant), Continue, fill Title, Category, Description, Continue to the Details step.
- **Expect**: `.listora-submission__map-picker` on the Details step has `data-provider="osm"`, `data-default-lat="40.7128"`, `data-default-lng="-74.006"`, `data-default-zoom="12"` (the saved settings, not a hard-coded city) and is a `.leaflet-container`. `typeof window.wbListoraMapPickers === 'object'`. Before the type is chosen no picker exists in the DOM.
- **Fail**: `includes/submission-field-renderer.php:379-390`, `src/utils/map-picker.js`, `src/blocks/listing-submission/view.js:83` / `:2039`.

### 2. Single-listing detail map
- **Action**: open `/listing/the-golden-fork/` (no tab click: the map is the sidebar "Location" card).
- **Expect**: `#listora-detail-map.listora-detail__map-embed--sidebar` has `data-provider="osm"`, `data-zoom`, `data-lat="40.721"`, is a `.leaflet-container` with tiles, plus a "Get directions" link. The page's tabs are Overview, the type's field group tabs, Services, Reviews (no Map tab). `window.wbListoraDetailMaps` is undefined while osm. Leaflet is only enqueued for osm.
- **Fail**: `templates/blocks/listing-detail/sidebar.php:98-125`, `blocks/listing-detail/render.php:196-211`, `src/interactivity/store.js:193` `initDetailMap`.

### 3. Directory map
- **Action**: open `/listings/`.
- **Expect**: tiles, markers at the listing coords (`mapConfig.markers[].lat/lng`), `mapConfig.provider === "osm"` (`wb_listora_map_config` filter, `blocks/listing-map/render.php:199`).

### 4. Coordinate consistency
- **Expect**: for the listing, the detail map `data-lat/lng`, `_listora_address` lat/lng and the `wp_listora_geo` row agree.

## Steps - provider = google (Pro + key)

### 5. Google engine registers and renders
- **Action**: provider Google + key; reload the picker and detail page.
- **Expect**: `window.wbListoraDetailMaps.google` registered on the detail page (verified with a dummy key: the registry key `google`, `data-provider="google"`, no Leaflet); `window.wbListoraMapPickers.google` on the submission page. With no key/engine Free falls back to OSM, never blank.
- **Fail**: Pro `includes/features/class-google-maps.php`, `src/maps/google-submission-picker.js`, `src/maps/google-detail-map.js`.

### 6. Role ladder
Anonymous: surfaces 2 and 3 only (Add Listing returns 200 with `.listora-submission__login-prompt` "log in or create an account to submit a listing.", no wizard). Member: all three. Admin (control): same plus the Settings > Maps preview picker (`.listora-settings-map__picker`).

## Not verified
Picker marker drag/click updating lat/lng fields (needs a real Google key for the google half; Leaflet drag not scripted).
