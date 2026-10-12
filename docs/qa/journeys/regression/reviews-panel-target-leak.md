---
journey: reviews-panel-target-leak
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [listing-detail, deep-links, card-10304369374]
prerequisites:
  - "A listing with at least one approved review - note a review id (listing 59 / review 14: /listing/flames-smoke-bbq/#review-14)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A deep link to reviews never leaves the Reviews panel stuck under another tab

`blocks/listing-detail/style.css:1839-1865` reveals `#panel-reviews` with `:target` / `:has(:target)` so review-reminder links (`#reviews`,
`#review-<id>`, `#oldest-unanswered`) show reviews before JavaScript runs. Tab switches use `history.replaceState()`, which never
recomputes `:target`, so the reveal used to keep Reviews visible under the active tab. The fallback tab script
(`assets/js/listing-detail-fallback.js:200`) now adds `.is-tabs-ready` to `.listora-detail` first and opens the tab that owns any hash target
(`#reviews`, or any element inside a panel); the CSS reveal is scoped `.listora-detail:not(.is-tabs-ready)` so it still serves no-JS visitors.
Panels are `#panel-overview`, `#panel-reviews`, ... (`role=tabpanel`, `hidden` when inactive), tabs `#tab-overview`, `#tab-reviews`.

Role ladder: anonymous (the reminder email recipient is usually the owner; the behaviour is role-independent, so anonymous is the
contract). Controls: the same link logged in as the owner behaves the same.

Use a fresh page load per step (navigate to `about:blank` first: a hash-only navigation does not re-run the script), and a fresh browser
context, because the script URL is versioned by plugin version.

## Steps (steps 1, 2, 4 verified in a browser 2026-10-12, 1440 wide, BuddyX Pro)

### 1. `#reviews` then switch
- **Action**: load `/listing/<slug>/#reviews`, wait ~1.5s, click `#tab-overview`.
- **Expect**: before the click `#panel-reviews` has height (603px) and `#panel-overview` has 0; after it only overview has height
  (443px) and Reviews has 0; `.listora-detail` has `is-tabs-ready`; the URL becomes `#overview`.

### 2. `#review-<id>` opens Reviews, then switch
- **Action**: load `/listing/<slug>/#review-14`, wait, click Overview.
- **Expect**: on load `#tab-reviews[aria-selected=true]` and Reviews has height; the URL normalises to `#reviews`; after the click
  Reviews has no height.

### 3. No JavaScript (NOT RUN)
- **Action**: JS-disabled context, load `<listing>#reviews`.
- **Expect**: `#panel-reviews` visible through the `:not(.is-tabs-ready)` CSS reveal.

### 4. Hashes that are not valid selectors (verified for `#x[`)
- **Action**: open the listing with `#a:b`, `#x[` and `#%E0%A4%A`.
- **Expect**: no uncaught console error; `.listora-detail` has `is-tabs-ready`; Overview is the active tab (verified `#x[`: ready=true,
  `#tab-overview[aria-selected=true]`, overview height 443). `#a:b` and the malformed escape were not separately re-run after the load.

### 5. `#oldest-unanswered` (reminder email marker; NOT RUN)
- Opens Reviews, then switching tab hides it. Note the marker only exists for the listing owner with an unanswered review.

### 6. Mobile (390)
- **Expect**: the tab bar scrolls inside its column (2.0.0), Reviews tab reachable, no horizontal page scroll.

## Fail diagnostics
`assets/js/listing-detail-fallback.js:200` (`is-tabs-ready`) and the hash block after it; `blocks/listing-detail/style.css:1863-1864`;
`templates/blocks/listing-detail/tabs.php:459` (`#panel-reviews`), `:531` (`id="review-<id>"`).

## Not verified
Steps 3 and 5, the `#a:b` / malformed-escape hashes, the 390 viewport, and dark/unknown-theme rendering.
