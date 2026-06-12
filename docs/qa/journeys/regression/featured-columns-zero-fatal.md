---
journey: featured-columns-zero-fatal
plugin: wb-listora
priority: critical
roles: [anonymous]
covers: [listing-featured-block, listing-grid-block, listing-categories-block, block-attribute-validation, columns-floor-guard]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least 1 published listing exists"
estimated_runtime_minutes: 4
covers_card: 9989784605
---

# columns=0 must never fatal or render a zero-column grid

Regression sentinel for BC #9989784605. The editor's NumberControl enforces
`min: 1` on the columns attribute, but that constraint is JS-only — the
block-renderer REST API (`/wp/v2/block-renderer/...`) and saved post content
both deliver raw attributes to render.php. The original bug:
`listing-featured/render.php` divided `count( $ids ) / $columns` for the
carousel dot count, so `columns: 0` threw an uncaught `DivisionByZeroError` —
a 500 in the editor preview and a fatally truncated page for every visitor
when the value was saved. Sibling blocks (listing-grid, listing-categories,
Pro needs-grid) emitted a `--*-columns: 0` CSS custom property, collapsing
the grid to zero tracks.

Two defense layers now exist and BOTH must hold:

1. `block.json` declares `"minimum": 1` on `columns` — WP attribute
   validation drops an invalid `0` and falls back to the default (and the
   block-renderer REST endpoint rejects it with 400 instead of 500).
2. `render.php` clamps `$columns = max( 1, (int) ( ... ) )` — the backstop
   for any path that bypasses schema validation.

## Steps

1. **Fixture** — create a throwaway published page whose content is exactly:
   ```
   <!-- wp:listora/listing-featured {"columns":0} /-->
   <!-- wp:listora/listing-grid {"columns":0} /-->
   <!-- wp:listora/listing-categories {"columns":0} /-->
   ```
   (`wp post create --post_type=page --post_status=publish ...`). Capture
   the permalink. Truncate `wp-content/debug.log`.
2. **Anonymous fetch** — `curl -s -o /dev/null -w "%{http_code}"` the
   permalink. Assert: **HTTP 200** (not 500).
3. **Rendered markup** — fetch the HTML. Assert: the featured block wrapper
   (`.listora-featured`) is present, AND every `--listora-*-columns:`
   inline custom property on the page has a value **>= 1** (never `0`).
4. **Debug log** — assert `wp-content/debug.log` contains zero
   `DivisionByZeroError` and zero `Fatal error` entries from this fetch.
5. **Render backstop (unit-level)** — assert the source guard exists:
   `grep -q "max( 1, (int) ( \$attributes\['columns'\]" blocks/listing-featured/render.php`
   and the same clamp in `blocks/listing-grid/render.php` +
   `blocks/listing-categories/render.php` (combo mode: Pro
   `blocks/needs-grid/render.php` too). This catches a future refactor that
   removes the clamp while the block.json schema still masks it.
6. **Schema layer** — assert `"minimum": 1` is present on the `columns`
   attribute in all three Free block.json files (combo: + Pro needs-grid).
7. **Cleanup** — delete the fixture page.

## Pass criteria

HTTP 200, no fatal/DivisionByZeroError in debug.log, no zero-column CSS
track on the rendered page, both defense layers present in source.

## Likely files on failure

- `blocks/listing-featured/render.php` (dot-count division)
- `blocks/listing-grid/render.php`, `blocks/listing-categories/render.php`
- `blocks/*/block.json` (columns attribute schema)
- Pro: `blocks/needs-grid/render.php` + `block.json`
