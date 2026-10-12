---
journey: sm-button-tap-target
plugin: wb-listora
priority: high
roles: [guest, owner]
covers: [f2-sm-tap-target, AUD-F2, ux-foundation-rule-13, tap-target-40px, button-row-height-parity, bc-10350942570]
prerequisites:
  - "Site reachable at $SITE_URL; Free active; `assets/css/listora-components.css` (served via the `.layer.css` twin) loaded"
  - "A Directory page with search-filter + pagination UI and more than one page of published listings (seed N QA-prefixed if needed)"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [390, 820, 1440]
themes: [buddyx-pro light, buddyx-pro dark, unbridged]
site_profile: required
supersedes: []
fails_on: 4b0fd91f
---

# Customer-facing --sm buttons meet the 40px tap-target floor, and buttons in one row share one height

## Why
Small buttons under 40px are mis-tapped on phones (ux-foundation Rule 13 / WCAG 2.1 AA 2.5.8), and an `<a>` Edit beside a `<button>` Edit with different heights or weights looks broken. Customer-facing `.listora-btn--sm` keeps a 40px minimum hit area with compact padding/font; wp-admin keeps its 34px density floor on the base button.

Source: `src/components/button.css` (`.listora-btn.listora-btn--sm, a.listora-btn.listora-btn--sm { min-height: 40px }`) compiled to `assets/css/listora-components.css` (+ `.layer.css`, RTL twins). Admin floor: `assets/css/admin.css` (`.listora-btn { min-height: 34px }`, which wins in admin because primitives sit in a lower cascade layer there).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Resize with the browser tool; sign in as {owner} for the dashboard rows.

## Steps

### 1. Render the Directory at 390
- **Action**: 390x844, open the Directory as {guest}.
- **Expect**: 200; filter bar and pagination (`.listora-grid__page-numbers` with `.listora-grid__page-num`, from `templates/blocks/listing-grid/pagination.php`) render.

### 2. Every customer-facing --sm button is >= 40px tall
- **Action**: for each `.listora-btn--sm` not inside `.wb-listora-admin`, read `getBoundingClientRect().height`; repeat as {owner} on the member dashboard (Edit/Delete `--sm` buttons: more than the public page).
- **Expect**: every height >= 40 at 390, 820 and 1440.

### 3. Admin exception intact (negative check)
- **Action**: `grep -n "min-height: 34px" assets/css/admin.css`; in wp-admin (Listora > Settings) measure `.listora-btn--sm` as {admin}.
- **Expect**: the rule present and admin buttons 34px; the 40px floor is customer-facing only.

### 4. No mis-tap overlap on filter controls and pagination
- **Expect**: no two interactive rects among the filter `--sm` buttons/chips overlap; adjacent `.listora-grid__page-num` rects do not overlap and each is >= 40px at 390.

### 5. Large set
- **Action**: open the last page of the Directory (`?listora_page=<pages>`), where `pages` = ceil(published / per_page).
- **Expect**: the same pagination controls with the same sizes.

### 6. Buttons in one action row share one rendered height (bc-10350942570)
- **Action**: as {owner} at 1440 and 390, open the dashboard Listings tab (row actions Edit, View and the rest) and the single listing page (`.listora-detail__actions`, owner toolbar Edit next to Share, Save, Claim). For each row read `getBoundingClientRect().height` and computed `fontSize`, `fontWeight`, `lineHeight`, `paddingBlock` of every `<a class="listora-btn">` and `<button class="listora-btn">`.
- **Expect**: within each row `new Set(heights).size === 1` and the four computed values are identical between anchor and button elements.
- **Read-back**: log `[...row.children].map(e => e.getBoundingClientRect().height)`; negative control: a row with one deliberate `.listora-btn--sm` among non-sm buttons reports two heights, proving the check can fail. fails_on: 4b0fd91f (parent of 939a5e6c; font-weight parity 015b0a09 followed). Not verified live.

### 7. Dark mode and the unbridged theme
- **Expect**: heights unchanged in dark and on the unbridged theme.

## Role ladder
| Rung | Expect |
|---|---|
| {guest} | steps 1-5 |
| {owner} | steps 2, 6 (more --sm buttons on the dashboard) |
| {admin} | step 3 (34px control) |

## Pass criteria
1. Every customer-facing `.listora-btn--sm` is >= 40px tall at 390, 820, 1440.
2. Admin buttons keep 34px.
3. Filter chips and page numbers do not overlap; anchors and buttons in a row share one height.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| --sm < 40px on frontend | rule or compiled twin stale | `src/components/button.css` (~:187) then recompile `assets/css/listora-components.css` |
| Admin buttons grew to 40px | admin floor or layer order | `assets/css/admin.css` (~:173); `Assets::enqueue_admin` |
| Filter chips overlap at 390 | | `blocks/listing-search/style.css` |
| Page numbers overlap | | `blocks/listing-grid/style.css` |

## Not verified
The 390px bounding-rect measurements were NOT run in a browser in the observed pass (source read plus live markup: three `.listora-btn--sm` nodes on the Directory as a guest); step 6 not run.

## Cleanup
None.
