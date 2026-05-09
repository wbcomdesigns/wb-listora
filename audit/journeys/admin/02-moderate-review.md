---
journey: admin-moderate-review
plugin: wb-listora
priority: critical
roles: [administrator]
covers: [reviews-rest-status-transition, review-status-hook]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin user (?autologin=1) with moderate_listora_reviews cap"
  - "At least 1 review with status='pending' exists in wp_listora_reviews"
estimated_runtime_minutes: 3
---

# Admin moderates a review

Admin opens Reviews moderation page, approves a pending review via the REST status-transition path (commit `36033b0` — `PUT /reviews/{id}` with `status` enum). Verifies the listing detail page now shows the review, `wb_listora_review_status_changed` fires, and a subscriber attempting the same transition is rejected.

## Setup

- Site: `$SITE_URL`
- Admin: `?autologin=1`
- Fixture: 1 pending review on a published listing. Capture `REVIEW_ID` and parent `LISTING_ID`.
  ```sql
  SELECT id, listing_id FROM wp_listora_reviews WHERE status='pending' LIMIT 1;
  ```

## Steps

### 1. Open Reviews admin page
- **Action**: `playwright_navigate $SITE_URL/wp-admin/admin.php?page=listora-reviews&autologin=1`
- **Expect**: review list table renders with status filter tabs (All / Pending / Approved / Rejected). The pending review is visible.
- **On fail**: `admin/class-reviews-page.php` (or wherever the page is registered)

### 2. Approve the review via inline action
- **Action**: click "Approve" on the review row
- **Expect**:
  - `PUT /wp-json/listora/v1/reviews/$REVIEW_ID` request with `{ "status": "approved" }`
  - 200 response
  - List refreshes, review moves to Approved filter
- **On fail**: `Reviews_Controller::update_review` cap check, the new status enum (commit 36033b0)

### 3. Verify DB
- **Action**:
  ```sql
  SELECT status FROM wp_listora_reviews WHERE id=$REVIEW_ID;
  ```
- **Expect**: `approved`

### 4. Verify hook fired
- **Action**: register a temp listener via wp eval OR check audit log if Pro is active:
  ```bash
  wp eval 'add_action("wb_listora_review_status_changed", function($id, $new, $listing) { error_log("STATUS_CHANGED:$id:$new"); }, 10, 3);'
  ```
  Then re-approve. Tail debug.log for `STATUS_CHANGED:$REVIEW_ID:approved`.
- **Expect**: log line present
- **On fail**: hook missing in `Reviews_Controller::update_review`

### 5. Verify the review now renders on the public detail page
- **Action**: `playwright_navigate $SITE_URL/listing/<slug>#reviews`
- **Expect**: DOM contains the review's text. Pre-fix (status enum missing): review remained pending forever, never visible publicly.
- **On fail**: `templates/blocks/listing-detail/tabs.php` reviews loop, `Reviews::get_for_listing` status filter

### 6. Negative test — subscriber cannot transition status
- **Action**: as `?autologin=tester` (subscriber, no `moderate_listora_reviews`), `curl -X PUT /wp-json/listora/v1/reviews/$REVIEW_ID -d '{"status":"pending"}' -H 'X-WP-Nonce: <subscriber-nonce>'`
- **Expect**: 403 with `code: listora_forbidden_status`
- **On fail**: cap check in `update_review` — must reject status changes from non-moderators while still allowing review-author content/rating edits

## Pass criteria

1. Admin transition via PUT returns 200 and updates `wp_listora_reviews.status`
2. `wb_listora_review_status_changed` fires with `($review_id, $new_status, $listing_id)` exactly once
3. Approved review renders on the public detail page
4. Subscriber attempting the status transition gets 403 `listora_forbidden_status`

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Approve button does nothing | admin page handler not registered | `admin/class-reviews-page.php` |
| 400 on PUT | status enum schema mismatch | `Reviews_Controller::register_routes` schema (commit 36033b0) |
| Review still pending after approve | status persistence | `Reviews::update_status` |
| Hook didn't fire | only fires on transition — re-approving an already-approved review is a no-op | `Reviews_Controller::update_review` transition guard |
| Subscriber got 200 (security regression) | cap not checked inside handler | inline `current_user_can('moderate_listora_reviews')` before the status branch |
