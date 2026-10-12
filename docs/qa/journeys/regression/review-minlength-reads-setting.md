---
journey: review-minlength-reads-setting
plugin: wb-listora
priority: normal
roles: [anonymous, subscriber, owner, administrator]
covers: [review-form, review-minlength-setting, rating-only-reviews, detail-page-review-form]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing NOT owned by the test reviewer (capture LISTING_ID + permalink, e.g. /listing/flames-smoke-bbq/)"
  - "reviews feature enabled"
estimated_runtime_minutes: 4
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The Write-a-Review form honours Settings > Reviews > Minimum length

The listing page's Reviews tab and the standalone Reviews block share ONE template (2.0.0):
`templates/blocks/listing-reviews/review-form.php`, included by `templates/blocks/listing-detail/tabs.php:722` with
`form_id = listora-detail-review` (so the textarea is `#listora-detail-review-content`) and by `reviews.php:92` (default
`listora-review`, `#listora-review-content`). The textarea reads `reviews.min_length` (default 20): `required minlength="N"` and
"Share your experience (minimum N characters)" when N > 0, and no `required`, no `minlength` and "Share your experience (optional)"
when N = 0. The server enforces the same rule on create AND edit, counted in characters
(`Reviews_Controller::validate_review_content()`, `includes/rest/class-reviews-controller.php:1027`).

Role ladder: a logged-in non-owner who has not reviewed sees **Write a Review** (`.listora-reviews__write-btn`); the owner and a member who
already reviewed do not (a pending notice instead: "You have already submitted a review for this listing. It is waiting for moderation
and will appear once approved."); logged out sees "Log in to write a review." (`.listora-reviews__login-notice`); admin non-owner
behaves as a member (control). Setting key: `wb_listora_settings['reviews']['min_length']` (the `require_login` key no longer exists).

## Steps (steps 1-3 verified over curl as subscriber2; setting restored to 20)

### 1. Minimum length 50
- **Action**: set `reviews.min_length = 50` (Settings > Reviews, or `update_option`), open the listing as the member, open Reviews > Write a Review.
- **Expect** on `#listora-detail-review-content[name=content]`: `required`, `minlength="50"` (not 20), placeholder
  "Share your experience (minimum 50 characters)". With 1: "minimum 1 character" (singular).

### 2. Minimum length 0 -> rating-only allowed
- **Action**: set `reviews.min_length = 0`, reload.
- **Expect** on the textarea: NO `required`, NO `minlength`, placeholder exactly "Share your experience (optional)"; the label has no
  `*`. The "Your Rating" radios (`input[name="overall_rating"]`) and the title (`input[name=title]`) keep `required`.

### 3. Parity with the Reviews block
- **Expect**: both surfaces render the same attributes for the same value (same template file; verified by code).

### 4. Submission honours the contract
- **min_length = 0, listing page**: choose 5 stars, a title, leave the text empty, **Submit Review**.
  - **Target**: accepted, review appears (pending or approved).
  - **Observed (BUG)**: nothing happens. `submitDetailReviewForm()` returns early on an empty `content`
    (`src/interactivity/store.js:2870`, `if ( ! rating || ! title || ! content ) return;`), while the Reviews block honours the
    textarea's `required` (`src/blocks/listing-reviews/view.js:72-73`). See PRODUCT BUG in the report.
- **min_length = 50**: a 10-char body is stopped client-side (HTML5 `minlength`) and, via REST, `POST /listings/{id}/reviews` returns
  400 `listora_review_too_short` "Review must be at least 50 characters.".

### 5. Cleanup
Restore `reviews.min_length` to 20; delete the QA review.

## Fail diagnostics
`templates/blocks/listing-reviews/review-form.php:32-33` (setting read), `:75-80` (attributes), `tabs.php:722`,
`src/interactivity/store.js:2870`, `src/blocks/listing-reviews/view.js:72`.

## Not verified
Step 4 submit was read in code, not clicked; 390px layout of the form.
