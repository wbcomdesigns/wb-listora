---
journey: pagination-active-page-contrast
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [b299fd6, pagination-active-page, theme-hardening, BuddyX anchor-rule defence]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "More than one page of listings published (per_page default ~12; needs >12 published listora_listing)"
  - "An aggressive-anchor theme active (BuddyX) — the repro theme; the fix is theme-agnostic but BuddyX is what surfaced it"
  - "Viewport ≥1280px during walk"
estimated_runtime_minutes: 3
covers_card: null
---

# Pagination active page number stays readable under aggressive theme link rules (b299fd6 sentinel)

`theme-isolation.css` deliberately excludes `[class*="page-num"]` from its anchor
reset, so pagination anchors fall back to the plugin's own pagination rules
(specificity 0,2–3,0). Aggressive themes that recolor `.entry-content a:not()×3`
out-specify those — BuddyX 5.x lands at 0,4,1 — and repaint the ACTIVE page
number in the theme link colour: blue text on the primary-fill (also blue)
background = unreadable.

Fix: commit b299fd6 — `src/components/theme-hardening.css` (compiled to
`assets/css/listora-components.css` + RTL twin) re-asserts the pagination colour
at specificity 0,5,1 (block-ancestor + element + doubled class/state, NO
`!important`), one tier above BuddyX's anchor rules. The active page renders
`color: var(--listora-primary-fg, #fff)` on `background: var(--listora-primary)`.

WP-core renders the *current* page as a `<span>` (which `.entry-content a` never
matches); WB Listora renders it as `<a class="listora-grid__page-num is-active"
aria-current="page">`, which IS an anchor — hence the need for the defence.

## Setup

- Site: `$SITE_URL`
- Anonymous browser session (use `?autologin=1` only if the directory is private)
- Confirm `>1` page of listings exists: `/listings/` must render a
  `.listora-grid__page-numbers` row with at least a page-2 link.

## Steps

### 1. Open the listings directory at desktop
- **Action**: `playwright_resize 1280 900`, then `playwright_navigate $SITE_URL/listings/`
- **Expect**: grid renders; a `.listora-grid__page-numbers` pagination row is
  present with more than one page link. No console errors.

### 2. Locate the active page number
- **Action**: `browser_evaluate` — find the element in
  `.listora-grid__page-num` (or `.page-numbers`) carrying `is-active` /
  `current` / `aria-current="page"`.
- **Expect**: exactly one active element; it is an `<a>` (the regression target),
  text content `1`, `aria-current="page"`.

### 3. Measure active-page contrast in LIGHT mode
- **Action**: `browser_evaluate` — read `getComputedStyle(active).color` and the
  effective background (walk ancestors to the first non-transparent
  `background-color`). Compute the WCAG contrast ratio.
- **Expect**:
  - `color` is the light foreground (e.g. `rgb(255, 255, 255)` from
    `--listora-primary-fg`)
  - effective background is the primary fill (e.g. `rgb(30, 115, 190)` from
    `--listora-primary`)
  - contrast ratio ≥ 4.5:1 (AA normal text)
  - text and background are NOT near-identical ("blue-on-blue" guard: per-channel
    delta must exceed ~20 on at least one channel)

### 4. Measure active-page contrast in DARK mode
- **Action**: `browser_run_code_unsafe` — `page.emulateMedia({ colorScheme: 'dark' })`,
  reload, repeat the step-3 measurement.
- **Expect**: same readable result — `prefers-color-scheme: dark` is active,
  contrast ratio ≥ 4.5:1, not blue-on-blue. The tokens resolve to a readable
  pair in both schemes (the fix uses tokens, never a hard-coded hex).

### 5. Non-active page numbers remain readable links
- **Action**: `browser_evaluate` — for a non-active `.listora-grid__page-num`,
  read its colour and contrast against the page background.
- **Expect**: colour resolves to `--listora-fg-default` (not the invisible theme
  link colour); contrast against the page background ≥ 4.5:1.

## Pass criteria

1. Exactly one active page number, rendered as an `<a>` with `aria-current="page"`
2. LIGHT mode: active-page text/background contrast ≥ 4.5:1, not blue-on-blue
3. DARK mode: active-page text/background contrast ≥ 4.5:1, not blue-on-blue
4. Non-active page numbers are readable (≥ 4.5:1) against the grid background
5. No `!important` is required to win — the rule sits at specificity 0,5,1

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Active page text is the theme link colour (blue-on-blue, contrast ~1:1) | regression of b299fd6 — theme out-specifies pagination colour again | `src/components/theme-hardening.css` — the `[class*="wp-block-listora"] a.listora-grid__page-num.listora-grid__page-num.is-active.is-active` block (0,5,1) must exist; rebuild via `npm run build:css` |
| Fix present in `src/` but not on the page | compiled CSS stale | `assets/css/listora-components.css` (+ `-rtl.css` twin) must carry the same block — never hand-edit; run `npm run build:css` |
| Dark mode reads as a hard hex, fails on a theme that overrides the token | colour was hard-coded instead of tokenised | same block must use `var(--listora-primary)` / `var(--listora-primary-fg)` / `var(--listora-fg-default)`, not literals |
| No pagination renders | too few listings / per_page covers everything | seed >12 published listings, or lower `listings_per_page` |
