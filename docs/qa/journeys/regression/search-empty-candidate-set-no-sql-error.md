---
journey: search-empty-candidate-set-no-sql-error
plugin: wb-listora
priority: high
roles: [system]
covers: [search, facets, sql, debug-log, cross-cutting-check-7]
prerequisites: ["WP_DEBUG_LOG on"]
estimated_runtime_minutes: 4
---

# An empty candidate set must not build `IN ()`

`implode( ',', array_fill( 0, 0, '%d' ) )` is `''`, so an unguarded placeholder
list renders `WHERE id IN ()` - invalid SQL. MySQL rejects it, `get_col()`
returns nothing, and WordPress logs `WordPress database error`, which is neither
a fatal nor a warning and so appeared in no check until cross-cutting check 7
started grepping for that string.

It fired live twice from Pro's saved-search alert cron before anything noticed.

Worth being precise about the impact: for a filter, empty-in-empty-out is the
**correct** answer, so this produced a logged error and a wasted query rather
than wrong data. That is exactly why it survived - the results looked right.

A sweep of all 20 placeholder builds in the search layer found 4 unguarded:
`filter_by_taxonomy` (`$ids`), the multi-value field filter (`$value`),
`add_taxonomy_facets` (`$candidate_ids`), and `Facets::taxonomy_facets`
(`$listing_ids`). The rest already had guards; two more build from hardcoded
arrays that can never be empty.

## Steps

### 1. Every entry point survives an empty set
```php
$m = new ReflectionMethod( Search_Engine::class, 'filter_by_taxonomy' );
$m->setAccessible( true );
$m->invoke( $engine, array(), 'listora_listing_feature', <term_id> ); // => []
```
Repeat for `add_taxonomy_facets( $facets, array(), array() )` (returns `$facets`
unchanged) and `Facets::taxonomy_facets( array() )` (returns `[]`).
- **Fails if** debug.log gains a single `WordPress database error`.

### 2. An empty multi-value filter is "no filter", not "match nothing"
Search with `field_filters => array( 'cuisine' => array() )`.
- Results must equal the same search with no `cuisine` key at all.
- **Fails if** results are empty. A cleared checkbox group must not blank the
  page - that is a worse answer than the bug.

### 3. Non-empty paths are unchanged
Same calls with real IDs still return the same rows as before.

### 4. No new unguarded builds
```bash
grep -n "array_fill(" includes/search/*.php
```
Every site must either have an `empty()` guard above it in the same function, or
build from a hardcoded array. New code that adds one without a guard reopens
this, and the only symptom will be a log line nobody greps for.

## Test-data trap

Calling these with a non-empty array proves nothing - the bug only exists at
zero. The fixture MUST pass a literal empty array.
