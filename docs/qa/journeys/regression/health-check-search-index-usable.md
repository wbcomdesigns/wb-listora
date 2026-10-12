---
journey: health-check-search-index-usable
plugin: wb-listora
priority: high
roles: [admin, editor, anonymous]
covers:
  - BC 10167581651
  - health-check
likely_files:
  - includes/admin/class-health-check.php
prerequisites:
  - "Admin login; do the destructive steps (2, 4) on a disposable copy, not the shared QA site"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# Health Check reports whether the search index is USABLE

The table check only proved `search_index` exists. `Health_Check::check_search_index()` (`includes/admin/class-health-check.php:299`) now FAILs when published listings exist and the index is empty, WARNs when the FULLTEXT index is missing or fewer than 90% of published listings are indexed, and PASSes otherwise (including a site with zero published listings). Where: Listora > Settings > Advanced tab (the standalone Health Check page moved; `?page=listora-health` 302s to `admin.php?page=listora-settings&tab=advanced#advanced`, `includes/admin/class-admin.php:745`).

Verified via: curl of the Advanced tab as admin: the card reads "Search index - 2,811 listings indexed, with t..." PASS; legacy URL redirect 302 confirmed, 2026-10-12. Steps 2-5 mutate the index and were NOT run on the shared site.

## Steps

1. **Healthy site (POSITIVE CONTROL)**: open `/wp-admin/admin.php?page=listora-settings&tab=advanced`.
   - **Expect**: a `.listora-health-card` titled "Search index", PASS icon (`listora-health-card__icon--pass`), naming the indexed row count and the FULLTEXT index. (Verified: 2,811 indexed.)
2. **Empty index**: `DELETE FROM {prefix}listora_search_index` with published listings present, reload.
   - **Expect**: FAIL "The index is empty while N published listings exist, so search returns no results. Rebuild it." with a "Rebuild Search Index →" link.
   - **Fail if** the page is all green (the original bug).
3. **Rebuild**: click the link (`admin.php?page=listora-settings&action=reindex&_wpnonce=...`, handled by `Admin::handle_search_reindex()`, `class-admin.php:768`).
   - **Expect**: it schedules the batched reindex; after it runs the card returns to PASS.
4. **FULLTEXT missing only**: drop the FULLTEXT index, leave rows.
   - **Expect**: WARN "The FULLTEXT index is missing, so keyword search falls back to slower, less accurate matching. Rebuilding recreates it." (not FAIL).
5. **Zero published listings, empty index**: PASS (no crying wolf on a new install).
6. **Partial index**: indexed < 90% of published -> WARN "Only X of Y published listings are indexed, so search results are incomplete."
7. **Role ladder**: administrator (and anyone with `manage_listora_settings`) sees the card; editor `qa_editor` without that capability gets the permission error for the settings page; member and anonymous cannot open wp-admin/this URL (redirect to login / dashboard). Admin is the actor.
8. **Large set**: the check uses one `COUNT(*)` of the index and `wp_count_posts()`, never a per-row loop; it is fine at 2,811 indexed rows (page loaded).

## Fail diagnostics

| Symptom | File |
|---|---|
| All green with an empty index | `includes/admin/class-health-check.php:313-339` |
| Rebuild link does nothing | `includes/admin/class-admin.php:768` `handle_search_reindex()` |
| Card missing | `includes/admin/class-advanced-settings-tab.php:316` calling `class-health-check.php:147` `render_section()` |
