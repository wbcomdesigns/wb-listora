---
journey: review-summary-respects-blocks
plugin: wb-listora
priority: high
roles: [member]
covers: [BC-10185680640, member-blocks, reviews, apple-guideline-1.2]
prerequisites:
  - "A listing with approved reviews from at least two REAL WP users"
  - "Seeded review rows often carry user_ids with no matching user - blocking cannot be exercised against those"
estimated_runtime_minutes: 5
covers_card: 10185680640
---

# The review headline must equal the reviews the viewer can actually see (BC 10185680640)

Blocking is **per-viewer**; the stored `search_index` aggregate is **one global
number**. The list query filtered blocked authors while the summary read the
stored aggregate, so a listing rendered "3 reviews" above a list of 2, with an
average the visible reviews could not produce.

This cannot be fixed by recomputing the aggregate — the same listing legitimately
has a different count for different people. `get_rating_summary()` now derives
the figure live when, and only when, the viewer blocks somebody.

Measured on listing 17 (5 approved reviews, one of them a guest):

| Viewer | Headline | Visible list |
|---|---|---|
| anonymous | 5 / 4.6 | 5 |
| member 6, no blocks | 5 / 4.6 | 5 |
| member 6 blocking member 7 | **4 / 4.75** | **4** |
| anonymous, after unblock | 5 / 4.6 | 5 |

## Steps

### 1. Headline equals list for a blocking viewer
```php
wp_set_current_user( $viewer );
\WBListora\Core\Member_Blocks::block( $viewer, $reviewer );
\WBListora\Core\Member_Blocks::flush();
$s = \WBListora\Core\Listing_Data::get_rating_summary( $listing_id );
$l = \WBListora\Core\Listing_Data::get_reviews( $listing_id, 'newest', 50 );
// assert: (int) $s['review_count'] === count( $l )
```
- **Fails if** the counts differ — the summary is reading the stored aggregate
  again.

### 2. No global side effect
Unblock, drop to anonymous, re-read. The figure must return to the stored
aggregate exactly. One member's block must never change what anybody else sees.

### 3. The fast path stays free
With no blocks, `hidden_from()` is empty and the method must still be a single
indexed `search_index` row read — no extra query for the anonymous majority.
Confirm by asserting the anonymous result is byte-identical to the stored row.

### 4. Moderation stays unfiltered
Admin counts and the moderation queue must NOT come through this path. A
moderator who cannot see a reported review cannot act on it.

## Test-data trap

`get_reviews()` is `( $listing_id, $sort, $limit )` — passing a limit where the
sort belongs returns zero rows and looks exactly like a working block filter.
Seeded reviews may also reference user IDs that do not exist, in which case
`Member_Blocks::block()` records nothing and the journey silently proves nothing.
Assert `wb_listora_hidden_review_authors()` is non-empty before trusting a pass.
