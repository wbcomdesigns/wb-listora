---
journey: block-attribute-trust-boundary
plugin: wb-listora
priority: critical
roles: [admin, guest, contributor]
covers: [block-renderer-rest, block-attribute-validation, all-registered-blocks, server-side-trust-boundary, block-css-allowlist, style-tag-breakout, unique-id-sanitise, spacing-radius-shadow-attributes, 2.0.0-security, bc-10393853821, bc-10393672585, bc-10393575266, card-9989784605]
prerequisites:
  - "Site reachable at $SITE_URL; admin session for the block-renderer REST API (profile autologin then wp.apiFetch), or WP-CLI rest_do_request as {admin}"
  - "At least one published listing ({listing}); a {contributor} account (can create posts, no unfiltered HTML)"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light]
site_profile: required
supersedes: [ora:system/adversarial-block-attributes.md, ora:system/2.0-block-style-attribute-injection.md]
fails_on: 2394e3c
---

# No Listora block attribute value can fatal the page, inject CSS, or close the style tag

## Why
Editor JS limits (NumberControl min/max) never protect the server: the block-renderer REST API and saved post content hand raw attribute JSON to `render.php` and `Block_CSS`. A `columns: 0` is a `DivisionByZeroError` (card 9989784605) that takes a public page down; a hostile string is stored XSS or CSS injection written by a Contributor and read by every visitor.

**Contract.** For every adversarial value the block-renderer response is 200 (clamped or default) or 400 (`rest_invalid_param`); 500 is always a failure. A saved page carrying the same values loads 200 with no fatal in debug.log. Lengths match `^\d+(\.\d+)?(px|em|rem|%|vw|vh)$` (a bad side drops the whole declaration), units are one of `px, em, rem, %, vw, vh` (else `px`), shadow offsets are integer-cast, colours are valid or default, the unique id passes `sanitize_html_class`, and final CSS is stripped of `<`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Enumerate blocks and attributes once: `WP_Block_Type_Registry::get_instance()->get_all_registered()` filtered to `listora/` (plus `listora-pro/` in combo mode); collect every `number`/`integer` attribute (11 Free blocks and 79 numeric attributes at last count; Pro adds comparison, credit-purchase, moderator-queue, needs-grid, post-need).
- Fixture page title `QA-J1 adversarial` is created and deleted by the steps.

## Steps

### 1. Numeric matrix never returns 500 ({admin})
- **Action**: for each (block, numeric attribute) call `GET /wp/v2/block-renderer/{block}?context=edit&attributes[{attr}]={v}` with `v` in `0`, `-1`, `-999999`, `0.4`, `999999`, `"abc"`.
- **Expect**: every status is 200 or 400 (observed 363 x 200, 111 x 400, zero 500 over 474 calls).
- **Read-back**: tally statuses; the failing triple (block, attribute, value) is the report.

### 2. Anonymous cannot probe the renderer
- **Action**: `GET /wp-json/wp/v2/block-renderer/listora/listing-featured?context=edit&attributes[columns]=3` logged out.
- **Expect**: 401 `block_cannot_read`; a 200 is a leak.

### 3. Saved content with zero/huge numerics renders ({guest})
- **Action**: publish a page with `<!-- wp:listora/listing-featured {"columns":0,"count":0} /--><!-- wp:listora/listing-grid {"columns":0,"perPage":0} /--><!-- wp:listora/listing-categories {"columns":0,"limit":0} /-->` (combo: add Pro needs-grid and credit-purchase at 0). Truncate debug.log; fetch the permalink logged out.
- **Expect**: 200; no `Fatal error` / `DivisionByZeroError` in body or debug.log; every inline `--listora-*-columns:` is >= 1 (observed 4, 3, 4).
- **Positive control**: the same page with `"columns":3` prints `--listora-featured-columns: 3`.

### 4. Strings and upper bounds reach the markup whitelisted
- **Action** ({admin}, renderer): on `listing-grid` send `attributes[cardLayout]=a b"><x`, `<script>`, `attributes[columns]=99999999999`, `attributes[perPage]=99999999999`; repeat `columns=99999999999` on `listing-featured` and `listing-categories`; then save a page with the same JSON and fetch it as {guest}.
- **Expect**: 200 or 400; on 200 the layout class is a registered layout name (unknown falls back to default, never echoed), no `"`, `<` or space inside any `class="..."` token, every inline `--listora-*-columns:` is an integer 1 to 12.
- **Positive control**: `cardLayout=list` and `columns=3` print `list` and `3`. fails_on: unknown. Not verified live.

