---
journey: map-notice-only-when-capped
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [map-marker-cap-notice]
prerequisites:
  - "Demo content with more than 50 geocoded listings"
estimated_runtime_minutes: 5
---

# The map's "zoom in to see more" appears only when markers are held back

Card 10393313839.

## Steps

### 1. Nothing held back
- **Action**: open a category archive with fewer listings than Max markers (e.g. /listing-category/casual/).
- **Expect**: `.listora-map__notice` is hidden AND empty (no "Showing 8 of 8 ... zoom in" text anywhere in the DOM).

### 2. Cap bites
- **Action**: set Settings > Maps > Max markers to 50; open the Directory (119 listings).
- **Expect**: notice visible: "Showing 50 of 119 on the map, zoom in to see more". Restore 500.
- **On fail**: `templates/blocks/listing-map/map.php`, `src/blocks/listing-map/view.js` (`updateMapNotice`).

## Pass criteria

1. The hint shows only when some results are not drawn.
