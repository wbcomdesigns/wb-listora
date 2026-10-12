---
group: directory-search
plugin: wb-listora
scope: Finding listings on the public Directory page and term pages - search bar, suggestions, filters panel, sort, result count, pager, empty state, featured filter/block, and the /search REST rows behind them
journeys: 14
site_profile: required
---
# Directory search: what an agent checks here

## Scope
Covers the Directory page ({directory}: a page holding the Listing Search block plus the Listing Grid block), the five term pages that render the same Directory, the Featured block and its "See all" target, and the `/listora/v1/search` family that feeds them.

Does NOT cover (neighbouring owner in brackets):
- The card itself: image, badges, heart, alt text, view-mode default and remembered choice (browse-blocks).
- Map, Near me button behaviour, radius, distance sort, markers (map). The Near me button and the radius select are listed under Components only so the sweep checks they exist and fit.
- Pro Load More, infinite scroll, Compare, Quick View, saved searches (Pro browse-blocks and Pro search journeys; no Pro folder exists for this group).
- Dashboard and wp-admin pagers beyond the one clamp check in `paging-past-last-page` (dashboard-account, admin-listings).
- Single listing page (listing-detail); SEO head and breadcrumb JSON-LD of term pages (seo-schema).

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| S1 | Directory, first paint, no filters | `$SITE_URL{directory}` | guest, {owner}, {other}, {editor}, {admin} (identical) | `templates/blocks/listing-search/*.php`, `templates/blocks/listing-grid/*.php`, `blocks/listing-search/render.php`, `blocks/listing-grid/render.php` |
| S2 | Directory with filters in the URL | `{directory}?listora_keyword=&listora_type=&listora_category=&listora_location=&listora_features=a,b&listora_min_rating=4&listora_featured=1&listora_sort=&listora_page=` (bare names are read, never written) | all | same as S1; URL helper `includes/search/search-url-helpers.php` |
| S3 | Directory, filters panel open | S1 then click Filters (`.listora-search__toggle-btn`) | all | `templates/blocks/listing-search/filters.php` |
| S4 | Directory, suggestions dropdown open | S1, focus `#listora-keyword`, type 2+ chars, wait 300 ms | all | `templates/blocks/listing-search/search-bar.php`; data `GET /search/suggest` |
| S5 | Directory, zero results | `{directory}?listora_keyword=$NOMATCH` | all | `templates/blocks/listing-grid/grid.php` (empty state) |
| S6 | Directory, last page / past-the-end page | `{directory}?listora_page=LAST` and `?listora_page=999999` | all | `templates/blocks/listing-grid/pagination.php`, `blocks/listing-grid/render.php` |
| S7 | Directory, featured only | `{directory}?listora_sort=featured&listora_featured=1` (target of "See all") | all | same as S1 |
| S8 | Term page (category, location, tag, feature, type) | `$SITE_URL/listing-category/{cat}/`, `/listing-location/{loc}/`, `/listing-tag/{tag}/`, `/listing-feature/{feature}/`, `/listing-type/{type}/` (use `get_term_link()`) | all | `includes/core/class-term-archives.php` + the Directory blocks |
| S9 | Bare archive redirect | `$SITE_URL/listing/` (301 to {directory}) | all | `includes/core/class-term-archives.php::redirect_post_type_archive` |
| S10 | Featured block on a page | page `$FEAT_URL` holding `wp:listora/listing-featured` | all | `blocks/listing-featured/render.php`, `templates/blocks/listing-featured/featured.php` |
| S11 | Featured block, empty | block pinned to `$EMPTY_TYPE` | all | `blocks/listing-featured/render.php` (shared empty state) |
| S12 | Grid pinned to a type, empty or mistyped | page with `wp:listora/listing-grid {"listingType":"$VALUE"}` | all | `blocks/listing-grid/render.php`, `grid.php` |
| S13 | Block editor sidebar: Listing Type select | wp-admin page editor, any Listora block | {editor}, {admin} | `src/shared/components/ListingTypeControl.js` |
| S14 | Settings > Advanced > Cache > "Search results TTL" | `$SITE_URL/wp-admin/admin.php?page=listora-settings` (Advanced tab) | {admin} | `includes/admin/class-advanced-settings-tab.php` |
| S15 | REST: `/search`, `/search/suggest`, `/listings/{id}/detail`, `/listings/{id}/related`, `/listings/bulk` | `$SITE_URL/wp-json/listora/v1/...` | all (public read, 120/min per IP for guests) | `includes/rest/class-search-controller.php`, `class-listings-controller.php` |

