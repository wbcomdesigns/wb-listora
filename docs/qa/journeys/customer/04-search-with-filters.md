---
journey: search-with-filters
plugin: wb-listora
priority: critical
roles: [anonymous, subscriber]
covers: [search-engine, geo-query, facets, autocomplete, filter-count-badge, empty-state-server-rendered]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least 5 published listora_listing posts across 2+ types"
  - "At least 1 listing has lat/lng in wp_listora_geo within 5km of a known address"
  - "Search index populated (run wp listora reindex if unsure)"
estimated_runtime_minutes: 5
---

# Search with filters

A logged-out visitor searches the directory with keyword + location radius + type tab + per-type facet, watches the active-filter-count badge update, and verifies result narrowing matches selected filters. Then drains the filter set down to zero results and confirms the canonical empty state renders. Covers the search engine end-to-end (FULLTEXT + Haversine + facet counts) plus the two recent regressions (filter-count badge ignoring dropdowns, empty state hidden on 0-result).

## Setup

- Site: `$SITE_URL`
- No login required
- Fixtures expected:
  - 5+ `listora_listing` posts of mixed types (Restaurant + Hotel + Business)
  - 1 row in `wp_listora_geo` within 5km of address "1 Main St, Springfield"
- Verify search index:
  ```bash
  wp eval 'global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}listora_search_index");'
  ```
  Expect ≥5; if 0, run `wp listora reindex`.

## Steps

### 1. Open directory listings page
- **Action**: `playwright_navigate $SITE_URL/listings/`
- **Expect**: DOM shows `.wp-block-listora-listing-grid` with cards. `state.totalResults > 0` in IAPI store. Filter panel visible.
- **On fail**: `blocks/listing-grid/render.php`, `Search_Engine::search()`

### 2. Type a keyword in the search bar
- **Action**: type `pizza` (or matching seeded fixture term) in the keyword input → wait 300ms
- **Expect**:
  - Network shows debounced `GET /wp-json/listora/v1/search/suggest?q=pizza` (≤1 request, not per keystroke)
  - Suggestions dropdown appears with at least 1 item
- **On fail**: `Search_Controller::suggest`, `src/blocks/listing-search/view.js` debounce config

### 3. Submit search
- **Action**: press Enter or click search button
- **Expect**: URL gains `?q=pizza`. Result count text reflects new total. `GET /wp-json/listora/v1/search?q=pizza` returns 200 with `{ items, total, has_more }`.
- **On fail**: `Search_Controller::search`, FULLTEXT index missing on `listora_search_index.searchable`

### 4. Add a location filter
- **Action**: type `Springfield` in location input → wait for autocomplete → select first match → set radius `5km`
- **Expect**:
  - URL gains `&location=...&radius=5`
  - Active-filter-count badge increments to `2` (keyword + location)
  - Each result card shows distance text (e.g. "1.2 km away")
- **On fail**: `Geo_Query`, frontend filter-count badge logic in `src/blocks/listing-search/view.js`

### 5. Switch to type tab
- **Action**: click the "Restaurant" type tab
- **Expect**:
  - URL gains `&type=restaurant`
  - Badge becomes `3`
  - Result list narrowed to restaurants only
- **On fail**: type tab handler in IAPI store, `Search_Engine::filter_by_type`

### 6. Add a per-type checkbox facet
- **Action**: open Filters panel → tick a type-specific checkbox (e.g. "Free WiFi" feature)
- **Expect**:
  - Badge becomes `4`
  - REST request includes `facets[features][]=free-wifi`
  - Result list narrows further
- **On fail**: facet wiring in `src/blocks/listing-search/view.js`, `Facets::apply`

### 7. Add a per-type dropdown facet (regression sentinel for filter-count-dropdowns)
- **Action**: select a value from a Cuisine-style dropdown in Filters panel
- **Expect**:
  - Badge becomes `5` (NOT `4` — pre-fix bug ignored dropdowns)
  - Result list narrows further
- **On fail**: badge calculation in `src/blocks/listing-search/view.js`. Pre-fix bug = card #9871208081.

### 8. Clear filters one at a time
- **Action**: click the × on each filter chip
- **Expect**: badge counts down `5 → 4 → 3 → 2 → 1 → 0`. With 0 filters, original full result list returns.

### 9. Drain to zero results (regression sentinel for empty-state-server-rendered)
- **Action**: type a deliberately-no-match query e.g. `xyzzy12345`
- **Expect**:
  - Result count = 0
  - DOM shows `.listora-grid__empty.listora-card--empty` (visible — NOT `display: none` or `is-hidden`)
  - Empty card has icon + "No listings found" + "Clear All Filters" CTA
- **On fail**: pre-fix bug = empty state was server-rendered hidden, IAPI getter `state.showEmptyState` didn't return true on `totalResults === 0`. See `src/interactivity/store.js` showEmptyState getter.

### 10. Click "Clear All Filters" CTA
- **Action**: click the CTA in the empty state card
- **Expect**: URL strips all query params, full result list returns.

## Pass criteria

ALL of the following hold:
1. Keyword + location + type + checkbox facet + dropdown facet ALL contribute `+1` to the active-filter-count badge each
2. `GET /listings/` returns 200 on first paint with `total > 0`
3. Geo-filtered results show distance text per card
4. Empty state renders with icon + CTA when results = 0 (NOT a blank page)
5. Clear All restores the full unfiltered list

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Search returns 0 with valid keyword | FULLTEXT index missing or stop-words config | `class-activator.php` (FULLTEXT split, commit 7606f8c), `wp-cli listora reindex` |
| Suggestions fire on every keystroke | debounce broken | `src/blocks/listing-search/view.js`, 250ms debounce |
| Distance text missing on cards | `Geo_Query` not joined | `Search_Engine::search()` Haversine join |
| Filter-count badge ignores dropdowns | regression of #9871208081 | `src/blocks/listing-search/view.js` badge calc |
| Empty state hidden on 0-result | regression of today's empty-state fix | `src/interactivity/store.js` showEmptyState getter, `templates/blocks/listing-grid/grid.php` data-wp-class binding |
