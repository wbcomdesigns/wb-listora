---
journey: search-with-filters
plugin: wb-listora
priority: critical
roles: [anonymous, subscriber, editor, admin]
covers: [search-engine, geo-radius, type-tabs, feature-facets, filter-count-badge, empty-state-server-rendered, pagination-last-page, term-pages-directory, autocomplete]
prerequisites:
  - "Site reachable at $SITE_URL (Local: http://wb-listora.local)"
  - "Directory page = $SITE_URL/listings/ (block listing-search + listing-map + listing-grid)"
  - "At least 20 published listings across 2+ types, at least 1 with coordinates (wp_listora_geo)"
  - "Search index populated (`SELECT COUNT(*) FROM {prefix}listora_search_index`; if 0 run the reindex CLI)"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Search with filters

A logged-out visitor searches the Directory with a keyword, a type tab, feature and rating filters and a Near me radius, watches the Filters badge and the count, then drains to zero results and recovers. The same totals show for every role. Category, location, tag and feature pages are the Directory filtered to that term.

Verified via: Playwright (isolated context) and curl. Numbers below are from the live site and drift as listings change: compare the page count with `GET /listora/v1/search` `total` / `X-WP-Total` at the same moment instead of hard-coding it.

## Setup

- Site: `$SITE_URL`. No login needed for steps 1-10.
- URL contract (server-rendered, shareable): `listora_keyword`, `listora_type`, `listora_category`, `listora_location`, `listora_features` (comma list of slugs), `listora_min_rating`, `listora_featured=1`, `listora_lat` + `listora_lng` + `listora_radius`, `listora_sort`, `listora_page`.
- REST: `GET /wp-json/listora/v1/search?keyword=&type=&category=&features=&min_rating=&featured_only=&lat=&lng=&radius=&page=&per_page=` returns `{listings, total, pages, has_more}`; `X-WP-Total` / `X-WP-TotalPages` headers; `GET /search/suggest?q=` returns `{suggestions:[{type,id,text,meta,url}]}`.

## Steps

### 1. Open the Directory
- **Action**: open `$SITE_URL/listings/`.
- **Expect**: `.wp-block-listora-listing-search`, `.wp-block-listora-listing-grid`; `.listora-grid__count` (role `status`) reads "Showing 1-20 of N listings" where N equals `X-WP-Total`; 20 `article.listora-card`; `.listora-search__filter-count` is `is-hidden`; the empty card `.listora-grid__empty` has `is-hidden`.
- **On fail**: `blocks/listing-grid/render.php`, `includes/rest/class-search-controller.php`

### 2. Autocomplete is debounced
- **Action**: click `#listora-keyword` (role `combobox`, placeholder "Search listings...") and type `fork` with a short delay between keys.
- **Expect**: exactly one `GET /listora/v1/search/suggest?q=fork&type=` request (not one per key); `#listora-suggestions` (role `listbox`) shows one item "The Golden Fork" with its area ("Manhattan") as meta.
- **On fail**: `src/interactivity/store.js:952` (`setSearchQuery`), `includes/rest/class-search-controller.php:77`

### 3. Enter and the Search button both run the search
- **Action**: press Enter in `#listora-keyword`; repeat with the "Search" button `.listora-search__submit`.
- **Expect**: page reloads to `/listings/?listora_keyword=fork`; count "Showing 1-1 of 1 listings", one card whose title is "The Golden Fork"; the badge `.listora-search__filter-count` reads `1`. Count and grid agree (the grid does not stay unfiltered while the count changes).
- **On fail**: `src/interactivity/store.js:837` (`searchImmediate`)

### 4. Type tab narrows (and is not a "filter" in the badge)
- **Action**: from `/listings/` click the type tab `.listora-search__type-tab` "Restaurant" (`aria-pressed="true"` afterwards).
- **Expect**: URL `/listings/?listora_type=restaurant`; count equals `GET /search?type=restaurant` `total` (19 at verification); every card has the badge "Restaurant". The Filters badge is not incremented by the type (pivot, not filter): see PRODUCT-BUGS in the report, the badge element shows "0" instead of hiding.
- **On fail**: `src/interactivity/store.js:552` (`activeFilterCount`)

