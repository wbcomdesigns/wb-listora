---
journey: blocking-a-reviewer-hides-review-not-total
plugin: wb-listora
priority: high
roles: [member, blocked-reviewer, anonymous, administrator]
covers: [BC-10185680640, member-blocks, reviews, apple-guideline-1.2, rating-one-public-number]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A {listing} with approved reviews from at least two REAL WP users (V = viewer who blocks, R = blocked reviewer). Seeded rows may carry user_ids with no user: blocking cannot be exercised against those"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: ["ora:regression/review-summary-respects-blocks.md", "ora:customer/03-write-and-reply-to-a-review.md (step 12)"]
fails_on: the pre-2.0.0 recompute (summary dropped for the blocking viewer; step 1)
---

# A listing has ONE public rating; blocking a reviewer hides their review, not the total

## Why
Since 2.0.0 the headline is one public average and count (one decimal) on page, cards, REST and structured data; blocking hides the review from the blocker only. An old recompute makes the headline differ per viewer. Contact-route enforcement of blocks: `customer/blocked-member-cannot-contact-owner.md`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). V = {other}, R = {owner} or another real reviewer; record `N_BEFORE` = approved review count and `AVG_BEFORE` from `search_index`, not from literals.
- `Listing_Data::get_rating_summary()` (`includes/core/class-listing-data.php:63`) returns the stored aggregate, `round(avg,1)`; `get_reviews()` (`:270+`, filter `:283-296`) and `GET /listings/{id}/reviews` drop reviews by authors hidden from the viewer (`wb_listora_hidden_review_authors()`, `includes/class-template-helpers.php:2072`; `Member_Blocks::hidden_from()`, `includes/core/class-member-blocks.php:103`). A headline of "N reviews" above a list of N-1 for a blocking viewer is intended.
- Test-data trap: `get_reviews()` is `( $listing_id, $sort, $limit )`; passing a limit where the sort belongs returns zero rows and looks like a working filter. Assert `wb_listora_hidden_review_authors()` is non-empty before trusting a pass.

## Steps

### 1. Total unchanged, list filtered for the blocking viewer
- **Action**: as V click "Block" on R's review (or `POST /me/blocks {"user_id":R}`), then
  ```php
  wp_set_current_user( V ); \WBListora\Core\Member_Blocks::block( V, R ); \WBListora\Core\Member_Blocks::flush();
  $s = \WBListora\Core\Listing_Data::get_rating_summary( $listing_id );
  $l = \WBListora\Core\Listing_Data::get_reviews( $listing_id, 'newest', 50 );
  ```
- **Expect**: `POST /me/blocks` `201 {"blocked":true}`; summary `{avg_rating:AVG_BEFORE, review_count:N_BEFORE}` UNCHANGED; list N_BEFORE-1; `wb_listora_hidden_review_authors()` = `[R]`; `GET /listings/{id}/reviews` as V: N_BEFORE-1 rows, `summary` `{average:AVG_BEFORE,total:N_BEFORE}`.
- **Read-back**: logged-out request still returns N_BEFORE rows. **Fails if** the summary drops (old recompute back) or the list still has N_BEFORE.

### 2. No global side effect
- **Action**: `DELETE /me/blocks/R` (`200 {"blocked":false}`) / `Member_Blocks::unblock`, flush, drop to {guest}.
- **Expect**: list N_BEFORE, hidden authors `[]`, summary unchanged.

### 3. All surfaces agree on the one number
- **Expect**: listing header `.listora-rating` aria-label "Rated X out of 5 based on N reviews", card badge, `rating.average`/`rating.count` in `/listings/{id}` and `/search`, JSON-LD `aggregateRating` all equal the stored aggregate for anyone, one decimal.
- **Read-back**: compare with `SELECT avg_rating, review_count FROM {prefix}listora_search_index WHERE listing_id=...`.

### 4. Moderation stays unfiltered
- **Expect**: admin counts and Listora > Reviews are not filtered by blocks.

## Role ladder
| Rung | Expect |
|---|---|
| V (blocks) | list minus R, headline unchanged |
| R (blocked) | still sees own review and the headline |
| {guest} | all reviews, same headline |
| {admin} / moderator (control) | all reviews, same headline; moderation counts unfiltered |

## Pass criteria
1. Headline identical for every viewer; blocking changes the list only for the blocker.
2. Unblock restores the list with no global side effect.
3. All surfaces show the same rounded number.

## Fail diagnostics
`includes/core/class-listing-data.php:63` (summary), `:283-296` (list filter), `includes/class-template-helpers.php:2059`.

## Not verified
Step 3 header/JSON-LD in a browser (REST `summary` only); 390px layout.

## Cleanup
Remove the block (`DELETE /me/blocks/R`).
