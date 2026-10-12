---
journey: review-pending-confirms-success
plugin: wb-listora
priority: high
roles: [member, owner, anonymous, admin]
covers:
  - D.review-pending-confirms-success
likely_files:
  - src/interactivity/store.js
  - includes/class-assets.php
  - includes/rest/class-reviews-controller.php
  - templates/blocks/listing-reviews/review-form.php
  - templates/blocks/listing-detail/tabs.php
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A review awaiting approval still reports that it saved

The member-facing twin of [[admin-save-confirms-success]]. After a successful `POST /listings/{id}/reviews` the form shows the server
message ("Review submitted and pending approval.") in `.listora-reviews__form-message` and, for a PENDING review, keeps the page
(no reload) and sets the submit button to **Awaiting approval**, disabled. Only an `approved` response reloads after 2 seconds, because
there the reloaded list is the durable confirmation. One review per member per listing is enforced server-side (409
`listora_already_reviewed`). Code: `src/interactivity/store.js:2862` (`submitDetailReviewForm`, branch at `:2908`; the Reviews
block has its own copy in `src/blocks/listing-reviews/view.js:55`).

Moderation is OFF by default (`reviews.auto_approve` false), so pending is the default experience on a fresh install.
Role ladder: the member submits; the owner cannot (no Write a Review button); logged out sees "Log in to write a review."; admin sees
the pending row in Listora > Reviews (control for "the row exists with status pending").

## Steps

1. Confirm moderation is on: `reviews.auto_approve` is falsy in `wb_listora_settings` (this site: `false`).
2. As a member who neither owns nor has reviewed listing L, open L, tab **Reviews**, **Write a Review**
   (`.listora-reviews__write-btn`). Pick a rating, title, content of at least the minimum length. **Submit Review**.
3. **Wait at least 4 seconds** (longer than the 2s reload timer). Asserting immediately passes even with the bug.
4. Assert the page did NOT reload: the text typed in step 2 is still in `#listora-detail-review-content`.
5. Assert `.listora-reviews__form-message` is visible (`hidden` removed) and reads "Review submitted and pending approval."
   (REST `message`; `jsReviewSubmitted` "Review submitted!" is only the fallback).
6. Assert the submit button is disabled and its label is "Awaiting approval".
7. Assert the row exists in `{prefix}listora_reviews` with `status = pending` (REST also returns `"status":"pending"`, verified for a
   new member on listing 17).
8. **Opposite direction**: set `reviews.auto_approve` true, repeat as a different member; assert the page DOES reload after ~2s
   and the review is visible with the count incremented. A fix that never reloads hides published reviews.
9. Another visit by the same member shows the pending notice ("You have already submitted a review for this listing. It is waiting
   for moderation and will appear once approved.", `.listora-reviews__pending-notice`) and no Write a Review button.
10. Restore `auto_approve` and delete the test reviews.

## Verified
2026-10-12: REST create returned 201 `{"status":"pending","message":"Review submitted and pending approval."}` and a pending row for a
fresh member (code path of steps 5-7). The earlier browser verification (2026-08-19, listing 17) predates the shared review-form
template; steps 2-4, 6 and 8 were not re-run in a browser this pass.

## Not verified
Steps 2-4, 6, 8, 9 in a browser (no reload, button label, 390px layout).
