---
journey: featured-filter-and-see-all
group: directory-search
actor: customer
mode: mixed
plugin: wb-listora
priority: high
roles: [guest, member, admin]
covers: [featured-filter, featured-see-all, featured-url-param, directory-filter-count]
prerequisites:
  - "Site reachable at $SITE_URL; at least 2 featured and 2 non-featured published listings"
  - "A page with the Featured listings block ($FEAT_URL, e.g. the Home page)"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - "ora:customer/2.0-featured-filter-and-see-all.md"
site_profile: required
---

# The Directory can show only featured listings, and the Featured block's "See all" opens exactly that list

## Why
"See all" used to open the theme's blog archive. A visitor clicking it must land on the Directory with every featured listing, and the filter must behave like any other filter (counted, cleared, composable).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `{type}` a type with featured listings, `$WORD` a word in a featured `{type}` title.
- Reference numbers (derive each run):
  ```bash
  wp --path=$WP_PATH eval 'global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}listora_search_index WHERE is_featured=1");'   # FEAT
  wp --path=$WP_PATH eval 'echo rest_do_request(new WP_REST_Request("GET","/listora/v1/search"))->get_data()["total"];'                           # ALL
  ```
  FEAT counts PUBLISHED featured listings in the index; ALL is every published listing. The filter has no toggle of its own: it is reached from "See all" or a link.

## Steps

### 1. "See all" goes to the Directory, not the archive (guest, 1440)
- **Action**: open `$FEAT_URL`; read and click `a.listora-featured__see-all`.
- **Expect**: href is `<Directory>/?listora_sort=featured&listora_featured=1` (plus `listora_type={type}` when the block is pinned to a type); you land on the Directory page, never `/listing/` (which 301s to `/listings/`) and never a blog list.
- **Layout**: `.listora-categories` and `.listora-featured` `margin-block` compute to `var(--listora-space-6)`, not 0 (`blocks/listing-categories/style.css:5-6`, `blocks/listing-featured/style.css:4-5`).
- **On fail**: `blocks/listing-featured/render.php:86-95`, `templates/blocks/listing-featured/featured.php`.

### 2. The Directory shows only featured listings
- **Expect**: `.listora-grid__count` reads "Showing 1-N of FEAT listings" (allow +/-1 for a listing featured in between); the Filters badge counts 1; every card carries `.listora-card__badge-featured`; sort control reads "Featured".
- **Positive control**: `/listings/` without the parameter shows ALL listings and non-featured cards without the badge, so FEAT < ALL proves the filter bites.
- **Paging**: `listora_page=2` keeps `listora_featured=1` in every page link; `listora_page=999` shows the last page (see `paging-past-last-page`).

### 3. It composes with other filters and clears
- **Action**: `/listings/?listora_featured=1&listora_type={type}`, then add `&listora_keyword=$WORD`.
- **Expect**: total is the featured `{type}` listings only (<= FEAT), then narrows further; removing `listora_featured` grows the total.
- **Action**: "Clear all" in the search block.
- **Expect**: `listora_featured` leaves the URL, the full directory returns, the badge returns to hidden.

### 4. Featured status is honest
- **Action** (`{admin}`): un-feature one listing (edit > Featured box, or `wp post meta update <id> _listora_is_featured 0` then `wp listora reindex`).
- **Expect**: FEAT drops by one on reload; the listing still appears in the unfiltered directory. Re-feature it afterwards.
- **Action**: a listing whose featured period ended (`_listora_featured_until` in the past) must not count after `wp listora cleanup`.
- **On fail**: `Featured` service / cron, `Search_Engine` `featured_only` (`class-search-engine.php:451`). (Reindex-on-expiry gaps are tracked in `featured-listings-block-and-expiry` steps 6-7.)

### 5. Empty and edge states
- **Action**: `/listings/?listora_featured=1&listora_keyword=zzzz-no-match`.
- **Expect**: HTTP 200, the Directory empty state "No listings found" with a way to clear filters, no PHP notice.
- **Action**: `?listora_featured=0` and `?listora_featured=yes`.
- **Expect**: both treated as off (only `1` activates): full directory.

### 6. The block with nothing featured has no dead "See all"
- **Action**: pin a Featured block to a type with no featured listing.
- **Expect**: shared empty state and NO "See all" link (empty-state markup is asserted in `featured-listings-block-and-expiry` step 4).

### 7. Boundary input
- **Action**: `?listora_featured=1&listora_featured=0`, `?listora_featured[]=1`, `?listora_featured=%00`.
- **Expect**: HTTP 200 each time; only exactly `1` activates the filter. Not verified live.

### 8. 390px
- **Expect**: "See all" stays visible next to the block title or wraps below; tap target >= 40px; carousel arrows do not overlap; no horizontal page scroll.

## Role ladder
| Rung | Expect |
|---|---|
| guest | steps 1-3, 5, 7 |
| `{owner}` | identical (featured is not personalised) |
| `{admin}` | identical counts: the filter never includes drafts or pending featured listings; step 4 writes |

## Pass criteria
1. "See all" targets the Directory with `listora_featured=1`.
2. The filtered total equals FEAT and every card shows the featured badge.
3. The filter composes, pages and clears like any other filter.
4. Un-featuring and expiry change the count; an empty block has no dead "See all".

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| "See all" opens `/listing/` | block still using the post-type archive link | `blocks/listing-featured/render.php` |
| Parameter ignored | not read into `featured_only` | `includes/search/search-url-helpers.php` |
| Badge shows 0 while filtered | `featuredOnly` missing from IAPI state | `includes/class-assets.php` (~521), `store.js::activeFilterCount` |
| Count differs from FEAT | index stale | `wp listora reindex` |

## Not verified
Steps 7 and 8.

## Cleanup
Re-feature anything un-featured in step 4.
