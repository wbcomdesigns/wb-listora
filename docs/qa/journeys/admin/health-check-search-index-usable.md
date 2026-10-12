---
journey: health-check-search-index-usable
plugin: wb-listora
priority: high
roles: [admin, editor, guest]
covers: [BC-10167581651, health-check, search-index]
prerequisites:
  - "Admin login; do the destructive steps (2, 4, 6) on a disposable copy, not a shared QA site"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: []
supersedes:
  - ora:regression/health-check-search-index-usable.md
fails_on: unrecorded
site_profile: required
---

# Health Check reports whether the search index is USABLE, not just present

## Why
The table check only proved `search_index` exists, so an empty index (search returns nothing) showed all green. `Health_Check::check_search_index()` (`includes/admin/class-health-check.php:299`) now FAILs on empty-with-listings, WARNs on a missing FULLTEXT index or < 90% indexed, PASSes otherwise.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Card lives at Listora > Settings > Advanced (`$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=advanced`); `?page=listora-health` 302s there (`includes/admin/class-admin.php:745`).
- Back up the index first (`wp db export` or `CREATE TABLE ... SELECT`).

## Steps

1. **Healthy site (positive control)**: open the Advanced tab as {admin}. **Expect**: a `.listora-health-card` titled "Search index", PASS icon (`listora-health-card__icon--pass`), naming the indexed row count and the FULLTEXT index. **Read-back**: the count equals `SELECT COUNT(*) FROM {prefix}listora_search_index`.
2. **Empty index**: `DELETE FROM {prefix}listora_search_index` with published listings present, reload. **Expect**: FAIL "The index is empty while N published listings exist, so search returns no results. Rebuild it." with a "Rebuild Search Index" link. Failing as all green is the original bug.
3. **Rebuild**: click the link (`admin.php?page=listora-settings&action=reindex&_wpnonce=...`, `Admin::handle_search_reindex()` `class-admin.php:768`). **Expect**: it schedules the batched reindex; after it runs the card returns to PASS and the count matches step 1.
4. **FULLTEXT missing only**: drop the FULLTEXT index, leave rows. **Expect**: WARN "The FULLTEXT index is missing, so keyword search falls back to slower, less accurate matching. Rebuilding recreates it." (not FAIL).
5. **Zero published listings, empty index**: PASS (no crying wolf on a new install).
6. **Partial index**: indexed < 90% of published -> WARN "Only X of Y published listings are indexed, so search results are incomplete."
7. **Legacy URL**: `?page=listora-health` returns 302 to the Advanced tab anchor.
8. **Large set**: the check uses one `COUNT(*)` of the index and `wp_count_posts()`, never a per-row loop; the page loads at 2000+ indexed rows.

## Role ladder
| Actor | Expect |
|---|---|
| {admin} (and anyone with `manage_listora_settings`) | sees the card |
| {editor} without the capability | permission error for the settings page |
| member / {guest} | cannot open wp-admin: redirect to login / dashboard |

## Pass criteria
1. PASS on a healthy index with the real count.
2. FAIL on empty-with-listings, WARN on missing FULLTEXT or < 90%, PASS on zero listings.
3. The rebuild link restores PASS.

## Fail diagnostics
| Symptom | File |
|---|---|
| All green with an empty index | `includes/admin/class-health-check.php:313-339` |
| Rebuild link does nothing | `includes/admin/class-admin.php:768` `handle_search_reindex()` |
| Card missing | `includes/admin/class-advanced-settings-tab.php:316` calling `class-health-check.php:147` `render_section()` |

## Not verified
Steps 2-6 mutate the index and were not run on the shared site; only step 1 and the 302 were observed (curl as admin).

## Cleanup
Restore the index from the backup or run the rebuild; recreate FULLTEXT via the rebuild.
