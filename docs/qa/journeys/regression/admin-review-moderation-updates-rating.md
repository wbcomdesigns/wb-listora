---
journey: admin-review-moderation-updates-rating
plugin: wb-listora
priority: critical
roles: [administrator, editor, subscriber]
covers: [reviews-admin, rating-aggregate, review-hooks]
prerequisites:
  - "A listing in the search index and a member who can review it (not the listing's owner). Create both as QA-J1- test data: a listing QA-J1-rev, review posted as subscriber2 via POST /listora/v1/listings/{id}/reviews (status pending)"
estimated_runtime_minutes: 6
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
changed: true
---

# Moderating a review in wp-admin updates the listing's rating

Sentinel for Listora > Reviews approve / reject / delete, single and bulk. The screen once wrote the review row directly: nothing recalculated `avg_rating` / `review_count` in `{prefix}listora_search_index` and no review hook fired, so an approved review never counted on cards, search or sort. `Admin::moderate_review()` (`includes/admin/class-admin.php:1541-1554`) now dispatches the same REST routes the API uses (`PUT /listora/v1/reviews/{id}` with `status`, `DELETE`), so wp-admin gets identical side effects.

## Steps (verified 2026-10-12 against a QA-J1- listing and review, both deleted afterwards)

### 1. Baseline
- **Action**: a pending review exists (`listora_reviews.status = 'pending'`); read the listing's index row: `SELECT avg_rating, review_count FROM {prefix}listora_search_index WHERE listing_id=<id>`.
- **Expect**: `0.00` / `0` before approval (pending reviews do not count).

### 2. Approve (row link)
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-reviews&status=all&s=QA-J1`, use the row's Approve action (`review_action=approve&review_id=<id>` with the `listora_review_action` nonce).
- **Expect**: redirect to `...&listora_notice=approve&n=1&same=0`, notice "1 review approved. It now shows on the listing."; index row `4.00` / `1` (equals the true approved average and count).

### 3. Reject, then approve again
- **Expect**: reject -> notice "1 review rejected. It no longer shows on the listing." and the index returns to `0.00` / `0`; approve again -> `4.00` / `1`. Each time the index matches the reviews table. POSITIVE CONTROL for "rejected reviews do not count": the drop to 0 proves the aggregate is recomputed, not just incremented.

### 4. Bulk
- **Action**: Reviews list, tick one row, Bulk actions -> Reject (or Approve), Apply (`form.listora-admin-table-form`, nonce `listora_review_bulk`).
- **Expect**: redirect `listora_notice=reject&n=1`, the notice above, and that listing's aggregate matches (verified: `0.00` / `0` after a bulk reject). Also see `admin-bulk-actions-submit.md`.
- **Already-in-state**: acting on a review that is already in that state (or deleted in another tab) gives the info notice "1 review was already in that state or no longer exists (perhaps changed in another tab or by another moderator) and was left as it was." with `same=1`.

### 5. Delete
- **Action**: row Delete.
- **Expect**: notice "1 review deleted."; the review row is gone and the listing's aggregate is recomputed (`0.00` / `0` verified).

### 6. Hooks fire
- **Action**: a throwaway mu-plugin logging `wb_listora_review_status_changed`, approve once.
- **Expect**: one call with `( review_id, 'approved', listing_id )` (`includes/rest/class-reviews-controller.php:753-758`); delete runs the `wb_listora_before_delete_review` filter and fires `wb_listora_after_delete_review`. (Hook arguments verified in source; not run with a logger.)

### 7. Health sweep
- **Expect**: `SELECT COUNT(*)` of listings whose `review_count` differs from their approved review count is 0.

### 8. Role ladder
| Role | Reviews screen |
|---|---|
| administrator (control) | steps 2-5 |
| editor (qa_editor, `moderate_listora_reviews`) | same actions and the same aggregate side effects (they go through REST with the editor's cap) |
| subscriber / review author | 403 on the screen; `PUT /reviews/{id}` with `status` is refused |
| logged out | wp-login.php redirect |

### 9. Large list
The Reviews list pages and filters (status, rating, listing type, period, reply, reported) on the full dataset without loading every row.

### 10. Restore
Delete the QA-J1- review and listing; rebuild the index if you touched real listings (Settings > Advanced > Rebuild Search Index, or `wp listora reindex`).

## Fail diagnostics
| Symptom | File |
|---|---|
| Aggregate stale after approve | admin writes the row instead of calling `moderate_review()`: `includes/admin/class-admin.php:1541`, handler `:1560-1620` |
| Nothing happens, no error | REST permission refused (Reviews feature off, or the user lacks `moderate_listora_reviews`): `includes/rest/class-reviews-controller.php:684-700` |
| Notice missing | `includes/admin/class-admin.php:1784-1808` |

Verified via: curl as administrator with real nonce'd links and a bulk POST, DB reads through wp eval. Editor/subscriber rows and the hook logger were not executed.
