---
journey: reviews-deep-link-tab-switching
group: reviews
actor: customer
mode: browser
plugin: wb-listora
priority: high
roles: [anonymous, listing_owner]
covers: [listing-detail, deep-links, tab-switching, card-10304369374]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A {listing} with at least one approved review; note REVIEW_ID"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: ["ora:regression/reviews-panel-target-leak.md"]
fails_on: the CSS-only :target reveal before `.is-tabs-ready` (step 1)
---

# A deep link to reviews opens the Reviews tab and never leaves it stuck under another tab

## Why
Review-reminder emails link to `#reviews`, `#review-<id>`, `#oldest-unanswered`. `history.replaceState()` never recomputes `:target`, so a CSS reveal kept Reviews visible under the active tab.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Use a fresh page load per step (navigate to `about:blank` first: a hash-only navigation does not re-run the script) and a fresh browser context (the script URL is versioned by plugin version).
- Reveal CSS `blocks/listing-detail/style.css:1839-1865`, scoped `.listora-detail:not(.is-tabs-ready)`; fallback script `assets/js/listing-detail-fallback.js:200` adds `.is-tabs-ready` and opens the tab owning the hash. Panels `#panel-overview`, `#panel-reviews` (`role=tabpanel`, `hidden` when inactive), tabs `#tab-overview`, `#tab-reviews`.

## Steps

### 1. `#reviews` then switch
- **Action**: load `$SITE_URL/listing/{listing}/#reviews`, wait ~1.5s, click `#tab-overview`.
- **Expect**: before: `#panel-reviews` has height and `#panel-overview` 0; after: only overview has height and Reviews 0; `.listora-detail` has `is-tabs-ready`; URL becomes `#overview`.
- **Read-back**: `getBoundingClientRect().height` of both panels.

### 2. `#review-<id>` opens Reviews, then switch
- **Action**: load `/#review-REVIEW_ID`, wait, click Overview.
- **Expect**: on load `#tab-reviews[aria-selected=true]` and Reviews has height; URL normalises to `#reviews`; after the click Reviews has no height.

### 3. No JavaScript
- **Action**: JS-disabled context, load `...#reviews`.
- **Expect**: `#panel-reviews` visible through the `:not(.is-tabs-ready)` reveal. Not verified live.

### 4. Hashes that are not valid selectors
- **Action**: open with `#a:b`, `#x[`, `#%E0%A4%A`.
- **Expect**: no uncaught console error; `.listora-detail` has `is-tabs-ready`; Overview is the active tab (`#x[` verified; others Not verified live).

### 5. `#oldest-unanswered` (reminder email marker)
- **Expect**: opens Reviews, then switching tab hides it. The marker exists only for the listing owner with an unanswered review. Not verified live.

### 6. Mobile (390)
- **Expect**: the tab bar scrolls inside its column, Reviews tab reachable, no horizontal page scroll. Dark and unbridged theme: Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {guest} | steps 1-6 (the contract) |
| {owner} | same link behaves the same; `#oldest-unanswered` marker present when an unanswered review exists |
| {other} / {editor} / {admin} | identical to guest (role-independent) |

## Pass criteria
1. A hash target opens the tab that owns it; switching afterwards hides Reviews.
2. Invalid hashes do not break tabs.
3. No-JS visitors still see Reviews.

## Fail diagnostics
`assets/js/listing-detail-fallback.js:200`; `blocks/listing-detail/style.css:1863-1864`; `templates/blocks/listing-detail/tabs.php:459` (`#panel-reviews`), `:531` (`id="review-<id>"`).

## Not verified
Steps 3, 5, `#a:b` and malformed hashes, 390, dark and unbridged theme.

## Cleanup
None.