### 5. Features filter: top items, "Show all features (N)"
- **Action**: click "Filters" (`.listora-search__toggle-btn`, `aria-expanded` becomes `true`, `#listora-filters-panel` visible). Read `.listora-search__filter-checkbox` labels and the `<details class="listora-search__filter-more">` summary.
- **Expect**: on the unfiltered Directory the summary reads "Show all features (56)" (N = every feature in use); with the Restaurant type chosen it lists only features its listings use (Reservations, Takeout, Delivery, Accepts Credit Cards, Live Music, ... "Show all features (14)"); WiFi is not offered for Restaurant here. Other filter groups: "Category" (select, first option "All Categories"), "Minimum Rating" (Any / 4 / 3 / 2 stars & up), "Date" (events).

### 6. Apply a feature and a rating
- **Action**: tick "Reservations" (`input[data-wp-on--change="actions.toggleFeatureFilter"]`), the badge shows `1` before applying; click "Apply Filters" (`.listora-search__apply-filters`). Then reopen Filters, set Minimum Rating = 4 stars & up, Apply.
- **Expect**: URL `...?listora_type=restaurant&listora_features=reservations` then `...&listora_min_rating=4`; counts narrow (19 -> 5 -> 5 at verification) and match `GET /search?type=restaurant&features=reservations&min_rating=4` `total`; badge `1` then `2` (each feature and each dropdown counts once; keyword, location, date, featured and Near me count once each).
- **On fail**: `src/interactivity/store.js:552-580`, `includes/search/class-search-engine.php`

### 7. Clear inside the panel
- **Action**: reopen Filters, click "Clear All Filters" (`.listora-search__clear-all`).
- **Expect**: feature and rating params disappear, the page reloads to `/listings/?listora_type=restaurant` (the type tab stays, it is not a filter) and the count returns to the type total.

### 8. Near me radius and distance on cards
- **Action**: open `$SITE_URL/listings/?listora_lat=40.758&listora_lng=-73.985&listora_radius=5` (the URL form of Near me; the geolocation button needs a secure context, over plain http it shows "Location access denied. Use the location search instead.").
- **Expect**: `.listora-search__radius` visible with `#listora-radius` options "1 km", "5 km" (selected = `near_me_radius` setting, 5), "10 km", "25 km", "50 km"; each card has `.listora-card__distance` reading "N.N km away" in the site's distance unit (`distance_unit` = km); badge `1`. Choose "1 km": URL `...listora_radius=1`, count shrinks (8 at verification) and distances are all <= 1.2 km; choose "50 km": count grows (76) and equals `GET /search?lat=40.758&lng=-73.985&radius=50` `total`.
- **POSITIVE CONTROL**: without lat/lng no `.listora-card__distance` exists and `.listora-search__radius` has `is-hidden` (step 1).

### 9. Zero results: empty state is server-rendered and visible
- **Action**: open `$SITE_URL/listings/?listora_keyword=xyzzy12345`.
- **Expect**: `.listora-grid__count` reads "0 results"; `.listora-grid__empty.listora-card--empty` is visible (no `is-hidden`, `role="status"`) with icon, `.listora-empty__title` "No listings found", `.listora-empty__desc` "Try adjusting your filters, or be the first to add a listing.", and two actions: link "Add a listing" (to `/add-listing/`) and button "Clear All Filters". `GET /search?keyword=xyzzy12345` returns `{"listings":[],"total":0,"pages":0,"has_more":false}`.
- **POSITIVE CONTROL**: step 1 shows the same element with `is-hidden` when results exist.

### 10. Clear All Filters CTA from the empty card
- **Action**: click "Clear All Filters" in the empty card.
- **Expect**: URL `/listings/` with no params; full list returns, badge hidden.

