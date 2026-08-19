---
journey: review-pending-confirms-success
plugin: wb-listora
priority: high
covers:
  - D.review-pending-confirms-success
likely_files:
  - src/interactivity/store.js
  - includes/class-assets.php
  - includes/rest/class-reviews-controller.php
  - templates/blocks/listing-reviews/review-form.php
  - templates/blocks/listing-detail/tabs.php
---

# A review awaiting approval still reports that it saved

The member-facing twin of [[admin-save-confirms-success]], missed when that
one was fixed. The review form DID show "Review submitted and pending
approval." — and then unconditionally called `window.location.reload()` two
seconds later. On the pending path the reload destroyed the only confirmation
AND put nothing in its place, because a pending review is not rendered in the
list. The member watched the form clear, saw the review count unchanged, and
had no way to tell whether anything had been saved. It had been: the row was
in `listora_reviews` with `status = pending` the whole time.

Moderation is OFF by default (`reviews.auto_approve` is unset, read through a
bare `! empty()`), so this was the DEFAULT experience on a fresh install, not
an edge case.

The reload is now conditional. Approved reviews still reload, because there
the reloaded list IS the durable confirmation. Pending reviews keep the
message and leave the submit button disabled — one review per user per listing
is enforced server-side (409 `listora_already_reviewed`), so re-enabling it
would only earn the member an error.

## Steps

1. Confirm moderation is on (the default): `reviews.auto_approve` falsy in
   `wb_listora_settings`.
2. As a member who neither owns nor has reviewed listing L, open L, click the
   Reviews tab, then "Write a Review". Fill rating, title and content. Submit.
3. **Wait at least 4 seconds** — longer than the old 2s reload timer. This
   wait is the test; asserting immediately after submit passes even when the
   bug is present.
4. Assert the page did NOT reload: the text typed in step 2 is still in the
   form. A cleared form is the regression.
5. Assert `.listora-reviews__form-message` is visible and reads "Review
   submitted and pending approval."
6. Assert the submit button is disabled and labelled "Awaiting approval".
7. Assert the row exists in `{prefix}listora_reviews` with `status = pending`.
8. **Opposite direction — do not over-fix.** Set `auto_approve` true, repeat
   as a different member, and assert the page DOES reload and the new review
   is visible in the list with the count incremented. A fix that never
   reloads hides published reviews, which is the worse bug.
9. Restore `auto_approve` and delete the test reviews.

## Verified

2026-08-19, wb-listora.local, Free+Pro 1.6.0 combo, listing 17.
Pending path: typed value survived 4s (no reload), message green and visible,
button "Awaiting approval" disabled, row 140 saved `status=pending`.
Approved path: page reload confirmed via a lost `window` stamp, Reviews tab
5 -> 6, new review rendered. State restored, both test reviews removed.
