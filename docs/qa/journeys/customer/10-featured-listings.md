---
journey: featured-listings-block-and-expiry
plugin: wb-listora
priority: normal
roles: [guest, member, owner, other, editor, admin]
covers: [listing-featured-block, featured-empty-state, expire-featured-cron, featured-flag-meta, featured-index-sync, phone-carousel, owner-feature-listing, card-9977213192]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least 3 published listings with _listora_is_featured = 1; one listing type with listings but NO featured ones ({empty_type}); one with featured ones ({featured_type})"
  - "A page hosting the listora/listing-featured block ($FEAT_URL, e.g. the Home page, with Count set)"
  - "A listing {owner} owns for the write steps (fixture listing, owner {owner})"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - "ora:regression/featured-block-empty-state.md"
site_profile: required
---

# The Featured block shows only featured listings, an empty state when there are none, and featured status follows feature and expiry

## Why
Owners pay or earn to be featured: a listing that does not appear after being featured, still appears after expiring, or a block that silently vanishes breaks trust for owners and visitors. Steps 6 and 7 fail on the current build by design.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `{count}` = the block's Count attribute; `{fixture}` = listing id from the fixtures output.
- Meta: `_listora_is_featured` (`1`/empty), `_listora_featured_until` (unix timestamp, 0/absent = permanent). Sweep: `\WBListora\Core\Featured::expire_featured_listings()` (daily cron `wb_listora_expire_featured`, `includes/core/class-featured.php:27,73`).
- The sweep touches every listing with a past `_listora_featured_until`; list them first (`wp post list --post_type=listora_listing --meta_key=_listora_featured_until --format=count`) and prefer a site where only `{fixture}` has one.
- Clean up: unfeature the fixture and reindex it.
- "See all" link target and the Directory featured filter are owned by `featured-filter-and-see-all`; not repeated here.

## Steps

### 1. Block renders only featured listings
- **Action**: open `$FEAT_URL`, scroll to `.wp-block-listora-listing-featured` (`div.listora-featured`, `--listora-featured-columns: 4`).
- **Expect**: `.listora-featured__track` holds `min({count}, featured total)` `article.listora-card`, every one with `.listora-badge--featured` ("Featured"); arrows `.listora-featured__arrow--prev/--next` (`aria-label` "Previous"/"Next"); `.listora-featured__dots` has one dot per page. The block never pads with non-featured listings (`wb_listora_featured_backfill` defaults false, `blocks/listing-featured/render.php:52`).

### 2. Every displayed card is featured in the data
- **Action**: collect `listingId` from each card's `data-wp-context`; for each `get_post_meta( ID, '_listora_is_featured', true )`.
- **Expect**: `1` for every ID, and `_listora_featured_until` empty, 0 or in the future.

### 3. Carousel arrows
- **Action**: at 1440 click "Next" (`.listora-featured__arrow--next`).
- **Expect**: `.listora-featured__track` scrolls by one page (scroll-snap `x mandatory`); dots update (`actions.syncFeaturedDots`); no page reload.

### 4. No featured listings shows the empty state, not a gap (card 9977213192)
- **Action**: page with `<!-- wp:listora/listing-featured {"listingType":"{empty_type}"} /-->`, view as guest. Repeat with a type that has zero listings.
- **Expect**: `div.listora-featured--empty.listora-card--empty[role="status"]` titled "No featured listings yet", description "Listings marked as featured will appear here.", NO "See all" link. The empty state shows whenever the chosen type has no FEATURED published listing (not only when it has no listings; the block no longer back-fills with top-rated).
- **Positive control**: `{"listingType":"{featured_type}"}` renders the normal block with `--listora-featured-columns: 4` and `.listora-featured__see-all`, no empty state.
- **Editor**: as `{editor}` insert the block in the editor preview - same empty card (server render), not a gap. Not verified live.
- **On fail**: `blocks/listing-featured/render.php:66-81` (bare `return`).

