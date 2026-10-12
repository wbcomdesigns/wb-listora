---
journey: review-summary-respects-blocks
plugin: wb-listora
priority: high
roles: [member, blocked-reviewer, anonymous, admin]
covers: [BC-10185680640, member-blocks, reviews, apple-guideline-1.2, rating-one-public-number]
prerequisites:
  - "A listing with approved reviews from at least two REAL WP users (listing 17: users 6, 7, 1 here)"
  - "Seeded review rows often carry user_ids with no matching user; blocking cannot be exercised against those"
estimated_runtime_minutes: 5
covers_card: 10185680640
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A listing has ONE public rating; blocking a reviewer hides their review, not the total

**Behaviour reversed in 2.0.0.** Previously `get_rating_summary()` recomputed the headline for a viewer who blocked someone (so the
count matched the visible list). Now (readme 2.0.0: "A listing's rating is one public average and count, rounded to one decimal, on the
page, cards, REST API and structured data; blocking a reviewer hides their review from you without changing the total"):
- `Listing_Data::get_rating_summary()` (`includes/core/class-listing-data.php:63`) always returns the stored `search_index` aggregate,
  `round( avg, 1 )` and the count, for every viewer.
- `Listing_Data::get_reviews()` (`:270+`) and `GET /listings/{id}/reviews` still drop reviews by authors hidden from the viewer
  (`wb_listora_hidden_review_authors()`, `includes/class-template-helpers.php:2072`; `Member_Blocks::hidden_from()`,
  `includes/core/class-member-blocks.php:103`).
The headline may therefore read "5 reviews" above a list of 4 for a blocking viewer. That is intended.

Role ladder: viewer V (blocks), blocked reviewer R, anonymous, admin/moderator (moderation counts stay unfiltered so a reported
review can be acted on). Control: admin sees all reviews and the same headline.

## Steps (verified 2026-10-12 on listing 17, viewer 6 blocks reviewer 7; unblocked afterwards)

### 1. Total unchanged, list filtered for the blocking viewer
```php
wp_set_current_user( $viewer );   // 6
\WBListora\Core\Member_Blocks::block( $viewer, $reviewer );   // 7
\WBListora\Core\Member_Blocks::flush();
$s = \WBListora\Core\Listing_Data::get_rating_summary( $listing_id );
$l = \WBListora\Core\Listing_Data::get_reviews( $listing_id, 'newest', 50 );
```
- **Expect** (observed): before block `{avg_rating: 4.6, review_count: 5}`, list 5; after block summary `{4.6, 5}` UNCHANGED, list **4**;
  `wb_listora_hidden_review_authors()` = `[7]`. `GET /listora/v1/listings/17/reviews` as viewer 6: 4 rows, `summary`
  `{average:4.6,total:5,distribution:{5:3,4:2,...}}`.
- **Fails if** the summary drops to 4 / 4.75 (the old recompute is back) or the list still has 5.

### 2. No global side effect
- Unblock (`Member_Blocks::unblock`), flush, drop to anonymous: list 5, hidden authors `[]`, summary `{4.6, 5}`.

### 3. All surfaces agree on the one number
- The listing header (`.listora-rating` aria-label "Rated 4.6 out of 5 based on 5 reviews"), card badge, `rating.average` / `rating.count`
  in `/listings/{id}` and `/search`, and JSON-LD `aggregateRating` all show 4.6 / 5 for anyone, rounded to one decimal.

### 4. Moderation stays unfiltered
- Admin counts and Listora > Reviews are not filtered by blocks.

## Test-data trap
`get_reviews()` is `( $listing_id, $sort, $limit )`; passing a limit where the sort belongs returns zero rows and looks like a working
filter. Seeded reviews may point at users that do not exist, in which case `Member_Blocks::block()` records nothing: assert
`wb_listora_hidden_review_authors()` is non-empty before trusting a pass.

## Fail diagnostics
`includes/core/class-listing-data.php:63` (summary), `:283-296` (list filter), `includes/class-template-helpers.php:2059`.

## Not verified
Step 3 header/JSON-LD in a browser (checked only the REST `summary`); 390px layout.
