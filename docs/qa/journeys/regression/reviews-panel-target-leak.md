---
journey: reviews-panel-target-leak
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [listing-detail, deep-links, card-10304369374]
prerequisites:
  - "A listing with at least one approved review - note a review id"
estimated_runtime_minutes: 4
---

# A deep link to reviews never leaves the Reviews panel stuck under another tab

`blocks/listing-detail/style.css` reveals `#panel-reviews` with `:target` so the review-reminder email
links (`#reviews`, `#review-<id>`, `#oldest-unanswered`) show reviews before JavaScript runs. Tab
switches use `history.replaceState()`, which never recomputes `:target`, so after landing on a deep
link and switching tab the `!important` rule kept the Reviews panel visible beneath the active tab
until a full reload (card 10304369374). `#review-<id>` and `#oldest-unanswered` also opened no tab at
all - they relied on the CSS alone.

The fallback tab script now opens the tab owning any hash target and marks `.listora-detail` with
`is-tabs-ready`; the CSS reveal is scoped to `:not(.is-tabs-ready)`, so it still serves no-JS visitors.

Use a fresh browser context per step - the script URL is versioned by plugin version, so a cached
copy silently tests the old code.

## Steps

### 1. #reviews then switch
- **Action**: load `<listing>#reviews`, wait ~1.5s, click Overview
- **Expect**: before the click only `panel-reviews` has height; after it only `panel-overview` does; `.listora-detail` has `is-tabs-ready`

### 2. #review-<id> opens Reviews, then switch
- **Action**: load `<listing>#review-<id>`, wait, click Overview
- **Expect**: Reviews tab active on load; after the click Reviews has no height

### 3. No JavaScript
- **Action**: JS-disabled context, load `<listing>#reviews`
- **Expect**: `panel-reviews` visible (the deep link still works without scripts)
