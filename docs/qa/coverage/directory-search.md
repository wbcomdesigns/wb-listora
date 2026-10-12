# Coverage: directory-search

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Zero-result grid empty state (first paint, live, pinned type, editor picker) | directory-search/directory-empty-states.md | F, A(editor), B | guest, owner, editor, admin | both (n.v.) | n.v. | hydration/live steps n.v. |
| Filters survive another plugin owning `category`/`type`/`page` | directory-search/directory-url-params-are-prefixed.md | F, R, B | all 5 | both (n.v.) | n.v. | mu-plugin conflict + Pro load-more/saved search n.v. |
| Active page number contrast and past-the-end page | directory-search/pagination-active-page-contrast.md | F | guest, owner | both | both | light mode + unknown theme n.v. |
| Every in-use feature is filterable ("Show all features (N)") | directory-search/search-features-filter-reaches-every-feature.md | F, R | guest, owner, admin | both (n.v.) | n.v. | 2000+ query-count check n.v. |
| Typing only suggests; Enter / Search button / location Enter run the search; Escape; one clear icon; suggest envelope | directory-search/search-keyboard-and-suggestions.md | F, R | guest, owner, admin | both (n.v.) | both (n.v.) | stop-word keyword returns 0 (expected-fail); browser run missing |
| rating / hours / featured_image / is_favorited on every listing row (REST) | directory-search/search-rest-listing-row-fields.md | R | all 5 | n/a | n/a | tz matrix and scale rerun added, n.v. |
| Featured filter + "See all" target | directory-search/featured-filter-and-see-all.md | frontend, REST | guest, member, admin | both | both | boundary values and 390 not live |
| Featured block, empty state, owner feature, expiry | directory-search/featured-listings.md | frontend, REST, cron | guest, owner, other, editor, admin | both | both | reindex on feature/expire (expected-fail); 100+ scale not live |
| Paging past the last page (Directory, wp-admin, dashboard) | directory-search/paging-past-last-page.md | frontend, admin, REST | guest, owner, other, admin | both | n | Admin_Table clamp (expected-fail); N=0/1/20/21 not live |
| Search cache TTL 0 disables caching | directory-search/search-cache-ttl-zero-disables.md | admin settings, CLI | admin, editor | n | n | boundaries and migration step not run |
| /search returns geo coordinates | directory-search/search-returns-coordinates.md | REST | guest, member, editor, admin | n | n | boundary bbox/paging step 4 not run |
| Directory keyword/type/feature/rating search, count equals REST total | directory-search/search-with-filters.md | frontend, REST | guest, member, editor, admin | both (+820) | both | feature rows 22px at 390 (expected-fail); scale seed 2,000 not run |
| Filters badge counts every active filter incl. dropdowns | directory-search/search-with-filters.md | frontend | guest + ladder | both | both | type-tab "0" badge and missing removal chips (expected-fail) |
| Search bar fits unreset themes (box-sizing) | directory-search/search-with-filters.md | frontend | guest, member | both | both | n live run |
| Zero-result empty state + Clear All | directory-search/search-with-filters.md | frontend, REST | guest | both | both | - |
| Term pages render Directory, redirect, escape hatch | directory-search/term-pages-show-directory.md | frontend | guest, other, owner, admin | both | both | trash/deleted-author and 2,000-row term not live |
| Sort control order and `listora_sort` round trip | directory-search/search-with-filters.md | frontend, REST | guest | both | n | no step per sort option; order vs REST not asserted |
| Per-type filter widgets (multiselect, toggle, Open Now, min/max) | directory-search/search-with-filters.md | frontend, REST | guest | both | n | no boundary or REST-parity steps |
| Search cache written and invalidated on listing change | directory-search/search-cache-ttl-zero-disables.md | CLI | admin | n | n | only TTL 0 and positive TTL covered, invalidation on write not run |
| Search engine never builds `IN ()`; extension seams scope every surface | ora:directory-search/search-engine-sql-safety-and-seams.md | R, F, B | admin, guest | 1440 | light | empty multi-value filter expected-fail; seam probe n.v. |
