---
journey: search-rating-average-nonzero
plugin: wb-listora
priority: high
roles: [anonymous, administrator]
covers: [search-rating-average, search-index-fallback, REST /search]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least one PUBLISHED listing with approved reviews (verified: listing 17 'The Golden Fork', 5 reviews, 4.6)"
  - "Search index built for that listing (avg_rating populated) - `wp listora reindex` if unsure"
estimated_runtime_minutes: 2
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# /search returns the real rating.average for reviewed listings

Read-only REST journey (public data; the ladder only confirms it). Verified via: curl + `wp eval` on the live site (observed values below).

`/wp-json/listora/v1/search` casts `rating.average` to float from meta, then falls back to the batch-loaded `search_index` map (`avg_rating`, `review_count`) when it is `0.0` (`includes/rest/class-search-controller.php:726-729`). The guard must compare the float literal `0.0 === ...`; an int `0` would never match under strict types and every result would report 0. Since 2.0.0 the public rating is one figure rounded to 1 decimal (`Listing_Data::get_rating_summary()`, `includes/core/class-listing-data.php:63`); the index already stores one-decimal values (0 non-round rows).

The envelope is `{ listings, total, pages, has_more }` (NOT `results`).

## Setup
- Reviewed id and real figures:
  ```sql
  SELECT s.listing_id, s.avg_rating, s.review_count, AVG(r.overall_rating) a, COUNT(r.id) n
  FROM {prefix}listora_search_index s JOIN {prefix}listora_reviews r ON r.listing_id=s.listing_id AND r.status='approved'
  GROUP BY s.listing_id ORDER BY n DESC LIMIT 5;
  ```
  (the reviews column is `overall_rating`). Observed: 17 -> 4.60 / 5; 24 -> 4.30 / 3 (raw average 4.3333, shown 4.3).

## Steps

### 1. Find the reviewed listing
- **Action**: `curl -s -G $SITE_URL/wp-json/listora/v1/search --data-urlencode "keyword=Golden Fork"` (query param `keyword`, alias `q`). **Do not** search "The Golden Fork": a leading stop word returns 0 (product bug).
- **Expect**: 200, `listings` contains id 17.

### 2. Non-zero average
- **Expect**: for id 17 `rating.average` is `4.6` (a number) and `rating.count` is `5`; both match the index row.

### 3. Broad search
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/search?per_page=50"`.
- **Expect**: every listing with `rating.count > 0` has `rating.average > 0` (observed 48 reviewed, 0 zero).

### 4. Unreviewed listing still 0 (the guard is correct)
- **Expect**: a published listing with `review_count = 0` (e.g. 318) reports `rating: {"average": 0, "count": 0}`.

### 5. Detail agrees
- `GET /listings/17/detail` -> `reviews_summary` `{avg_rating: 4.6, review_count: 5, star_distribution: {...}}` equals step 2.

## Fail diagnostics
| Symptom | File |
|---|---|
| All reviewed report 0 | guard reverted to int: `class-search-controller.php:726` must read `0.0 ===` |
| Reviewed 0 but /detail shows rating | index row missing: `wp listora reindex`, `search_index.avg_rating` |
| Unreviewed shows non-zero | fallback made unconditional (`isset( $ratings_map[...] )` half removed) |
| average is a string | `(float)` cast dropped at `:700`/`:727` |