## Components on each surface
Check every item below on every surface it appears on; this is the MICRO_CHECKS sweep list.

**Search block (S1-S5, S7)** root `.wp-block-listora-listing-search` > `.listora-search.listora-search--horizontal|vertical`
1. Loading bar `.listora-search__loading-bar[role=progressbar]` (hidden unless `state.isLoading`).
2. Search bar `.listora-search__bar`:
   - keyword field `.listora-search__field--keyword`: label (sr-only) "Search", icon, `input#listora-keyword[type=search][role=combobox]`, clear button `.listora-search__clear[aria-label="Clear search"]`, suggestions `#listora-suggestions[role=listbox]` with `a.listora-search__suggestion-item[role=option]` (`-item-text`, `-item-meta`).
   - location field `.listora-search__field--location`: `input#listora-location[type=text]`, Near me button `.listora-search__near-me[aria-label="Find listings near your location"]` (only when Near me is on).
   - submit `button.listora-search__submit` (icon + "Search").
3. Type chips `.listora-search__type-tabs-wrap` > prev arrow `.listora-search__type-tabs-nav--prev`, `.listora-search__type-tabs[role=group]` with "All" + one `button.listora-search__type-tab.listora-type--{slug}` per type (icon + name, `aria-pressed`), next arrow `--next`. Only rendered when the block is not pinned to a type and the site has 2+ types.
4. Actions row `.listora-search__actions-row`: Filters toggle `.listora-search__filters-toggle > button.listora-search__toggle-btn[aria-expanded][aria-controls=listora-filters-panel]` with badge `.listora-search__filter-count`; Pro hook slot `wb_listora_after_search_results` (Save This Search for logged-in members when Pro is active).
5. Filters panel `#listora-filters-panel[role=group][aria-label="Search filters"]` (grid of `.listora-search__filter-group`): Category select (`aria-labelledby=listora-filter-category-label`, first option "All Categories"); Minimum Rating select (Any / 4 / 3 / 2 stars & up); Features checkboxes `.listora-search__filter-checkbox` (first 8 by usage) + `details.listora-search__filter-more` "Show all features (N)"; per-type filters from the type config (select, multiselect checkboxes, single checkbox toggle, "Open Now" toggle, number/price Min-Max `.listora-search__range-input`); Date group `.listora-search__filter-group--date` (events only): presets Today / This Weekend / Happening Now (`.listora-search__date-btn`), From/To date inputs; actions `.listora-search__filter-actions`: "Clear All Filters" `.listora-search__clear-all` (hidden until a filter is active) and "Apply Filters" `.listora-search__apply-filters`.
6. Active filter pills `.listora-search__active-filters[aria-live=polite]` (rendered empty today, see Known defects).
7. Radius row `.listora-search__radius` with `select#listora-radius` (only when Near me is on and a location is set).
8. Error banner `.listora-search__error[role=alert]` (hidden unless `state.searchError`).

**Grid block (S1-S7, S12)** root `.wp-block-listora-listing-grid`
1. Toolbar `.listora-grid__toolbar`: count `.listora-grid__count[role=status][aria-live=polite]` ("Showing A-B of N listings" with an en dash, or "N results" at 0); view toggle `.listora-grid__view-toggle[role=radiogroup]` with two `.listora-grid__view-btn[role=radio][aria-checked]` (Grid view / List view; owned by browse-blocks); sort `select#listora-sort.listora-grid__sort-select` (Featured, Newest, Highest Rated, Price: Low to High, Price: High to Low, Most Reviewed, A to Z, plus Nearest only when distances exist).
2. Results `.listora-grid__results.listora-grid[role=list][aria-busy]` containing `article.listora-card` per listing (card internals are browse-blocks).
3. Skeletons `.listora-grid__skeletons` (up to 6 `.listora-card--skeleton`, `aria-hidden`, visible only while loading).
4. Empty state `.listora-card.listora-card--empty.listora-grid__empty[role=status]`: icon, title, description, "Add a listing" link (only when submission is on for the viewer), "Clear All Filters" button (omitted on a grid pinned to a type); carries `is-hidden` when results exist.
5. Pager `nav.listora-grid__pagination[aria-label=Pagination]` (absent when pages <= 1): prev (`a` or disabled `button`, aria-label "Previous page"), `.listora-grid__page-numbers` (max 7 `a.listora-grid__page-num`, active one `.is-active[aria-current=page]`, `.listora-grid__page-ellipsis`), next.

