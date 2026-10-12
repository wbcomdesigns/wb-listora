---
journey: search-features-all-reachable
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [search-features-filter]
prerequisites:
  - "Demo content with more than 8 feature terms in use (this site: 42 features in use)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Every feature can be filtered on from the Directory

UX audit 2026-10-11, screen 2. Verified via: curl of the server-rendered Directory (`/listings/`) and the type-narrowed variant; the panel open/close and 1440/390 layout were NOT clicked in a browser.

## Steps

### 1. Closed list
- **Action**: open `$SITE_URL/listings/` (All types), open **Filters** (`actions.toggleFiltersPanel`, button text "Filters").
- **Expect**: in the Features group (`.listora-search__filter-group`, label "Features") 8 checkboxes (`.listora-search__filter-checkbox`) are visible, most used first (wifi 38, ac 28, credit-cards 21 ...), then a `<details class="listora-search__filter-more">` with summary "Show all features (42)" where 42 is every feature in use (observed 42 checkboxes in the DOM).

### 2. Open and apply
- **Action**: open "Show all features (42)", tick one from the hidden part (e.g. `elevator`, the 9th), press **Apply Filters**.
- **Expect**: URL carries `listora_features=elevator`; results narrow (observed total 10); after the reload the `<details>` is `open` and the box is ticked (observed `filter-more" open`). At 1440 the open list spans the panel width; no horizontal scroll at 390.
- **Positive control**: `?listora_features=wifi` (a visible-8 feature) leaves the `<details>` closed.

### 3. Type narrowing
- **Action**: choose type Restaurant (or `?listora_type=restaurant`).
- **Expect**: only that type's features remain, including inside "Show all" (observed "Show all features (14)"); a type with a "Selected only" list shows exactly that list.

### 4. Role ladder
Public filter: identical for anonymous, subscriber and admin. Admin control: same count (hidden/draft-only features do not appear because `hide_empty` is true).

### 5. Large set
Feature counts come from published listings only (84 here); with 2,000+ published listings the term query is cached by `wb_listora_get_terms_for_listing_type()` (`includes/helpers.php:212`); confirm the panel stays one query per term type (no per-term count loop).

## Fail diagnostics
`templates/blocks/listing-search/filters.php:132-176` (query, chunking, summary), filter `wb_listora_search_features_visible` (`:161`, default 8), allow-list `wb_listora_get_feature_filter_map()` (`:125`).
