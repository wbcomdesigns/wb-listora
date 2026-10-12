---
journey: admin-location-column-no-city
plugin: wb-listora
priority: high
roles: [administrator, editor]
covers: [listing-columns, search-indexer-geo, address-meta-shape]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WP-CLI + DB access"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
changed: true
---

# The admin Location column renders region-level addresses

On `wp-admin/edit.php?post_type=listora_listing` the **Location** cell was blank for any listing whose reverse geocode produced no city: a map point in a sparsely populated region resolves to state/country only, and `Search_Indexer::update_geo_index()` stores `city` as `''`. Two faults: the cell gated on `! empty( $geo['city'] )`, and its fallback read `_listora_address` as a string while that meta is a composite array.

`Listing_Columns::render_column()` (`includes/admin/class-listing-columns.php:286-318`) now renders the two most specific non-empty parts of `city / state / country` from `{prefix}listora_geo`; with no geo row it reads the composite address (`city/state/country`, then the typed `address` line); with nothing it prints a `span.listora-listing-col__placeholder` "—".

## Setup

- Use a throwaway listing so no real data is changed: `ID=$(wp post create --post_type=listora_listing --post_title="QA-J1-loc" --post_status=publish --porcelain)` then insert a geo row: `wp eval 'global $wpdb; $wpdb->insert($wpdb->prefix."listora_geo",["listing_id"=>ID,"lat"=>60.5,"lng"=>-120.5,"city"=>"Yellowknife","state"=>"Northwest Territories","country"=>"Canada"]);'`
- Find it with `$SITE_URL/wp-admin/edit.php?post_type=listora_listing&s=QA-J1-loc`; read `tr#post-<ID> td.column-listora_location`.
- Geo table columns today: `listing_id, lat, lng, address, city, state, country, postal_code, geohash, timezone`.

## Steps (each result verified 2026-10-12 against a QA-J1- listing)

### 1. Fully resolved row (control: the fix must not over-reach)
- **Expect**: `Yellowknife, Northwest Territories` (two parts only; country is NOT appended).

### 2. Region-level row (the reported bug)
- **Action**: `UPDATE {prefix}listora_geo SET city='' WHERE listing_id=<ID>`; reload the list.
- **Expect**: `Northwest Territories, Canada`. Pre-fix the cell was empty.

### 3. Country-only row
- **Action**: also `state=''`.
- **Expect**: `Canada`.

### 4. No geo row, composite address meta only
- **Action**: `DELETE FROM {prefix}listora_geo WHERE listing_id=<ID>`; `update_post_meta(<ID>,'_listora_address',['address'=>'1 Main St','city'=>'Hay River','state'=>'NT','country'=>'Canada'])`.
- **Expect**: `Hay River, NT` (components, not the whole array).
- **Then**: meta with only `address` => the cell shows `1 Main St`.

### 5. Nothing at all
- **Action**: delete the address meta too.
- **Expect**: `<span class="listora-listing-col__placeholder">—</span>`, never an empty cell, matching the Rating column's empty treatment.

### 6. Role ladder
Admin screen, capability-gated. Control: administrator (above). Editor sees the same cell (`edit_others_listora_listings`); subscriber and logged-out cannot open `edit.php?post_type=listora_listing`.

### 7. Large table
The list holds ~2,800 rows: page 2 and a sort by Rating render the Location cells with the one cached geo query per page (`prime_column_caches()`), not one query per row.

### 8. Cleanup
`wp post delete <ID> --force` (removes the QA-J1- listing; its geo row is cleared with the index). For a real listing you edited instead, restore city/state/country and run `wp listora reindex`.

## Fail diagnostics
| Symptom | File |
|---|---|
| Region-level cell blank | city gate back in `includes/admin/class-listing-columns.php:291-296` |
| Composite fallback blank | `is_array()` branch lost, `:299-307` |
| Placeholder missing | `:315-317` |

Verified via: curl/DOM as administrator and wp eval mutations on a QA-J1- listing (all five states above), then the listing was deleted.