**Term page (S8)** adds above the blocks: `body.listora-term-archive.listora-term-archive--{taxonomy}`, `<h1>` term name, `nav.listora-term-archive__crumbs[aria-label=Breadcrumb]` ("Directory > term"), optional `.listora-term-archive__desc`.

**Featured block (S10, S11)** root `.wp-block-listora-listing-featured > div.listora-featured`: header `.listora-featured__header` (title `.listora-featured__title` h2, hidden when equal to the page title; "See all ->" `.listora-featured__see-all`; arrows `.listora-featured__nav-arrows` prev/next, only when more than one carousel page), track `.listora-featured__track` (scroll-snap, `article.listora-card` with `.listora-badge--featured`), dots `.listora-featured__dots[role=group]` (one `.listora-featured__dot` per page, only when more than one page). Empty variant: `div.listora-featured--empty.listora-card--empty[role=status]` "No featured listings yet", no See all.

**Editor sidebar (S13)**: Listing Type select (first option "All types", then types by name; unknown saved slug shown as "<slug> (not a listing type on this site)").

## States to force
| Surface | State | How |
|---|---|---|
| S1 | N = 0 listings | filter to `$NOMATCH`, or run on a site/type with none (`$EMPTY_TYPE`) |
| S1 | N = 1 | `listora_keyword` of a unique `{listing}` title; count must read "Showing 1-1 of 1 listings" |
| S1 | N = 20 / 21 | keyword with exactly 20 / 21 matches (no pager / two pages) |
| S1 | many (2,000+) | `fixtures/2.0-fixtures.php` scale seed (QA-prefixed), then `clean` |
| S1 | loading | throttle network to Slow 3G and press Search; loading bar + skeletons show |
| S1 | error | block `/wp-json/listora/v1/search` in DevTools, press Search; `.listora-search__error` shows |
| S1 | logged-out vs member vs admin | `?autologin={owner}` / `{admin}` / none (identical output expected) |
| S1 | long content | seed a listing with a 120-character title and a long unbroken word; check card and suggestion row clip, no page scroll |
| S1 | RTL | switch site locale to an RTL language (type-tab arrows, clear icon, pager arrows mirror) |
| S1 | feature off | Settings: turn off submission (the empty state loses "Add a listing"); turn Near me off in block attributes (button and radius row disappear) |
| S3 | types with/without dropdown, multiselect, price and Open Now filters | pick `{type}` vs `{type_b}`; `$EVENT_TYPE` shows the Date group |
| S3 | features > 8 | a site where 9+ features are in use; "Show all features (N)" appears |
| S4 | zero suggestions | type `$NOMATCH`: dropdown stays closed |
| S6 | past-the-end | `listora_page=LAST+1`, `999999`, `0`, `-3`, `abc` |
| S7 | nothing featured | un-feature all in `{type}` or pin the block to `$EMPTY_TYPE` |
| S8 | real term with 0 listings | 200 with empty state; unknown term 404 |
| S8 | pending/draft/trashed/deleted-author listing in the term | change status, reload: never counted |
| S10 | featured expired | set `_listora_featured_until` in the past, run the sweep |
| S12 | misspelt type slug / deleted type | block attr `listingType` |
| any | block pinned to a type | `wp:listora/listing-search {"listingType":"{type}"}`: type chips are not rendered |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| Settings > Advanced > Cache > Search results TTL (`wb_listora_settings[search_cache_ttl]`, `#search_cache_ttl`) | 0-120 minutes, 0 disables | S15 `/search` writes no `_transient_listora_search_*` row at 0; admin-only field |
| Settings > General > listings per page (`wb_listora_settings[per_page]`, default 20) | integer | S1 count text "Showing 1-N", pager length, `X-WP-TotalPages`; block `perPage` overrides it |
| Grid block attribute `perPage`, `columns` (default 3), `defaultView`, `showViewToggle`, `showResultCount`, `showSort`, `showPagination`, `cardLayout` | per attribute | toolbar items, pager, column count (`--listora-grid-columns`) appear or vanish |
| Search block attribute `showKeyword`, `showLocation`, `showTypeFilter`, `showMoreFilters`, `showNearMe`, `placeholder`, `defaultSort`, `layout` | per attribute | keyword/location fields, type chips, Filters toggle + panel, Near me button, placeholder text, initial sort, horizontal vs vertical layout |
| Filter `wb_listora_search_features_visible` (default 8) | int | number of visible feature checkboxes before "Show all" |
| Filter `wb_listora_featured_backfill` (default false) | bool | Featured block pads with top-rated when true |
| Filter `wb_listora_term_archive_filters` / `wb_listora_term_archive_intro` | `__return_empty_array` / html | S8 falls back to theme archive / intro text replaced |
| Submission toggle (`wb_listora_can_offer_submission()`) | on/off | "Add a listing" button on the empty state |
| Distance unit (`distance_unit`, `near_me_radius`) | km/mi, radius list | S1 radius select labels (mi/km) |
| Site timezone | UTC, UTC+14, UTC-12 | `timezone` and `hours` on REST rows; featured expiry day |

