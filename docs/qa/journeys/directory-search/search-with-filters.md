---
journey: search-with-filters
group: directory-search
actor: customer
mode: browser
plugin: wb-listora
priority: critical
roles: [guest, member, editor, admin]
covers: [search-engine, type-tabs, feature-facets, filter-count-badge, dropdown-facet-count, empty-state-server-rendered, autocomplete, search-inputs-box-sizing, card-10331467188, BC-10194590939]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Directory page ($SITE_URL/listings/) holds the listing-search + listing-grid blocks"
  - "At least 20 published listings across 2+ types; search index populated (SELECT COUNT(*) FROM ${PREFIX}listora_search_index > 0, else run the reindex CLI)"
  - "An unreset theme (Twenty Twenty-Five) installed per browser for step 14, never switched site-wide"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, twentytwentyfive]
supersedes:
  - "ora:regression/filter-count-dropdowns.md"
  - "ora:regression/search-inputs-fit-on-unreset-themes.md"
site_profile: required
---

# A visitor searches the Directory with keyword, type, features and rating, and the count, badge and grid always agree

## Why
A visitor who cannot narrow or recover from zero results leaves; the Filters badge and the count lying about what is applied makes the search untrustworthy. Near me, term pages, featured and paging past the end each have their own journey (see Not verified for pointers) and are not repeated here.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Placeholders: `{listing}` a listing whose title is unique, `{type}` a listing type with 5+ listings and a dropdown facet, `{feature}` a feature used by `{type}` listings, `$EVENT_TYPE` an Event type (may have 0 listings).
- URL contract (server-rendered, shareable): `listora_keyword`, `listora_type`, `listora_category`, `listora_location`, `listora_features` (comma list of slugs), `listora_min_rating`, `listora_featured=1`, `listora_lat`+`listora_lng`+`listora_radius`, `listora_sort`, `listora_page`.
- REST: `GET $SITE_URL/wp-json/listora/v1/search?keyword=&type=&category=&features=&min_rating=&page=&per_page=` returns `{listings,total,pages,has_more}` with `X-WP-Total` / `X-WP-TotalPages`; `GET /search/suggest?q=` returns `{suggestions:[{type,id,text,meta,url}]}`. Compare page counts with `total` read at the same moment; never hard-code a total.
- Roles: guest (steps 1-12), `{owner}` member, `{editor}`, `{admin}` (control).

## Steps

### 1. Open the Directory
- **Action**: open `$SITE_URL/listings/` as guest.
- **Expect**: `.wp-block-listora-listing-search`, `.wp-block-listora-listing-grid`; `.listora-grid__count` (role `status`) reads "Showing 1-20 of N listings" with N equal to `X-WP-Total`; 20 `article.listora-card`; `.listora-search__filter-count` carries `is-hidden` (no "0" shown); `.listora-grid__empty` carries `is-hidden`.
- **Read-back**: `GET /search?per_page=1` `total` === N.

### 2. Autocomplete is debounced
- **Action**: type the first word of `{listing}` in `#listora-keyword` with a short delay between keys.
- **Expect**: exactly one suggest request (not one per key) and `{listing}` in the listbox. The suggest envelope, "typing only suggests", highlighted-suggestion and Escape rules are `directory-search/search-keyboard-and-suggestions.md` steps 1-3 and 7.

### 3. Enter and the Search button both run the search
- **Action**: press Enter in `#listora-keyword`; repeat with `.listora-search__submit`.
- **Expect**: page reloads to `/listings/?listora_keyword=<word>`; `{listing}` is among the cards; badge reads `1`. Enter / button parity and the "grid never stays unfiltered while the count changes" check are `directory-search/search-keyboard-and-suggestions.md` steps 4-5.

