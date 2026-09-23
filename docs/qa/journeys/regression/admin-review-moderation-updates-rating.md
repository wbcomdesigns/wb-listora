---
journey: admin-review-moderation-updates-rating
plugin: wb-listora
priority: critical
roles: [administrator]
covers: [reviews-admin, rating-aggregate, review-hooks]
prerequisites:
  - "A pending review on a listing that is in the search index"
estimated_runtime_minutes: 6
covers_card: null
---

# Moderating a review in wp-admin updates the listing's rating

Regression sentinel for Listora → Reviews approve / reject / delete, single and bulk.

## Background

The moderation screen wrote the review row directly. Nothing recalculated the listing's `avg_rating` / `review_count` in `search_index`, and no review hook fired (`before_/after_update_review`, `review_status_changed`, `before_/after_delete_review`). An approved review never counted toward the listing's stars or review count on cards, search or sort. Found in the 2026-09-23 hooks audit: after an admin Approve, listing 6324 had 2 approved reviews while its index said 0. `Admin::moderate_review()` now dispatches the same REST routes the API uses, so wp-admin gets identical side effects.

## Steps

### 1. Baseline
- **Action**: pick a pending review; read its listing's `search_index.avg_rating` / `review_count` and the true approved average and count from `listora_reviews`.

### 2. Approve (row link)
- **Expect**: `review_count` rises by one and `avg_rating` matches the true approved average.

### 3. Reject, then approve again
- **Expect**: the count drops back, then rises again. Each time the index matches the reviews table.

### 4. Bulk approve
- **Action**: tick one pending row, Bulk Actions → Approve → Apply.
- **Expect**: "Bulk action applied." and that listing's aggregate matches. (Also needs `admin-bulk-actions-submit.md` - before that fix Apply posted nothing.)

### 5. Hooks fire
- **Action**: with a throwaway mu-plugin logging `wb_listora_review_status_changed`, approve once.
- **Expect**: one call with `(review_id, 'approved', listing_id)`.

### 6. Health sweep
- **Expect**: `SELECT COUNT(*)` of listings whose `review_count` differs from their approved review count is 0.

### 7. Restore the reviews you changed and rebuild the index (Settings → Rebuild Search Index, or `wp listora reindex`).

## Fail diagnostics
- Aggregate stale after approve → `includes/admin/class-admin.php` writes the row again instead of calling `moderate_review()`.
- Nothing happens and no error → the REST permission check refused. Is the Reviews feature on, and does the user have `moderate_listora_reviews`?
