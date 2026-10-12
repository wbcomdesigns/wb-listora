---
journey: search-engine-extension-seams
plugin: wb-listora
priority: normal
roles: [administrator, anonymous]
covers: [search-engine, listing-grid, listing-map, listing-featured, search-rest]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "50+ published listings, a handful carrying a distinguishing meta key (this site: 84 published; 2,809 listing rows including 2,709 drafts)"
estimated_runtime_minutes: 7
covers_card: 10156792825
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light]
changed: true
---

# An extension can scope the directory on every surface

Regression sentinel for the search-engine extension seams. Verified via: source read (all five filters and the three blocks call `Search_Engine::search()`); the end-to-end five-surface comparison was NOT executed in this pass (see Not verified). Roles: the seams are server-side; the visitor-facing result must be identical for anonymous and admin unless the listener itself varies by user (then the listener must add that to the args, see the docblock at `includes/search/class-search-engine.php:1860-1868`).

## Filters (all present at e4f9f093)
| Filter | Where |
|---|---|
| `wb_listora_search_parse_args` | `class-search-engine.php:301` |
| `wb_listora_search_where_clauses` | `:552` |
| `wb_listora_search_where_params` | `:562` |
| `wb_listora_search_orderby` | `:799` |
| `wb_listora_search_result` | `:1876` (before caching) |
| `wb_listora_search_args` (REST only, older) | `includes/rest/class-search-controller.php:451` |

Blocks calling the engine directly: `blocks/listing-grid/render.php:70`, `blocks/listing-map/render.php:94`, `blocks/listing-featured/render.php:34`.

## The probe
```php
add_filter( 'wb_listora_search_parse_args', function ( $args ) { $args['qa_seam'] = 1; return $args; } ); // part of the cache key
add_filter( 'wb_listora_search_where_clauses', function ( $where, $args ) {
    global $wpdb;
    if ( empty( $args['qa_seam'] ) ) { return $where; }
    $where[] = "s.listing_id IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_qa_seam_keep' )";
    return $where;
}, 10, 2 );
```
Install as a temporary mu-plugin, mark N listings `QA-J1` with `_qa_seam_keep`, remove both afterwards.

## Steps
### 1. Every surface agrees
- **Expect**: `Search_Engine::search()`, `GET /wp-json/listora/v1/search`, the grid, the map and the featured block all return exactly N. Baseline first: the map must drop from its unfiltered marker count to N (0 before and after proves nothing).
### 2. Toolbar total agrees with the cards
- **Expect**: `.listora-grid__count` "Showing 1&ndash;N of N listings" matches the rendered `.listora-card` count.
### 3. Placeholders and values line up
- Add a `%s` clause via `..._where_clauses` and its value via `..._where_params`. **Expect**: correct rows, no SQL warning. Filter the array, never the joined string.
### 4. ORDER BY is filterable and fails safe
- `wb_listora_search_orderby` returning `s.title ASC, s.listing_id ASC` sorts alphabetically; returning `''` falls back to the default (no `ORDER BY ` fatal).
### 5. The result filter reaches blocks
- `wb_listora_search_result` truncating `listing_ids` and `total`: the grid renders the truncated set.
### 6. No listener, no change (positive control for 1-5)
- With the mu-plugin removed, results equal the baseline.

## Trap
Search results are cached in transients; clear with `wp_cache_flush()`, vary an arg in `parse_args`, or set `search_cache_ttl` to 0.

## Automated coverage
`tests/integration/SearchEngineSeamsTest.php` (8 tests, verified count).

## Not verified
Steps 1-6 end to end (needs a temporary mu-plugin and the five surfaces compared).
