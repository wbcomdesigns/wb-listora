---
journey: dashboard-tabs-emit-no-db-errors
plugin: wb-listora
priority: critical
roles: [member]
covers: [dashboard, favorites, sql, debug-log, counter-vs-list]
prerequisites:
  - "WP_DEBUG + WP_DEBUG_LOG on, WP_DEBUG_DISPLAY off"
  - "A member with at least one row behind EVERY dashboard tab (listing, review, favorite, credit transaction)"
estimated_runtime_minutes: 8
---

# Every dashboard tab query runs clean, and its counter matches its list

The Favorites tab shipped ordering by `id DESC` on `{prefix}favorites`, a table
whose primary key is the composite `(user_id, listing_id)` and which has no `id`
column at all. `$wpdb->get_col()` returned an empty array, so the panel rendered
"No saved listings yet" for a member with 32 favorites — while the nav badge
beside it, counting from a different query, still said **32**.

Nothing caught it, and the reason is worth keeping:

- The page returned **HTTP 200**. Status assertions pass.
- The empty state rendered **correctly**. Markup assertions pass.
- There was **no PHP warning and no fatal**. A debug-log check that greps for
  `Fatal error` / `Warning:` passes. WordPress logs a failed query as
  `WordPress database error …`, which is neither.
- The badge and the list were each checked alone, and each looked plausible.

So this journey asserts the two things that would have caught it in one step:
**no DB error**, and **the counter agrees with what it counts**.

## Steps

### 1. Baseline the log
```bash
wc -c wp-content/debug.log > /tmp/dblog-baseline
```

### 2. Visit every dashboard tab as the member
`?autologin=<member>` then walk `#listings`, `#reviews`, `#favorites`,
`#credits`, `#services` — every tab the build registers, not a sample. Each tab
is a separate query path; the bug lived in exactly one of them.

### 3. Assert zero database errors
```bash
tail -c +$(cat /tmp/dblog-baseline) wp-content/debug.log \
  | grep -i "WordPress database error"
```
- **Fails if** this matches anything. Grep for this string specifically — a
  fatal/warning-only check does not see it.

### 4. Counter equals list, on every tab
For each tab, read the nav badge and count the rendered rows.
- **Fails if** badge > 0 while the panel shows its empty state. That pairing is
  the signature of this bug class and is never legitimate.
- **Fails if** badge and row count disagree in either direction while the tab is
  on its first page. (With pagination, assert badge == total across pages, not
  rows on screen.)

### 5. Prove the query against the schema, not against a green screen
```sql
SHOW KEYS FROM wp_listora_favorites;
```
Every column named in an `ORDER BY` must appear in the table. `favorites` orders
by `created_at, listing_id`; there is no `id`.
- **Fails if** any dashboard query orders by a column the table does not define.

## Test-data trap

A member with **zero** favorites passes every assertion above while the bug is
fully present — empty badge, empty list, no error, agreement. The fixture MUST
have rows behind each tab. Verify with `SELECT COUNT(*)` per table before
trusting a pass; a journey run against an empty account proves nothing.
