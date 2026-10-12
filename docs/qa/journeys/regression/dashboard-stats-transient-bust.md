---
journey: dashboard-stats-transient-bust
plugin: wb-listora
priority: normal
roles: [member]
covers: ["#9982046916", "dashboard stat cache invalidation", "object-cache group bump on favorite/review/claim writes"]
prerequisites:
  - "Logged-in user; at least 1 published listing"
estimated_runtime_minutes: 2
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Dashboard stat counts refresh immediately after favorite and review writes

Every dashboard aggregate (listing counts by status, reviews, favourites, claims) comes from `Dashboard_Data::stats( $user_id, $type_slug )` (`includes/core/class-dashboard-data.php:43`) and is cached in the `wb_listora_dashboard` object-cache group under a key that embeds the group's `last_changed` stamp (`Core\Cache::key()`, `includes/core/class-cache.php:106`). A write invalidates by BUMPING the group, not by deleting a key: favourites add / remove (`wb_listora_after_add_favorite` / `_remove_favorite` -> `Cache::bump_dashboard()`), review create / update / delete (`bump_reviews()`), listing writes (`bump_listings()`) and claim submit / update (`bump_dashboard()`), all wired at `includes/core/class-cache.php:66-80`.

Earlier (1.x) this was a 60 s transient (`listora_dashboard_stats_<uid>`) busted with the wrong API. Those keys no longer exist; do not assert on them. The assertion is behavioural: the stat reflects a write on the very next render.

Roles: the member whose stats move (actor); another member's stats must NOT change; logged out has none; admin as control.

## Setup

- A logged-in member with at least 1 published listing and the ability to favourite another's listing (QA: `qa_vendor_01`, favouriting listing 746). REST nonce via `wp-admin/admin-ajax.php?action=rest-nonce`.
- Cache note: the QA site has no persistent object cache (`wp_using_ext_object_cache()` is false), so `wp_cache_*` lives for one request and a stale read cannot occur there. With Redis / Memcached installed this journey has teeth; without it, steps 1 to 3 still prove the write path and step 4 proves the invalidation wiring.

## Steps

### 1. Favorite is reflected immediately
- **Action**: note the Favorites badge (`#dash-tab-favorites`) and the Overview "Saved" tile; `POST /listora/v1/favorites` body `{"listing_id":746}` as the member; reload the dashboard at once.
- **Expect** (verified, QA): HTTP 201 `{"favorited":true}`; the badge goes 1 -> 2 on the next render, not after 60 s.

### 2. Unfavorite
- **Action**: `DELETE /listora/v1/favorites/746`.
- **Expect** (verified): 200 `{"favorited":false}`; the badge returns to 1 on the next render.

### 3. Review create / delete
- **Action**: as the member `POST /listora/v1/listings/<other member's listing>/reviews` (rating + body; one review per listing), reload; then `DELETE /listora/v1/reviews/<id>`, reload.
- **Expect**: the Reviews badge / tile and the listing's rating move with each write (create: +1, delete: -1) on the next render. Use a `QA-J1-` review body and delete it.

### 4. Invalidation is wired (works without a persistent cache)
- **Action**: `wp eval 'foreach ( ["wb_listora_after_add_favorite","wb_listora_after_remove_favorite","wb_listora_after_create_review","wb_listora_after_delete_review","wb_listora_after_update_claim"] as $h ) { echo $h, " => ", has_action( $h ) ? "wired" : "MISSING", "\n"; }'`
- **Expect**: every hook is `wired` (to `WBListora\Core\Cache::bump_*`).
- **With a persistent object cache**: `wp eval 'echo wp_cache_get_last_changed( "wb_listora_dashboard" );'` before and after a favourite write returns a different stamp, and a stale `Dashboard_Data::stats()` array is not returned afterwards.

### 5. Positive control and role ladder
- **Control**: step 1 moving the number is the control for "no stale value": a dashboard whose count never moves would also look "fresh".
- **Another member**: a second member's badge and tile do not change when the first favourites (stats are keyed per user and type).
- **Logged out**: `POST /favorites` returns 401; no stats.
- **Pinned dashboard**: the cache key carries the listing type, so two pinned pages never serve each other's numbers.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Count lags after a write | bump hook missing | `includes/core/class-cache.php:66-80` |
| Count wrong for a pinned page | cache key lacks the type | `includes/core/class-dashboard-data.php:46` |
| Group never invalidates | key built without `last_changed` | `includes/core/class-cache.php:106-109` |
| Stale after a write only with Redis | something caches outside the group | `includes/rest/class-dashboard-controller.php:597-625` |

## Not verified

Steps 1 and 2 were run live over REST as `qa_vendor_01` (badge read from the served dashboard HTML: 1 -> 2 -> 1; listing 746 favourite removed again). Step 4's `has_action` check was run (all five hooks `wired`). Step 3 (reviews) and the persistent-cache assertions were NOT run; the cache design was read in source.
