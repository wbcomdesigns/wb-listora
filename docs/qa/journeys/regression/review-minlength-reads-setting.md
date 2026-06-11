---
journey: review-minlength-reads-setting
plugin: wb-listora
priority: normal
roles: [anonymous, subscriber]
covers: [review-form, review-minlength-setting, rating-only-reviews, detail-page-review-form]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing NOT owned by the test reviewer (capture LISTING_ID + slug)"
  - "reviews feature enabled"
estimated_runtime_minutes: 4
covers_card: null
covers_commit: 236dc03
---

# Detail-page Write-a-Review form honours Settings → Reviews → Minimum length

Regression sentinel for M3 (`236dc03`). The Write-a-Review form on the listing
**detail** page hardcoded `minlength="20"` + `required` on the content textarea
regardless of the configured minimum. It must instead read
`reviews.min_length` from settings, matching the standalone review-form block
(`listing-reviews/review-form.php`). The detail-page form lives in
`templates/blocks/listing-detail/tabs.php:603-648`:
`$detail_review_min_length` from the setting (default 20),
`$detail_review_required = $detail_review_min_length > 0`.

## Setup

- Site: `$SITE_URL`; `LISTING_ID` + slug (not owned by the reviewer).
- Reviewer: a logged-in subscriber who hasn't reviewed this listing.

## Steps

### 1. Minimum length = 50 → textarea is required with minlength="50"
- **Action**:
  ```
  wp eval "\$s = wb_listora_get_setting('reviews'); \$s['min_length'] = 50; update_option('wb_listora_settings', array_merge((array) get_option('wb_listora_settings'), ['reviews'=>\$s]));"
  ```
  (Set `reviews.min_length = 50` via Settings → Reviews, then) navigate `$SITE_URL/listing/<slug>/?autologin=<reviewer>` → open the Reviews tab → Write a Review.
- **Expect** on `#listora-detail-review-content` (`textarea[name="content"]`):
  - has the `required` attribute,
  - `minlength="50"` (NOT 20),
  - placeholder reads "Share your experience (minimum 50 characters)".
- **On fail**: `tabs.php:603-648` — `$detail_review_min_length` not read from the setting, or the `required`/`minlength` conditionals hardcoded.

### 2. Minimum length = 0 → rating-only reviews allowed
- **Action**: set `reviews.min_length = 0`; reload the form.
- **Expect** on the content textarea:
  - NO `required` attribute,
  - NO `minlength` attribute,
  - placeholder reads exactly "Share your experience (optional)".
  - The "Your Rating" radio group still carries `required` (rating is always mandatory).
- **On fail**: `$detail_review_required` not derived from `min_length > 0`.

### 3. Parity with the standalone review-form block
- **Action**: compare the detail-page form against `listing-reviews/review-form.php` for the SAME setting value (render a page with the listing-reviews block).
- **Expect**: both forms render identical `required` + `minlength` behaviour for the same `reviews.min_length`. The two templates must not diverge.
- **On fail**: `tabs.php` and `review-form.php` read the setting differently.

### 4. Submission honours the contract
- **Action**: with `min_length = 0`, submit a rating-only review (5 stars, empty content) from the detail form.
- **Expect**: accepted (HTTP 200), review appears. With `min_length = 50`, a 10-char body is rejected client-side (HTML5 `minlength`) and server-side.

### Cleanup
- Restore `reviews.min_length` to its default (20); delete the QA review.

## Notes
- The two review forms (detail-tab + reviews-block) are the only customer surfaces that collect a review — keep them in lockstep with the setting. If a 3rd surface is added, extend this journey.
