---
journey: near-me-radius
plugin: wb-listora
priority: high
roles: [anonymous, administrator]
covers: [near-me-search, near-me-radius-setting, locations-panel-help]
prerequisites:
  - "Demo content with geocoded listings and a Directory page mapped"
estimated_runtime_minutes: 10
---

# Near me searches around the visitor, within a radius they can change

Cards 10351203714 (owner decision 2026-10-11: owner default + visitor choice) and 10355019529.

## Steps

### 1. Near me filters by distance
- **Action**: on the Directory at 1440, stub `navigator.geolocation` to a point inside the demo area, click the Near me button in the search bar.
- **Expect**: URL carries `listora_lat`, `listora_lng` (3 decimals) and `listora_radius=<owner default>`; the result count drops below the unfiltered total; sort is Nearest; the "Near me, within" picker is visible with 1/5/10/25/50 km (or mi) and the default selected; Filters badge counts 1.

### 2. Visitor widens the radius
- **Action**: choose 50 in the picker.
- **Expect**: page reloads with `listora_radius=50`, more results, and the picker still reads 50.

### 3. Clear all
- **Expect**: Clear all removes lat/lng/radius and the Nearest sort; picker hidden.

### 4. Owner default
- **Action**: Settings > General > Near me radius = 25, save.
- **Expect**: a Near me URL without `listora_radius` selects 25. Restore 5.
- **On fail**: `includes/search/search-url-helpers.php` (`wb_listora_search_geo_from_url`), `src/interactivity/store.js` (`searchImmediate`, `setSearchRadius`).

### 5. Locations panel help (admin)
- **Action**: edit any listing in the block editor, open the Locations panel.
- **Expect**: a help line says Locations drive Browse by Location and filters, and the shown address comes from the Address field.

### 6. 390px
- **Expect**: picker wraps under the search, 40px tall, no horizontal scroll.

## Pass criteria

1. Near me narrows results to the radius, and the radius is changeable by the visitor and defaulted by the owner.
