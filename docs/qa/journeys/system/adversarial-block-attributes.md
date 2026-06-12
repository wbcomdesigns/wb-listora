---
journey: adversarial-block-attributes
plugin: wb-listora
priority: critical
roles: [administrator]
covers: [block-renderer-rest, block-attribute-validation, all-registered-blocks, server-side-trust-boundary]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin login available (?autologin=1)"
  - "At least 1 published listing exists"
estimated_runtime_minutes: 8
covers_card: 9989784605
---

# No registered block may 500 on adversarial attribute values

Class-level guard born from BC #9989784605 (Featured Listings
`DivisionByZeroError` at `columns: 0`). Editor JS constraints (NumberControl
min/max) do not protect the server — the block-renderer REST API and saved
post content deliver raw attribute JSON to render.php. This journey is the
generic version of what QA did manually: feed every registered Listora block
out-of-range and wrong-typed attribute values and assert the server never
fatals.

**Contract:** for every adversarial value, the block-renderer REST response
is **200** (rendered, possibly with a clamped/default value) or **400**
(`rest_invalid_param` from schema validation). **500 is always a failure.**
A saved page carrying the same values must load HTTP 200 with no
fatal/`DivisionByZeroError` in debug.log.

## Steps

1. **Enumerate blocks** — from an admin editor session (or
   `wp.blocks.getBlockTypes()` filtered to `listora/` — combo mode:
   `listora-pro/` too), collect every registered block name and, from each
   block's `attributes` schema, every `number`-typed attribute name.
2. **Adversarial matrix** — for each (block, numeric attribute), call
   `GET /wp/v2/block-renderer/{block}?context=edit&attributes[{attr}]={v}`
   with each value in: `0`, `-1`, `-999999`, `0.4`, `999999`, `"abc"`
   (wrong type). Record HTTP status per call.
3. **Assert** — every response status is 200 or 400. Any 500 = FAIL with the
   block + attribute + value triple.
4. **Saved-content spot-check** — create a throwaway published page
   embedding the 3 layout-owning Free blocks (featured, grid, categories —
   combo: + Pro needs-grid, credit-purchase) with their numeric attributes
   set to `0` in the raw block comment JSON. Truncate debug.log, fetch the
   permalink anonymously. Assert HTTP 200, no `Fatal error` /
   `DivisionByZeroError` in debug.log, and every inline `--listora-*-columns:`
   value on the page is >= 1.
5. **Cleanup** — delete the fixture page, re-truncate debug.log.

## Pass criteria

Zero 500s across the full matrix; saved-content fixture renders clean.

## Likely files on failure

- `blocks/{block}/render.php` — missing `max( 1, (int) ... )` floor on the
  flagged attribute (see `bin/check-block-attr-guards.py`, coding-rules
  Rule 7 — run it first, it usually pinpoints the line)
- `blocks/{block}/block.json` — missing `"minimum"`/`"maximum"` on the
  attribute schema
