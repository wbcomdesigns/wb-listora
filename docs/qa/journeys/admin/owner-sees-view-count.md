---
journey: owner-sees-view-count
plugin: wb-listora
priority: high
roles: [anonymous, owner, administrator]
covers: [analytics-lite, listora_analytics-upsert-aggregate, views-owner-admin-gate, analytics-lite-pro-supersession, wb_listora_view_recorded]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Free active (Pro optional; one step is combo-only)"
  - "At least one published listora_listing owned by a known non-admin author"
estimated_runtime_minutes: 6
covers_card: null
covers_commit: HEAD
---

# Free analytics-lite counts a real view once and surfaces it to owner + admin only

The 1.1.0 Free `Analytics_Lite` service records per-listing view counts on the
existing `listora_analytics` table and exposes them on three surfaces - the
owner dashboard listing row, the admin listings table (sortable Views column),
and the listing REST response (owner/admin-gated). This journey locks all
three, the write contract (one upsert-per-day aggregate row, bot/dedupe gated),
and the Pro supersession contract: when Pro's analytics toggle is ON, Pro owns
recording and Free must not write (cross-ref Pro
`regression/analytics-no-double-count.md`).

Key table fact: `listora_analytics` is an UPSERT-per-day aggregate keyed
`(listing_id, event_type, event_date)` - a day of views is ONE row whose
`count` column increments. Assert on the `count` COLUMN delta, never on row
count.

## Setup

- Site: `$SITE_URL`
- Listing: `<LID>` ← a published `listora_listing` whose `post_author` is a
  known non-admin user `<OWNER>`
- Today: `<TODAY>` ← `current_time('Y-m-d')` server-side date
- Clear the per-visitor dedupe so a fresh count records:
  ```sql
  DELETE FROM wp_options WHERE option_name LIKE '_transient_listora_view_<LID>_%'
     OR option_name LIKE '_transient_timeout_listora_view_<LID>_%';
  ```

## Steps

### 1. Read the current aggregate (COLUMN, not row count)
- **Action**: `mysql_query "SELECT count FROM wp_listora_analytics WHERE listing_id=<LID> AND event_type='view' AND event_date='<TODAY>'"`
- **Capture**: `BEFORE` ← the `count` value (missing row = 0)
- **On fail**: table absent → `includes/class-activator.php` (analytics table DDL)

### 2. View the listing as an anonymous real browser
- **Action**: `playwright_navigate <single permalink of LID>` with a normal
  desktop browser UA (NOT curl/python - `Analytics_Lite::is_bot()` rejects
  `curl`, `python-requests`, `bot`, headless, missing UA). HTTP 200 render.
- **Expect**: page renders the listing detail (not a redirect)
- **On fail**: `includes/features/class-analytics-lite.php::maybe_record_view`
  (hook `wp` priority 20) / `is_countable_request` / `is_bot`

### 3. Re-read the aggregate - delta is exactly +1
- **Action**: `mysql_query "SELECT count FROM wp_listora_analytics WHERE listing_id=<LID> AND event_type='view' AND event_date='<TODAY>'"`
- **Expect**: `count` == `BEFORE + 1`. The row count for that listing/day stays
  at exactly 1 (upsert incremented, not appended).
- **On fail**: `record_view()` upsert `INSERT ... ON DUPLICATE KEY UPDATE count = count + 1`

### 4. A second immediate view from the SAME visitor does NOT double-count
- **Action**: re-navigate the same permalink in the same browser session
  (same IP) within the hour.
- **Action**: re-read the aggregate `count`.
- **Expect**: `count` UNCHANGED from step 3 - the `listora_view_<LID>_<iphash>`
  transient suppresses the repeat for `DEDUPE_WINDOW` (1 hour).
- **On fail**: dedupe transient logic in `record_view()`

### 5. Anonymous REST response does NOT carry `views`
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/listings/<LID>/detail"`
  (no auth cookie)
- **Expect**: response JSON has NO `views` key (gate requires
  `is_user_logged_in()`)
- **On fail**: `includes/rest/class-listings-controller.php:985-991` (gate)

### 6. Owner REST response carries `views`
- **Action**: authenticated request as `<OWNER>` (autologin cookie or
  application-password) to `GET /listora/v1/listings/<LID>/detail`
