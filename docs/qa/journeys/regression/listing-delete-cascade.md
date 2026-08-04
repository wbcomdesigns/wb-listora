---
journey: listing-delete-cascade
plugin: wb-listora
priority: high
roles: [admin]
covers: [listing-data-eraser, delete-cascade, orphan-backfill, pro-listing-tables]
prerequisites:
  - "WP-CLI access"
  - "Pro active for the need_responses / coupon_usage assertions (skip those rows on Free-only)"
estimated_runtime_minutes: 3
---

# Hard-deleting a listing must clean every listing-scoped table

BC 10156782139: before 1.4.1 only Search_Indexer's four index tables
(search_index, field_index, geo, hours) cascaded on delete. reviews,
review_votes, favorites, claims, services, analytics (Free) and
need_responses, coupon_usage (Pro) kept their rows forever. 1.4.1 adds
`Listing_Data_Eraser` on `before_delete_post` + the
`wb_listora_listing_data_deleted` action Pro listens to, and an orphan
backfill in `wp listora cleanup` (+ `wb_listora_purge_orphaned_listing_data`
for Pro). `payments` and `audit_log` rows are INTENTIONALLY retained
(accounting policy / audit trail).

## Steps

### 1. Seed a probe listing with one row per listing-scoped table
- **Action**: `wp eval` — create a `listora_listing` post; insert one row
  each into `reviews` (then `review_votes` on that review's id),
  `favorites`, `claims`, `services`, `analytics`, and with Pro:
  `need_responses`, `coupon_usage` — all keyed on the new listing ID.
- **Expect**: every insert lands (check `$wpdb->insert_id` / row counts —
  a silent 0-row seed makes the rest of the journey vacuous).

### 2. Trash keeps data, drops index
- **Action**: `wp_trash_post( $id )` → recount
- **Expect**: all DATA rows intact; `search_index` row gone (indexer
  handles trash). Restore must be lossless.

### 3. Hard delete cascades everything
- **Action**: `wp_delete_post( $id, true )` → recount all tables
- **Expect**: ZERO rows in all Free data tables + both index and Pro
  tables. `review_votes` gone even though it has no listing_id column
  (deleted via its parent review before the reviews rows).

### 4. Orphan backfill
- **Action**: insert rows keyed on a non-existent listing ID (e.g.
  99999999) into `favorites`, `reviews` (+ a vote on it),
  `need_responses` → run `wp listora cleanup`
- **Expect**: output includes `Orphaned listing rows purged: N` (N ≥ 3)
  and recounts return zero, including the Pro table (proves the
  `wb_listora_purge_orphaned_listing_data` listener fires).

### 5. Retention policy unchanged
- **Action**: confirm no code path deletes `payments` or `audit_log` rows
  by listing_id (grep `Listing_Data_Eraser::DATA_TABLES` and Pro's
  `erase_listing_data`)
- **Expect**: neither table appears — financial records and audit trail
  survive the listing.
