---
journey: reviews-feature-disabled
plugin: wb-listora
priority: high
roles: [anonymous, subscriber, administrator]
covers: [reviews-feature-toggle, reviews-display-gating, reviews-rest-403, review-subsettings, bc-10393672617]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing WITH >=1 approved review (listing 59, /listing/flames-smoke-bbq/)"
  - "Reviews feature ON at start"
estimated_runtime_minutes: 6
covers_card: 9895809632
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Reviews feature toggle hides ALL surfaces + sub-settings enforced

Disabling the Reviews feature must hide every read/write surface, and the review sub-settings must be enforced. The toggle lives in
option `wb_listora_features` (key `reviews`), edited in Listora > Settings > Features ("Reviews & Ratings"); the review sub-settings
live in `wb_listora_settings['reviews']` (`auto_approve`, `min_length`, `one_per_listing`, `allow_reply`; `require_login` was removed
in 2.0.0, login is always required). Per-type Reviews (Off) is covered by `per-type-moderation-and-reviews.md`.

Role ladder: anonymous reads (all surfaces), a member writes, admin toggles; with the feature off BOTH member and admin get the same
403, so the gate is the feature, not the caller. Positive control is step 1 and step 3.

## Steps (steps 1-3 verified 2026-10-12 with curl; feature restored to ON)

### 1. Baseline (ON)
- **Expect** on `$SITE_URL/listing/<slug>/`: header rating `span.listora-rating[aria-label="Rated 5.0 out of 5 based on 2 reviews"]`
  (`blocks/listing-detail/render.php:502`), tab `#tab-reviews` + panel `#panel-reviews`, `aggregateRating` in the JSON-LD;
  `GET /listora/v1/listings/59?_fields=id,rating` -> `{"id":59,"rating":{"average":5,"count":2}}`;
  `GET /listings/59/detail` has `reviews_summary`; `GET /listings/59/reviews` 200 with rows (deleted users show "Former member").

### 2. Disable the Reviews feature
- **Action**: Settings > Features > uncheck "Reviews & Ratings" > save (or `wb_listora_features['reviews']=false`). Reload.
- **Expect (all)**: no `.listora-rating` header stars, no Reviews tab/panel, no `listing-reviews` block output, no rating badge on grid
  cards, no `aggregateRating`; `GET /listings/59?_fields=id,rating` -> `{"id":59}`; `/detail` has no `reviews_summary`;
  `GET /listings/59/reviews` -> 403 `listora_reviews_disabled` "Reviews are currently disabled on this site."; `POST` the same
  (also 403, not 401, for a guest).
- **On fail**: gated surfaces use `wb_listora_feature_enabled('reviews')` / `wb_listora_listing_reviews_enabled()`:
  `blocks/listing-detail/render.php:152`, `templates/blocks/listing-detail/tabs.php:703`, `blocks/listing-reviews/render.php`,
  `blocks/listing-card/render.php`, `includes/schema/class-schema-generator.php`, `includes/rest/class-listings-controller.php`,
  `includes/rest/class-reviews-controller.php:1074-1100`.

### 3. Re-enable and verify return
- **Expect**: all step-1 surfaces return; other tabs unaffected.

### 4. Sub-settings enforced (Reviews ON)
- **min_length**: 100 -> a 20-char body returns 400 `listora_review_too_short` "Review must be at least 100 characters."; 0 -> rating-only accepted
  by the API (the listing-page form blocks an empty body in the UI, see `review-minlength-reads-setting.md`).
  - **Edit path** (card 10393672617): with min_length 100, as the review's author `PUT /reviews/<own id> {"content":"x"}` returns 400 `listora_review_too_short`, and a 100-character body returns 200; the stored `content` is unchanged after the 400 (read it back). **fails_on**: 2394e3c. Not verified live.
- **auto_approve**: OFF -> new review `pending` (not shown publicly); ON -> `approved` immediately.
- **one_per_listing**: ON -> a second review by the same user returns 409 `listora_already_reviewed`; OFF -> allowed
  (`wb_listora_one_review_per_listing()`, `wb-listora.php:494`).
- **allow_reply**: OFF -> owner reply UI hidden AND `POST /reviews/{id}/reply` returns 403 `listora_replies_disabled`
  (`wb_listora_review_replies_enabled()`, `wb-listora.php:471`).

### 5. Mobile (390)
- Baseline rating row and Reviews tab fit; with the feature off no empty gap where the tab was.

## Not verified
Step 4 sub-settings and the card badge/JSON-LD were checked by code and the REST summary, not exercised in a browser.
