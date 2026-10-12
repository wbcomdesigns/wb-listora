# Coverage: browse-blocks

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Categories block: tiles, counts, hide-empty, View all | browse-blocks/categories-block.md | F, B | guest, other, owner, admin | 1440/390 | light/dark | block attribute boundary (step 8) not run |
| Save heart overlays the card; count follows the toggle | browse-blocks/favorite-controls-on-card-and-detail.md | F, R | owner, other, editor, guest, admin | both (n.v.) | n.v. | browser run |
| Grid default view and remembered choice | browse-blocks/grid-view-mode-default-and-remembered.md | F, B | guest, owner, admin | both (390 n.v.) | n.v. | hydration steps n.v. |
| Browse, favourite, dashboard Favorites tab + template override | ora:browse-blocks/browse-and-favourite-a-listing | frontend, REST, template | guest, owner, other, editor, admin | both | both | override file, hooks and 25-favourite paging not live |
| Events calendar, timezone, agenda | ora:browse-blocks/calendar-block | frontend, REST | guest, member, owner, admin | both | both | lifecycle, timezone matrix, scale not live; 31px arrows (expected-fail) |
| Card/detail/map image alt and placeholder | ora:browse-blocks/card-image-alt-fallback | frontend, block | guest + ladder | both | both | untitled/map popup/a11y snapshot not live |
| Home screen spacing, type-chip arrows, category tiles | ora:browse-blocks/home-screen-ux | frontend, block | guest, member, admin | both (+820) | both | measurements not run in browser |
| Compare bar, toggles, table, no-page-no-UI, REST | pro:browse-blocks/compare-listings | frontend, REST, block | guest, member, editor, admin | both (+820) | both | rate burst, dark/phone measurements, 2,000 site not live |
| Compare: one renderer, one table (Pro) | pro:browse-blocks/compare-one-renderer-one-table.md | F, B | guest, owner, admin | 1440/820/390 (n.v.) | n.v. | alignment/RTL n.v. |
| Comparison labels wrap, sticky, phone columns | pro:browse-blocks/comparison-label-wrap.md | F | guest, owner, editor, admin | 1440/820/390 | light/dark | step 6 and count boundaries not run |
| Infinite scroll no-JS, bottom loading, rel/noscript links | pro:browse-blocks/infinite-scroll-no-js-bottom-and-rel-links | frontend, head, noscript | guest, member, admin | both | both | JS-off browser run, scale not live |
| Load More counters, deep links, pinned grid, two grids | pro:browse-blocks/load-more-counters-and-two-grids | frontend, block | guest, member, admin | both | both | card-shape parity, two-grid clicks, 2,000 scale not live |
| Load More disabled while loading | pro:browse-blocks/load-more-disabled-while-loading | F | guest, member, admin | Y/Y | n | count boundary step unrun |
| Quick view dialog (close, no photo, arrows, rating, switch) | pro:browse-blocks/quick-view-dialog | frontend, REST | guest, member, editor, admin | both | both | empty-src read-back, contrast, RTL not live |
