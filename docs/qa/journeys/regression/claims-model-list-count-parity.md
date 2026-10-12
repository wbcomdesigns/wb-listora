---
journey: claims-model-list-count-parity
plugin: wb-listora
priority: high
roles: [administrator]
covers: [claims-model, admin-claims-list, claims-rest-list, list-count-parity, geo-bounding-box]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Several listora_claims rows exist across statuses (capture a count > the admin per-page limit so pagination is exercised)"
estimated_runtime_minutes: 5
covers_card: null
covers_commit: db8e2cd
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
changed: true
---

# Admin claims list and REST claims list share one model: same rows, same COUNT

One read model, `Claims_Model` (`includes/core/class-claims-model.php`), serves the admin Claims screen, the REST claims list and the admin dashboard tile counts. `get_list()` and `get_list_count()` share a single private `build_where()` predicate so the rows and the displayed total cannot disagree. An optional lat/lng bounding box (geo INNER JOIN on the geo table, `idx_lat_lng`) keeps geo-filtered queries off an unbounded scan.

Roles: administrator (control) can read both surfaces; a moderator without `manage_listora_claims`, another member and logged-out must be refused on both surfaces identically.

## Setup

- Site `$SITE_URL`, 37 claim rows on the QA site (`SELECT status, COUNT(*) FROM wp_listora_claims GROUP BY status`: pending 17, approved 19, rejected 1), more than the admin page size of 20, so pagination is exercised. Capture `TOTAL_PENDING` and `TOTAL_ALL`.
- REST nonce for the admin session: `curl -b <jar> $SITE_URL/wp-admin/admin-ajax.php?action=rest-nonce`.

## Steps

### 1. Both callers route through the model
- **Action**:
  ```
  grep -n "Claims_Model::get_list\|Claims_Model::get_list_count" includes/admin/class-admin.php includes/rest/class-claims-controller.php
  grep -n "function get_list\|function get_list_count\|function build_where" includes/core/class-claims-model.php
  ```
- **Expect**: the admin screen (`class-admin.php:2072-2073`) and the REST controller (`class-claims-controller.php:430-431`) both call `get_list()` + `get_list_count()`. Neither builds its own `SELECT ... FROM {prefix}listora_claims` for the list. `build_where()` is private and shared.

### 2. Model list + count agree for the same args
- **Action**:
  ```
  wp eval '$a=["status"=>"pending","limit"=>10,"offset"=>0]; echo count(\WBListora\Core\Claims_Model::get_list($a))." of ".\WBListora\Core\Claims_Model::get_list_count($a);'
  ```
- **Expect**: `get_list_count` equals `TOTAL_PENDING`; the page returns `min(limit, total)` rows. (Args are `status`, `search`, `limit`, `offset`, `order`; there is no `page`/`per_page` at model level.)

### 3. Admin total matches REST total for the same filter
- **Action**: admin `GET $SITE_URL/wp-admin/admin.php?page=listora-claims&status=pending` and read the "N claims" count under the table; REST `GET /wp-json/listora/v1/claims?status=pending&per_page=10&page=1` as admin.
- **Expect**: the Pending tab label count, the table's "17 claims" count and the REST envelope `{claims, total, pages, has_more}` `total` are all `TOTAL_PENDING`. `status=all` on the screen shows "All" count = `TOTAL_ALL` and 2 pages at 20 per page (`paged=2` link, "50"/"100" per-page links).

### 4. Pagination is bounded (big-site readiness)
- **Action**: request REST `page=2&per_page=10`, and on the screen `...&status=all&paged=2`.
- **Expect**: distinct rows from page 1, no duplicates, no 500. `per_page=500` is rejected with 400 `rest_invalid_param` (max 100). The SQL carries `LIMIT %d OFFSET %d`; there is no unbounded `SELECT *`.

### 5. Geo-filtered query uses the bounding-box join
- **Action**:
  ```
  wp eval '$g=["min_lat"=>-90,"max_lat"=>90,"min_lng"=>-180,"max_lng"=>180,"limit"=>5]; echo count(\WBListora\Core\Claims_Model::get_list($g))." of ".\WBListora\Core\Claims_Model::get_list_count($g);'
  ```
- **Expect**: no fatal; rows come from the INNER JOIN on the geo table (only claims whose listing has a geocoded row: 37 of 37 on the QA site). A box that excludes every listing, e.g. `min_lat=0,max_lat=1,min_lng=0,max_lng=1`, returns 0 with a count of 0.

### 6. Positive control and role ladder
- **Control**: step 3 with the Pending filter proves the count is not simply "all rows"; the All tab total (37) differs from Pending (17).
- **Another member** (`author1`): `GET /listora/v1/claims` returns 403 `listora_forbidden`; `GET /listora/v1/dashboard/claims` returns only their own claims (35 on the QA site).
- **Logged out**: `GET /listora/v1/claims` returns 401 `listora_unauthorized`.
- **Editor** (`qa_editor`): the screen is refused unless granted `manage_listora_claims`.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Admin and REST totals differ | a caller passes different args into the model | `includes/admin/class-admin.php:2066-2073`, `includes/rest/class-claims-controller.php:421-431` |
| Rows and total disagree | predicate diverged between list and count | `includes/core/class-claims-model.php:120-170` (`build_where`), `:209`, `:251` |
| Page 2 equals page 1 | offset ignored | `includes/core/class-claims-model.php:209-229` |
| Geo box ignored | box edges not all passed (needs all four of `min_lat`, `max_lat`, `min_lng`, `max_lng`) | `includes/core/class-claims-model.php:153-162`, `:176-186` |

## Not verified

Steps 2, 3, 4 and 5 were run live with wp-cli and curl (admin screen HTML read via curl: "37 claims", 20 per page, `paged=2`; REST total 17 pending). 403 (`author1`), 401 (logged out) and the 400 for `per_page=500` were called live.
