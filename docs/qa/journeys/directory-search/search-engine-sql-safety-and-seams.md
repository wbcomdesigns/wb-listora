---
journey: search-engine-sql-safety-and-seams
group: directory-search
actor: system
mode: mixed
plugin: wb-listora
priority: high
roles: [admin, guest]
covers: [search, sql, debug-log, cross-cutting-check-7, search-engine, listing-grid, listing-map, listing-featured, search-rest, card-10156792825]
prerequisites:
  - "WP_DEBUG_LOG on"
  - "50+ published listings, a handful carrying a distinguishing meta key (marked $QA_SEAM for the seam probe)"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light]
supersedes:
  - ora:regression/search-empty-candidate-set-no-sql-error.md
  - ora:regression/search-engine-extension-seams.md
fails_on: unrecorded
site_profile: required
---

# The search engine never builds `IN ()`, and an extension can scope the directory on every surface

## Why
`implode( ',', array_fill( 0, 0, '%d' ) )` is `''`, so an unguarded placeholder list renders `WHERE id IN ()`, which MySQL rejects (Pro's saved-search alert cron hit it live). Separately, the five engine filters must scope the grid, map, featured block and REST identically.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Note `$WP_PATH/wp-content/debug.log` size before the run; search results are cached in transients, so clear with `wp_cache_flush()`, vary an arg in `parse_args`, or set `search_cache_ttl` to 0.
- System behaviour: the same engine serves REST and the blocks for every actor, so the ladder only confirms it.
- Filters (all at e4f9f093): `wb_listora_search_parse_args` (`class-search-engine.php:301`), `wb_listora_search_where_clauses` (`:552`), `wb_listora_search_where_params` (`:562`), `wb_listora_search_orderby` (`:799`), `wb_listora_search_result` (`:1876`, before caching), `wb_listora_search_args` (REST only, `includes/rest/class-search-controller.php:451`). Blocks calling the engine directly: `blocks/listing-grid/render.php:70`, `blocks/listing-map/render.php:94`, `blocks/listing-featured/render.php:34`.
- Seam probe, as a temporary mu-plugin (remove afterwards):
```php
add_filter( 'wb_listora_search_parse_args', function ( $args ) { $args['qa_seam'] = 1; return $args; } ); // part of the cache key
add_filter( 'wb_listora_search_where_clauses', function ( $where, $args ) {
    global $wpdb;
    if ( empty( $args['qa_seam'] ) ) { return $where; }
    $where[] = "s.listing_id IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_qa_seam_keep' )";
    return $where;
}, 10, 2 );
```

## Steps

### A. Empty candidate sets
1. **Entry points survive an empty set**:
```php
$e = wb_listora_service('search_engine');
$m = new ReflectionMethod($e,'filter_by_taxonomy'); $m->setAccessible(true);
$m->invoke($e, [], 'listora_listing_feature', 1);          // => []
$m = new ReflectionMethod($e,'add_taxonomy_facets'); $m->setAccessible(true);
$m->invoke($e, ['x'=>1], [], []);                          // => ['x'=>1]
```
   - **Expect**: `[]` and the facets array unchanged; `debug.log` gains no `WordPress database error`. (Guarded sites: `filter_by_taxonomy` `:1417`/guard `:1426`, `phase_2_field_filter` `:1466`, `add_taxonomy_facets` `:1764`/guard `:1769`.)
2. **An empty multi-value filter is "no filter"**:
   - **Action**: `$e->search(['type'=>'{type}','per_page'=>5,'field_filters'=>['$FIELD'=>[]]])` vs the same search without `field_filters`.
   - **Expect**: equal totals. `expected-fail: product bug, empty multi-value field filter returns 0 and logs "PHP Warning: Array to string conversion" at class-search-engine.php:1513` (the empty array falls through to the exact-match branch and `$filter_count` was already incremented at `:1478`; the comment at `:1498-1503` claims the opposite).
3. **Non-empty paths unchanged** (positive control): `field_filters => ['$FIELD'=>['$VALUE']]` with a value present in `listora_field_index` returns the same rows as before; no log line. The fixture of step 2 must pass a literal empty array, a non-empty one proves nothing.
4. **No new unguarded builds**: `grep -n "array_fill(" includes/search/*.php`: each hit (`class-search-engine.php`, `class-geo-query.php:246`, `class-search-indexer.php:818`) is behind an `empty()` guard in the same function or builds from a hardcoded array.

### B. Extension seams
5. **Every surface agrees**: with the probe installed and N listings marked `_qa_seam_keep`, `Search_Engine::search()`, `GET /search`, the grid, the map and the featured block all return exactly N. Baseline first: the map must drop from its unfiltered marker count to N (0 before and after proves nothing).
6. **Toolbar total agrees with the cards**: `.listora-grid__count` "Showing 1-N of N listings" matches the rendered `.listora-card` count.
7. **Placeholders and values line up**: add a `%s` clause via `..._where_clauses` and its value via `..._where_params`: correct rows, no SQL warning. Filter the array, never the joined string.
8. **ORDER BY is filterable and fails safe**: `wb_listora_search_orderby` returning `s.title ASC, s.listing_id ASC` sorts alphabetically; returning `''` falls back to the default (no bare `ORDER BY ` fatal).
9. **The result filter reaches blocks**: `wb_listora_search_result` truncating `listing_ids` and `total` makes the grid render the truncated set.
10. **No listener, no change** (positive control for 5-9): with the mu-plugin removed, results equal the baseline.

## Role ladder
| Actor | Expect |
|---|---|
| {guest} | same totals as {admin} for steps 5-6 |
| {admin} | control; drafts never appear. A listener that varies by user must add that to the args (docblock `class-search-engine.php:1860-1868`) |

## Pass criteria
1. No `WordPress database error` for empty candidate sets; steps 1, 3, 4 hold.
2. All surfaces return exactly N under the probe and baseline after removal.
3. Step 2 is the only expected-fail.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| `IN ()` SQL error | guard removed | `includes/search/class-search-engine.php:1426`, `:1466`, `:1769` |
| One surface ignores the probe | surface bypasses the engine | the three block render files above |
| Stale results after probe change | transient cache | clear cache or vary an arg |

## Not verified
Steps 5-10 end to end (need the mu-plugin and the five surfaces compared). Automated: `tests/integration/SearchEngineSeamsTest.php` (8 tests).

## Cleanup
Remove the mu-plugin and the `_qa_seam_keep` meta.
