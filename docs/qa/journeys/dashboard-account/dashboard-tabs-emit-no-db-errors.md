---
journey: dashboard-tabs-emit-no-db-errors
group: dashboard-account
actor: customer
mode: mixed
plugin: wb-listora
priority: critical
roles: [owner, other, editor, admin, guest]
covers: [dashboard, favorites, sql, debug-log, counter-vs-list]
prerequisites:
  - "WP_DEBUG + WP_DEBUG_LOG on, WP_DEBUG_DISPLAY off"
  - "{owner} has rows behind EVERY dashboard tab (favourites, a listing, a review, a credit row) or the tab is marked 'no rows'"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged theme]
site_profile: required
supersedes:
  - ora:regression/dashboard-tabs-emit-no-db-errors.md
---

# Every dashboard tab query runs clean, and its counter matches its list

## Why
The Favorites tab once ordered by a column (`id`) that `${PREFIX}listora_favorites` does not have (primary key `(user_id, listing_id)`). `$wpdb->get_col()` returned `[]`, so the panel said "No saved listings yet" for a member whose nav badge said 32. HTTP 200, correct markup, no PHP warning: only `WordPress database error` in the log shows it.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Test-data trap: a member with zero favourites passes every assertion while the bug is present. Run `SELECT COUNT(*)` per table before trusting a pass.
- `L=$WP_PATH/wp-content/debug.log`; `S0=$(wc -c < "$L")`.

## Steps

### 1. Baseline the log
- **Action**: record `S0`.
- **Expect**: file exists (or is created by the first notice).
- **Read-back**: `echo $S0`.

### 2. Visit every tab as the member
- **Action**: `?autologin={owner}`, then GET `$DASH/?tab=` for `overview`, `listings`, `reviews`, `favorites`, `claims`, `credits`, `profile`, `leads`, plus Pro `analytics`, `needs`, `my-responses`, `saved-searches`. Repeat as {admin}.
- **Expect**: HTTP 200 each (an off Pro tab falls back to Overview, still 200).
- **Read-back**: status codes collected.

### 3. Assert zero database errors
- **Action**: `tail -c +$((S0+1)) "$L" | grep -ci "WordPress database error"`.
- **Expect**: prints 0. A fatal/warning-only grep does not see this class.
- **Read-back**: the same grep over `PHP (Warning|Notice|Deprecated)` also 0 for Listora paths.

### 4. Counter equals list (positive control included)
- **Action**: read each `.listora-dashboard__nav-count` and count rendered rows for the tab.
- **Expect**: Favorites badge equals the favourites row count, rendered across pages of 20 (e.g. 25 -> 20 + 5); a zero-row tab shows its empty state with no badge; fail when a badge > 0 while the panel shows its empty state.
- **Read-back**: `SELECT COUNT(*) FROM ${PREFIX}listora_favorites WHERE user_id=<owner>` equals the badge; control: a member whose badge is 0 shows the empty state correctly.

### 5. Prove the query against the schema
- **Action**: `SHOW KEYS FROM ${PREFIX}listora_favorites;` then read `includes/core/class-dashboard-data.php:258`.
- **Expect**: `ORDER BY created_at DESC, listing_id DESC`, both real columns. Every column named in an `ORDER BY` of a dashboard query exists in its table.
- **Read-back**: repeat `SHOW COLUMNS` for each table behind a `list_*` / `count_*` method (table names in `includes/class-activator.php:246`).

### 6. Scale and lifecycle
- **Action**: as a member with 2,000+ favourites (seed via the fixtures script) load `?tab=favorites&page=2` and the last page; include a favourite whose listing is trashed.
- **Expect**: no DB error; counter equals the sum of page rows; the trashed listing does not strand the page.
- **Read-back**: re-run step 3. Not verified live.

### 7. 390 and dark
- **Action**: repeat step 2 at 390 and dark.
- **Expect**: same status and zero errors.
- **Read-back**: step 3 grep. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | steps 2-4 |
| {other} | own badges only |
| {editor} | like a member |
| {admin} | control |
| {guest} | `.listora-dashboard__login-prompt`; no queries against another user's rows |

## Pass criteria
1. Every tab returns 200 and the log delta has zero `WordPress database error`.
2. Every badge equals the rows its panel renders; empty state only when the count is 0.
3. Every `ORDER BY` column exists.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| `WordPress database error` in the log | bad column / table | `includes/core/class-dashboard-data.php` (`list_*`, `count_*`), `includes/class-activator.php:246` |
| Badge > 0, panel empty | tab template vs counter | `templates/blocks/user-dashboard/tab-*.php` vs `blocks/user-dashboard/render.php:305-333` |

## Not verified
Credits and Reviews-written badges with rows; steps 6-7; 390px.

## Cleanup
Remove seeded favourites and any log noise you generated.
