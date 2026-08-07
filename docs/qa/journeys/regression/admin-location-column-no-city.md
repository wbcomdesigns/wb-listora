---
journey: admin-location-column-no-city
plugin: wb-listora
priority: high
roles: [admin]
covers: [listing-columns, search-indexer-geo, address-meta-shape]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least one published listing with a geocoded address"
  - "WP-CLI + DB access"
estimated_runtime_minutes: 3
---

# The admin Location column must render region-level addresses

Basecamp 10172069880. On `wp-admin/edit.php?post_type=listora_listing` the
**Location** cell was blank for any listing whose reverse geocode produced no
city — a map point in a sparsely populated region resolves to state/country
only, and `Search_Indexer::update_geo_index()` stores `city` as `''`.

Two independent faults:

1. `Listing_Columns::render_column()` gated the whole cell on
   `! empty( $geo['city'] )`, so state/country were never shown.
2. Its fallback read `_listora_address` and rendered only `if is_string()`,
   but that meta is a **composite array** — the fallback was dead code for
   every listing on the site.

Fixed by rendering the two most specific non-empty parts of
`city / state / country`, falling back to the composite address meta, then to
an em-dash placeholder.

## Setup

- Site: `$SITE_URL`
- Admin list: `$SITE_URL/wp-admin/edit.php?post_type=listora_listing`
- Pick a listing with a fully populated geo row; note its id as `<id>` and
  record its current `city` / `state` / `country` so step 5 can restore them.

## Steps

### 1. Fully resolved rows are unchanged
- **Action**: load the admin list.
- **Expect**: `<id>`'s Location cell reads `"<city>, <state>"` exactly as
  before the fix. This guards against the fix over-reaching and appending
  country to every row.

### 2. Region-level row (the reported bug)
- **Action**:
  ```sql
  UPDATE wp_listora_geo SET city = '', state = 'Northwest Territories',
         country = 'Canada' WHERE listing_id = <id>;
  ```
  Reload the admin list.
- **Expect**: cell reads `Northwest Territories, Canada`.
  **Pre-fix: the cell was empty.**
- **On fail**: the city gate has been reintroduced.

### 3. Country-only row
- **Action**: `UPDATE wp_listora_geo SET city = '', state = '' WHERE listing_id = <id>;`
- **Expect**: cell reads `Canada`.

### 4. No geo row, composite address meta only
- **Action**: `DELETE FROM wp_listora_geo WHERE listing_id = <id>;` and confirm
  `_listora_address` holds an array with `city` / `state`.
- **Expect**: cell falls back to the address components rather than blanking.
- **On fail**: the `is_array()` fallback branch regressed to `is_string()`.

### 5. Nothing at all
- **Action**: clear both the geo row and `_listora_address`.
- **Expect**: cell renders the `—` placeholder (never an empty cell), matching
  the Rating column's empty treatment.

### 6. Restore
- **Action**: restore the recorded `city` / `state` / `country` values, then
  `wp listora reindex <id>` (or re-save the listing) to rebuild the geo row.
- **Expect**: cell returns to `"<city>, <state>"`.
