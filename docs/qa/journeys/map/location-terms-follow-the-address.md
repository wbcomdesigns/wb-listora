---
journey: location-terms-follow-the-address
group: map
actor: system
mode: mixed
plugin: wb-listora
priority: high
roles: [admin, owner, other, guest]
covers: [meta-handler-sync-location-terms, term-helper-set-location-terms, demo-seed-location-terms, google-places-location-terms, csv-import-location-terms, listing-submission, location-taxonomy, wp-admin-edit, locations-panel-help, bc-10355037108, card-9909685628, card-10331867610, block-editor-write, boundary-address]
prerequisites:
  - "WP-CLI (wp eval) for the DB checks"
  - "Member session {owner} via ?autologin"
  - "Pro with a Google key only for step 8; Pro CSV importer for step 9"
estimated_runtime_minutes: 14
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/location-terms-from-address.md
  - ora:regression/submission-sets-location-terms.md
  - ora:regression/near-me-radius.md
  - ora:admin/listing-location-terms-follow-address.md
requires: [wb-listora-pro]
fails_on: unrecorded
site_profile: required
---

# Country > State > City location terms follow the address on every write surface

## Why
Frontend submission once left wp-admin's Locations panel empty and the location filter missed member listings. Terms are now synced at the SOURCE: `Meta_Handler::sync_location_terms()` (`includes/core/class-meta-handler.php:55-78`, hooked at `:50-51`) runs on `added_post_meta`/`updated_post_meta` for `_listora_address` and calls `wb_listora_set_location_terms()` (`includes/import-export/import-helpers.php:64`) -> `Term_Helper::set_location_terms()` (`class-term-helper.php:198`), so demo seeder, Google Places, CSV/JSON importers, frontend form, wp-admin box, block editor and REST all get terms. It fires only when the stored address changes, so a hand-picked term survives unrelated saves.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Cleanup of probes: every probe listing/term uses a `QA-` prefix.
- A one-off `Location_Repair` (1.9.0) merges duplicate country roots; unrelated damaged addresses are `map/repair-locations-command.md`.

## Steps

### 1. Every published listing that has an address has location terms
- **Action** (`wp eval`, one statement): per listing type, count published listings and those with a `listora_listing_location` relationship:
```sql
SELECT typ.slug, COUNT(DISTINCT p.ID) total, COUNT(DISTINCT loc.object_id) with_location
FROM ${PREFIX}posts p
JOIN ${PREFIX}term_relationships trt ON trt.object_id=p.ID
JOIN ${PREFIX}term_taxonomy ttt ON ttt.term_taxonomy_id=trt.term_taxonomy_id AND ttt.taxonomy='listora_listing_type'
JOIN ${PREFIX}terms typ ON typ.term_id=ttt.term_id
LEFT JOIN (SELECT tr.object_id FROM ${PREFIX}term_relationships tr JOIN ${PREFIX}term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='listora_listing_location') loc ON loc.object_id=p.ID
WHERE p.post_type='listora_listing' AND p.post_status='publish' GROUP BY typ.slug;
```
- **Expect**: `with_location == total` for every type that has addresses. Gaps are listings with no address meta; list them with a `NOT EXISTS (_listora_address)` query before calling it a defect.

### 2. A meta write alone creates the hierarchy (the regression guard)
- **Action**: `$id = wp_insert_post( array( 'post_type' => 'listora_listing', 'post_status' => 'draft', 'post_title' => 'QA-loc' ) ); update_post_meta( $id, '_listora_address', array( 'address' => '1 Test St', 'city' => 'QA City', 'state' => 'QA State', 'country' => 'QA Land' ) );`
- **Expect**: `wp_get_object_terms( $id, 'listora_listing_location' )` returns three terms; "QA Land" parent 0, "QA State" child of the country, "QA City" child of the state. Delete the post and the three terms afterwards.
- **Fail**: `class-meta-handler.php:55` hooks removed, or `import-helpers.php:64` not loaded.

### 3. Frontend submission fills the taxonomy
- **Action**: as {owner}, Add Listing ({type}), Details step: type in `input[name="meta_address[address]"]` and pick the suggestion (or fill city/state/country), submit.
- **Expect**: `wp post term list <id> listora_listing_location --fields=name` lists all three, hierarchical Country > State > City.

### 4. wp-admin shows it and terms agree with address and map
- **Action**: as {admin} open the listing's edit screen and any demo listing page, change the address in the Listing Fields box to a new `{loc}` city/state/country and Update.
- **Expect**: the Locations panel follows the new hierarchy (`wp post term list $ID listora_listing_location --field=name` equals it); Locations panel ticked for the three terms; Address in the Listing Fields box filled; the terms match the city/state/country in `_listora_address` and the marker's place.

