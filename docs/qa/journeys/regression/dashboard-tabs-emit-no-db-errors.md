---
journey: dashboard-tabs-emit-no-db-errors
plugin: wb-listora
priority: critical
roles: [member, admin, anonymous]
covers: [dashboard, favorites, sql, debug-log, counter-vs-list]
prerequisites:
  - "WP_DEBUG + WP_DEBUG_LOG on, WP_DEBUG_DISPLAY off (true on this site: wp-config.php:91-93)"
  - "A member with rows behind EVERY dashboard tab. subscriber3 (user 5) has 25 favourites; add a listing, review and credit row for the others or mark the tab 'no rows'"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Every dashboard tab query runs clean, and its counter matches its list

History: the Favorites tab once ordered by a column (`id`) that `{prefix}listora_favorites` does not have (primary key `(user_id, listing_id)`). `$wpdb->get_col()` returned `[]`, so the panel said "No saved listings yet" for a member whose nav badge said 32. HTTP 200, correct markup, no PHP warning: only `WordPress database error` in the log shows it.

Verified via: curl walk of 12 tabs as subscriber3 and admin on 2026-10-12, debug.log delta grep returned 0 database errors. Browser not used.

## Steps

### 1. Baseline the log
- `L="<site>/wp-content/debug.log"; S0=$(wc -c < "$L")`.

### 2. Visit every tab as the member
- **Action**: `?autologin=subscriber3`, then GET `/my-listings/?tab=` for `overview`, `listings`, `reviews`, `favorites`, `claims`, `credits`, `profile`, `leads`, plus Pro `analytics`, `needs`, `my-responses`, `saved-searches` (tabs are `?tab=` links now, not `#hash`). Repeat as `varundubey`.
- **Expect**: HTTP 200 each (an off Pro tab falls back to Overview, still 200).

### 3. Assert zero database errors
- `tail -c +$((S0+1)) "$L" | grep -ci "WordPress database error"` must print 0. A fatal/warning-only grep does not see this class.

### 4. Counter equals list (POSITIVE CONTROL included)
- **Action**: read each `.listora-dashboard__nav-count` and count rendered rows for the tab.
- **Expect**: subscriber3 Favorites badge `25` and 25 cards across 2 pages (20 + 5); Credits `0`; listings `0`. Fail when badge > 0 while the panel shows its empty state; compare with a member whose badge is 0 (subscriber3 Credits / Listings) where the empty state is correct.

### 5. Prove the query against the schema
- `SHOW KEYS FROM wp_listora_favorites;` then read `includes/core/class-dashboard-data.php:258`: `ORDER BY created_at DESC, listing_id DESC`, both real columns. Every column named in an `ORDER BY` of a dashboard query must exist in its table.

### 6. Role ladder
- Owner (subscriber3) steps 2-4; another member (subscriber2) sees their own badges only; logged out gets `.listora-dashboard__login-prompt` (no queries against another user's rows); editor (`qa_editor`) like a member; admin only as the control.

## Test-data trap

A member with zero favourites passes every assertion while the bug is present. Run `SELECT COUNT(*)` per table before trusting a pass.

## Fail diagnostics

| Symptom | File |
|---|---|
| `WordPress database error` in the log | `includes/core/class-dashboard-data.php` (every `list_*` / `count_*`), table names in `includes/class-activator.php:246` |
| Badge > 0, panel empty | tab template under `templates/blocks/user-dashboard/tab-*.php` vs `blocks/user-dashboard/render.php:305-333` |

## Not verified

Credits and Reviews-written badges with rows (subscriber3 has none); 390px.
