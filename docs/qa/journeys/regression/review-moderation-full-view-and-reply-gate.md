---
journey: review-moderation-full-view-and-reply-gate
plugin: wb-listora
priority: high
roles: [administrator, anonymous]
covers: [reviews-admin, review-reply, multi-criteria-reviews]
prerequisites:
  - "At least one pending review and one approved review with criteria_ratings"
estimated_runtime_minutes: 6
covers_card: 10328137367
---

# Moderators can read the whole review, reply only to published ones, and visitors see the criteria

Regression sentinel for three review defects fixed together.

## Background

Listora → Reviews showed a 15-word excerpt and nothing else, offered Reply on pending and rejected reviews (and the REST route accepted it), and the per-criterion stars a reviewer gave were stored but never shown on the listing. The admin now has a native "Read full review" disclosure (full text + criteria), Reply is only offered on approved reviews and `POST /reviews/{id}/reply` returns 403 `listora_review_not_approved` otherwise, and Free hooks `wb_listora_render_review_criteria()` on `wb_listora_review_after_content` so both review templates (and theme overrides of them) show the criteria.

## Steps

### 1. Pending filter
- **Action**: as admin, open Listora → Reviews with the Pending filter.
- **Expect**: no row has a Reply link and no hidden reply form row exists. Rows with more than 15 words or with criteria show "Read full review"; opening it shows the full text and the criteria.

### 2. The route refuses a pending review
- **Action**: `POST /wp-json/listora/v1/reviews/<pending id>/reply` with the REST nonce.
- **Expect**: 403 `listora_review_not_approved`.

### 3. Approved control
- **Expect**: approved rows still offer Reply, and a reply to an approved review returns 200 and shows under the review. Clear the test reply afterwards.

### 4. Criteria on the frontend
- **Action**: logged out, open a listing whose approved review has `criteria_ratings`; open the Reviews tab.
- **Expect**: under that review, one row per configured criterion with its label and stars (`aria-label="N out of 5 stars"`). A review with no criteria shows nothing extra. At 390px the rows wrap without horizontal scroll.

## Fail diagnostics
- Reply on pending → `includes/admin/class-admin.php` lost the `'approved' === $rev['status']` gate.
- 200 on pending → `Reviews_Controller::owner_reply()` status check removed.
- No criteria on the listing → the `wb_listora_review_after_content` hook in `class-plugin.php`, or `wb_listora_get_review_criteria_scores()` not matching the type's criteria keys.