- **Expect**: response has `views` = the step-3 `count` value (owner is
  `post_author`)
- **On fail**: same gate - owner branch `get_current_user_id() === post_author`

### 7. Admin sees `views` on every listing (list + detail)
- **Action**: authenticated as an administrator (has `edit_others_posts`):
  `GET /listora/v1/listings/<LID>/detail` and `GET /listora/v1/listings?per_page=5`
- **Expect**: `views` present on the detail and on each item where the gate
  applies; values come from the batched `prepare_views()` prefetch (one query
  per page, not per row)
- **On fail**: `class-listings-controller.php:181-184` (batch prime) +
  `:768-773` (list-item gate)

### 8. Owner dashboard listing row shows the view count
- **Action**: `playwright_navigate` the dashboard Listings tab as `<OWNER>`
  (e.g. `/dashboard/?autologin=<OWNER>#listings`)
- **Expect**: the row for `<LID>` shows an "N views" tag matching the step-3
  count (`_n('%s view','%s views')`)
- **On fail**: `blocks/user-dashboard/render.php:184-189` (batched prefetch) +
  `templates/blocks/user-dashboard/tab-listings.php:233-241` (render)

### 9. Admin listings table has a sortable Views column
- **Action**: `playwright_navigate /wp-admin/edit.php?post_type=listora_listing`
  as administrator
- **Expect**: a "Views" column renders (with the visibility dashicon + count for
  listings with views); the column header is a sort link. Clicking it reloads
  with `orderby=listora_views` and rows reorder by view count.
- **On fail**: `includes/admin/class-listing-columns.php:164` (column),
  `:339`/`:355-381` (`sortable_columns` + `sort_by_views` posts_clauses JOIN)

### 10. Pro supersession (combo only) - Pro owns recording, Free stands down
- **Action**: with Pro active and the analytics toggle ON, eval the ownership
  filter: `wp eval 'var_dump( apply_filters( "wb_listora_pro_owns_analytics", false ) );'`
- **Expect**: `bool(true)`. Free's `Analytics_Lite::init()` does NOT register
  the `wp` recorder, and `record_view()` early-returns. Reads on steps 5-9
  still work (they read whatever `view` rows Pro wrote). Full single-vs-double
  recording contract lives in Pro
  `regression/analytics-no-double-count.md`.
- **On fail**: `class-analytics-lite.php::pro_owns_recording` /
  `wb-listora-pro/includes/features/class-analytics.php:55` (`add_filter('wb_listora_pro_owns_analytics','__return_true')`)

### 11. debug.log clean
- **Action**: diff `wp-content/debug.log` across the walk
- **Expect**: zero new fatals/warnings

## Pass criteria

ALL of the following hold:
1. A real anonymous browser view increments the `count` COLUMN by exactly +1;
   the listing/day stays one row (upsert, not append).
2. A same-visitor repeat within the hour does not increment (dedupe).
3. Anonymous REST never exposes `views`; owner and admin do.
4. Owner dashboard row + admin sortable Views column both show the count.
5. (Combo) `wb_listora_pro_owns_analytics` is true with the Pro toggle ON and
   Free writes nothing; reads still resolve.
6. debug.log clean throughout.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Aggregate grows by >1 per view | dedupe/bot gate bypassed OR Pro double-counting | `includes/features/class-analytics-lite.php::record_view`; combo → Pro `class-analytics.php` |
| Row count grows per view (not column) | upsert key broken | `class-activator.php` UNIQUE `(listing_id,event_type,event_date)` |
| Anonymous REST leaks `views` | gate dropped the `is_user_logged_in()` check | `class-listings-controller.php:768-773` + `:985-991` |
| Owner gets no `views` | owner branch compares wrong author | same gate - `get_current_user_id() === (int) $post->post_author` |
| Admin Views column not sortable | sortable filter/posts_clauses missing | `class-listing-columns.php:339`/`:355-381` |
| Dashboard row missing count | prefetch not wired | `blocks/user-dashboard/render.php:184-189` |
| Counts double on combo sites | Free didn't stand down | `class-analytics-lite.php::pro_owns_recording` + Pro `class-analytics.php:55` |
