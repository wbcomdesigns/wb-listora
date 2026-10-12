---
journey: search-rest-listing-row-fields
group: directory-search
actor: system
mode: api
plugin: wb-listora
priority: high
roles: [guest, owner, other, editor, admin]
covers: [rest-search, rating-average, search-index-fallback, business_hours-shape-parity, search-hours-additive, Business_Hours, open-now-computability, overnight-span, Image_Schema, featured_image, app-contract, is_favorited-search-parity, favorites-n1-batching, Favorites_Cache, favorite_count-batching, listings-offset-path-priming, BC-10194450677, BC-10203381688]
prerequisites:
  - "A published listing $REVIEWED with approved reviews and a built search-index row (`wp listora reindex` if unsure)"
  - "A published listing $HOURS_LISTING with rows in ${PREFIX}listora_hours, ideally with an overnight span"
  - "A published listing $IMAGE_LISTING whose featured attachment has thumbnail/medium/medium_large/large registered and which appears in /search and in some listing's /related; a published listing $NO_IMAGE without an image"
  - "A member {owner} with >= 5 rows in ${PREFIX}listora_favorites; a second member {other} with 0; 20+ published listings so per_page=20 fills a page"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
supersedes:
  - ora:regression/search-rating-average-nonzero.md
  - ora:regression/search-hours-normalised-additive.md
  - ora:regression/rest-featured-image-one-shape.md
  - ora:regression/is-favorited-batched-and-on-search.md
fails_on: unrecorded
site_profile: required
---

# Every listing row on /search, /listings, /detail, /related and /bulk carries the same rating, hours, image and favourite fields

## Why
The mobile app and the blocks read these fields, and each regressed silently: rating.average 0 for reviewed listings, hours the app could not compute "Open now" from, three image shapes, and 20 favourite queries per page.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- REST base `$SITE_URL/wp-json/listora/v1`. Envelope is `{ listings, total, pages, has_more }` (NOT `results`). Logged-out reads are limited to 120/minute per IP in 2.0.0, so run the curls in one short burst.
- Reviewed figures: `SELECT s.listing_id, s.avg_rating, s.review_count, AVG(r.overall_rating) a, COUNT(r.id) n FROM ${PREFIX}listora_search_index s JOIN ${PREFIX}listora_reviews r ON r.listing_id=s.listing_id AND r.status='approved' GROUP BY s.listing_id ORDER BY n DESC LIMIT 5;` (public rating is one figure rounded to 1 decimal, `Listing_Data::get_rating_summary()` `includes/core/class-listing-data.php:63`).
- Favourites harness: save as `$WP_PATH/../scratch/fav-count.php` and run `wp eval-file` with the {owner} user id. It counts statements matching `listora_favorites` through the `query` filter around `rest_do_request()` for `/search` and `/listings` at per_page=20, as the user and as anonymous (user 0), and prints `items present mismatches FAV_QUERIES`. Truth set: `SELECT listing_id FROM ${PREFIX}listora_favorites WHERE user_id = %d`.

## Steps

### A. Rating
1. **Find the reviewed listing**: `curl -G $SITE_URL/wp-json/listora/v1/search --data-urlencode "keyword={reviewed-title-without-leading-stop-word}"` -> 200, `listings` contains $REVIEWED. (A leading "The" returning 0 is covered as expected-fail in `directory-search/search-keyboard-and-suggestions.md`.)
2. **Non-zero average**: `rating.average` is a number equal to the index row and `rating.count` equals `review_count`.
3. **Broad search**: `?per_page=50`: every listing with `rating.count > 0` has `rating.average > 0`.
4. **Unreviewed stays 0** (the guard is correct): a published listing with `review_count = 0` reports `rating: {"average": 0, "count": 0}`.
5. **Detail agrees**: `GET /listings/$REVIEWED/detail` `reviews_summary` `{avg_rating, review_count, star_distribution}` equals step 2. The index stores one-decimal values (0 non-round rows).

### B. Hours
6. **Legacy key unchanged**: `listings[i].meta.business_hours[0]` is exactly `{"day":N,"open":"HH:MM","close":"HH:MM"}` (keys `day/open/close`, nothing added or removed). Failure is a release blocker (`class-search-controller.php:696`).
7. **Additive fields**: `timezone` is a non-empty IANA id and `hours[0]` has ALL of `day, day_name, open_time, close_time, is_closed, is_24h, timezone`.
8. **Same parser as detail**: for $HOURS_LISTING, `GET /listings/{id}/detail` `business_hours` equals the row's `hours` exactly.
9. **Open now is computable from /search alone**: `timezone`, `is_closed`, `is_24h` present; an overnight span (`close_time < open_time`) exists so a client can apply midnight-wrap in the listing's zone.
10. **Batched**: with a `query` filter counting `listora_hours` statements, `GET /search?per_page=20` gives `items=20 hours_queries=1`.
11. **No hours degrades honestly**: a listing with no `listora_hours` rows returns `hours: []` and the SITE zone from `get_effective_timezone()`, never a device zone. The old `timezone: ""` contract no longer holds.
12. **Timezone matrix** (added): set the site timezone to UTC+14, then UTC-12, then restore; `timezone` in step 7 follows the effective zone and `hours` is unchanged. Not verified live.

