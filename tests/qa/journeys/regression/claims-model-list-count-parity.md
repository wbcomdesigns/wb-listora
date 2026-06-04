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
---

# Admin claims list and REST claims list share one model — same rows + same COUNT

Regression sentinel for DUP-1-claims-model (`db8e2cd`). The claims list query
and its `COUNT(*)` were duplicated verbatim between the admin Claims page
(`includes/admin/class-admin.php`) and the REST claims controller
(`includes/rest/class-claims-controller.php`). Two copies drift — the list and
its displayed total could disagree. The fix extracts a single read model at
`includes/core/class-claims-model.php` with `get_list()` / `get_list_count()`
sharing one `build_where()` predicate, so the page count and the rows always
agree. Both callers route through it. An optional lat/lng bounding-box WHERE
(geo INNER JOIN on `idx_lat_lng`) keeps geo-filtered queries off an unbounded
scan.

## Setup

- Site: `$SITE_URL`
- Capture `TOTAL_OPEN` = `SELECT COUNT(*) FROM wp_listora_claims WHERE status='pending'` (or whatever the default admin filter is).

## Steps

### 1. The model is the single source of the query
- **Action**:
  ```
  grep -n "Claims_Model::get_list\|Claims_Model::get_list_count" includes/admin/class-admin.php includes/rest/class-claims-controller.php
  grep -n "function get_list\|function get_list_count\|function build_where" includes/core/class-claims-model.php
  ```
- **Expect**: BOTH the admin page and the REST controller call `Claims_Model::get_list()` + `Claims_Model::get_list_count()`. Neither builds its own `SELECT ... FROM {prefix}listora_claims` for the list. `build_where()` is private and shared by both methods.
- **On fail**: `db8e2cd` — a caller still has an inline duplicated query.

### 2. Model list + count agree for the same args
- **Action**:
  ```
  wp eval "\$args=['status'=>'pending','per_page'=>10,'page'=>1]; \$rows=\WBListora\Core\Claims_Model::get_list(\$args); \$cnt=\WBListora\Core\Claims_Model::get_list_count(\$args); echo 'rows_on_page:'.count(\$rows).' total:'.\$cnt;"
  ```
- **Expect**: `total` equals `TOTAL_OPEN`; `rows_on_page` is `min(per_page, total)`. The COUNT uses `get_list_count()` (a dedicated `SELECT COUNT(*)`), never `count(get_list())`.
- **On fail**: `build_where()` predicate diverges between `get_list()` and `get_list_count()`.

### 3. Admin Claims page total matches REST total for the same filter
- **Action**:
  - Admin: `GET $SITE_URL/wp-admin/admin.php?page=listora-claims&autologin=1` — read the rendered total / pagination count for the default (pending) filter.
  - REST: `curl -s --cookie "<admin-cookie>" "$SITE_URL/wp-json/listora/v1/claims?status=pending&per_page=10&page=1" -H "X-WP-Nonce: <nonce>"`.
- **Expect**: the REST envelope `total` == the admin page's displayed total == `TOTAL_OPEN`. The first page of rows (by ID) is identical between the two surfaces.
- **On fail**: one caller passing different default args into the model than the other.

### 4. Pagination is bounded (big-site readiness)
- **Action**: request page 2 of the REST list (`page=2`).
- **Expect**: distinct rows from page 1, no duplicates, no 500. The query carries `LIMIT`/`OFFSET` from the model (no unbounded `SELECT *`).
- **On fail**: `get_list()` missing LIMIT/OFFSET.

### 5. Geo-filtered query uses the bounding-box join
- **Action**: call the model with a lat/lng bounding box arg:
  ```
  wp eval "\$rows=\WBListora\Core\Claims_Model::get_list(['ne_lat'=>90,'ne_lng'=>180,'sw_lat'=>-90,'sw_lng'=>-180,'per_page'=>5]); echo 'geo_rows:'.count(\$rows);"
  ```
- **Expect**: returns rows without fatal; the SQL uses the geo INNER JOIN (idx_lat_lng) rather than a full scan. Whole-world box returns the geocoded subset.
- **On fail**: bounding-box WHERE not wired in `build_where()` / `joins()`.

## Notes
- This is the canonical big-site-readiness pattern: one model, `get_list()` + `get_list_count()`, shared predicate, indexed geo join. If a new claims surface is added (e.g. a moderator queue), it MUST route through `Claims_Model` too — extend this journey with that caller.