## Fixtures and setup
- Site profile: `docs/qa/sites/<site-host>.json` (select with `QA_SITE`); resolves {directory}, {owner}, {other}, {editor}, {admin}, {cat}, {loc}, {tag}, {feature}, {type}, {type_b}, {listing}.
- Seed: `wp --path=$WP_PATH eval-file wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php seed` (prints ids; idempotent). Restore: same file with `clean` (removes only `_j20_fixture` rows).
- Derive counts in the step, never hard-code: `ALL`/`PUB` = `X-WP-Total` of `GET /search`; `FEAT` = `SELECT COUNT(*) FROM {prefix}listora_search_index WHERE is_featured=1`; term count = `wp term get <taxonomy> <slug> --by=slug --field=count`; `LAST = ceil(PUB / per_page)`.
- Scale seed for paging and counts: a QA-prefixed batch of published listings so total >= 2,000; trash it afterwards.
- Temporary pages for pinned-type and empty-state checks: `wp post create --post_type=page --post_status=publish --post_title="QA grid empty" --post_content='<!-- wp:listora/listing-grid {"listingType":"$VALUE"} /-->' --porcelain`; delete them afterwards.
- mu-plugins used by some steps (`zz-qa-claim-category-TEMP.php`, hook listeners, term-archive filter): remove every one.
- Restore afterwards: `search_cache_ttl`, `per_page`, site timezone, un-featured/re-featured listings, trashed/draft listings, QA seeds.

## Run order
Surface sweep first (MICRO_CHECKS on S1-S8, S10, S12 across the matrix), then:
1. `search-with-filters` - mode browser, critical: count, badge, type tab, filters panel, zero results, 390 and unreset theme.
2. `search-keyboard-and-suggestions` - browser, critical: typing only suggests; Enter, button, location Enter, Escape, clear icon.
3. `search-features-filter-reaches-every-feature` - browser, high: "Show all features (N)" reaches every in-use feature.
4. `directory-empty-states` - mixed, high: empty card on first paint, after live filter, pinned type, editor picker.
5. `pagination-active-page-contrast` - browser, high: one active page, AA contrast, 40 px targets.
6. `paging-past-last-page` - mixed, high: clamp on Directory, wp-admin tables, dashboard tabs.
7. `featured-filter-and-see-all` - mixed, high: "See all" target and the featured filter.
8. `featured-listings` - mixed, normal: Featured block, empty state, owner feature, expiry.
9. `term-pages-show-directory` - mixed, critical: five term URLs, redirect, 404, escape hatch.
10. `directory-url-params-are-prefixed` - mixed, critical: `listora_` params survive another plugin owning `category`.
11. `search-rest-listing-row-fields` - api, high: rating, hours, image, `is_favorited` on every row.
12. `search-returns-coordinates` - api, high: `geo` numbers, one geo query, `null` when un-geocoded.
13. `search-cache-ttl-zero-disables` - cli, high: TTL 0 writes and serves no cache.

