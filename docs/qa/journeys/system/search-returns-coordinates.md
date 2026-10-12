---
journey: search-returns-coordinates
plugin: wb-listora
priority: high
roles: [guest, member, editor, admin]
covers: ["/search rows carry geo{lat,lng}", "geo is batched, one query per page", "geo is null (not 0,0) when un-geocoded"]
prerequisites:
  - "Seeded data: 5+ published listings inside a bounding box {bbox} and at least one published listing WITHOUT a {prefix}listora_geo row ({ungeocoded})"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
supersedes: []
site_profile: required
---

# GET /search returns coordinates so a map client can plot results

## Why
The web map builds its own marker blob, but a native map client plots `/search` rows directly: missing, string or `0,0` coordinates put pins in the wrong place (Null Island) or break the client.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Read-only REST journey on public data; the same for every role. `{bbox}` = `bounds[ne_lat]`, `bounds[ne_lng]`, `bounds[sw_lat]`, `bounds[sw_lng]` around the demo area.
- `{ungeocoded}`: `SELECT p.ID FROM {prefix}posts p LEFT JOIN {prefix}listora_geo g ON g.listing_id=p.ID WHERE g.listing_id IS NULL AND p.post_status='publish' AND p.post_type='listora_listing' LIMIT 1`. Do not delete geo rows on a shared site; use a listing that has none, or create a QA listing without an address and delete it afterwards.

## Steps

### 1. A bbox query returns coordinates
```bash
curl -s "$SITE_URL/wp-json/listora/v1/search?per_page=3&bounds%5Bne_lat%5D={ne_lat}&bounds%5Bne_lng%5D={ne_lng}&bounds%5Bsw_lat%5D={sw_lat}&bounds%5Bsw_lng%5D={sw_lng}"
```
- **Expect**: `total` > 0; every listing has a `geo` key; `geo.lat`/`geo.lng` are JSON numbers (not strings) inside the bbox.
- **FAIL**: key absent -> select-list change reverted; strings -> `(float)` cast dropped (`includes/rest/class-search-controller.php:748-752`).

### 2. Batched, one query per page
```bash
wp --path=$WP_PATH eval '$c=0; add_filter("query",function($q) use (&$c){ if(false!==strpos($q,"listora_geo")&&0===stripos(ltrim($q),"SELECT")){$c++;} return $q; });
$r=new WP_REST_Request("GET","/listora/v1/search"); $r->set_param("per_page",20);
$d=rest_do_request($r)->get_data(); echo "rows:".count($d["listings"])." geo_queries:".$c;'
```
- **Expect**: `rows:20 geo_queries:1`. **FAIL** > 1: the batched `$geo_map` fetch (`:645-659`) was bypassed (N+1).

### 3. Un-geocoded listing reports `geo: null`
- **Action**: request `/search?per_page=100` and find `{ungeocoded}`.
- **Expect**: that item's `geo` is `null`, not `{"lat":0,"lng":0}` and not `{}` (Null Island is a real place; `null` is the only honest unknown).

### 4. Boundary and pagination
- **Action**: bbox with swapped corners, a zero-area bbox, `per_page=0`, `per_page=101`, `page=999`, and a bbox containing no listings.
- **Expect**: no 5xx; empty bbox returns `total: 0, listings: []`; invalid per_page is rejected (400) or clamped to the documented bound; swapped corners return 400 or an empty set (name the one the build returns in the run notes).
- **Pagination**: with N rows in the bbox at 20 per page the last page has `N mod 20` rows and `has_more` false; the map block's "zoom in to see more" appears only when the marker limit hides results.

### 5. Role ladder
- Public data: guest, member, editor and admin get identical bodies. Draft/pending listings never appear for any of them.

## Role ladder
See step 5.

## Pass criteria
1. Every row has `geo` numbers inside the bbox.
2. One geo query per page regardless of row count.
3. Un-geocoded rows report `null`.
4. Bad bbox/paging input never 5xx.

## Fail diagnostics
| Symptom | File |
|---|---|
| `geo` key absent | `includes/rest/class-search-controller.php:748` |
| `geo.lat` string | `:750-751` cast |
| `geo_queries` > 1 | `:645-659` |
| `0,0` for un-geocoded | `:748-753` null branch |

## Not verified
Step 4 and step 5 (editor/member bodies) were not run; steps 1-3 were observed with curl and `wp eval`.

## Cleanup
Delete any QA listing created for the null-geo case.