### 4. Type tab narrows and is not a filter
- **Action**: click the `{type}` tab `.listora-search__type-tab` (`aria-pressed="true"` afterwards).
- **Expect**: URL `/listings/?listora_type={type}`; count equals `GET /search?type={type}` `total`; every card shows the `{type}` badge; the Filters badge is NOT incremented by a type tab (pivot, not filter) and keeps `is-hidden`.
- **expected-fail**: product finding - the badge element renders "0" instead of hiding when only a type tab is chosen. Fix target: `src/interactivity/store.js:535` `hasActiveFilters` / `filters.php:50`.

### 5. The badge counts every active filter, including dropdowns
- **Action**: from `/listings/` run the sequence below, reading `.listora-search__filter-count` after each:
  1. keyword `pizza` in `#listora-keyword`, Enter.
  2. open Filters (`button[aria-controls="listora-filters-panel"]`), pick a value in the Category select (`aria-labelledby="listora-filter-category-label"`; "All Categories" is the reset option).
  3. type a place in `#listora-location`, Enter.
  4. click the `{type}` tab.
  5. pick a value in the `{type}` dropdown facet (e.g. Cuisine).
- **Expect**: badge `1`, `2` (NOT `1`: the dropdown regression), `3`, stays `3` after the type tab (tab is navigation), `4` after the facet.
- **Action**: on `?listora_type=$EVENT_TYPE` click one date preset in `.listora-search__date-presets` (Today / This Weekend / Happening Now).
- **Expect**: +1 whichever of preset or from/to are set; the Date group is hidden for non-event types (`!state.isEventType`).

### 6. Feature checkboxes: top items and "Show all features (N)"
- **Action**: click "Filters" (`.listora-search__toggle-btn`, `aria-expanded` becomes `true`, `#listora-filters-panel` visible); read the other groups.
- **Expect**: groups "Category" (select, first option "All Categories"), "Minimum Rating" (Any / 4 / 3 / 2 stars & up), "Date" (events only). The feature list itself (closed list, "Show all features (N)" with N = features in use by published listings, narrowing by `{type}`, applying a hidden one) is `directory-search/search-features-filter-reaches-every-feature.md`.

### 7. Apply a feature and a rating
- **Action**: tick `{feature}` (`input[data-wp-on--change="actions.toggleFeatureFilter"]`) - badge shows `+1` before applying; click "Apply Filters" (`.listora-search__apply-filters`); reopen, set Minimum Rating = 4 stars & up, Apply.
- **Expect**: URL gains `listora_features={feature}` then `listora_min_rating=4`; count narrows and equals `GET /search?type={type}&features={feature}&min_rating=4` `total`; badge `+1` per ticked value (two ticked = +2), +1 for the rating.

### 8. Clear inside the panel
- **Action**: reopen Filters, click "Clear All Filters" (`.listora-search__clear-all`, `actions.clearAllFilters`).
- **Expect**: feature and rating params disappear, URL is `/listings/?listora_type={type}` (tab stays), count returns to the type total, badge hidden.
- **expected-fail**: individual removal chips - `.listora-search__active-filters` is rendered empty (`templates/blocks/listing-search/search.php:109-116`) and nothing creates `.listora-filter-pill`; expected: one removable chip per active filter.

### 9. Zero results: empty state is server-rendered and visible
- **Action**: open `$SITE_URL/listings/?listora_keyword=xyzzy12345`.
- **Expect**: `.listora-grid__count` reads "0 results" and REST returns `{"listings":[],"total":0,"pages":0,"has_more":false}`. The empty card (visible without `is-hidden`, `role="status"`, copy, "Add a listing", "Clear All Filters") and its positive control are `directory-search/directory-empty-states.md` steps 1-2 and 4.

### 10. Clear All from the empty card
- **Action**: click "Clear All Filters" in the empty card.
- **Expect**: URL `/listings/` with no params; full list returns; badge hidden (the same control as `directory-search/directory-empty-states.md` step 4).

### 11. Result counts at scale
- **Action**: combine keyword + category; request `GET /search?per_page=100&page=N` for the first and last page.
- **Expect**: count text, badge, `pages` and `X-WP-TotalPages` agree; first paint stays under 1 s. Scale seed: publish a QA-prefixed batch so total >= 2,000 for the large-set pass (paging boundaries are owned by `paging-past-last-page`).

