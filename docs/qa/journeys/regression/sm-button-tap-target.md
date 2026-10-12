---
journey: sm-button-tap-target
plugin: wb-listora
priority: high
roles: [anonymous, member-owner]
covers: [f2-sm-tap-target, AUD-F2, ux-foundation-rule-13, tap-target-40px, bc-10350942570]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Free active; assets/css/listora-components.css (served via the .layer.css twin) loaded"
  - "A directory page with the search-filter + pagination UI rendered (the Directory $SITE_URL/listings/ has 84 published, 5 pages)"
estimated_runtime_minutes: 4
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [390, 820, 1440]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Customer-facing --sm buttons meet the 40px tap-target floor at 390px

Customer-facing `.listora-btn--sm` keeps a 40px minimum hit area (ux-foundation Rule 13 / WCAG 2.1 AA) with compact padding/font. Source: `src/components/button.css:174-189` (`.listora-btn.listora-btn--sm, a.listora-btn.listora-btn--sm { min-height: 40px }`), compiled to `assets/css/listora-components.css:469-484` (+ `.layer.css`, RTL twins). wp-admin keeps the 34px density floor on the base button: `assets/css/admin.css:170-173` (`.listora-btn { min-height: 34px }`, which wins in admin because the primitives sit in a lower cascade layer there). NOTE: the former `.wb-listora-admin .listora-btn--sm { min-height: 34px }` rule no longer exists as such; the admin floor is on `.listora-btn` itself.

Verified via: source read and the live Directory markup (3 `.listora-btn--sm` nodes on `/listings/` anonymous, `.listora-grid__page-num` present for the 5 pages). The 390px bounding-rect measurements were NOT run in a browser here.

## Steps

### 1. Render the directory at 390px
- **Action**: resize 390x844, open `$SITE_URL/listings/` (anonymous).
- **Expect**: 200; filter bar and pagination (`.listora-grid__page-numbers` with `.listora-grid__page-num`, `templates/blocks/listing-grid/pagination.php:63-78`) render.

### 2. Every customer-facing --sm button is >= 40px tall
- **Action**: for each `.listora-btn--sm` not inside `.wb-listora-admin`, read `getBoundingClientRect().height`.
- **Expect**: every height >= 40. Repeat as a logged-in owner on `/my-listings/` (dashboard Edit/Delete `--sm` buttons: more of them than the public page).

### 3. Admin exception intact (negative check)
- **Action**: `grep -n "min-height: 34px" assets/css/admin.css` and, in wp-admin (Listora > Settings), measure `.listora-btn--sm`.
- **Expect**: `admin.css:173` present and admin buttons report 34px; the 40px floor is customer-facing only.

### 4. No mis-tap overlap on filter controls
- **Expect**: no two interactive rects among the filter `--sm` buttons/chips overlap; gaps keep each 40px hit box distinct.

### 5. No mis-tap overlap on pagination
- **Expect**: adjacent `.listora-grid__page-num` rects do not overlap and each is >= 40px at 390.

### 6. Large set
- Page 5 of the Directory (`?listora_page=5`) renders the same pagination controls with the same sizes (84 rows, 20 per page).

### 7. Buttons in one action row share one rendered height (card 10350942570)
- **Action**: as OWNER at 1440 and 390, open `/my-listings/?tab=listings` (row actions Edit, View and the rest) and the single listing page (`.listora-detail__actions`, owner toolbar Edit next to Share, Save, Claim). For each row of buttons read `getBoundingClientRect().height`, and `getComputedStyle` `fontSize`, `fontWeight`, `lineHeight`, `paddingBlock` of every `<a class="listora-btn">` and `<button class="listora-btn">` in that row.
- **Expect**: within each row `new Set(heights).size === 1` and the four computed values are identical between anchor and button elements (the `<button>` Edit/View is not shorter or lighter than the `<a>` ones).
- **Read-back**: log `[...row.children].map(e => e.getBoundingClientRect().height)`; negative control: a row where one control is deliberately `.listora-btn--sm` among non-sm buttons reports two distinct heights, proving the check can fail.
- **fails_on**: 4b0fd91f (parent of 939a5e6c, the button specificity fix; font-weight parity 015b0a09 followed). Not verified live.

## Fail diagnostics
| Symptom | File |
|---|---|
| --sm < 40px on frontend | `src/components/button.css:187` -> recompile `assets/css/listora-components.css:482` |
| Admin buttons grew to 40px | `assets/css/admin.css:173` or layer order in `Assets::enqueue_admin` |
| Filter chips overlap at 390 | `blocks/listing-search/style.css` |
| Page numbers overlap | `blocks/listing-grid/style.css` pagination rules |
