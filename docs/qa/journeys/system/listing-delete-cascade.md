---
journey: listing-delete-cascade
plugin: wb-listora
priority: high
roles: [admin]
covers: [listing-data-eraser, delete-cascade, orphan-backfill, pro-listing-tables, leads-retention]
prerequisites:
  - "WP-CLI access (`wp eval` / `wp listora cleanup`) at $WP_PATH"
  - "Pro active for the `need_responses` / `coupon_usage` assertions (skip those rows on Free-only)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
site_profile: required
supersedes: []
requires: "wb-listora-pro (Pro table rows only)"
---

# Hard-deleting a listing cleans every listing-scoped table

## Why
Before 1.4.1 only four index tables cascaded. `Listing_Data_Eraser` (hooked on `before_delete_post`) now removes reviews, votes, favorites, claims, services and analytics rows (and Pro's `need_responses`, `coupon_usage` through `wb_listora_listing_data_deleted`) plus the listing's review photos and images; orphaned rows left by a missed delete break counts and privacy.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). System/CLI journey run as admin only: hard delete has no member entry point, so the role ladder is a single rung.
- Table prefix `{prefix}listora_`. Seed with `wp eval-file` (scripts live outside the plugin); all seeded rows use title/message `QA-J1`. Seed columns: `reviews(listing_id,user_id,title,content,status,ip_address)`, `review_votes(user_id,review_id)`, `favorites(user_id,listing_id)`, `claims(listing_id,user_id,proof_text)`, `services(listing_id,title,description,video_url)`, `analytics(listing_id,event_type,event_date)`, Pro `need_responses(need_id,listing_id,user_id,message)`, `coupon_usage(coupon_id,user_id,listing_id)`.
- Retention by design: `payments` and `audit_log` are NOT cascaded (accounting / audit trail).
- Code: `includes/core/class-listing-data-eraser.php:35` (`DATA_TABLES`), `:58`, `:73` (`erase()`), `:115`; Pro `includes/class-pro-plugin.php:149`, `:377` (`erase_listing_data`).

## Steps

### 1. Seed a probe listing with one row per table (positive control)
- **Action**: `wp_insert_post` a published `listora_listing` titled `QA-J1-cascade`, then insert one row per table keyed on the new ID (votes keyed on the review's insert id).
- **Expect**: every `$wpdb->insert_id` non-zero.
- **Read-back**: `SELECT COUNT(*) ... WHERE listing_id=<ID>` is 1 for each table (and 1 for `search_index`). A silent 0 makes the rest vacuous.

### 2. Trash keeps data, drops index
- **Action**: `wp_trash_post( $id )`, recount; `wp_untrash_post`.
- **Expect**: all data rows still 1; `search_index` 0; untrash restores without data loss.

### 3. Hard delete cascades
- **Action**: `wp_delete_post( $id, true )`, recount.
- **Expect**: 0 rows in reviews, review_votes, favorites, claims, services, analytics, need_responses, coupon_usage, search_index.
- **Read-back**: counts per table.

### 4. Orphan backfill
- **Action**: insert rows keyed on a non-existent listing ID (e.g. 999999991) into `favorites`, `reviews` (+ a `review_votes` row), `need_responses`; run `wp listora cleanup`.
- **Expect**: all four tables return 0 rows for that ID (Pro table included, proving `wb_listora_purge_orphaned_listing_data` fired).
- **Read-back**: assert on table counts. **expected-fail: `wp listora cleanup` prints `Orphaned listing rows purged: 0`** although rows were purged (`includes/class-cli-commands.php:276` then `:285`: the daily-cleanup action run first already removed them); the printed number should report the rows purged. The same purge runs daily from the `wb_listora_daily_cleanup` cron (`class-listing-data-eraser.php:58`), so a site that never runs the CLI still heals.

### 5. Retention policy unchanged
- **Action**: `grep -n "payments\|audit_log" includes/core/class-listing-data-eraser.php` and Pro `erase_listing_data`.
- **Expect**: neither table is touched (constant lists only the five Free tables; Pro lists only `need_responses`, `coupon_usage`).

### 6. Leads (current behaviour, owner decision open)
- **Action**: seed a `listora_leads` row for the probe listing, hard delete the listing, run `Listing_Data_Eraser::purge_orphans()`.
- **Expect** (observed): the lead row SURVIVES both; it ages out with the retention setting (`wb_listora_lead_retention_months`, default 12; `admin/leads-admin-retention-privacy-ownership.md` step 3). If the owner decides leads must follow the listing, this step flips to "0 rows".
- **Read-back**: delete the probe lead by hand.

### 7. Scale
- **Action**: delete a listing that has 2000+ review/vote rows (QA-prefixed seed).
- **Expect**: the delete completes within the request time limit using indexed `listing_id` deletes. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {admin} | steps 1-7 (hard delete has no member entry point) |
| {other} (member) | cannot hard-delete another's listing (`wp_delete_post` cap); own trash keeps data (step 2) |

## Pass criteria
1. Steps 1-5 as written; step 6 matches the observed behaviour or the owner has changed it.
2. Trash never deletes data; hard delete and orphan purge clean every listed table, none of `payments`/`audit_log`.

## Fail diagnostics
`includes/core/class-listing-data-eraser.php:35,58,73,115`; Pro `includes/class-pro-plugin.php:149,377`; `includes/class-cli-commands.php:276,285`.

## Not verified
Step 7 and the member row; Free-only mode rows for Pro tables.

## Cleanup
`DELETE FROM {prefix}listora_leads WHERE name='QA-J1'` and any `QA-J1` rows left in reviews / need_responses.
