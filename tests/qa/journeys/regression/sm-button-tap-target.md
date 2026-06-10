---
journey: sm-button-tap-target
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [f2-sm-tap-target, AUD-F2, ux-foundation-rule-13, tap-target-40px]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Free active; compiled assets/css/listora-components.css served"
  - "A directory/listings page with the search-filter + pagination UI rendered"
estimated_runtime_minutes: 4
covers_card: null
covers_commit: HEAD
---

# Customer-facing --sm buttons meet the 40px tap-target floor at 390px

The wave-2 `f2-sm-tap-target` fix lifts every customer-facing `.listora-btn--sm`
to a 40px minimum hit area (ux-foundation Rule 13 / WCAG 2.1 AA), while keeping
the dense visual via compact `--sm` padding/font. wp-admin context keeps its
34px density exception (`.wb-listora-admin .listora-btn--sm`). This journey
locks the customer floor at a 390px mobile viewport and verifies no mis-tap
overlap on the filter/pagination controls.

## Setup

- Site: `$SITE_URL`
- Anonymous browser, viewport resized to **390 x 844**
- A listings/directory page that renders search-filter `--sm` buttons (apply /
  clear / facet chips) and grid pagination (`.listora-grid__page-num`)

## Steps

### 1. Render the directory at 390px
- **Action**: `playwright_resize 390 844` then `playwright_navigate <listings page>`
- **Expect**: HTTP 200; the filter bar + pagination render
- **On fail**: page/block render, not this fix

### 2. Every customer-facing --sm button has hit area >= 40px
- **Action**: for each `.listora-btn--sm` in the customer surface (NOT inside
  `.wb-listora-admin`), read `getBoundingClientRect().height`.
- **Expect**: every such button's rendered height is `>= 40`px. The source rule
  is `min-height: 40px` on `.listora-btn--sm` (compiled
  `assets/css/listora-components.css:315`).
- **On fail**: `src/components/button.css:152-160` (`.listora-btn--sm`
  min-height) not compiled → recompile `assets/css/listora-components.css`

### 3. wp-admin --sm exception is intact (negative check)
- **Action**: confirm the rule `.wb-listora-admin .listora-btn--sm { min-height: 34px; }`
  still exists in the compiled CSS (admin density exception is intentional).
- **Expect**: present - the 40px floor is customer-facing only; admin keeps 34px.
- **On fail**: `src/components/button.css` admin override removed

### 4. No mis-tap overlap on filter controls
- **Action**: enumerate the filter `--sm` buttons/chips' bounding rects.
- **Expect**: no two interactive tap targets overlap; vertical/horizontal gaps
  keep each 40px hit box distinct (a tap on one cannot land on its neighbour).
- **On fail**: filter layout spacing in the search/filter block CSS

### 5. No mis-tap overlap on pagination
- **Action**: enumerate `.listora-grid__page-num` rects on a >1-page result.
- **Expect**: adjacent page numbers do not overlap; each is independently
  tappable at 390px.
- **On fail**: pagination layout CSS in the grid block

### 6. Visual sanity screenshot
- **Action**: `playwright_take_screenshot` of the filter + pagination region at 390px.
- **Expect**: buttons look dense (compact padding) but each is comfortably
  tappable; no clipped/overlapping controls.

## Pass criteria

ALL of the following hold:
1. Every customer-facing `.listora-btn--sm` renders >= 40px tall at 390px.
2. The `.wb-listora-admin .listora-btn--sm` 34px exception still exists.
3. No two filter tap targets overlap.
4. No two pagination tap targets overlap.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| --sm button < 40px on frontend | source min-height not compiled | `src/components/button.css:158` → recompile `assets/css/listora-components.css:315` |
| Admin buttons grew to 40px | density exception dropped | `src/components/button.css` `.wb-listora-admin .listora-btn--sm` |
| Filter chips overlap at 390px | filter flex/gap regression | search/filter block CSS |
| Pagination numbers overlap | grid pagination layout | `blocks/listing-grid` pagination CSS |