No Pro folder exists for this group; Pro extends these surfaces from `browse-blocks` (Load More, infinite scroll) and `wb-listora-pro/docs/qa/journeys/browse-blocks/_group.md`.
14. `search-engine-sql-safety-and-seams.md` - mode mixed, high. The search engine never builds `IN ()`, and an extension can scope the directory on every surface

## Group-specific micro checks
| id | Check (exact element, exact expectation) |
|---|---|
| G-01 | The count text is "Showing A&ndash;B of N listings" with an EN DASH (U+2013) between A and B, and the three numbers are inside `[data-wp-text="state.gridCountFrom|To|Total"]` spans; after a live search all three spans update, not only the total. |
| G-02 | At N = 1 the count reads "Showing 1-1 of 1 listings" (the plural is hard-coded in `toolbar.php`); record it under P-05 if the product intends "1 listing". At N = 0 it reads "0 results" (plural `_n`), never "Showing 0-0". |
| G-03 | `.listora-search__filter-count` text equals the number of applied filters and is hidden (`is-hidden`) at zero; a type tab never increments it; two ticked features add 2; the date preset/from/to add exactly 1; a keyword adds 1. |
| G-04 | `.listora-search__clear-all` (inside the panel) is hidden until `hasActiveFilters`; note that `hasActiveFilters` is true for a type tab alone, so the button and a "0" badge can show with only a type tab chosen (same defect as search-with-filters step 4). |
| G-05 | Suggest debounce is 300 ms: typing with key gaps under 300 ms fires one `/search/suggest` request; gaps over 300 ms fire one per pause. Both requests carry `q=` and `type=`. |
| G-06 | `#listora-keyword` has `role=combobox`, `aria-controls=listora-suggestions`, `aria-expanded` toggles with the listbox; each `.listora-search__suggestion-item` has `role=option` and an `href` equal to the suggestion `url`; the listbox is not focusable inside a clipped container (not cut by the actions row). |
| G-07 | The pager shows at most 7 numbered links; with 8+ pages page 1 and LAST stay reachable with an ellipsis only when a gap of 2+ pages exists (no ellipsis between 1 and 2). |
| G-08 | Active page number size: `.listora-grid__page-num` is 32 x 32 px at 1440 (2rem) and 44 x 44 px at 390 (2.75rem); report 32 px at 1440 as informational, 44 px at 390 as the pass line. The prev/next `listora-btn--icon` buttons are >= 40 px at 390. |
| G-09 | Disabled prev on page 1 and disabled next on the last page are `<button disabled>` (not anchors) with aria-label "Previous page"/"Next page"; enabled ones are `<a href>` whose href keeps every active `listora_*` filter and drops `listora_page` only for page 1 or keeps `=1` consistently. |
| G-10 | The sort select's visible value equals the URL `listora_sort` (or the block `defaultSort`) on first paint; "Nearest" appears only when the result carries distances; choosing a sort writes `listora_sort` and resets `listora_page`. |
| G-11 | "Show all features (N)": N in the summary equals the number of `.listora-search__filter-checkbox[data-listora-feature-id]` in the group; the first 8 are ordered by usage count descending; the `<details>` is `open` on load exactly when a hidden feature is in `listora_features`. |
| G-12 | Narrowing the type chip at runtime hides feature checkboxes not in `data-listora-feature-allowlist` for that type without a reload; a feature ticked before the switch and no longer allowed is not sent in the next request. |
| G-13 | The Category select lists only categories of the chosen type with published listings (`hide_empty`), "All Categories" first; the category count shown anywhere equals `wp term get ... count`. |
| G-14 | Featured block: `.listora-featured__dots` count equals `ceil(cards / columns)`; arrows and dots are absent when there is one page; the active dot follows the scroll position after Next; the block `h2` is absent when its text equals the page title. |
| G-15 | "See all" `href` equals `{directory}?listora_sort=<block sort>[&listora_type=<pinned type>]&listora_featured=1` with no empty parameters (`array_filter` drops them) and no `/listing/` URL. |
| G-16 | Empty state on a pinned grid: title "No <Type Name> listings yet" uses the type NAME (capitalised, from the registry), never the slug; for a misspelt slug it uses the slug; "Clear All Filters" absent; once the visitor adds a filter the generic copy and the button return. |
| G-17 | Term page `<title>` is "<term name> - <site name>", `<link rel=canonical>` equals the term URL (not `{directory}`), the page-2 link is `<term URL>?listora_page=2`, and the Directory count equals `term count`. |
| G-18 | `GET /search` envelope is `{listings,total,pages,has_more}` and its `total` equals the `X-WP-Total` header, equals the number in the grid count, equals `pages x per_page` rounding (`X-WP-TotalPages`); for a guest, drafts and pending never appear in any count. |
| G-19 | The keyword/location inputs and the Search button share one row at 1440 and stack at 390 without overflow; `#listora-keyword` and `#listora-location` have identical widths when stacked, and the Near me button sits inside the location field without covering typed text (padding-inline-end >= button width). |
| G-20 | After Apply Filters the Filters panel state, the ticked boxes, the selected category and the rating select all restore from the URL on reload and on back/forward (F-06, F-07); closing the panel does not clear them. |

