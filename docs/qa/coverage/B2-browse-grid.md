# Coverage: B2 browse, grid, featured, compare, quick view, saved search, visibility

One owner row per behaviour. Paths are relative to each repo's docs/qa/journeys (Free = ora, Pro = pro). Viewport column: 1440/390; mode: light/dark (L/D). "n" = not exercised in the journey.

| behaviour | owner journey | surfaces | roles | 1440/390 | L/D | gaps |
|---|---|---|---|---|---|---|
| Directory keyword/type/feature/rating search, count equals REST total | ora:customer/04-search-with-filters | frontend, REST | guest, member, editor, admin | both (+820) | both | feature rows 22px at 390 (expected-fail); scale seed 2,000 not run |
| Filters badge counts every active filter incl. dropdowns | ora:customer/04-search-with-filters | frontend | guest + ladder | both | both | type-tab "0" badge and missing removal chips (expected-fail) |
| Search bar fits unreset themes (box-sizing) | ora:customer/04-search-with-filters | frontend | guest, member | both | both | n live run |
| Zero-result empty state + Clear All | ora:customer/04-search-with-filters | frontend, REST | guest | both | both | - |
| Near me radius, distance, unit | ora:customer/near-me-radius-and-distance | frontend, admin settings, REST | guest, member, admin | both | both | unit iteration and scale (steps 3, 8) not live |
| Term pages render Directory, redirect, escape hatch | ora:customer/term-pages-show-directory | frontend | guest, other, owner, admin | both | both | trash/deleted-author and 2,000-row term not live |
| Paging past the last page (Directory, wp-admin, dashboard) | ora:customer/paging-past-last-page | frontend, admin, REST | guest, owner, other, admin | both | n | Admin_Table clamp (expected-fail); N=0/1/20/21 not live |
| Featured filter + "See all" target | ora:customer/featured-filter-and-see-all | frontend, REST | guest, member, admin | both | both | boundary values and 390 not live |
| Featured block, empty state, owner feature, expiry | ora:customer/10-featured-listings | frontend, REST, cron | guest, owner, other, editor, admin | both | both | reindex on feature/expire (expected-fail); 100+ scale not live |
| Browse, favourite, dashboard Favorites tab + template override | ora:customer/01-browse-and-favourite-a-listing | frontend, REST, template | guest, owner, other, editor, admin | both | both | override file, hooks and 25-favourite paging not live |
| Events calendar, timezone, agenda | ora:customer/08-calendar-block | frontend, REST | guest, member, owner, admin | both | both | lifecycle, timezone matrix, scale not live; 31px arrows (expected-fail) |
| Listing header: one Verified chip, one address | ora:customer/listing-header-chips-and-address | frontend | guest + ladder | n/390 n | both | steps 2, 8, 390 not live |
| Card/detail/map image alt and placeholder | ora:customer/card-image-alt-fallback | frontend, block | guest + ladder | both | both | untitled/map popup/a11y snapshot not live |
| Home screen spacing, type-chip arrows, category tiles | ora:customer/home-screen-ux | frontend, block | guest, member, admin | both (+820) | both | measurements not run in browser |
| Featured image drag and drop in submission | ora:customer/submission-featured-image-drag-drop | frontend, REST | owner, guest, no-upload member, admin | both | both | real upload, retry, edit path not live |
| Admin map picker mounts in wp-admin | ora:admin/admin-map-picker-renders | admin | admin, editor, owner, guest | both | n | mount, drag, 390 not live |
| Marker clustering setting vs block attribute | ora:admin/map-clustering-site-default | admin, frontend, block, REST | admin, moderator, guest, member | both | n | steps 2-5, 7 not live |
| /search returns geo coordinates | ora:system/search-returns-coordinates | REST | guest, member, editor, admin | n | n | boundary bbox/paging step 4 not run |
| Search cache TTL 0 disables caching | ora:system/search-cache-ttl-zero-disables | admin settings, CLI | admin, editor | n | n | boundaries and migration step not run |
| Term/email helper consolidation (Free canonical, Pro via functions) | ora:system/term-helper-consolidation | CLI, email | admin | n | n | Mailpit AltBody not run |
| Load More counters, deep links, pinned grid, two grids | pro:customer/load-more-counters-and-two-grids | frontend, block | guest, member, admin | both | both | card-shape parity, two-grid clicks, 2,000 scale not live |
| Infinite scroll no-JS, bottom loading, rel/noscript links | pro:customer/infinite-scroll-no-js-bottom-and-rel-links | frontend, head, noscript | guest, member, admin | both | both | JS-off browser run, scale not live |
| Quick view dialog (close, no photo, arrows, rating, switch) | pro:customer/quick-view-dialog | frontend, REST | guest, member, editor, admin | both | both | empty-src read-back, contrast, RTL not live |
| Compare bar, toggles, table, no-page-no-UI, REST | pro:customer/04-compare-listings | frontend, REST, block | guest, member, editor, admin | both (+820) | both | rate burst, dark/phone measurements, 2,000 site not live |
| Save a search from the Directory + dashboard + alerts | pro:customer/07-saved-search-alert | frontend, email, cron | owner, other, guest, editor, admin | both | both | geometry at both widths, boundary names, new-listing alert not live |
| Saved searches REST contract + alert job | pro:customer/13-saved-search-crud | REST, cron, hooks | owner, other, guest, editor, admin | n | n | boundary body, parallel create not live |
| Coming Soon / Private visibility gate; also: Private / Coming Soon visibility modes persist and gate; also: Private/Coming Soon gate lets logged-in members in | pro:customer/10-coming-soon-mode | frontend, REST, sitemap, oEmbed, BuddyPress | guest, member, author, editor, admin | both | both | upgrade pin (disposable site), author/contributor rows not live; Whole-site at 390/dark; sitemap/feed; no-role user row; overlaps other visibility journeys |
| BuddyPress review-author links and labelled stars | pro:customer/09-buddypress-integration | frontend, REST | guest, member, editor, admin | both | both | aria-label read-back not live |
| Google vs OpenStreetMap provider | pro:customer/12-google-maps-render | frontend, admin, REST | guest, member, editor, admin | both | both | Google render needs a key; popup image parity (expected-fail) |
| Needs budget unit, card author, quote positivity | pro:customer/needs-budget-unit-card-author | frontend, REST, admin | owner, other, guest, moderator, admin | both | both | lapsed-deadline edit, big site not live |
| Pro empty states use the shared component | pro:customer/pro-empty-states-shared-component | frontend | guest, member, moderator, owner, admin | both | both | never run live |
| Service hooks and booking_url extension point | pro:customer/15-service-booking-cta | frontend, REST, hooks | guest, member, owner, editor, admin | both | both | owner decision on built-in Book CTA (expected-fail) |
| Analytics favourited / saved / zero-result totals | pro:admin/analytics-favourited-saved-zero-result | admin, REST | admin, owner, member, editor, guest | both | both | timezone matrix and scale not live |
| Pro feature defaults on fresh activation | pro:admin/feature-defaults-on-fresh-activation | admin, CLI | admin, editor, member | 1440 | n | activator reactivation not live |
| Credit mapping edit, Pricing column, direct gateway card; also: Active Mappings names provider and product (Pro) | pro:admin/mappings-edit-and-pricing | admin | admin, editor, guest | both | n | boundary/zero-decimal and retry not live; role refusals, mobile n.v. |
| Webhooks admin list paging + last delivery | pro:admin/webhooks-list-pagination | admin, REST | admin, editor, member, guest | both | n | past-last-page (expected-fail); concurrency not live |
