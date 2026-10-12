---
journey: location-terms-from-address
plugin: wb-listora
priority: high
roles: [administrator]
covers: [meta-handler-sync-location-terms, term-helper-set-location-terms, demo-seed-location-terms, google-places-location-terms, csv-import-location-terms]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WP-CLI (wp eval) for the DB checks"
  - "Pro with a Google key only for step 5; Pro CSV importer for step 6"
estimated_runtime_minutes: 7
covers_card: 9909685628
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
changed: true
---

# Country > State > City location terms follow the address everywhere

The terms are now synced at the SOURCE: `Meta_Handler::sync_location_terms()` (`includes/core/class-meta-handler.php:55-78`) runs on `added_post_meta` / `updated_post_meta` for `_listora_address` and calls `wb_listora_set_location_terms()` (`includes/import-export/import-helpers.php:64`) -> `Term_Helper::set_location_terms()` (`class-term-helper.php:198`). So the demo seeder, Google Places, CSV/JSON importers, the frontend form and the admin metabox all get terms without each calling the helper. It fires only when the stored address changes, so a location an admin picked by hand survives unrelated saves. A one-off `Location_Repair` (1.9.0) merges duplicate country roots.

Verified via: `wp eval` SQL on the live site and a temporary draft listing. Admin-only data journey (no member or visitor surface of its own), so no role ladder; the visitor-facing consequence is checked in step 4.

## Steps

### 1. Every published listing that has an address has location terms
- **Action** (`wp eval`, single statement per type):
```sql
SELECT typ.slug, COUNT(DISTINCT p.ID) total, COUNT(DISTINCT loc.object_id) with_location
FROM wp_posts p
JOIN wp_term_relationships trt ON trt.object_id=p.ID
JOIN wp_term_taxonomy ttt ON ttt.term_taxonomy_id=trt.term_taxonomy_id AND ttt.taxonomy='listora_listing_type'
JOIN wp_terms typ ON typ.term_id=ttt.term_id
LEFT JOIN (SELECT tr.object_id FROM wp_term_relationships tr JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='listora_listing_location') loc ON loc.object_id=p.ID
WHERE p.post_type='listora_listing' AND p.post_status='publish' GROUP BY typ.slug;
```
- **Expect**: `with_location == total` for every type that has addresses. Observed 2026-10-12: hotel 20/20, place 7/7, real-estate 16/16, education 6/6, healthcare 6/6, business 7/8, restaurant 17/19. The gaps are listings with no address meta (QA/scale fixtures); list them with a `NOT EXISTS (_listora_address)` query before calling it a defect.

### 2. A meta write alone creates the hierarchy (the regression guard)
- **Action**: `$id = wp_insert_post( array( 'post_type' => 'listora_listing', 'post_status' => 'draft', 'post_title' => 'QA-J1-loc' ) ); update_post_meta( $id, '_listora_address', array( 'address' => '1 Test St', 'city' => 'QA-J1 City', 'state' => 'QA-J1 State', 'country' => 'QA-J1 Land' ) );`
- **Expect**: `wp_get_object_terms( $id, 'listora_listing_location' )` returns three terms; "QA-J1 Land" has parent 0, "QA-J1 State" is a child of the country, "QA-J1 City" a child of the state. Delete the post and the three terms afterwards.
- **Fail**: `class-meta-handler.php:55` hooks removed, or `import-helpers.php:64` not loaded.

### 3. Terms agree with address and map
- **Action**: open any demo listing page and its admin edit screen.
- **Expect**: the location terms match the city/state/country in `_listora_address` and the marker's place.

### 4. The visitor consequence
- **Action**: logged out, open the term page of a city (e.g. `/listing-location/<country>/<state>/<city>/` from the location filter or "Browse by location").
- **Expect**: the Directory filtered to that location with the term title as heading and a breadcrumb (2.0.0 term pages), count > 0. Control: a term with no listings is not linked from Browse by location.

### 5. Google Places import (Pro, key required)
- **Action**: import one place.
- **Expect**: the listing has location terms from the parsed address (`wb-listora-pro/includes/features/class-google-places.php` `parse_address()` at `:429`; the terms come from the meta write).

### 6. CSV / visual import (Pro)
- **Action**: run a visual CSV import mapping geo_city / geo_state / geo_country.
- **Expect**: imported listings carry matching location terms (`wb-listora-pro/includes/importexport/class-visual-importer.php:522` calls `wb_listora_set_location_terms`).

## Not verified
Steps 5 and 6 need a Google key / a CSV and were read in source only. Step 4 URL shape depends on the site's `location_slug` setting.