### 11. Pagination at scale and past the last page
- **Action**: open `/listings/?listora_page=2`, then `/listings/?listora_page=999`.
- **Expect**: page 2 reads "Showing 21-40 of N listings", `.listora-grid__pagination` (nav, aria-label "Pagination") has numbered `.listora-grid__page-num` links and a "Previous page" button; page 999 shows the LAST page ("Showing 81-83 of 83 listings" at verification), never "Showing 1,961-127 of 127".
- **Scale note**: this site has about 85 published listings (2,716 of the 2,853 listings are drafts, so they are not searchable). For a 2,000+ row check publish a seeded batch, or run `GET /search?per_page=100&page=N` and confirm `pages` and `X-WP-TotalPages` stay consistent and the first paint stays under 1 s.

### 12. Featured filter and term pages
- **Action**: open `/listings/?listora_featured=1`; then `$SITE_URL/listing-category/casual/`, `/listing-location/ny/`, `/listing-tag/pasta/`, `/listing-feature/wifi/` and `$SITE_URL/listing/`.
- **Expect**: featured: only featured listings (26 at verification). Each term URL returns 200 and shows the Directory (`.wp-block-listora-listing-grid`) filtered to that term, `<h1>` = the term name ("Casual", "New York", "pasta", "WiFi"), page title "<term> - Listora", breadcrumb present; the count equals `GET /search?category=casual` `total` (6). `/listing/` redirects to `/listings/` (Directory).

### 13. ROLE LADDER
| Actor | Action | Expect |
|---|---|---|
| Logged out | steps 1-12 | as above |
| Member (QA-J1-member) | `GET /search?per_page=1` | same `total` as logged out |
| Editor (`qa_editor`) | same | same `total` (public search never lists drafts/pending, even for moderators) |
| Admin (control) | same | same `total`; admin All Listings (wp-admin) is where drafts show |

### 14. Layout
- **Action**: repeat steps 1, 5 and 9 at 820 and 390 (BuddyX Pro light and dark).
- **Expect**: `scrollWidth <= viewport`; at 390 keyword input 44px, Search 46px, Filters 44px, Near me 44px, type tab 44px, Apply Filters 46px. The feature checkbox rows measure about 22px high (see PRODUCT-BUGS).

## Pass criteria

1. Directory count equals `X-WP-Total`; keyword, type, features, rating, radius each narrow to the REST total.
2. Autocomplete fires once per pause and lists real titles.
3. The badge counts keyword, dropdowns, each feature, location/Near me; the type tab is navigation.
4. "Show all features (N)" and per-type feature lists render.
5. Near me shows distances in the site unit and honours 1/5/10/25/50.
6. Zero results renders the visible empty card with both actions; Clear All returns to `/listings/`.
7. A page past the last shows the last page; term URLs show the filtered Directory with the term as heading; `/listing/` redirects.
8. No horizontal scroll at 390.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Count and grid disagree after search | search not navigating / debounce path | `src/interactivity/store.js:837` |
| Zero results with a valid keyword | index empty or FULLTEXT missing | `includes/class-activator.php`, `includes/search/class-search-indexer.php:200` |
| Suggestions per keystroke | debounce removed | `src/interactivity/store.js:952` (`setSearchQuery`) |
| No distances / radius hidden with lat,lng | geo join or `hasUserLocation` | `includes/search/class-geo-query.php`, `templates/blocks/listing-card/card-content.php:154` |
| Empty card hidden at 0 results | getter regression | `src/interactivity/store.js:653` (`showEmptyState`), `templates/blocks/listing-grid/grid.php:131` |
| Term page shows the plain archive / `/listing/` does not redirect | term archive handling | `includes/core/class-term-archives.php:49` (`redirect_post_type_archive`), `blocks/listing-grid/render.php:32` |
| "Showing 1,961-127" | last-page clamp removed | `blocks/listing-grid/render.php:71-76` |