### 5. CSS injection matrix through the renderer ({admin})
For each row call `GET /wp/v2/block-renderer/listora/listing-grid?context=edit` with `attributes[uniqueId]=qa` plus the attributes shown and read the `<style>` in `rendered`:
| Case | Attributes | Expect in the style |
|---|---|---|
| good length (positive control) | `padding={top:12,right:8,bottom:12,left:8}`, `paddingUnit=rem` | `padding: 12rem 8rem 12rem 8rem;` |
| declaration breakout | `padding.top = "1px;}body{display:none}/*"` (others `1`) | no padding rule; no `body{display:none}` anywhere |
| bad unit | `paddingUnit = "px;}x{"` | falls back to `px`: `padding: 4px 4px 4px 4px;` |
| url() colour | `boxShadow=true`, `shadowColor="url(https://evil.example/x.png)"` | default `rgba(0,0,0,0.12)`; no `url(` |
| tag breakout colour | `boxShadow=true`, `shadowColor="red}</style><script>alert(1)</script>"` | default colour; no `<script>`, no extra `</style>` |
| good colour (positive control) | `shadowColor="rgba(10,20,30,0.4)"`, `shadowBlur=12` | `box-shadow: 0px 4px 12px 0px rgba(10,20,30,0.4);` |
| unique id | `uniqueId = "a b}</style><script>x"` | class is sanitised `.listora-block-abstylescriptx` |
- **Expect**: HTTP 200 on every row; `grep -c '<script'` of every `rendered` is 0.

### 6. Saved content a {contributor} can write, read by {guest}
- **Action**: as {contributor} save a draft containing `<!-- wp:listora/listing-grid {"uniqueId":"qb","boxShadow":true,"shadowColor":"red}</style><script>window.__qa=1</script>","padding":{"top":"1px;}body{background:red}/*","right":1,"bottom":1,"left":1}} /-->`; {editor} or {admin} publishes; open it logged out.
- **Expect**: 200; no `window.__qa` in view-source and undefined in the console; no `body{background:red}`; the block still renders its listings.
- **Read-back**: `curl -s <page> | grep -c '</style><script>'` is 0 and the `<style>` tag count equals the same page with the attribute removed. **Positive control**: `{"uniqueId":"qb","paddingUnit":"rem","padding":{"top":2,"right":2,"bottom":2,"left":2}}` prints `padding: 2rem ...`. Not verified live.

### 7. Every string-typed attribute of every block (bc-10393672585)
- **Action** ({admin} renderer, then contributor-saved page read as guest): enumerate string-typed attributes in each `block.json` (height, width, gap, border colour, background, text colour, font size, map height); send `red;} body{display:none`, `url(https://evil.example/x.png)`, `expression(alert(1))`, `10px;position:fixed`.
- **Expect**: none of the four appears in rendered `<style>` or inline `style=""`; the attribute falls back to default or the declaration is dropped; `grep -c 'display:none'` is 0.
- **Positive control**: `10px`, `2rem`, `#336699` print as given. fails_on: 2394e3c (map height alone: 104866df). Not verified live.

### 8. All blocks share the one helper
- **Expect**: every Free block prints its style through `Block_CSS::render`; repeat the "declaration breakout" and "tag breakout colour" rows of step 5 on `listing-featured` and `listing-categories`: output unchanged versus the grid.
- **Read-back**: `python3 bin/check-block-attr-guards.py` exits 0 and names the `file:line` when a floor is missing.

### 9. Cleanup of fixtures
Delete the fixture pages; re-truncate debug.log.

## Role ladder
| Rung | Action | Expect |
|---|---|---|
| {admin} | steps 1, 4, 5, 7 | pass (renderer needs an editor session) |
| {guest} | step 2; reads pages from steps 3, 4, 6 | 401 on the renderer; sanitised output on pages |
| {contributor} | step 6 save | allowed to save; output sanitised when anyone renders it |
| {other} | views the published page | same sanitised output |

## Pass criteria
1. Zero 500s across the numeric matrix and the saved-content fixtures.
2. No attribute value can add a CSS rule, load an external URL, echo a class token, or close the style tag.
3. Valid lengths, units and colours still render (positive controls).

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| 500 / DivisionByZero | missing `max( 1, (int) ... )` floor | `blocks/{block}/render.php` (for example `listing-featured/render.php:19`) |
| Out-of-range accepted | missing `minimum`/`maximum` | `blocks/{block}/block.json` |
| `body{...}` in output | length allow-list loosened | `Block_CSS::css_length` (`includes/class-block-css.php`) |
| `<script>` in output | `<` strip removed or second style writer | `Block_CSS::render`; grep other `<style>` emitters |
| Unit passes through | `unit()` not used for a box attribute | `Block_CSS::generate` box loop |

## Not verified
Steps 3 (positive control), 4, 6, 7 were not executed live. Pro blocks were enumerated but only Free blocks were driven in the observed run.

## Cleanup
Delete fixture pages and the contributor draft; truncate debug.log.