## Known defects (expected-fail)
| Journey | Step | Defect |
|---|---|---|
| search-with-filters | 4 | Type tab alone shows a "0" Filters badge instead of staying hidden (`src/interactivity/store.js:535`, `filters.php:50`) |
| search-with-filters | 8 | `.listora-search__active-filters` is rendered empty; no removable chip per active filter (`search.php:109-116`) |
| search-with-filters | 13 | Feature checkbox rows measure about 22 px high at 390 (below 40 px) |
| search-keyboard-and-suggestions | 11 | A keyword starting with a stop word ("The ...") returns 0 results (`class-search-engine.php:316-346`) |
| featured-listings | 6 | Newly featured listing does not reindex (`class-featured.php:164`), absent from `featured_only` |
| featured-listings | 7 | Expired featured listing keeps `is_featured = 1` in the index, stays in the block |
| featured-listings | 10 | "See all" link about 61 x 22 px at 390 (below 40 px) |
| paging-past-last-page | 3 | `Admin_Table` shows the empty state for a past-the-end `paged` (admin-listings owns the fix surface) |

## Not covered yet
Found while reading the templates, source and CSS; no journey touches them:
1. Sort control: no step selects each sort option (Newest, Highest Rated, Price asc/desc, Most Reviewed, A to Z) and checks order against REST; "Price" sorts on a type with no price field; `listora_sort` reset of `listora_page`.
2. Per-type filter widgets: multiselect checkboxes, single checkbox toggle, "Open Now", number/price Min-Max inputs (`setFilterCheckbox`, `setFilterToggle`, `_min`/`_max` keys) have no boundary or REST-parity steps; only select and feature filters are exercised.
3. Event Date group: presets, From/To inputs, `from > to`, timezone edge for "Today" / "This Weekend" / "Happening Now" are only mentioned as a badge step.
4. Search error and loading states: `.listora-search__error` after a failed request, 20-second abort, loading bar + skeletons, `aria-busy` on the results, retry after failure (F-14) have no steps.
5. Block attribute matrix: `showKeyword/Location/TypeFilter/MoreFilters/NearMe`, `placeholder`, `layout=vertical`, grid `columns`, `perPage`, `showPagination=false`, `showSort=false` - no journey flips them and checks the surface (F-18).
6. Two Directory blocks on one page, and a search block with no grid on the same page (shared store keys), pinned-type search block hiding type chips.
7. Type chip overflow: arrow buttons and scroll fade appear only on overflow (`home-screen-ux` in browse-blocks measures arrows; the filter effect of each chip with 10+ types is not run).
8. Keyboard operation of the Filters panel and pager (Tab order, Escape closes the panel, focus returns to the Filters button), and a screen-reader announcement of the count change (`aria-live` on `.listora-grid__count`).
9. Suggestion row content: `type`/`meta` display for non-listing suggestions (category, location), long titles clipping, and the stale-bundle risk (`build/` vs `src/`) are only listed as fail diagnostics.
10. Search with the cache on: a listing published, trashed or featured must leave and enter results within the TTL (cache invalidation on write); only TTL 0 is covered.
