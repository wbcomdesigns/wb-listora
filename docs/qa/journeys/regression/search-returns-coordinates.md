---
journey: search-returns-coordinates
plugin: wb-listora
priority: high
roles: [anonymous]
covers:
  - "/search rows carry geo{lat,lng} so a map client can plot them"
  - "geo is batched - one query per page, never per row"
  - "geo is null (not 0,0) when a listing has no geocoded row"
prerequisites:
  - "Seeded demo data (75 published listings are inside the NYC bbox used below)"
  - "At least one listing with a row in {prefix}listora_geo and one published listing without (verified: 845 has none)"
estimated_runtime_minutes: 2
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# /search returns coordinates

Read-only REST journey, public data (same for every role). Verified via: curl + `wp eval-file` (steps 1-2 observed; step 3 observed WITHOUT deleting the geo row, by picking a listing that has none).

The web `listing-map` block builds its own marker blob in `render.php`; the REST `/search` is the surface a native map client uses.

## Steps

### 1. A bbox query returns coordinates
```bash
curl -s "http://wb-listora.local/wp-json/listora/v1/search?per_page=3&bounds%5Bne_lat%5D=40.92&bounds%5Bne_lng%5D=-73.70&bounds%5Bsw_lat%5D=40.49&bounds%5Bsw_lng%5D=-74.26"
```
- **Assert**: `total` > 0 (observed 75); every listing has a `geo` key; `geo.lat`/`geo.lng` are JSON numbers (not strings) inside the bbox (observed `{"lat":40.7728,"lng":-73.9712}`).
- **FAIL**: key absent -> select-list change reverted; strings -> `(float)` cast dropped (`includes/rest/class-search-controller.php:748-752`).

### 2. Batched, one query per page
```bash
wp eval '$c=0; add_filter("query",function($q) use (&$c){ if(false!==strpos($q,"listora_geo")&&0===stripos(ltrim($q),"SELECT")){$c++;} return $q; });
$r=new WP_REST_Request("GET","/listora/v1/search"); $r->set_param("per_page",20);
$d=rest_do_request($r)->get_data(); echo "rows:".count($d["listings"])." geo_queries:".$c;'
```
- **Assert**: `rows:20 geo_queries:1` (observed). **FAIL** >1: the batched `$geo_map` fetch (`:645-659`) was bypassed.

### 3. Un-geocoded listing reports `geo: null`
- **Action** (read-only): pick a published listing with no `listora_geo` row (`SELECT p.ID FROM posts p LEFT JOIN listora_geo g ON g.listing_id=p.ID WHERE g.listing_id IS NULL AND p.post_status='publish' LIMIT 1`; observed 845), request `/search?per_page=100`.
- **Assert**: that item's `geo` is `null`, not `{"lat":0,"lng":0}` and not `{}` (observed `null`). Null Island is a real place; `null` is the only honest unknown.
- **Do not delete geo rows on a shared site**; use a listing that has none, or create a `QA-J1-` listing without an address and delete it afterwards.

### 4. Map surface and pagination
- 75 rows in the bbox at 20 per page: page 4 has 15 rows; `has_more` false on the last page; the map block's "zoom in to see more" appears only when the marker limit hides results (2.0.0).

## Fail diagnostics
| Symptom | File |
|---|---|
| `geo` key absent | `class-search-controller.php:748` |
| `geo.lat` string | `:750-751` cast |
| `geo_queries` > 1 | `:645-659` |
| `0,0` for un-geocoded | `:748-753` null branch |
