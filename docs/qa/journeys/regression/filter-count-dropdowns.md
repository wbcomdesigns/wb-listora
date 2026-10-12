---
journey: filter-count-dropdowns
plugin: wb-listora
priority: normal
roles: [anonymous, member, admin]
covers: [filter-count-badge, dropdown-facet-count, active-filter-pills]
prerequisites:
  - "Site reachable at http://wb-listora.local"
  - "Restaurant type with its Cuisine dropdown facet; Event type for the date presets (Event has 0 listings here, so the badge is still countable but results are empty)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The Filters badge counts every active filter, including dropdowns

The badge is `span.listora-search__filter-count` inside the Filters button (`templates/blocks/listing-search/filters.php:50`), text `state.activeFilterCount` (`src/interactivity/store.js:552`), hidden (`is-hidden`) at 0. The getter counts: keyword (1), category select (1), location (1), date (preset or from/to = ONE), near-me position (1), featured-only (1), and every `state.filters` entry (one per value for array facets such as checked features). Choosing a listing-TYPE tab is navigation and does NOT count.

Verified via: source read of the getter; the badge arithmetic was not exercised in a browser (shared pane busy). Static DOM: badge span and `#listora-filters-panel` present on `/listings/`.

## Steps

### 1. Baseline
- **Action**: open `/listings/`.
- **Expect**: `.listora-search__filter-count` carries `is-hidden` (no "0" shown).

### 2. Keyword
- **Action**: type `pizza` in `#listora-keyword`, Enter.
- **Expect**: badge `1`.

### 3. Category dropdown (the regression target)
- **Action**: open Filters (`button[aria-controls="listora-filters-panel"]`), pick a value in the Category select (`aria-labelledby="listora-filter-category-label"`, "All Categories" is the reset option).
- **Expect**: badge `2`, NOT `1`.

### 4. Location
- **Action**: type a place in `#listora-location`, Enter.
- **Expect**: badge `3`.

### 5. Type tab is not a filter (POSITIVE CONTROL contrast)
- **Action**: click the "Restaurant" type tab.
- **Expect**: badge stays `3`; after adding a Cuisine dropdown value it becomes `4`.

### 6. Date (Event type only)
- **Action**: on `?listora_type=event` the Date group (`.listora-search__date-presets`) shows presets "Today", "This Weekend", "Happening Now"; click one.
- **Expect**: +1 whichever of preset/from/to are set. The date group is hidden for other types (`!state.isEventType`).

### 7. Per-type checkbox facet
- **Action**: tick a feature checkbox (Restaurant: e.g. a feature in the Features filter; "Show all features (N)" expands the rest).
- **Expect**: +1 per ticked value (two ticked = +2).

### 8. Clear
- **Action**: click "Clear All Filters" (`filters.php:373`, `actions.clearAllFilters`).
- **Expect**: badge hidden again, URL back to `/listings/`. Individual removal chips are NOT available: `.listora-search__active-filters` is rendered empty (`templates/blocks/listing-search/search.php:109-116`) and nothing in `src/` or `build/` creates `.listora-filter-pill` (see report, product finding).

### 9. Role ladder
- Public UI: anonymous, member, owner, editor and admin identical; no role-specific state. Admin is the control only.

### 10. Large set
- 104 public listings (2,811 incl. drafts). Combine keyword + category and confirm count and badge agree with `totalResults`.

### 11. 390px
- **Expect**: Filters button tap target >= 40px, badge not clipped, no horizontal scroll. Not run.

## Fail diagnostics

| Symptom | File |
|---|---|
| Dropdown selection does not bump badge | `src/interactivity/store.js:552` `activeFilterCount` (must read `state.selectedCategory`) |
| Date counts as 2 | same getter, date branch |
| Badge never hides | `filters.php:50` `!state.hasActiveFilters`, store `hasActiveFilters` getter `:535` |
| No chips to remove | `templates/blocks/listing-search/search.php:109` empty container |