### C. Featured image shape
13. **The attachment is not the variable**: `wp eval 'echo implode(",", array_keys(wp_get_attachment_metadata(<ATT_ID>)["sizes"] ?? []));'` lists thumbnail, medium, medium_large, large.
14. **All three endpoints agree**: `GET /listings/$PARENT/related` (a bare JSON LIST), `GET /listings/$IMAGE_LISTING/detail` (object) and `GET /search?per_page=100` (find the row by id) all expose identical key sets `alt, full, id, large, medium, medium_large, thumbnail`.
15. **Types are stable**: `id` int, every other value a string (`""` for a missing size), never `false`/`null`.
16. **No image is `null` everywhere**: `/listings/$NO_IMAGE/detail` has the `featured_image` key with value `null`; `/search` and `/related` rows too. Assert with `array_key_exists`, not `??`.
17. **`image_sizes` still trims**: `/detail?image_sizes=thumbnail,medium` returns exactly `id, alt, thumbnail, medium`; `?image_sizes=banana` returns the full set (no error).
18. **Nothing was removed**: `large` still on `/detail`, `medium_large` still on `/related`.

### D. is_favorited and favourite counts
19. **Present on /search**: `GET /search?per_page=5` first listing has the key (`false` for anonymous).
20. **/search is one query** (logged in as {owner}): `items=20 present=20 mismatches=0 FAV_QUERIES=1`.
21. **/listings OFFSET path also one**: same row for `/listings per_page=20`. `FAV_QUERIES=20` means the OFFSET branch (what the app calls) is not primed.
22. **Values are correct, not merely cheap** (positive control): `POST /listings/bulk {ids:[<10 of the truth set>]}` as {owner} -> all 10 `is_favorited: true`, `fav_queries <= 2`.
23. **Anonymous is false with zero queries**: `present=20 mismatches=0 FAV_QUERIES=0`.
24. **favorite_count matches the DB**: `GET /listings/{id}/detail` `favorite_count` equals `SELECT COUNT(*) FROM ${PREFIX}listora_favorites WHERE listing_id={id}`.
25. **Write then read in one request is not stale**: as {owner} `POST /favorites {listing_id: <not yet favourited>}`, then GET its `/detail` in the same PHP process -> `is_favorited: true`. Cleanup `DELETE /favorites/{id}`.

## Role ladder
| Actor | Expect |
|---|---|
| {guest} | all payload fields present; `is_favorited` false with 0 queries; limit 120/min per IP |
| {owner} (has favourites) | steps 20-25 |
| {other} (0 favourites) | every `is_favorited` false, mismatches=0 against their own truth |
| {editor} | like a member; public search never lists drafts |
| {admin} | control: same payloads and totals |

## Pass criteria
1. Reviewed listings report non-zero rating; unreviewed report 0; detail agrees.
2. `meta.business_hours` unchanged; `hours`+`timezone` identical to `/detail`; hours cost one query per page.
3. One `featured_image` shape on `/detail`, `/related`, `/search`; missing size `""`, no image `null`.
4. `is_favorited` on every surface, one favourites query per page (zero for anonymous), values correct, counts match the DB.
5. Repeat at `per_page=50` and `page=6` on a 2000+ row site: queries stay 1 (added). Not verified live.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| All reviewed report 0 | guard reverted to int | `includes/rest/class-search-controller.php:726` must read `0.0 ===` |
| Reviewed 0, /detail shows rating | index row missing | `wp listora reindex`; `search_index.avg_rating` |
| Unreviewed non-zero | fallback made unconditional | `isset( $ratings_map[...] )` half removed |
| average is a string | `(float)` cast dropped | `:700`/`:727` |
| `meta.business_hours` changed | legacy key altered | `class-search-controller.php:696` |
| `hours`/`timezone` absent or differ from detail | producer | `class-search-controller.php:714-719`; `Business_Hours::get()` in `includes/core/` |
| `hours_queries=20` | prime missing | `Business_Hours::prime()` before the loop |
| Image shape differs | builder bypassed | `includes/core/class-image-schema.php:64`; `class-listings-controller.php`; `class-search-controller.php` `get_image_data()` |
| `/search` has no `is_favorited` | hydration | `class-search-controller.php` `hydrate_listings()` |
| FAV_QUERIES=20 / bulk 10+ | prime missing | `includes/core/class-favorites-cache.php`; `class-listings-controller.php` `get_items()`, `get_bulk()` (`:460`) |
| anon queries > 0 | early return missing | `$user_id <= 0` in `Favorites_Cache` |
| Stale write-then-read | forget not called | `Favorites_Cache::forget()` from `bump_favorites_generation()` |

## Not verified
Step 13 (attachment sizes) was assumed from response keys; steps 12 and the scale rerun in 5 were added in consolidation. Everything else was observed with curl / `wp eval`.

## Cleanup
Remove the scratch script; favourites row count returns to its start value.
