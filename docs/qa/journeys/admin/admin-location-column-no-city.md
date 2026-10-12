---
journey: admin-location-column-no-city
plugin: wb-listora
priority: high
site_profile: required
roles: [admin, editor, other, guest]
covers: [listing-columns, search-indexer-geo, address-meta-shape]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WP-CLI + DB access (`--path=$WP_PATH`)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
supersedes: []
fails_on: "pre-fix: Listing_Columns::render_column() gated the cell on a non-empty geo city (cell blank for region-level rows)"
---

# The admin Location column renders region-level addresses

## Why

On `wp-admin/edit.php?post_type=listora_listing` the **Location** cell was blank for any listing whose reverse geocode produced no city (`Search_Indexer::update_geo_index()` stores `city` as `''`), and its fallback read `_listora_address` as a string although that meta is a composite array. `Listing_Columns::render_column()` (`includes/admin/class-listing-columns.php:286-318`) now shows the two most specific non-empty parts of city/state/country, falls back to the composite address, and prints a placeholder when there is nothing.

## Setup

- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Use a throwaway listing: `ID=$(wp --path="$WP_PATH" post create --post_type=listora_listing --post_title="QA-loc-$(date +%s)" --post_status=publish --porcelain)` then insert a geo row: `wp eval 'global $wpdb; $wpdb->insert($wpdb->prefix."listora_geo",["listing_id"=>ID,"lat"=>60.5,"lng"=>-120.5,"city"=>"Yellowknife","state"=>"Northwest Territories","country"=>"Canada"]);'` (replace `ID`).
- Find it with `$SITE_URL/wp-admin/edit.php?post_type=listora_listing&s=<its title>`; read `tr#post-<ID> td.column-listora_location`. Sign in as {admin}.
- Geo table columns today: `listing_id, lat, lng, address, city, state, country, postal_code, geohash, timezone`.

## Steps

### 1. Fully resolved row (control: the fix must not over-reach)
- **Action**: open the list as {admin}.
- **Expect**: `Yellowknife, Northwest Territories` (two parts only; country is NOT appended).
- **Read-back**: the geo row still holds all three parts (`SELECT city,state,country FROM ${PREFIX}listora_geo WHERE listing_id=<ID>`).

### 2. Region-level row (the reported bug)
- **Action**: `UPDATE ${PREFIX}listora_geo SET city='' WHERE listing_id=<ID>`; reload the list.
- **Expect**: `Northwest Territories, Canada`. Pre-fix the cell was empty.

### 3. Country-only row
- **Action**: also `state=''`.
- **Expect**: `Canada`.

### 4. No geo row, composite address meta only
- **Action**: `DELETE FROM ${PREFIX}listora_geo WHERE listing_id=<ID>`; `update_post_meta(<ID>,'_listora_address',['address'=>'1 Main St','city'=>'Hay River','state'=>'NT','country'=>'Canada'])`.
- **Expect**: `Hay River, NT` (components, not the whole array).
- **Then**: meta with only `address` => the cell shows `1 Main St`.

### 5. Nothing at all
- **Action**: delete the address meta too.
- **Expect**: `<span class="listora-listing-col__placeholder">—</span>`, never an empty cell, matching the Rating column's empty treatment.

### 6. Boundary input
- **Action**: set `city` to a markup string (`<b>x</b>`), a 300-character string and an emoji, then reload.
- **Expect**: each renders escaped as text, no raw HTML, the row layout holds. Not verified live (added by consolidation).

### 7. Large table
- **Action**: open page 2 and sort by Rating on the full list (count with `wp post list --post_type=listora_listing --format=count`; seed per the fixtures script if under 2,000).
- **Expect**: Location cells render with the one cached geo query per page (`prime_column_caches()`), not one query per row.
- **Read-back**: with `SAVEQUERIES`, geo-table queries on the page number 1.

## Role ladder

| Actor | Expect |
|---|---|
| {admin} (control) | steps 1-7 |
| {editor} | sees the same cell (`edit_others_listora_listings`) |
| {other} (member) | cannot open `edit.php?post_type=listora_listing`, redirected or denied |
| {guest} | redirect to wp-login.php |

## Pass criteria

1. A resolved row shows exactly city and state.
2. A region-level row shows state and country; country-only shows the country.
3. With no geo row the composite address components render, then the typed address line.
4. An empty listing shows the placeholder, never a blank cell.
5. Markup in a part is escaped; the page issues one geo query.

## Fail diagnostics

| Symptom | File |
|---|---|
| Region-level cell blank | city gate back in `includes/admin/class-listing-columns.php:291-296` |
| Composite fallback blank | `is_array()` branch lost, `:299-307` |
| Placeholder missing | `:315-317` |
| One query per row | `prime_column_caches()` removed |

## Not verified

Steps 1-5 ran 2026-10-12 against a throwaway listing via curl/DOM as administrator and wp eval mutations. Steps 6-7 and 390px layout (light and dark), and the {editor}/{other}/{guest} rows, were not run.

## Cleanup

`wp post delete <ID> --force` (the geo row is cleared with the index). For a real listing you edited instead, restore city/state/country and run `wp listora reindex`.
