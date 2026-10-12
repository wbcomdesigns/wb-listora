# Coverage: dashboard-account

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Dashboard list tabs reach every row; Claims REST + tab pagination | ora:dashboard-account/dashboard-tabs-paginate-every-row.md | F, R | owner, other, editor, guest, admin | all 3 (390 n.v.) | n.v. | reviews-written pager needs seed |
| Deactivate/reactivate return `already_*` flags | ora:dashboard-account/listing-deactivate-reactivate-flags.md | R, F | owner, other, guest, editor, admin | both (n.v.) | n.v. | dashboard toast n.v. |
| My Listings badge, rows, statuses, filters, paging, REST agreement | ora:dashboard-account/dashboard-my-listings-statuses-counts-filters.md | F R | owner, other, guest, admin | Y/Y | n | needs 2000+ seed; cache staleness unobservable without object cache |
| Dashboard grid, rail, in-page tab nav, params dropped on switch | ora:dashboard-account/dashboard-layout-and-navigation.md | F | owner, other, editor, admin, guest | both | not run | computed styles unrun in browser |
| Dashboard pinned to a listing type | ora:dashboard-account/dashboard-listing-type.md | F, A (block editor), R | owner, other, admin, guest | both | not run | pinned-page steps unrun (needs second type) |
| Listings tab row actions, deactivate/reactivate, feature, delete, scale | ora:dashboard-account/dashboard-listings-tab-manage.md | F, R | owner, other, editor, admin, guest | both | partly | feature with credits, Renew modal |
| Member notification feed (REST only) | ora:dashboard-account/dashboard-notifications-feed.md | R | owner, other, editor, admin, guest | n/a | n/a | consumer unknown (retire candidate); no frontend/admin entry point (intentional) |
| Overview tiles, next steps, counters refresh after writes | ora:dashboard-account/dashboard-overview-tab.md | F, R | owner, other, nocap, admin, guest | both | not run | persistent-cache assertions |
| Profile save, sanitising, email staging, Blocked Members, Change photo | ora:dashboard-account/dashboard-profile-tab.md | F, R | owner, blocker, other, editor, admin, guest | both | not run | expected-fail email-change password UX; BP avatar |
| Every tab query is clean and counters equal lists | ora:dashboard-account/dashboard-tabs-emit-no-db-errors.md | F, CLI (log) | owner, other, editor, admin, guest | both | not run | scale step unrun |
| Tabs exist only while their feature is on; off/unknown ids fall back to Overview | ora:dashboard-account/dashboard-tabs-follow-features.md | F, A, R | owner, other, editor, admin, guest | both | not run | expected-fail: feature-off REST routes still answer (owner decision) |
| Document title and single heading follow the active tab | ora:dashboard-account/dashboard-title-follows-tab.md | F | owner, editor, admin, guest | both | not run | JS title update unrun |
| Account deactivate/reactivate round trip | ora:dashboard-account/deactivate-and-reactivate-account.md | R, CLI | owner, other, guest | n/a | n/a | scale (200 listings); no web or admin entry point |
| Account permanent delete and erasure map | ora:dashboard-account/delete-account.md | R, CLI | owner, other, guest | n/a | n/a | expected-fail leads eraser id; no web/admin entry point |
