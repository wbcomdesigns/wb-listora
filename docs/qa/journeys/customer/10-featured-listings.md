---
journey: featured-listings-expiry
plugin: wb-listora
priority: normal
roles: [anonymous, subscriber, listing-owner, other-member, admin]
covers: [listing-featured-block, featured-see-all, expire-featured-cron, featured-flag-meta, featured-index-sync, phone-carousel]
prerequisites:
  - "Site reachable at $SITE_URL (Local: http://wb-listora.local)"
  - "At least 3 published listings with `_listora_is_featured = 1` (this site: 21)"
  - "A page hosting the listora/listing-featured block: the Home page `$SITE_URL/` (block uniqueId 3fc97e6f, sort=featured, count 8)"
  - "A listing you own for the write steps (fixture QA-J1 Zephyrquill Cafe, owner QA-J1-owner)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Featured listings block and featured expiry

The Featured block shows only featured listings (up to Count), "See all" lists every featured listing in the Directory, the owner can feature their own listing, and a listing whose featured period has ended drops out of the block after the daily sweep. The last two steps are written to FAIL on the current build (see "Known failing").

Verified via: Playwright (1440 and 390), curl REST, WP-CLI to run the sweep for the fixture.

## Setup

- Featured page: `$FEAT_URL = $SITE_URL/`. Meta: `_listora_is_featured` (`1`/empty), `_listora_featured_until` (unix timestamp, 0 or absent = permanent). Sweep: `\WBListora\Core\Featured::expire_featured_listings()` (daily cron hook `wb_listora_expire_featured`, `includes/core/class-featured.php:27,73`).
- On this site only listing 31 has a `_listora_featured_until` (a future date), so the sweep touches only your fixture.
- Clean up: unfeature the fixture and reindex it.

## Steps

### 1. Block renders only featured listings
- **Action**: open `$FEAT_URL`, scroll to `.wp-block-listora-listing-featured` (`div.listora-featured`, `--listora-featured-columns: 4`).
- **Expect**: `.listora-featured__track` holds 8 `article.listora-card` (Count = 8), every one has `.listora-badge--featured` ("Featured"); header has link `.listora-featured__see-all` "See all ->" (`href` = `$SITE_URL/listings/?listora_sort=featured&listora_featured=1`) and arrows `.listora-featured__arrow--prev/--next` (`aria-label` "Previous"/"Next"); `.listora-featured__dots` has one dot per page (2 here). The block never pads with non-featured listings (`wb_listora_featured_backfill` defaults to false).
- **On fail**: `blocks/listing-featured/render.php:14-60`

### 2. Every displayed card is featured in the data
- **Action**: collect `listingId` from each card's `data-wp-context` JSON, then for each `get_post_meta( ID, '_listora_is_featured', true )`.
- **Expect**: `1` for every ID (and `_listora_featured_until` empty, 0 or in the future).

### 3. Carousel arrows
- **Action**: at 1440 click "Next" (`.listora-featured__arrow--next`).
- **Expect**: `.listora-featured__track` scrolls by one page (about 1,278px, scroll-snap `x mandatory`); the dots update (`actions.syncFeaturedDots`); no page reload.

### 4. "See all" shows every featured listing
- **Action**: click "See all".
- **Expect**: Directory at `/listings/?listora_sort=featured&listora_featured=1`; count "Showing 1-20 of N listings" where N equals `GET /listora/v1/search?featured_only=1` `total` (21 at verification) and is >= the 8 cards in the block.

### 5. Owner features their own listing
- **Action**: as OWNER `POST /wp-json/listora/v1/listings/LISTING_ID/feature` (dashboard "Feature" action).
- **Expect**: `200` `{"featured":true,"permanent":false,"featured_until":<ts>,"days":30,"message":"Your listing is now featured until <Month D, YYYY>."}`; `_listora_is_featured = 1`, `_listora_featured_until` = now + 30 days.
- **ROLE LADDER**: other member `403 listora_forbidden` ("You do not have permission to feature this listing."); logged out `401 listora_unauthorized` ("You must be logged in to feature a listing."); admin/editor may feature any listing (control).

### 6. KNOWN FAILING: a newly featured listing appears in the block and in "featured only"
- **Action**: after step 5 reload `$FEAT_URL` and `GET /search?featured_only=1&per_page=100`.
- **Expect (spec)**: the listing is in the results and ranks with the featured ones.
- **Actual on e4f9f093**: not present; `{prefix}listora_search_index.is_featured` stays `0` because `Featured::feature_listing()` writes meta but never reindexes; it appears only after the listing is next saved/reindexed.
- **On fail**: `includes/core/class-featured.php:164` (`update_post_meta`), `includes/search/class-search-indexer.php:349` (index reads `Featured::is_featured`), `includes/rest/class-listings-controller.php:1603`

### 7. KNOWN FAILING: expiry removes it from the block
- **Action**: with the fixture indexed as featured, set `_listora_featured_until` to `time() - 60`, run `\WBListora\Core\Featured::expire_featured_listings()` (or `wp cron event run wb_listora_expire_featured`), reload `$FEAT_URL` and re-query `featured_only`.
- **Expect (spec)**: `_listora_is_featured` is empty and `_listora_featured_until` is 0 (this part passes) AND the listing is gone from the block and from `featured_only` results.
- **Actual on e4f9f093**: meta is cleared but the index row keeps `is_featured = 1`, so the listing stays in the block, in "See all" and ranked first until a reindex (`featured_only` total stays 22 instead of 21).
- **On fail**: `includes/core/class-featured.php:73-110` (`expire_featured_listings`) and `:198-230` (`unfeature_listing`) need a reindex

### 8. Hooks fire once per render
- **Action**: register `wb_listora_before_featured_listings` and `wb_listora_after_featured_listings` listeners (mu-plugin), reload.
- **Expect**: each fires once per block render (`blocks/listing-featured/render.php:115,136`).

### 9. Scale
- **Action**: with 100+ featured listings the block still renders exactly Count cards; "See all" paginates (20 per page) and `featured_only` `total` equals the DB count of published listings with `_listora_is_featured = 1`.
- **Note**: this site has 21 featured of about 85 published, not 2,000; seed to exercise.

### 10. Phone layout (390)
- **Action**: open `$FEAT_URL` at 390x844.
- **Expect**: `scrollWidth <= 390`; the track scrolls horizontally inside itself (`scrollWidth 2248` vs `clientWidth 345`), cards are `min(260px, 80vw)` = 260px wide; the prev/next arrows are not shown (swipe); the "See all" link is only 61x22px, below the 40px tap-target floor (see PRODUCT-BUGS in the report).

## Pass criteria

1. Block shows only featured listings, up to Count; every card carries the Featured badge.
2. "See all" total equals the featured total from the REST search.
3. Owner can feature; others 403; logged out 401.
4. (Currently failing) newly featured appears in the block; expired featured leaves the block after the sweep.
5. No horizontal page scroll at 390.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Non-featured cards appear | backfill filter on / query | `blocks/listing-featured/render.php:52`, `includes/search/class-search-engine.php:452` |
| Block empty with featured data present | index stale | `includes/search/class-search-indexer.php:349` |
| Featured listing absent right after "Feature" | no reindex on feature | `includes/core/class-featured.php:164` |
| Expired listing still in block | sweep does not reindex | `includes/core/class-featured.php:73-110` |
| Sweep does nothing | `_listora_featured_until` not numeric or cron not scheduled | `includes/core/class-featured.php:54` (`schedule_cron`) |
| "See all" total differs | `featured_only` param | `includes/rest/class-search-controller.php:218` |
