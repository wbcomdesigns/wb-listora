---
journey: review-form-settings-take-effect
plugin: wb-listora
priority: high
roles: [anonymous, subscriber, listing_owner, administrator]
covers: [review-form, review-minlength-setting, rating-only-reviews, review-auto-approve, review-one-per-listing, review-allow-reply, review-pending-confirms-success, detail-page-review-form, D.review-pending-confirms-success]
prerequisites:
  - "Site reachable at $SITE_URL; Reviews feature ON"
  - "A published {listing} NOT owned by the test reviewer ({other}); {owner} owns it"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: ["ora:regression/review-minlength-reads-setting.md", "ora:regression/review-pending-confirms-success.md", "ora:regression/reviews-feature-disabled.md (sub-settings step)"]
---

# Each Settings > Reviews option changes the Write-a-Review form and its outcome

## Why
The review form and REST must follow the owner's Settings > Reviews choices (minimum length, auto-approve, one per listing, replies); a form that ignores them, or a pending review that looks lost, loses reviews and trust.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Settings key `wb_listora_settings['reviews']` (`min_length`, `auto_approve`, `one_per_listing`, `allow_reply`; `require_login` no longer exists: login is always required).
- The listing page Reviews tab and the Reviews block share ONE template, `templates/blocks/listing-reviews/review-form.php` (`form_id` `listora-detail-review` -> `#listora-detail-review-content`; default `listora-review`).
- Restore `min_length=20`, `auto_approve=false` at the end.

## Steps

### 1. Minimum length 50
- **Action**: set `reviews.min_length=50`, open the listing as {other}, Reviews > Write a Review.
- **Expect**: `#listora-detail-review-content[name=content]` has `required`, `minlength="50"`, placeholder exactly `Share your experience (minimum 50 characters)`; with `1`: `(minimum 1 character)` (singular); no `\n`, no leading/trailing whitespace.
- **Read-back**: REST `POST /listings/<id>/reviews` with a 10-char body returns `400 listora_review_too_short` "Review must be at least 50 characters."; raw server HTML (curl) carries the same attributes.

### 2. Minimum length 0 allows rating-only
- **Action**: set `min_length=0`, reload.
- **Expect**: textarea has NO `required`, NO `minlength`, placeholder exactly `Share your experience (optional)`; label has no `*`; the rating radios and title keep `required`.

### 3. Submit with min_length 0 and empty text
- **Action**: choose 5 stars, a title, leave text empty, Submit Review.
- **Expect** (target): the review is accepted (`201`) and appears pending, like the Reviews block does.
- **expected-fail: listing-page form ignores min_length 0** - `submitDetailReviewForm()` returns early on empty `content` (`src/interactivity/store.js:2870`, `if ( ! rating || ! title || ! content ) return;`) while the block honours the textarea's `required` (`src/blocks/listing-reviews/view.js:72-73`).
- **Read-back**: `listora_reviews` row exists with empty content.

### 4. Pending review confirms it saved (auto_approve OFF)
- **Action**: `auto_approve` false; as {other} submit a valid review; wait at least 4 seconds (longer than the 2s reload timer; asserting immediately passes with the bug).
- **Expect**: no page reload (text typed is still in `#listora-detail-review-content`); `.listora-reviews__form-message` visible reading "Review submitted and pending approval." (REST `message`; `jsReviewSubmitted` is only the fallback); submit button disabled, label "Awaiting approval".
- **Read-back**: row `status='pending'`; REST returned `"status":"pending"`.

### 5. Returning to a pending review
- **Action**: {other} revisits the listing.
- **Expect**: `.listora-reviews__pending-notice` "You have already submitted a review for this listing. It is waiting for moderation and will appear once approved." and no Write a Review button.

### 6. auto_approve ON reloads and shows the review (opposite direction)
- **Action**: set `auto_approve` true; as a different member submit.
- **Expect**: `201 "status":"approved"`, page reloads after ~2s, review visible, count incremented. A fix that never reloads hides published reviews.
- **Read-back**: row `status='approved'`.

### 7. one_per_listing
- **Action**: second review by the same member with `one_per_listing` ON, then OFF.
- **Expect**: ON `409 listora_already_reviewed`; OFF `201`. (`wb_listora_one_review_per_listing()`, `wb-listora.php:494`.)

### 8. allow_reply
- **Action**: `allow_reply` OFF, then ON; owner opens the dashboard Reviews tab and calls `POST /reviews/<id>/reply`.
- **Expect**: OFF: reply UI hidden and `403 listora_replies_disabled`; ON: UI present, `200`. (`wb_listora_review_replies_enabled()`, `wb-listora.php:471`.)

### 9. Edit path obeys the same minimum (cross-check)
- **Action**: with `min_length=100`, author `PUT /reviews/<own id> {"content":"x"}` then a 100-char body.
- **Expect**: `400 listora_review_too_short`, then `200`; stored `content` unchanged after the 400. Full edit/gate matrix: `customer/review-actions-gated-on-every-route.md` step 1.
- **Read-back**: `SELECT content ...` after the 400. **fails_on**: 2394e3c. Not verified live.

### 10. Layout
- **Action**: steps 1-4 at 390 and in dark mode.
- **Expect**: form fits, `scrollWidth <= 390`, textarea/submit >= 40px; placeholder readable in dark mode. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {other} (non-owner, not yet reviewed) | **Write a Review** (`.listora-reviews__write-btn`), steps 1-8 |
| Member who already reviewed | pending notice, no button (step 5) |
| {owner} | no Write a Review button; `403 listora_own_listing` |
| {guest} | "Log in to write a review." (`.listora-reviews__login-notice`) |
| {admin} non-owner (control) | behaves as a member |

## Pass criteria
1. Textarea attributes and placeholder follow `min_length` (N, 1, 0) on both surfaces.
2. A pending review keeps the page, shows the server message and "Awaiting approval"; `auto_approve` ON reloads.
3. `one_per_listing` and `allow_reply` each enforce on REST and in the UI.
4. Step 3 is the one known failing step (listing-page rating-only submit).

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Wrong minlength/placeholder | setting read | `templates/blocks/listing-reviews/review-form.php:32-33`, `:75-80`, `tabs.php:722` |
| Page reloads on pending / button not disabled | branch | `src/interactivity/store.js:2862`, branch `:2908`; block copy `src/blocks/listing-reviews/view.js:55` |
| Rating-only submit does nothing | early return | `src/interactivity/store.js:2870` |
| Reply works with replies off | gate | `wb-listora.php:471` |

## Not verified
Steps 3, 4-6, 8-10 in a browser (REST halves of 1, 2, 4 were run); 390px layout.

## Cleanup
Restore `reviews.min_length=20`, `auto_approve=false`, `one_per_listing`, `allow_reply`; delete the QA reviews.
