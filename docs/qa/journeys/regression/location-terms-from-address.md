---
journey: location-terms-from-address
plugin: wb-listora
priority: high
roles: [administrator]
covers: [demo-seed-location-terms, term-helper-set-location-terms, google-places-location-terms, csv-import-location-terms]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Ability to reset + re-import demo data, OR run an import"
estimated_runtime_minutes: 6
covers_card: 9909685628
---

# Country > State > City location terms are created from the address everywhere

Regression sentinel for the shared `Term_Helper::set_location_terms()` work. Every data-injection path (demo seeder, Google Places import, CSV/visual import) must build hierarchical `listora_listing_location` terms from the same address array that becomes the `_listora_address` meta + geo row — so every listing is reachable via the location filter regardless of type or source.

## Background (the bug this guards)

Before the fix, the demo seeder built terms only from a top-level `address` key that 4 of 9 packs had; the 5 meta-only packs (restaurant, hotel, real_estate, job, business) produced ZERO location terms. Both Pro importers had the same gap.

## Steps

### 1. Demo re-import — all packs get location terms
- **Action**: reset site → import demo data (all packs) → flush cache.
- **Verify DB**:
  ```sql
  SELECT typ.name, COUNT(DISTINCT p.ID) total,
         COUNT(DISTINCT loc.object_id) with_location
  FROM wp_posts p
  JOIN wp_term_relationships trt ON trt.object_id=p.ID
  JOIN wp_term_taxonomy ttt ON ttt.term_taxonomy_id=trt.term_taxonomy_id AND ttt.taxonomy='listora_listing_type'
  JOIN wp_terms typ ON typ.term_id=ttt.term_id
  LEFT JOIN (SELECT tr.object_id FROM wp_term_relationships tr
             JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='listora_listing_location') loc
    ON loc.object_id=p.ID
  WHERE p.post_type='listora_listing' AND p.post_status='publish'
  GROUP BY typ.name;
  ```
- **Expect**: `with_location == total` for EVERY type (restaurant, hotel, real_estate, job, business included). Pre-fix these 5 were 0.
- **On fail**: `demo/class-demo-seeder.php` (must call `Term_Helper::set_location_terms()` from `meta.address`).

### 2. Terms agree with address + map
- **Action**: open any demo listing's detail page + its admin edit screen.
- **Expect**: the Country>State>City location terms match the city/state/country in `_listora_address` and the map marker's location.

### 3. Google Places import creates terms
- **Action**: (Pro, with key) import a place via Google Places.
- **Expect**: the imported listing has `listora_listing_location` terms derived from the parsed address.
- **On fail**: Pro `includes/features/class-google-places.php` (must call `wb_listora_set_location_terms`).

### 4. CSV/visual import creates terms
- **Action**: (Pro) run a visual CSV import mapping geo_city/geo_state/geo_country.
- **Expect**: imported listings have location terms matching the mapped address.
- **On fail**: Pro `includes/importexport/class-visual-importer.php` `set_geo_data`.
