---
journey: search-rating-average-nonzero
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [5106ee4, search-rating-average, search-index-fallback, REST /search]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least one PUBLISHED listing with approved reviews (capture REVIEWED_ID + its real average)"
  - "Search index built for that listing (avg_rating column populated) — wp listora reindex if unsure"
estimated_runtime_minutes: 2
covers_card: null
---

# /search returns the real rating.average for reviewed listings (5106ee4 sentinel)

`/wp-json/listora/v1/search` casts each result's `rating.average` to `float`,
then — when meta carries no rating — falls back to the batch-loaded search-index
map (`avg_rating` / `review_count`). The fallback was guarded on
`0 === $listing['rating']['average']`, an INT comparison. Under strict typing
`0 === 0.0` is `false`, so the fallback never fired and EVERY `/search` result
reported `rating.average: 0`, even for heavily-reviewed listings.

Fix: commit 5106ee4 — `includes/rest/class-search-controller.php` compares
against the float literal `0.0 === $listing['rating']['average']` so the
search-index fallback fires when (and only when) the average is genuinely zero.

This is the search path specifically; `/listings/{id}/detail` already read its
average from meta and was never affected.

## Setup

- Site: `$SITE_URL`
- Anonymous browser/curl session
- Identify REVIEWED_ID + its real average via DB:
  `SELECT listing_id, COUNT(*) n, ROUND(AVG(overall_rating),2) avg
   FROM {prefix}listora_reviews WHERE status='approved'
   GROUP BY listing_id HAVING avg > 0 ORDER BY n DESC LIMIT 5;`
  (note: the reviews table column is `overall_rating`, not `rating`).
- Confirm the same listing has a non-zero `avg_rating` in
  `{prefix}listora_search_index` (the fallback source).

## Steps

### 1. Search for the reviewed listing
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/search?q=<keyword matching REVIEWED_ID title>"`
- **Expect**: 200; the results array contains REVIEWED_ID.
- The results live under the `results` key of the search envelope (alongside
  `total`, `pages`, `has_more`).

### 2. Assert the reviewed listing reports a NON-ZERO average
- **Action**: read `rating.average` and `rating.count` for REVIEWED_ID from the
  step-1 response.
- **Expect**:
  - `rating.average` > 0 — equals the listing's real approved-review average
    (within rounding; the search-index value is authoritative for `/search`)
  - `rating.count` > 0 — matches the indexed review count
- **On fail**: regression of 5106ee4 — the float-literal guard reverted to the
  int `0`, suppressing the search-index fallback.

### 3. Broad search confirms the fallback fires across results
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/search?type=<a type with several reviewed listings>&per_page=20"`
- **Expect**: every result that HAS approved reviews reports `rating.average > 0`.
  None of the reviewed listings reports a flat `0`.

### 4. A genuinely-unreviewed listing still reports 0 (the guard is correct, not removed)
- **Action**: find a published listing with zero approved reviews and zero
  `avg_rating` in the search index; locate it via `/search`.
- **Expect**: `rating.average` is `0` for that listing — the fallback only fills
  in when the index actually has a rating; it does not fabricate one.

## Pass criteria

1. `/search` returns `rating.average > 0` for a reviewed listing (was always 0)
2. The reported average matches the listing's indexed average within rounding
3. Across a broad search, no reviewed listing reports a flat `0`
4. A genuinely-unreviewed listing still correctly reports `0`
5. `rating.count` tracks `rating.average` (both filled from the same index row)

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| All reviewed listings report `rating.average: 0` | regression of 5106ee4 — guard reverted to `0 ===` (int) | `includes/rest/class-search-controller.php` — the fallback condition must read `0.0 === $listing['rating']['average']` |
| Reviewed listing reports 0 but `/detail` shows a rating | search-index row missing `avg_rating` | rebuild: `wp listora reindex`; confirm `{prefix}listora_search_index.avg_rating` is populated for the listing |
| Unreviewed listing suddenly reports a non-zero average | guard removed entirely / fallback unconditional | same file — the `isset( $ratings_map[ $post->ID ] )` half of the condition must still gate the fill |
| `rating.average` is a string `"0.00"` not a number | cast dropped | same file — `(float)` cast on the assignment must remain |
