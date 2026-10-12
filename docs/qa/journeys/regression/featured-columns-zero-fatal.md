---
journey: featured-columns-zero-fatal
plugin: wb-listora
priority: critical
roles: [anonymous, admin]
covers: [listing-featured-block, listing-grid-block, listing-categories-block, block-attribute-validation, columns-floor-guard]
prerequisites:
  - "Site reachable at http://wb-listora.local"
  - "At least 1 published listing exists"
estimated_runtime_minutes: 4
covers_card: 9989784605
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# columns=0 must never fatal or render a zero-column grid

Regression sentinel for BC #9989784605. The editor's NumberControl enforces `min: 1` only in JS; saved post content and `/wp/v2/block-renderer/...` deliver raw attributes to `render.php`. Original bug: `listing-featured/render.php` divided `count( $ids ) / $columns` -> `DivisionByZeroError` (500). Siblings emitted `--*-columns: 0`. Two layers must both hold: `block.json` `"minimum": 1` on `columns`, and the `render.php` clamp `max( 1, (int) ... )`.

Verified via: temporary page with all three blocks at `columns:0` (QA-J1, deleted), curl 200, columns 4 / 3 / 4, no fatal, 2026-10-12.

## Steps

1. **Fixture**: `wp post create --post_type=page --post_status=publish --post_title="QA-J1 cols0"` with content
   ```
   <!-- wp:listora/listing-featured {"columns":0} /-->
   <!-- wp:listora/listing-grid {"columns":0} /-->
   <!-- wp:listora/listing-categories {"columns":0} /-->
   ```
   Note the debug.log size.
2. **Anonymous fetch**: `curl -s -o /dev/null -w "%{http_code}"` -> **200** (verified).
3. **Rendered markup**: every inline `--listora-*-columns:` value >= 1 (verified: `--listora-featured-columns: 4`, `--listora-grid-columns: 3`, `--listora-cat-columns: 4`); `.listora-featured`, `.listora-grid-wrapper`, `.listora-categories` wrappers present.
4. **Debug log**: the delta has zero `DivisionByZeroError` and zero `Fatal error`.
5. **Render backstop** (verified): `blocks/listing-featured/render.php:19`, `blocks/listing-grid/render.php:17`, `blocks/listing-categories/render.php:14` and Pro `blocks/needs-grid/render.php:26` all `max( 1, (int) ...columns )`; the dot-count division is guarded at `listing-featured/render.php:109`.
6. **Schema layer** (verified): `"minimum": 1` on `columns` in `blocks/listing-featured/block.json:15`, `blocks/listing-categories/block.json:14` and `blocks/listing-grid/block.json:27`; Pro `needs-grid/block.json:25` declares `columns` (check its minimum when running).
7. **POSITIVE CONTROL**: set `columns` to 3 on the same page; the CSS custom property reads 3 (the clamp must not rewrite valid values).
8. **Role ladder**: the fixture is public: anonymous (step 2), member, owner, editor, admin fetch the same 200. Editor insertion through `/wp/v2/block-renderer/listora/listing-featured?attributes[columns]=0` returns 400 (schema) and never 500. Admin is the control.
9. **Cleanup**: delete the fixture page.

## Pass criteria

HTTP 200, no fatal in the log, no zero-column track, both defence layers present in source.

## Fail diagnostics

- `blocks/listing-featured/render.php` (dot-count division and clamp)
- `blocks/listing-grid/render.php`, `blocks/listing-categories/render.php`
- `blocks/*/block.json` (columns attribute schema)
- Pro: `blocks/needs-grid/render.php` + `block.json`

## Not verified

The block-renderer 400 and Pro needs-grid minimum were not run.
