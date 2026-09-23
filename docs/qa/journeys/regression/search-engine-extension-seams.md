---
journey: search-engine-extension-seams
plugin: wb-listora
priority: normal
roles: [administrator, anonymous]
covers: [search-engine, listing-grid, listing-map, listing-featured, search-rest]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "50+ published listings, a handful carrying a distinguishing meta key"
estimated_runtime_minutes: 7
covers_card: 10156792825
---

# An extension can scope the directory on every surface

Regression sentinel for the search-engine extension seams.

## Background

The engine had two `apply_filters` against seventeen `$wpdb` calls, and neither could touch the query: one is a telemetry array passed by value *after* caching, the other an on/off boolean for a LIKE branch. Hooking both changed nothing.

The one real args filter, `wb_listora_search_args`, lives in the REST controller and carries a `$request`. The listing-grid, listing-map and listing-featured blocks call `Search_Engine::search()` directly, so they never saw it — three of five entry paths had no extensibility at all. A space that wanted to show only its own listings had nowhere to hook on the surfaces a visitor actually sees.

BuddyNext is the consumer. The language column in `search_index` stays parked — that part is still speculative and gated on two unanswered product questions.

## The probe

An mu-plugin that scopes results to listings carrying `_qa_seam_keep`:

```php
add_filter( 'wb_listora_search_parse_args', function ( $args ) {
    $args['qa_seam'] = 1;          // Part of the cache key.
    return $args;
} );
add_filter( 'wb_listora_search_where_clauses', function ( $where, $args ) {
    global $wpdb;
    if ( empty( $args['qa_seam'] ) ) { return $where; }
    $where[] = "s.listing_id IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_qa_seam_keep' )";
    return $where;
}, 10, 2 );
```

## Steps

### 1. Every surface agrees — the acceptance test
- **Action**: mark N listings with the meta, install the probe, then read all five paths.
- **Expect**: `Search_Engine::search()`, `GET /listora/v1/search`, the listing-grid block, the listing-map block and the listing-featured block all return exactly N. **If the blocks and REST disagree, the seam is not done.**
- **Baseline it**: the map should drop from its unfiltered marker count to N — a map that renders 0 both before and after proves nothing.

### 2. The toolbar total agrees with the cards
- **Expect**: the grid's "Showing … of N" matches the number of cards rendered. A seam that filters rows but not the count is the counter-disagrees failure again.

### 3. Placeholders and values line up
- **Action**: add a clause with `%s` via `…_where_clauses` and its value via `…_where_params`.
- **Expect**: correct results, no SQL warning. Filter the **array**, never the joined string.

### 4. ORDER BY is filterable and fails safe
- **Action**: `wb_listora_search_orderby` returning `s.title ASC, s.listing_id ASC`.
- **Expect**: alphabetical results. Returning `''` must fall back to the default, not emit `ORDER BY ` and fatal.

### 5. The result filter reaches blocks too
- **Action**: `wb_listora_search_result` truncating `listing_ids` and `total`.
- **Expect**: the grid renders the truncated set. It fires before caching, so the cached entry matches what was returned.

### 6. No listener, no change
- **Expect**: identical results to before the release. This is every existing site.

## A trap when testing by hand

Search results are cached in transients. Deleting them with direct SQL does **not** clear the object cache inside the same request, so a filter added afterwards looks like it did nothing — that happened during this build. Use `wp_cache_flush()`, vary an arg in `wb_listora_search_parse_args`, or set `search_cache_ttl` to 0.

## Automated coverage

`tests/integration/SearchEngineSeamsTest.php` (8 tests) covers all five filters, the grid block, the REST route, the empty-ORDER-BY fallback, and the no-listener case.