### 5. Edit updates it
- **Action**: {owner} edits the address to another city (Dashboard > Edit).
- **Expect**: the terms follow (e.g. `Dallas | Texas | United States`); the old city term is no longer attached.

### 6. Address without a country
- **Action**: save an address with no `country`.
- **Expect**: no terms change, no error, no notice in `debug.log`.

### 7. A hand-picked term survives an unchanged save
- **Action**: as {admin} tick a Location term by hand, Update without touching the address.
- **Expect**: the term stays (the hook fires only on an address change).

### 8. Google Places import (Pro, key required)
- **Action**: import one place.
- **Expect**: the listing has location terms from the parsed address (`wb-listora-pro/includes/features/class-google-places.php` `parse_address()` `:429`; terms come from the meta write).

### 9. CSV / visual import (Pro)
- **Action**: run a visual CSV import mapping geo_city / geo_state / geo_country.
- **Expect**: imported listings carry matching location terms (`wb-listora-pro/includes/importexport/class-visual-importer.php:522` calls `wb_listora_set_location_terms`).

### 10. The visitor consequence
- **Action**: logged out, open the term page of a city (`/listing-location/<country>/<state>/<city>/` from the location filter or Browse by location), and `GET /listora/v1/search?location=$CITY_SLUG`.
- **Expect**: the Directory filtered to that location with the term title as heading and a breadcrumb, count > 0; the newly published listing is found by its city. **Control**: a term with no listings is not linked from Browse by location.

### 11. Locations panel help (admin)
- **Action**: edit any listing in the block editor, open the Locations panel (taxonomy `listora_listing_location`).
- **Expect**: a help line "Controls where this listing appears in Browse by Location and location filters. The address shown on the listing comes from its Address field." above the term list (`assets/js/admin/location-panel-help.js`).

### 12. Existing Location terms are searchable in both wp-admin location UIs (card 10355037108)
- **Action**: as {admin} open `post-new.php?post_type=listora_listing`; fixture Location term `QA-Springfield` plus 30+ other terms so the list is paged. In the Locations meta box (core checklist) type `Springfield` in "Search Locations"; in the editor sidebar Locations panel type `Springfield`.
- **Expect**: both list `QA-Springfield` as selectable; selecting it and Update saves it; the panel's `GET /wp/v2/listora_listing_location?search=Springfield` returns 200 with the term. **Read-back**: `wp_get_object_terms( ID, 'listora_listing_location', ['fields'=>'names'] )` = `["QA-Springfield"]`; negative control: search `zzzzqq` shows no match in either. **fails_on**: unknown (no fix commit found). Not verified live.

### 13. Boundary address input
- **Action**: save an empty address, whitespace only, markup, a very long string, and an address whose country/city already exists as a term.
- **Expect**: empty address leaves the terms untouched; markup is stored escaped; an existing country/city term is reused (no duplicate term).
- **Read-back**: `wp term list listora_listing_location --name=<existing> --format=count` stays 1. Not verified live.

## Role ladder
| Actor | Expect |
|---|---|
| {owner} | steps 3, 5, 6 (can edit own listing's address) |
| {other} | cannot edit the listing's address (403) |
| {guest} | no form; steps 10 only |
| {admin} | steps 1, 2, 4, 7, 11, 12 (control; owner flow is the contract) |

## Pass criteria
1. A `_listora_address` change from any surface produces the Country > State > City hierarchy.
2. An address without a country or an unchanged address does not alter terms.
3. The visitor can filter by the resulting location term.

## Fail diagnostics
| Symptom | File |
|---|---|
| Terms empty after submit/import | `includes/core/class-meta-handler.php:50-51, 66-77` hook registration |
| Terms not following an edit | same hook (only reacts to `_listora_address`); see `WB_LISTORA_META_PREFIX` |
| City/state/country not split | `includes/import-export/class-term-helper.php:198` `set_location_terms()` |
| Facet misses the listing | `includes/search/class-search-indexer.php` |
| Panel help missing | `assets/js/admin/location-panel-help.js` |

## Not verified
Steps 8, 9 need a Google key / a CSV (source read only). Frontend submission (3), wp-admin panel (4, 7), 10-12 and the role ladder were not run; steps 5 and 6 and the hierarchy were verified at the meta layer with `update_post_meta` in wp-cli. A first attempt through `POST /listora/v1/submit` with a `meta_address` array hung the wp-cli process (geocoding or duplicate check); not investigated. Step 10 URL shape depends on the `location_slug` setting.

## Cleanup
Delete the QA listings and terms they created (`wp term delete listora_listing_location <id>`).
