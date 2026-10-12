---
journey: adversarial-block-attributes
plugin: wb-listora
priority: critical
roles: [administrator]
covers: [block-renderer-rest, block-attribute-validation, all-registered-blocks, server-side-trust-boundary, bc-10393853821]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin login available (?autologin=1)"
  - "At least 1 published listing exists"
estimated_runtime_minutes: 8
covers_card: 9989784605
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---
<!-- System journey (REST + one saved page): no frontend screen, no owner object, so no theme/viewport/role ladder beyond the admin-as-actor plus the logged-out control in step 2a. Verified by running the matrix below with rest_do_request as the admin on 2026-10-12: 16 blocks, 79 numeric attributes, 474 calls, 363 x 200, 111 x 400, zero 500. -->

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
   `wp.blocks.getBlockTypes()` filtered to `listora/`, plus `listora-pro/` in combo mode), or server side
   `WP_Block_Type_Registry::get_instance()->get_all_registered()`, collect every registered block name and, from each
   block's `attributes` schema, every `number`- or `integer`-typed attribute name.
   Today: 11 Free blocks (`listora/listing-calendar, listing-card, listing-categories, listing-detail, listing-featured, listing-grid, listing-map, listing-reviews, listing-search, listing-submission, user-dashboard`) and 5 Pro (`listora-pro/comparison, credit-purchase, moderator-queue, needs-grid, post-need`); 79 numeric attributes in total (columns, count, perPage, limit, shadow offsets and so on).
2. **Adversarial matrix** — for each (block, numeric attribute), call
   `GET /wp/v2/block-renderer/{block}?context=edit&attributes[{attr}]={v}`
   with each value in: `0`, `-1`, `-999999`, `0.4`, `999999`, `"abc"`
   (wrong type). Record HTTP status per call.
3. **Assert** — every response status is 200 or 400. Any 500 = FAIL with the
   block + attribute + value triple. Observed 2026-10-12: 363 x 200, 111 x 400, no other status.
   - **Logged-out control (2a)**: `GET /wp-json/wp/v2/block-renderer/listora/listing-featured?context=edit&attributes[columns]=3` anonymously returns `401 block_cannot_read`, so the matrix really needs an admin session; a 200 here would be a leak.
4. **Saved-content spot-check** — create a throwaway published page
   (title `QA-J1 adversarial`) with exactly:
   `<!-- wp:listora/listing-featured {"columns":0,"count":0} /--><!-- wp:listora/listing-grid {"columns":0,"perPage":0} /--><!-- wp:listora/listing-categories {"columns":0,"limit":0} /-->`
   (combo: add Pro needs-grid and credit-purchase with their numeric attributes at 0). Truncate debug.log, fetch the
   permalink anonymously. Assert HTTP 200, no `Fatal error` /
   `DivisionByZeroError` in the body or debug.log, and every inline `--listora-*-columns:` value is >= 1.
   Observed: 200; `--listora-featured-columns: 4`, `--listora-grid-columns: 3`, `--listora-cat-columns: 4`.
   **POSITIVE CONTROL**: the same page with `"columns":3` must render `--listora-featured-columns: 3`, proving the page really renders the blocks (not executed in this refresh).
5. **Cleanup** — delete the fixture page, re-truncate debug.log.

6. **String attributes and upper bounds reach the markup whitelisted** (bc-10393853821)
   - **Action** (administrator, block-renderer REST): for `listora/listing-grid` call `attributes[cardLayout]=a b"><x`, `attributes[cardLayout]=<script>`, `attributes[columns]=99999999999`, `attributes[perPage]=99999999999`; repeat `columns=99999999999` on `listing-featured` and `listing-categories`. Then save a page with the same JSON and fetch it anonymously.
   - **Expect**: status 200 or 400 as before, AND on 200 the printed layout class is one of the registered layout names (unknown values fall back to the default, never echoed), no `"`, `<` or space inside any `class="..."` token, and every inline `--listora-*-columns:` value is an integer between 1 and 12 inclusive.
   - **Read-back**: `grep -oE 'listora-(grid|card)[a-z0-9_-]*' <rendered>` shows no token containing `a`, `b` split from one value; POSITIVE CONTROL: `cardLayout=list` and `columns=3` print `list` and `3`.
   - fails_on: unknown. Not verified live.


## Pass criteria

Zero 500s across the full matrix; saved-content fixture renders clean.

## Likely files on failure

- `blocks/{block}/render.php` — missing `max( 1, (int) ... )` floor on the
  flagged attribute (for example `blocks/listing-featured/render.php:19`; run
  `python3 bin/check-block-attr-guards.py` first, it exits 0 today and pinpoints the line when it does not)
- `blocks/{block}/block.json` — missing `"minimum"`/`"maximum"` on the
  attribute schema
