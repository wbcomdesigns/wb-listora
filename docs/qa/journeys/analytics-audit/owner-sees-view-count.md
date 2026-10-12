---
journey: owner-sees-view-count
group: analytics-audit
actor: customer
mode: mixed
plugin: wb-listora
priority: high
roles: [anonymous, member-owner, member-other, administrator]
covers: [analytics-lite, listora_analytics-upsert-aggregate, views-owner-admin-gate, analytics-lite-pro-supersession, wb_listora_view_recorded]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI at $WP_PATH"
  - "Free active (Pro optional; step 10 is combo-only)"
  - "A published {listing} whose post_author is {owner} (a non-admin)"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged default theme]
supersedes:
  - ora:admin/owner-sees-view-count.md
site_profile: required
---

# Free analytics-lite counts a real view once and shows it to the owner and admin only

## Why
Free's `Analytics_Lite` records per-listing view counts on `listora_analytics` and exposes them on three surfaces: the owner dashboard listing row, the admin listings table (sortable Views column) and the listing REST response (owner/admin-gated). `listora_analytics` is an UPSERT-per-day aggregate keyed `(listing_id, event_type, event_date)`: a day of views is ONE row whose `count` increments, so assert on the `count` column delta, never row count. When Pro's analytics toggle is ON, Pro owns recording and Free must not write (Pro `analytics-audit/analytics-no-double-count.md`).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `LID` = {listing}; `TODAY` = `wp eval 'echo wb_listora_site_date();'` (the SITE calendar date, never the database server's clock: `gmdate()` can be a day off near midnight on a non-UTC site).
- Clear the per-visitor dedupe: `DELETE FROM ${PREFIX}options WHERE option_name LIKE '_transient_listora_view_<LID>_%' OR option_name LIKE '_transient_timeout_listora_view_<LID>_%';`

## Steps

### 1. Read the aggregate
- **Action**: `SELECT count FROM ${PREFIX}listora_analytics WHERE listing_id=<LID> AND event_type='view' AND event_date='<TODAY>'` (missing row = 0).
- **Capture**: `BEFORE`.

### 2. View the listing as an anonymous real browser
- **Action**: open the permalink with a normal desktop UA (NOT curl/python/headless: `Analytics_Lite::is_bot()` rejects them).
- **Expect**: HTTP 200, the listing detail renders.

### 3. Delta is exactly +1
- **Action**: re-read the count.
- **Expect**: `count == BEFORE + 1`; row count for that listing/day stays 1 (upsert incremented, not appended).

### 4. A same-visitor repeat does not double-count
- **Action**: reload in the same session within the hour; re-read.
- **Expect**: `count` unchanged (the `listora_view_<LID>_<iphash>` transient suppresses repeats for the 1-hour `DEDUPE_WINDOW`).

### 5. Anonymous REST carries no `views`
- **Action**: `curl -s $SITE_URL/wp-json/listora/v1/listings/<LID>/detail` (no cookie).
- **Expect**: no `views` key (gate needs `is_user_logged_in()`).

### 6. Owner REST carries `views`
- **Action**: same request as {owner}.
- **Expect**: `views` equals the step 3 count (owner is `post_author`).

### 7. Admin sees `views` on every listing
- **Action**: as {admin} `GET .../listings/<LID>/detail` and `GET .../listings?per_page=5`.
- **Expect**: `views` on the detail and each list item where the gate applies; values come from the batched `prepare_views()` prefetch (one query per page, not per row).

### 8. Owner dashboard row
- **Action**: dashboard Listings tab as {owner}.
- **Expect**: the row for `<LID>` shows "N views" matching step 3, including "0 views" for a listing with none (`_n('%s view','%s views')`).

### 9. Admin listings table has a sortable Views column
- **Action**: `edit.php?post_type=listora_listing` as {admin}; click the Views header.
- **Expect**: a "Views" column (visibility dashicon + count); sort link reloads with `orderby=listora_views` and rows reorder by count.

### 10. Pro supersession (combo only)
- **Action**: Pro active, analytics toggle ON: `wp eval 'var_dump( apply_filters( "wb_listora_pro_owns_analytics", false ) );'`
- **Expect**: `bool(true)`; Free's `Analytics_Lite::init()` does not register the `wp` recorder and `record_view()` early-returns; reads in steps 5-9 still work.

### 11. Timezone (added)
- **Action**: with site timezone UTC+14 then UTC-12 near midnight, view once.
- **Expect**: the day bucket is the SITE date, not UTC; restore UTC. Not verified live.

### 12. debug.log clean
- **Expect**: zero new fatals/warnings across the walk.

## Role ladder

| Rung | Surface | Expect |
|---|---|---|
| {guest} | `GET /listora/v1/listings/<LID>` | no `views` key |
| {other} | same | no `views` key; their dashboard shows only their own listings' counts |
| {owner} | same + dashboard row | `views` present; dashboard shows the number incl. "0 views" |
| {admin} | same + Listings > Views column | present, sortable |

## Pass criteria
1. A real anonymous view increments the `count` column by exactly +1; one row per listing/day.
2. A same-visitor repeat within the hour does not increment.
3. Anonymous REST never exposes `views`; owner and admin do.
4. Owner dashboard row and admin Views column show the count.
5. (Combo) Pro owns recording and Free writes nothing; reads still resolve.
6. debug.log clean.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Count grows by more than 1 per view | dedupe/bot gate bypassed or Pro double-counting | `includes/features/class-analytics-lite.php` `record_view`; Pro `class-analytics.php` |
| Row count grows per view | upsert key broken | `includes/class-activator.php` UNIQUE `(listing_id,event_type,event_date)` |
| Anonymous REST leaks `views` | `is_user_logged_in()` check dropped | `includes/rest/class-listings-controller.php` gate |
| Owner gets no `views` | owner branch compares wrong author | same gate |
| Views column not sortable | `sortable_columns` / `posts_clauses` | `includes/admin/class-listing-columns.php` |
| Dashboard row missing count | prefetch not wired | `blocks/user-dashboard/render.php`, `templates/blocks/user-dashboard/tab-listings.php` |
| Counts double on combo sites | Free did not stand down | `class-analytics-lite.php` `pro_owns_recording`; Pro `class-analytics.php` |

## Not verified
Step 11.

## Cleanup
Remove the dedupe transients you seeded; no data created.