### 5. Owner features their own listing
- **Action**: as `{owner}` `POST /wp-json/listora/v1/listings/{fixture}/feature` (dashboard "Feature" action).
- **Expect**: `200 {"featured":true,"permanent":false,"featured_until":<ts>,"days":<N>,"message":"Your listing is now featured until <Month D, YYYY>."}`; `_listora_is_featured = 1`, `_listora_featured_until` = now + N days (N from the setting).
- **Role ladder**: `{other}` member `403 listora_forbidden` ("You do not have permission to feature this listing."); guest `401 listora_unauthorized` ("You must be logged in to feature a listing."); `{admin}`/`{editor}` may feature any listing (control).

### 6. A newly featured listing appears in the block and in "featured only"
- **Action**: after step 5 reload `$FEAT_URL` and `GET /search?featured_only=1&per_page=100`.
- **Expect**: the listing is in the results and ranks with the featured ones; `{prefix}listora_search_index.is_featured = 1` for it.
- **expected-fail**: `Featured::feature_listing()` writes meta but never reindexes, so the index row stays `is_featured = 0` until the next save (`includes/core/class-featured.php:164`, `includes/search/class-search-indexer.php:349`, `includes/rest/class-listings-controller.php:1603`).

### 7. Expiry removes it from the block
- **Action**: with the fixture indexed as featured, set `_listora_featured_until` to `time() - 60`, run `\WBListora\Core\Featured::expire_featured_listings()` (or `wp cron event run wb_listora_expire_featured`), reload `$FEAT_URL`, re-query `featured_only`.
- **Expect**: `_listora_is_featured` empty and `_listora_featured_until` 0 (passes) AND the listing is gone from the block, "See all" and `featured_only` results (total drops by one).
- **expected-fail**: meta is cleared but the index row keeps `is_featured = 1`, so it stays in the block and ranks first until reindexed (`includes/core/class-featured.php:73-110`, `:198-230`).

### 8. Hooks fire once per render
- **Action**: register `wb_listora_before_featured_listings` and `wb_listora_after_featured_listings` listeners (mu-plugin), reload.
- **Expect**: each fires once per block render (`blocks/listing-featured/render.php:115,136`).

### 9. Scale
- **Action**: seed 100+ featured listings (QA-prefixed).
- **Expect**: the block still renders exactly `{count}` cards; `featured_only` `total` equals `wp post list ... --meta_key=_listora_is_featured --meta_value=1 --post_status=publish --format=count`; the request is one query, not a whole-table load.

### 10. Phone layout (390)
- **Action**: open `$FEAT_URL` at 390x844, light and dark.
- **Expect**: `scrollWidth <= 390`; the track scrolls horizontally inside itself, cards are `min(260px, 80vw)` wide; prev/next arrows are hidden (swipe); empty card (step 4) is centred with no overflow.
- **expected-fail**: the "See all" link measures ~61x22px, below the 40px tap-target floor (product finding).

## Role ladder
Public output (steps 1-4, 10): guest, member, owner, editor and admin see identical blocks; admin is the control. Write ladder: step 5.

## Pass criteria
1. Block shows only featured listings, up to Count; every card carries the Featured badge; a type with none renders the shared empty state with no dead link.
2. Owner can feature; others 403; guest 401.
3. (Currently failing) a newly featured listing appears and an expired one leaves the block after the sweep.
4. No horizontal page scroll at 390.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Non-featured cards appear | backfill filter on / query | `blocks/listing-featured/render.php:52`, `includes/search/class-search-engine.php:452` |
| Block vanishes when none featured | empty-state call missing | `blocks/listing-featured/render.php:70` |
| Block empty with featured data present | index stale | `includes/search/class-search-indexer.php:349` |
| Featured listing absent right after "Feature" | no reindex on feature | `includes/core/class-featured.php:164` |
| Expired listing still in block | sweep does not reindex | `includes/core/class-featured.php:73-110` |
| Sweep does nothing | `_listora_featured_until` not numeric / cron not scheduled | `includes/core/class-featured.php:54` |
| Total differs from DB | `featured_only` param | `includes/rest/class-search-controller.php:218` |

## Not verified
Steps 6 and 7 are expected to fail on this build; the editor preview in step 4 and the 100+ seed in step 9 were not run live.

## Cleanup
Unfeature the fixture, reindex it, remove listeners and QA seeds.
