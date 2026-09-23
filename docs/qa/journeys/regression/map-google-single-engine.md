---
journey: map-google-single-engine
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [listing-map, google-maps, leaflet]
prerequisites:
  - "Pro active with the google_maps feature ON"
  - "A page with the listing-map block (the Directory page)"
estimated_runtime_minutes: 5
covers_card: 10328160694
---

# With Google live, only Google draws the directory map

Regression sentinel for the listing-map double initialisation.

## Background

Free's listing-map block enqueued Leaflet unconditionally, and its `onMapInit` only stood down when `L` was undefined. With Google live, Pro's `google-maps-init.js` also built a map on the same `.listora-map` element, so two engines fought over one container. Free now loads Leaflet only when the resolved `mapConfig.provider` is empty or `osm`, and `onMapInit` returns for any other provider. The single-listing detail map already delegated by provider and was not affected.

## Steps

### 1. Google live
- **Action**: Settings → Maps: Provider = Google, save a key (a dummy key is enough for this check; the map then shows Google's key error, which is expected). Open the Directory page.
- **Expect**: `typeof L === 'undefined'`; `.listora-map` has no `leaflet-container` class and no `.leaflet-pane`; it carries `data-google-map-init="1"`; `mapConfig.provider` is `google`.

### 2. OSM control
- **Action**: set the provider back to OpenStreetMap. Reload.
- **Expect**: Leaflet loaded, tiles and markers drawn, no `data-google-map-init`.

### 3. Restore the owner's settings.

## Fail diagnostics
- Leaflet classes on the Google map → `blocks/listing-map/render.php` enqueues Leaflet before/without checking `$map_config['provider']`, or `src/blocks/listing-map/view.js` `onMapInit` lost its provider guard.
- Blank map on OSM → the provider guard is excluding `osm` or an empty provider.
