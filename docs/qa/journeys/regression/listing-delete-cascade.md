---
journey: listing-delete-cascade
plugin: wb-listora
priority: high
roles: [admin]
covers: [listing-data-eraser, delete-cascade, orphan-backfill, pro-listing-tables, leads-retention]
prerequisites:
  - "WP-CLI access (wp eval / wp listora cleanup)"
  - "Pro active for the need_responses / coupon_usage assertions (skip those rows on Free-only)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
changed: true
---

# Hard-deleting a listing must clean every listing-scoped table

Before 1.4.1 only the four index tables (search_index, field_index, geo, hours) cascaded on delete. `Listing_Data_Eraser` (hooked on `before_delete_post`) now deletes the rows of `reviews`, `review_votes`, `favorites`, `claims`, `services`, `analytics` (Free) and, through the `wb_listora_listing_data_deleted` action, Pro's `need_responses` and `coupon_usage`. It also deletes the listing's review photos and its own images. `payments` and `audit_log` are retained on purpose (accounting / audit trail). `leads` are NOT in the cascade (see step 6).

Verified via: `wp eval-file` against the live site (REST + DB). No browser surface: this is a system/CLI journey, run as admin only because hard delete has no member entry point. Role ladder does not apply.

## Setup
- Table prefix is `wp_listora_`. Seed with `wp eval-file` (scripts live outside the plugin). All seeded rows use title/message `QA-J1`.
- Column notes that make a seed row insert: `reviews(listing_id,user_id,title,content,status,ip_address)`, `review_votes(user_id,review_id)`, `favorites(user_id,listing_id)`, `claims(listing_id,user_id,proof_text)`, `services(listing_id,title,description,video_url)`, `analytics(listing_id,event_type,event_date)`, Pro `need_responses(need_id,listing_id,user_id,message)`, `coupon_usage(coupon_id,user_id,listing_id)`.

## Steps

### 1. Seed a probe listing with one row per table (positive control)
- **Action**: `wp_insert_post` a published `listora_listing` titled `QA-J1-cascade`, then insert one row per table above, all keyed on the new ID (votes keyed on the review's insert id).
- **Expect**: every `$wpdb->insert_id` is non-zero and `SELECT COUNT(*) ... WHERE listing_id = <ID>` returns 1 for each table (and 1 for `search_index`). A silent 0 here makes the rest vacuous.

### 2. Trash keeps data, drops index
- **Action**: `wp_trash_post( $id )`, recount.
- **Expect**: all data rows still 1; `search_index` 0 (observed). `wp_untrash_post` must restore without data loss.

### 3. Hard delete cascades
- **Action**: `wp_delete_post( $id, true )`, recount.
- **Expect**: 0 rows in reviews, review_votes, favorites, claims, services, analytics, need_responses, coupon_usage, search_index.
- **Fail**: `includes/core/class-listing-data-eraser.php:35` (`DATA_TABLES`), `:73` (`erase()`), `:115` (the `wb_listora_listing_data_deleted` action); Pro `includes/class-pro-plugin.php:149` and `:377` (`erase_listing_data`).

### 4. Orphan backfill
- **Action**: insert rows keyed on a non-existent listing ID (e.g. 999999991) into `favorites`, `reviews` (+ a `review_votes` row on it) and `need_responses`; run `wp listora cleanup`.
- **Expect**: all four tables return 0 rows for that ID afterwards (Pro table included, proving `wb_listora_purge_orphaned_listing_data` fired).
- **Known**: the CLI line `Orphaned listing rows purged: N` prints `0` even though rows were purged, because the daily-cleanup action run first already removed them (`includes/class-cli-commands.php:276` then `:285`). Assert on the table counts, not on that number. Reported as a product bug.
- **Also**: the same purge runs daily from the `wb_listora_daily_cleanup` cron (`class-listing-data-eraser.php:58`), so a site that never runs the CLI still heals.

### 5. Retention policy unchanged
- **Action**: `grep -n "payments\|audit_log" includes/core/class-listing-data-eraser.php` and Pro `erase_listing_data`.
- **Expect**: neither table is touched (the constant lists only the five Free tables; Pro lists only `need_responses`, `coupon_usage`).

### 6. Leads (current behaviour, owner decision open)
- **Action**: seed a `wp_listora_leads` row (`listing_id, owner_id, name, email, phone, message, created_at`) for the probe listing, hard delete the listing, run `Listing_Data_Eraser::purge_orphans()`.
- **Expect (observed 2026-10-12)**: the lead row SURVIVES both the delete and the orphan purge; it only ages out with the retention setting (default 12 months, `wb_listora_lead_retention_months`). Delete the probe row by hand afterwards. If the owner decides leads must follow the listing, this step flips to "0 rows".

## Pass criteria
Steps 1-5 as written; step 6 matches the observed behaviour or the owner has changed it.

## Cleanup
`DELETE FROM wp_listora_leads WHERE name='QA-J1'` and any `QA-J1` rows left in reviews / need_responses.
