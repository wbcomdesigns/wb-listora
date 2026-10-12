---
journey: pagination-active-page-contrast
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [pagination-active-page, theme-hardening, buddyx-anchor-rule-defence]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "More than one page of listings (this site: 81 published, 20 per page = 5 pages)"
  - "BuddyX Pro active (the repro theme); repeat once on an unknown default theme"
estimated_runtime_minutes: 3
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Pagination active page number stays readable under aggressive theme link rules

Aggressive themes that recolour `.entry-content a:not()x3` (BuddyX 5.x, specificity 0,4,1) used to repaint the ACTIVE page
number in the link colour on the primary fill. The Directory renders the current page as an anchor
`<a class="listora-grid__page-num is-active" aria-current="page">`, so it needs the defence in
`src/components/theme-hardening.css:251-259` (specificity 0,5,1, no `!important`), compiled to
`assets/css/listora-components.css` (+ `-rtl` twin; never hand-edit).

Role ladder: anonymous only (the pagination is identical for every role; a logged-in member was used for the 2026-10-12 browser run).

## Steps

### 1. Open the Directory (1440)
- **Action**: navigate `$SITE_URL/listings/`.
- **Expect**: `nav.listora-grid__pagination[aria-label="Pagination"]` containing `.listora-grid__page-numbers` with page links
  `a.listora-grid__page-num` whose href is `/listings/?listora_page=N`. No console errors.

### 2. Exactly one active page
- **Action**: `document.querySelectorAll('.listora-grid__page-num.is-active')`.
- **Expect**: 1 element, `<A>`, `aria-current="page"`, text `1`.

### 3. Contrast, light mode
- **Action**: read `getComputedStyle(active).color` and `.backgroundColor` (convert `oklch()` through a canvas, the primary
  colour can be authored in oklch), compute the WCAG ratio.
- **Expect**: ratio >= 4.5:1 and not near-identical channels (the "blue-on-blue" guard). Colours come from tokens
  `--listora-primary-fg` on `--listora-primary-fill`.

### 4. Contrast, dark mode
- **Action**: switch the theme to dark (BuddyX Pro toggles `html.dark-mode`; on a default theme use
  `emulateMedia({colorScheme:'dark'})`), reload, repeat step 3.
- **Expect**: ratio >= 4.5:1. Measured 2026-10-12 (BuddyX Pro, dark): text `rgb(15,23,42)` on `rgb(255,110,110)` = 6.55:1.

### 5. Non-active numbers are readable links
- **Expect**: a non-active `.listora-grid__page-num` has colour `--listora-fg-default` and contrast >= 4.5:1 against its
  background (measured dark: `rgb(229,229,229)` on `rgb(10,10,10)` = 15.7:1).

### 6. Tap targets and overflow (390)
- **Expect**: each page number >= 40px square (rule `min-width: 40px; height: 40px`; measured 61x42), no horizontal page scroll.

### 7. Large site: a page past the end shows the last page (2.0.0)
- **Action**: `$SITE_URL/listings/?listora_page=9999`.
- **Expect**: the last page (5 on this site) is active; no "Showing 1,961-127 of 127" style range.
  Positive control: `?listora_page=2` makes page 2 active. Note this site has 81 public listings (2,700+ drafts), so 2000-row
  pagination is exercised in wp-admin (`edit.php?post_type=listora_listing`), not on the public Directory.

## Pass criteria
1. One active `<a aria-current="page">`. 2. Contrast >= 4.5:1 in light and dark. 3. Non-active numbers >= 4.5:1.
4. 40px targets, no sideways scroll at 390. 5. Past-the-end page shows the last page.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Active text is the theme link colour | theme out-specifies pagination | `src/components/theme-hardening.css:251-259` (blocks `a.listora-grid__page-num...is-active.is-active`); rebuild `npm run build:css` |
| Fix in `src/` not on the page | compiled CSS stale | `assets/css/listora-components.css:2022` area (+ `listora-components-rtl.css`) |
| Hard-coded hex fails in dark | token not used | same block must read `var(--listora-primary-fill)` / `var(--listora-primary-fg)` |
| No pagination | too few listings | seed more or lower `listings_per_page` |

## Not verified
Light-mode measurement (the browser profile was already in dark mode) and the unknown default theme were not run.
