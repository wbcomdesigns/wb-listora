---
journey: pagination-active-page-contrast
plugin: wb-listora
priority: high
roles: [guest, owner]
covers: [pagination-active-page, theme-hardening, buddyx-anchor-rule-defence, accessibility-contrast]
prerequisites:
  - "More than one page of published listings (set `listings_per_page` low or seed)"
  - "BuddyX Pro active (the repro theme); repeat once on an unknown default theme"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/pagination-active-page-contrast.md
fails_on: unrecorded
site_profile: required
---

# The active page number stays readable under aggressive theme link rules

## Why
Themes that recolour `.entry-content a:not()x3` (BuddyX 5.x, specificity 0,4,1) repainted the active page number in the link colour on the primary fill. The Directory renders the current page as `<a class="listora-grid__page-num is-active" aria-current="page">`, defended in `src/components/theme-hardening.css:251-259` (0,5,1, no `!important`), compiled to `assets/css/listora-components.css` (+ `-rtl`; never hand-edit).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `P` = number of pages = `GET /search` `pages` for the unfiltered Directory.

## Steps

### 1. Open the Directory (1440)
- **Action**: navigate `$SITE_URL/{directory-path}/`.
- **Expect**: `nav.listora-grid__pagination[aria-label="Pagination"]` with `.listora-grid__page-numbers` links `a.listora-grid__page-num` whose href is `/{directory-path}/?listora_page=N`; no console errors.

### 2. Exactly one active page
- **Action**: `document.querySelectorAll('.listora-grid__page-num.is-active')`.
- **Expect**: 1 element, `<A>`, `aria-current="page"`, text `1`.

### 3. Contrast, light mode
- **Action**: read `getComputedStyle(active).color` and `.backgroundColor` (convert `oklch()` through a canvas), compute the WCAG ratio.
- **Expect**: ratio >= 4.5:1 and not near-identical channels (the blue-on-blue guard); colours come from `--listora-primary-fg` on `--listora-primary-fill`.

### 4. Contrast, dark mode
- **Action**: switch to dark (BuddyX Pro: `html.dark-mode`; default theme: `emulateMedia({colorScheme:'dark'})`), reload, repeat step 3.
- **Expect**: ratio >= 4.5:1.

### 5. Non-active numbers are readable links
- **Expect**: a non-active `.listora-grid__page-num` uses `--listora-fg-default` with contrast >= 4.5:1 against its background.

### 6. Tap targets and overflow (390)
- **Expect**: each page number >= 40px square (`min-width: 40px; height: 40px`), no horizontal scroll.

### 7. A page past the end shows the last page
- **Action**: `?listora_page=99999`, then `?listora_page=2`.
- **Expect**: page P is active (never a range like "Showing 1,961-127 of 127"); `?listora_page=2` makes page 2 active (positive control). The 2000-row case is exercised in wp-admin (`edit.php?post_type=listora_listing`) and `customer/paging-past-last-page.md`.

## Role ladder
Pagination is identical for every role; {guest} is the actor, {owner} (member) is a spot check.

## Pass criteria
1. One active `<a aria-current="page">`.
2. Contrast >= 4.5:1 in light and dark; non-active numbers >= 4.5:1.
3. 40px targets, no sideways scroll at 390.
4. Past-the-end shows the last page.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Active text is the theme link colour | theme out-specifies pagination | `src/components/theme-hardening.css:251-259`; rebuild `npm run build:css` |
| Fix in `src/` not on page | compiled CSS stale | `assets/css/listora-components.css` (+ `-rtl`) |
| Fails in dark | hard-coded hex | block must read `var(--listora-primary-fill)` / `var(--listora-primary-fg)` |
| No pagination | too few listings | seed more or lower `listings_per_page` |

## Not verified
Light-mode measurement (profile was in dark) and the unknown default theme were not run.

## Cleanup
Restore `listings_per_page` if changed.