### 12. Role ladder
| Actor | Action | Expect |
|---|---|---|
| guest | steps 1-11 | as above |
| `{owner}` member | `GET /search?per_page=1` | same `total` as guest |
| `{editor}` | same | same `total` (public search never lists drafts or pending, even for moderators) |
| `{admin}` (control) | same | same `total`; drafts show only in wp-admin All Listings |

### 13. Layout at 820 and 390
- **Action**: repeat steps 1, 6 and 9 at 820 and 390 (BuddyX Pro light and dark).
- **Expect**: `document.documentElement.scrollWidth <= innerWidth`; at 390 keyword input, Search, Filters, Near me and type tab are >= 40px high, Apply Filters >= 40px, Filters button not clipped by the badge.
- **expected-fail**: feature checkbox rows measure ~22px high at 390 (below the 40px floor; product finding).

### 14. The search bar fits a phone on themes that do not reset inputs (card 10331467188)
- **Action**: preview Twenty Twenty-Five (`?customize_theme=twentytwentyfive` as `{admin}`, or a pristine install), open the Directory at 390, 820 and 1440 as guest.
- **Expect**: `scrollWidth === innerWidth`; `#listora-keyword` (type=search) and `#listora-location` (type=text) have the same `getBoundingClientRect().width` and `getComputedStyle(el).boxSizing === 'border-box'`; `.listora-search__submit` >= 40px tall; at 1440 the Location field sits beside the keyword field.
- **Positive control**: on Twenty Twenty-Five override `.listora-input{box-sizing:content-box}` in devtools and confirm Location overflows.
- **Read-back**: `assets/css/listora-base.css:459-465` (and the `.layer.css` and `-rtl` twins) set `box-sizing: border-box` on `.listora-input`.
- **fails_on**: not recorded (source-read sentinel, card 10331467188). Not verified live.

## Role ladder
See step 12 (table). Layout and theme matrix: steps 13-14.

## Pass criteria
1. Directory count equals `X-WP-Total`; keyword, type, features and rating each narrow to the REST total.
2. Autocomplete fires once per pause and lists real titles.
3. The badge counts keyword, category, location, date, each feature and each dropdown once; a type tab never counts.
4. "Show all features (N)" and per-type feature lists render.
5. Zero results renders the visible empty card with both actions; Clear All returns to `/listings/`.
6. No horizontal scroll at 390 on BuddyX Pro or an unreset theme.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Count and grid disagree after search | search not navigating | `src/interactivity/store.js:837` (`searchImmediate`) |
| Dropdown selection does not bump badge | getter ignores `state.selectedCategory` | `src/interactivity/store.js:552` (`activeFilterCount`) |
| Badge never hides | `hasActiveFilters` getter | `templates/blocks/listing-search/filters.php:50`, store `:535` |
| Suggestions per keystroke | debounce removed | `src/interactivity/store.js:952` (`setSearchQuery`) |
| Zero results with a valid keyword | index empty or FULLTEXT missing | `includes/class-activator.php`, `includes/search/class-search-indexer.php:200` |
| Empty card hidden at 0 results | getter regression | `src/interactivity/store.js:653`, `templates/blocks/listing-grid/grid.php:131` |
| Location overflows on unreset theme | `box-sizing` missing from `.listora-input` | `assets/css/listora-base.css:459-465` |

## Not verified
Step 14 (no browser run), the badge arithmetic of step 5 (source read only), steps 11 scale seed and 13 layout at 390 for the feature rows. Owned elsewhere: Near me (`near-me-radius-and-distance`), term pages (`term-pages-show-directory`), featured filter (`featured-filter-and-see-all`), paging past the last page (`paging-past-last-page`).

## Cleanup
Remove the Twenty Twenty-Five preview and any QA-prefixed scale seed.
