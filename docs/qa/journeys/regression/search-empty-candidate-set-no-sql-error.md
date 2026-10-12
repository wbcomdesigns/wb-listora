---
journey: search-empty-candidate-set-no-sql-error
plugin: wb-listora
priority: high
roles: [system]
covers: [search, sql, debug-log, cross-cutting-check-7]
prerequisites: ["WP_DEBUG_LOG on (it is on at this site)"]
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---

# An empty candidate set must not build `IN ()`

System behaviour (SQL), not role-bound; the same engine serves REST and the blocks for every actor, so no role ladder. Verified via: `wp eval-file` with reflection and a `debug.log` size diff.

`implode( ',', array_fill( 0, 0, '%d' ) )` is `''`, so an unguarded placeholder list renders `WHERE id IN ()`, which MySQL rejects and WordPress logs as `WordPress database error`. Pro's saved-search alert cron hit it live.

2.0.0 note: the `Facets` class (`Facets::taxonomy_facets`) no longer exists, so that entry point is gone; the remaining guarded sites are in `includes/search/class-search-engine.php`: `filter_by_taxonomy` (`:1417`, guard `:1426`), `phase_2_field_filter` (`:1466`), `add_taxonomy_facets` (`:1764`, guard `:1769`).

## Steps

### 1. Entry points survive an empty set
```php
$e = wb_listora_service('search_engine');
$m = new ReflectionMethod($e,'filter_by_taxonomy'); $m->setAccessible(true);
$m->invoke($e, [], 'listora_listing_feature', 1);          // => []
$m = new ReflectionMethod($e,'add_taxonomy_facets'); $m->setAccessible(true);
$m->invoke($e, ['x'=>1], [], []);                          // => ['x'=>1]
```
- **Expect**: `[]` and the facets array unchanged (observed). `debug.log` gains no `WordPress database error`.

### 2. An empty multi-value filter is "no filter" (EXPECTED; currently FAILS)
- **Action**: `$e->search(['type'=>'restaurant','per_page'=>5,'field_filters'=>['cuisine'=>[]]])` vs the same search without `field_filters`.
- **Expect**: equal totals (17 for restaurant on this site).
- **Observed on e4f9f093**: total 0 and `PHP Warning: Array to string conversion` at `class-search-engine.php:1513`; the empty array falls through to the exact-match branch (`(string) $value` is "Array") and `$filter_count` was already incremented (`:1478`). The in-code comment at `:1498-1503` claims the opposite. Reported as a product bug; this step is the sentinel for it.

### 3. Non-empty paths unchanged (positive control)
- **Action**: `field_filters => ['cuisine'=>['Italian']]` (use a value that exists in `listora_field_index`). **Expect**: same rows as before the change; no log line.

### 4. No new unguarded builds
- `grep -n "array_fill(" includes/search/*.php`: each site is either behind an `empty()` guard in the same function or builds from a hardcoded array (currently 15 hits in `class-search-engine.php`, `class-geo-query.php:246`, `class-search-indexer.php:818`).

## Test-data trap
Calling with a non-empty array proves nothing: the fixture must pass a literal empty array.
