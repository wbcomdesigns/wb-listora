---
journey: claims-list-and-count-agree
plugin: wb-listora
priority: high
roles: [administrator, member-other, editor, anonymous]
covers: [claims-model, admin-claims-list, claims-rest-list, list-count-parity, geo-bounding-box, claims-pagination-bounded]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Several `listora_claims` rows across statuses, more than the admin page size (20); if fewer, seed QA-J1 claims. Capture counts from SQL: `SELECT status, COUNT(*) FROM {prefix}listora_claims GROUP BY status` -> TOTAL_PENDING, TOTAL_ALL"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
site_profile: required
supersedes: ["ora:regression/claims-model-list-count-parity.md"]
fails_on: "covers_commit db8e2cd (rows and total diverged)"
---

# The admin Claims screen and the REST claims list share one model: same rows, same COUNT

## Why
`Claims_Model` serves the admin screen, the REST list and the dashboard tile counts; `get_list()` and `get_list_count()` share one private `build_where()` so rows and total cannot disagree. A divergence shows moderators a count that does not match the table.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). REST nonce for the admin session: `curl -b <jar> $SITE_URL/wp-admin/admin-ajax.php?action=rest-nonce`.
- Model: `includes/core/class-claims-model.php` (`build_where` ~120-170, `get_list` ~209, `get_list_count` ~251, geo box ~153-186). Model args are `status`, `search`, `limit`, `offset`, `order` (no `page`/`per_page`).

## Steps

### 1. Both callers route through the model
- **Action**:
  ```
  grep -n "Claims_Model::get_list\|Claims_Model::get_list_count" includes/admin/class-admin.php includes/rest/class-claims-controller.php
  grep -n "function get_list\|function get_list_count\|function build_where" includes/core/class-claims-model.php
  ```
- **Expect**: admin screen (`class-admin.php:2072-2073`) and REST controller (`class-claims-controller.php:430-431`) both call `get_list()` + `get_list_count()`; neither builds its own `SELECT ... FROM {prefix}listora_claims`; `build_where()` is private and shared.

### 2. Model list and count agree for the same args
- **Action**: `wp eval '$a=["status"=>"pending","limit"=>10,"offset"=>0]; echo count(\WBListora\Core\Claims_Model::get_list($a))." of ".\WBListora\Core\Claims_Model::get_list_count($a);'`.
- **Expect**: count equals TOTAL_PENDING; the page returns `min(limit, total)` rows.

### 3. Admin total matches REST total for the same filter
- **Action**: admin `GET $SITE_URL/wp-admin/admin.php?page=listora-claims&status=pending`, read "N claims" under the table; REST `GET /wp-json/listora/v1/claims?status=pending&per_page=10&page=1` as {admin}.
- **Expect**: the Pending tab count, the table's "N claims" and the REST envelope `{claims, total, pages, has_more}` `total` are all TOTAL_PENDING; `status=all` shows TOTAL_ALL and the right number of pages at 20 per page (`paged=2` link, "50"/"100" per-page links).
- **Read-back**: Pending (TOTAL_PENDING) differs from All (TOTAL_ALL): positive control that the count is not "all rows".

### 4. Pagination is bounded (big-site readiness)
- **Action**: REST `page=2&per_page=10`; screen `...&status=all&paged=2`; REST `per_page=500`.
- **Expect**: distinct rows from page 1, no duplicates, no 500; `per_page=500` rejected `400 rest_invalid_param` (max 100). SQL carries `LIMIT %d OFFSET %d`; no unbounded `SELECT *`.
- **Read-back**: page 2 ids disjoint from page 1.

### 5. Geo-filtered query uses the bounding-box join
- **Action**: `wp eval '$g=["min_lat"=>-90,"max_lat"=>90,"min_lng"=>-180,"max_lng"=>180,"limit"=>5]; echo count(\WBListora\Core\Claims_Model::get_list($g))." of ".\WBListora\Core\Claims_Model::get_list_count($g);'`; then a box excluding every listing (`min_lat=0,max_lat=1,min_lng=0,max_lng=1`).
- **Expect**: no fatal; rows come from the INNER JOIN on the geo table (`idx_lat_lng`; only claims whose listing has a geocoded row); the empty box returns 0 with count 0. All four edges must be passed.

### 6. Search, empty and single-row cases
- **Action**: admin search by listing title, claimant name and email; filter to a status with 0 claims; filter to a status with exactly 1.
- **Expect**: matching rows only and the count agrees with REST `search=`; 0 -> empty state, count 0; 1 -> "1 claim". Not verified live.
- **Read-back**: screen count equals REST `total` for the same `search`.

## Role ladder
| Rung | Expect |
|---|---|
| {admin} (control) | reads both surfaces; counts agree |
| member-other | `GET /listora/v1/claims` `403 listora_forbidden`; `GET /listora/v1/dashboard/claims` returns only their own claims |
| {guest} | `401 listora_unauthorized` |
| {editor} | screen refused unless granted `manage_listora_claims` (default Editor has it: see `admin/moderation-and-settings-screen-capabilities.md`) |

## Pass criteria
1. Admin and REST lists route through the model with one predicate.
2. Rows and total agree for every filter; All differs from Pending.
3. Pagination is bounded with LIMIT/OFFSET; `per_page` is capped at 100.
4. Geo bounding box does not fatal and returns an honest count.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Admin and REST totals differ | a caller passes different args | `class-admin.php:2066-2073`, `class-claims-controller.php:421-431` |
| Rows and total disagree | predicate diverged | `class-claims-model.php:120-170` (`build_where`), `:209`, `:251` |
| Page 2 equals page 1 | offset ignored | `class-claims-model.php:209-229` |
| Geo box ignored | not all four edges passed | `class-claims-model.php:153-162`, `:176-186` |

## Not verified
Steps 2-5 were run live with wp-cli and curl; 403/401 and the 400 for `per_page=500` were called live; step 6, 390 and the 2000+ rows scale seed were not.

## Cleanup
Delete seeded QA-J1 claims.
