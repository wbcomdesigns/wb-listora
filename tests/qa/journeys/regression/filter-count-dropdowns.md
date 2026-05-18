---
journey: filter-count-dropdowns
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [filter-count-badge, dropdown-facet-count]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Listing type with dropdown facet (e.g. Restaurant Cuisine)"
  - "Listing type with location set + date filter available"
estimated_runtime_minutes: 2
---

# Filter-count badge counts dropdowns regression sentinel

The active-filter-count badge on the listings page must increment for EVERY active filter, including dropdown facets. Pre-fix #9871208081: the badge ignored dropdowns and undercounted. Sentinel.

## Setup

- Site: `$SITE_URL`
- Listings with Restaurant type that has Cuisine dropdown facet

## Steps

### 1. Open `/listings/`
- **Action**: `playwright_navigate $SITE_URL/listings/`
- **Expect**: filter panel renders, badge shows `0`

### 2. Add keyword
- **Action**: type `pizza` in keyword input → submit
- **Expect**: badge = `1`

### 3. Add category dropdown
- **Action**: open Filters → pick a category from the Category `<select>`
- **Expect**: badge = `2` (NOT `1`). Pre-fix bug: dropdown ignored.
- **On fail**: regression of #9871208081 — see `src/blocks/listing-search/view.js` badge calc logic; must include dropdown facet count.

### 4. Add location
- **Action**: type a location in location input → autocomplete → set radius
- **Expect**: badge = `3`

### 5. Add date filter (preset)
- **Action**: click "This week" or other date preset
- **Expect**: badge = `4` (date counts as ONE regardless of from/to/preset internals)

### 6. Add cuisine dropdown
- **Action**: pick a Cuisine value (per-type dropdown)
- **Expect**: badge = `5`

### 7. Add a per-type checkbox facet
- **Action**: tick "Free WiFi" or other feature checkbox
- **Expect**: badge = `6`

### 8. Clear filters one at a time
- **Action**: click × on each filter chip
- **Expect**: badge counts down `6 → 5 → 4 → 3 → 2 → 1 → 0`

## Pass criteria

1. Each filter type contributes `+1` to the badge
2. Specifically dropdowns are counted (the regression target)
3. Date counts as ONE regardless of from/to/preset
4. Decrementing chips works in any order

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Dropdown selection doesn't bump badge | regression of #9871208081 | `src/blocks/listing-search/view.js` getActiveFilterCount logic — must include `select` inputs from Filters panel |
| Date filter counts as 2 (from + to) | over-counting | view.js — collapse date as single |
| Badge doesn't decrement on clear | clear handler not firing | view.js clear-chip handler |
