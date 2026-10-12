---
journey: block-columns-zero-never-fatal
plugin: wb-listora
priority: critical
roles: [guest, owner, editor, admin]
covers: [listing-featured-block, listing-grid-block, listing-categories-block, block-attribute-validation, columns-floor-guard, BC-9989784605, card-9989784605]
prerequisites:
  - "At least 1 published listing exists"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/featured-columns-zero-fatal.md
fails_on: unrecorded
site_profile: required
---

# `columns=0` never fatals or renders a zero-column grid

## Why
The editor's NumberControl enforces `min: 1` only in JS; saved post content and `/wp/v2/block-renderer/...` deliver raw attributes to `render.php`. `listing-featured/render.php` divided `count( $ids ) / $columns` -> `DivisionByZeroError` (500); siblings emitted `--*-columns: 0`. Two layers must both hold: `"minimum": 1` in `block.json` and the `render.php` clamp `max( 1, (int) ... )`. (The wider adversarial-input gates live in `system/block-attribute-trust-boundary.md`.)

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Note the `$WP_PATH/wp-content/debug.log` size.

## Steps

1. **Fixture**: `wp post create --post_type=page --post_status=publish --post_title="QA cols0"` with content `<!-- wp:listora/listing-featured {"columns":0} /-->`, `<!-- wp:listora/listing-grid {"columns":0} /-->`, `<!-- wp:listora/listing-categories {"columns":0} /-->`.
2. **Anonymous fetch**: `curl -s -o /dev/null -w "%{http_code}"` -> **200**.
3. **Rendered markup**: every inline `--listora-*-columns:` value >= 1 (featured, grid, cat); wrappers `.listora-featured`, `.listora-grid-wrapper`, `.listora-categories` present.
4. **Debug log**: the delta has zero `DivisionByZeroError` and zero `Fatal error`.
5. **Render backstop**: `blocks/listing-featured/render.php:19`, `blocks/listing-grid/render.php:17`, `blocks/listing-categories/render.php:14` and Pro `blocks/needs-grid/render.php:26` all `max( 1, (int) ...columns )`; the dot-count division is guarded at `listing-featured/render.php:109`.
6. **Schema layer**: `"minimum": 1` on `columns` in `blocks/listing-featured/block.json:15`, `blocks/listing-categories/block.json:14`, `blocks/listing-grid/block.json:27`; Pro `needs-grid/block.json:25` declares `columns` (check its minimum when running).
7. **Positive control**: set `columns` to 3 on the same page; the CSS custom property reads 3 (the clamp must not rewrite valid values).
8. **Block-renderer route**: as {editor} `GET /wp/v2/block-renderer/listora/listing-featured?attributes[columns]=0` returns 400 (schema) and never 500.
9. **Negative values and wrong type** (added): `columns` = -3 and `"abc"` render with a clamp of 1 and no fatal. Not verified live.

## Role ladder
| Actor | Expect |
|---|---|
| {guest}, {owner} (member), {editor}, {admin} | the public fixture fetch is 200 for all (step 2) |
| {editor} | step 8 (block-renderer) |
| {admin} | control |

## Pass criteria
HTTP 200, no fatal in the log, no zero-column track, both defence layers present in source, valid values unchanged.

## Fail diagnostics
- `blocks/listing-featured/render.php` (dot-count division and clamp), `blocks/listing-grid/render.php`, `blocks/listing-categories/render.php`.
- `blocks/*/block.json` (columns attribute schema).
- Pro: `blocks/needs-grid/render.php` + `block.json`.

## Not verified
The block-renderer 400 (step 8), Pro needs-grid minimum, and step 9 were not run.

## Cleanup
Delete the fixture page.
