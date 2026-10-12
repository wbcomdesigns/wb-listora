# Coverage: D-dashboard-account

Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. Viewports/themes: "1440/390" and "L/D" = light and dark; "n/a" for REST/CLI-only.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | L/D | Gaps |
|---|---|---|---|---|---|---|
| Dashboard grid, rail, in-page tab nav, params dropped on switch | ora:customer/dashboard-layout-and-navigation.md | F | owner, other, editor, admin, guest | both | not run | computed styles unrun in browser |
| Document title and single heading follow the active tab | ora:customer/dashboard-title-follows-tab.md | F | owner, editor, admin, guest | both | not run | JS title update unrun |
| Tabs exist only while their feature is on; off/unknown ids fall back to Overview | ora:customer/dashboard-tabs-follow-features.md | F, A, R | owner, other, editor, admin, guest | both | not run | expected-fail: feature-off REST routes still answer (owner decision) |
| Dashboard pinned to a listing type | ora:customer/dashboard-listing-type.md | F, A (block editor), R | owner, other, admin, guest | both | not run | pinned-page steps unrun (needs second type) |
| Overview tiles, next steps, counters refresh after writes | ora:customer/11-dashboard-overview-tab.md | F, R | owner, other, nocap, admin, guest | both | not run | persistent-cache assertions |
| Listings tab row actions, deactivate/reactivate, feature, delete, scale | ora:customer/14-dashboard-listings-tab-manage.md | F, R | owner, other, editor, admin, guest | both | partly | feature with credits, Renew modal |
| Services modal presentation | ora:customer/dashboard-services-modal.md | F | owner, other, admin, guest | both | not run | browser run |
| Every tab query is clean and counters equal lists | ora:customer/dashboard-tabs-emit-no-db-errors.md | F, CLI (log) | owner, other, editor, admin, guest | both | not run | scale step unrun |
| Profile save, sanitising, email staging, Blocked Members, Change photo | ora:customer/12-dashboard-profile-tab.md | F, R | owner, blocker, other, editor, admin, guest | both | not run | expected-fail email-change password UX; BP avatar |
| Member notification feed (REST only) | ora:system/dashboard-notifications-feed.md | R | owner, other, editor, admin, guest | n/a | n/a | consumer unknown (retire candidate); no frontend/admin entry point (intentional) |
| Account deactivate/reactivate round trip | ora:customer/11-deactivate-and-reactivate-account.md | R, CLI | owner, other, guest | n/a | n/a | scale (200 listings); no web or admin entry point |
| Account permanent delete and erasure map | ora:customer/12-delete-account.md | R, CLI | owner, other, guest | n/a | n/a | expected-fail leads eraser id; no web/admin entry point |
| Suspended or role-stripped account write gate | ora:system/member-write-gate.md | A (Users), R | admin, moderator, owner, other, role-stripped, guest | n/a | n/a | bulk/parallel unrun |
| Demo accounts cannot log in | ora:system/demo-accounts-not-loginable.md | CLI, F (wp-login), R | guest, admin | n/a | n/a | fresh-import step unrun |
| Rate limit: members by account, guests by IP | ora:system/rate-limit-counts-members-by-account.md | R, CLI | owner, other, guest, admin | n/a | n/a | HTTP 429 unprovoked |
| `wb_listora_send_notification` passes recipient | ora:system/send-notification-to-arg.md | CLI (hook) | admin, system | n/a | n/a | admin-recipient step unrun |
| Success card links to the registered Dashboard URL | ora:customer/submission-dashboard-url-registry.md | F | owner, other, admin, guest | both | not run | click-through unrun |
| Anonymous login modal always has Create account | ora:customer/anon-login-modal-register-cta.md | F | guest, owner, admin | both | not run | click/filter steps unrun |
| "Listed by" never shows a login | ora:customer/listed-by-never-shows-login.md | F, R | guest, owner, unnamed, editor, admin | both | not run | filter/boundary steps unrun |
| Taxonomy CRUD and term pages | ora:admin/07-taxonomy-crud.md | A, F, CLI | admin, editor, guest | 1440 (390 hub added) | not run | scale step unrun |
| Listings admin table columns and layout | ora:admin/admin-listings-table-layout.md | A | admin, editor | both | n/a | pixel widths unmeasured |
| Pages-review notice X persists | ora:admin/pages-review-notice-dismiss-persists.md | A | admin, editor | both | n/a | X click unrun; merge with pages-notice-settings-only |
| Pages created once, Create page recovers | ora:admin/pages-are-created-once.md | A, CLI | admin, editor, guest | both | n/a | Pro lifecycle steps scratch-site only |
| One form and one Save per settings tab | ora:admin/settings-one-save-per-tab.md | A | admin, editor, guest | both | partly | saves unperformed on shared site |
| Analytics screen numbers, search report, retention | pro:admin/11-analytics-dashboard.md | A, R | admin, owner, other, editor, guest | both | not run | timezone matrix; 50k-row run |
| Notification digest queue and flush | pro:admin/13-notification-digest.md | A, E, CLI | admin, owner, editor, guest | both | n/a | timezone of 9 AM schedule; scale |
| Visibility tab always present; Features save keeps hidden keys | pro:admin/visibility-tab-gated-by-toggle.md | A | admin, editor, other, guest | both | not run | form-driven save unrun |
| Licence deactivation clears every option | pro:admin/license-deactivation-actually-deactivates.md | A, CLI | admin, editor, guest | both | n/a | store release call skipped on .local |
| Private/Coming Soon gate lets logged-in members in | pro:customer/coming-soon-private-cap.md | F, R | guest, owner, editor, admin | 1440 | not run | no-role user row; overlaps other visibility journeys |
| BuddyPress profile Listings/Reviews cards, pagination, per-page | pro:customer/bp-profile-listings-styled.md | F | owner, other, editor, admin, guest | both (+820) | not run | dark/unbridged unrun; overlaps buddypress-tabs-pagination |
| My Needs 20/page, badge counts all, controls parity | pro:customer/my-needs-pages-20-and-badge-counts-all.md | F, A (Needs list) | owner, other, editor, admin, guest | both | not run | parity and 500-need steps unrun |
