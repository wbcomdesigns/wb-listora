---
journey: map-provider-honored
plugin: wb-listora
priority: high
roles: [administrator, subscriber]
covers: [map-provider-setting, submission-map-picker, detail-display-map, map-default-coords, map-picker-registry]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing with coordinates (array-format _listora_address with lat/lng) exists (LISTING_ID + slug)"
  - "Add Listing page reachable (submission block)"
estimated_runtime_minutes: 6
covers_card: 9852373335
---

# Map surfaces honour the configured provider + default coordinates

Regression sentinel for the map-provider work. All three map surfaces (Add Listing picker, single-listing detail map, directory/search map) must resolve the SAME `map_provider` setting, default coordinates must flow through, and the JS extension-point registries must exist so Pro can swap in Google.

## Setup

- Site: `$SITE_URL`. Default install: `map_provider = osm`, empty `google_maps_key`.
- `map_default_lat/lng/zoom` configured in Settings → Maps.

## Steps — provider = osm (default)

### 1. Add Listing picker (submission)
- **Action**: autologin; open Add Listing → advance to the step containing `.listora-submission__map-picker`.
- **Expect**: element carries `data-provider="osm"` and `data-default-lat/lng/zoom` matching the saved settings (NOT a hardcoded New York unless that IS the configured default). Leaflet initializes (OSM tiles + draggable marker). `window.wbListoraMapPickers` registry object exists.
- **On fail**: `includes/submission-field-renderer.php`, `src/blocks/listing-submission/view.js` `initMapPickers`.

### 2. Single-listing detail map
- **Action**: open `$SITE_URL/listing/<slug>` → click the "Map" tab.
- **Expect**: `#listora-detail-map` carries `data-provider="osm"` + `data-zoom`; Leaflet initializes (OSM tiles + marker); zero console errors. `window.wbListoraDetailMaps` registry object exists. Leaflet is enqueued only for the osm provider.
- **On fail**: `templates/blocks/listing-detail/tabs.php`, `blocks/listing-detail/render.php` (conditional Leaflet enqueue), `src/interactivity/store.js` `initDetailMap`.

### 3. Directory / search map
- **Action**: open the page with the listing-map block.
- **Expect**: OSM tiles render; markers at the listings' geo coords; `wb_listora_map_config` filter resolves `provider`.
- **On fail**: `blocks/listing-map/render.php` + `src/blocks/listing-map/view.js`.

### 4. Coordinate consistency
- **Expect**: for LISTING_ID, the detail-map marker, the `_listora_address` lat/lng, and the `{prefix}geo` row all agree on the same coordinates.

## Steps — provider = google (needs a real key; combo + Pro active)

### 5. Google engine registers + renders
- **Action**: set `map_provider = google` + a valid Google Maps key (Maps JS + Places + Geocoding APIs); reload picker + detail page.
- **Expect**: `window.wbListoraMapPickers.google` and `window.wbListoraDetailMaps.google` are registered (Pro `class-google-maps.php` enqueues the engines on the submission + listing-detail blocks); the picker + detail map render a Google map (not Leaflet); marker drag/map-click update the lat/lng fields (picker). With no key/engine, Free falls back to OSM (never blank).
- **On fail**: Pro `includes/features/class-google-maps.php`, `src/maps/google-submission-picker.js`, `src/maps/google-detail-map.js`.
