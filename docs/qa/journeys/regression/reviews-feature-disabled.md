---
journey: reviews-feature-disabled
plugin: wb-listora
priority: high
roles: [anonymous, subscriber, administrator]
covers: [reviews-feature-toggle, reviews-display-gating, reviews-rest-403, review-subsettings]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing WITH >=1 approved review exists (capture as LISTING_ID + slug)"
  - "Reviews feature ON at start"
estimated_runtime_minutes: 6
covers_card: 9895809632
---

# Reviews feature toggle hides ALL surfaces + sub-settings enforced

Regression sentinel for the reviews fix wave. Disabling the Reviews feature must hide every read/write surface (not just the form), and the five review sub-settings must actually be enforced. The original bug: the disable-gate read the wrong option key and only covered the write path, leaking stars/reviews/schema/REST while "disabled".

## Setup

- Site: `$SITE_URL`
- Toggle lives in the `wb_listora_features` option (key `reviews`), NOT `wb_listora_settings`.
- Baseline: Reviews ON; LISTING_ID has approved reviews.

## Steps

### 1. Baseline (Reviews ON)
- **Action**: navigate `$SITE_URL/listing/<slug>`
- **Expect**: header rating stars render; "Reviews" tab present; review list/summary render; `aggregateRating` JSON-LD present in source; `GET /listora/v1/listings/<ID>?_fields=id,rating` returns `rating`.

### 2. Disable the Reviews feature
- **Action**: Features tab → uncheck "Reviews & Ratings" → save (or set `wb_listora_features['reviews']=false`). Flush cache. Reload the listing.
- **Expect (ALL must be hidden/absent)**:
  - Header rating stars gone (`.listora-detail__rating` absent)
  - "Reviews" tab + panel gone
  - `listing-reviews` block summary + list gone
  - listing-card rating badge gone (check a grid page)
  - `aggregateRating` absent from JSON-LD
  - `GET /listings/<ID>?_fields=id,rating,reviews_summary` omits `rating`/`reviews_summary`
  - `GET /listings/<ID>/reviews` not served / 403
  - `POST /listings/<ID>/reviews` → 403 `listora_reviews_disabled`
- **On fail**: the 7 gated surfaces — `blocks/listing-detail/render.php`, `tabs.php`, `blocks/listing-reviews/render.php`, `blocks/listing-card/render.php`, `includes/schema/class-schema-generator.php`, `includes/rest/class-listings-controller.php`, `includes/rest/class-reviews-controller.php`. All must use `wb_listora_feature_enabled('reviews')`.

### 3. Re-enable + verify return
- **Action**: re-enable Reviews; reload.
- **Expect**: all surfaces from step 1 return; page otherwise intact (other tabs work).

### 4. Sub-settings enforced (Reviews ON)
- **min_length**: set Reviews → min length = 100. Submit a 20-char review via `POST /reviews`. **Expect** rejection (too short). Set min_length = 0 → a rating-only (empty text) review is accepted.
- **auto_approve**: with auto_approve OFF a new review is `pending` (not shown publicly); ON → `approved` immediately. (Reads `reviews.auto_approve`, NOT the listing-submission `moderation` setting.)
- **one_per_listing**: ON → a second review by the same user on the same listing is blocked; OFF → allowed.
- **allow_reply**: OFF → owner reply UI hidden AND `POST /reviews/{id}/reply` returns 403.
- **On fail**: `includes/rest/class-reviews-controller.php` (`create_review`, `owner_reply`) + `templates/blocks/user-dashboard/tab-reviews.php`.

> Note: `require_login=false` (anonymous reviews) is intentionally NOT supported yet — see card 9909629024. Login is always required.
