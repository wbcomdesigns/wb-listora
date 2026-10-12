# Coverage: admin-listings

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Reviews and claims bulk Apply / Search submit | ora:admin-listings/admin-bulk-actions-submit.md | A | admin, editor | Y/Y | n | no pending review on QA site; Reviews/Claims filters, sort, drawer, row actions unrun |
| wp-admin listing media fields, moderator keeps media, deleted attachments | ora:admin-listings/listing-media-fields-and-retention.md | A F R | admin, moderator, guest | Y/Y | n | interactive steps unrun |
| Add listing from wp-admin | ora:admin-listings/add-listing-from-wp-admin.md | A, F, R | admin, editor, author, contributor, owner, guest | P | light | retry, timezone, lifecycle, service-category three-entry read-back |
| Listings admin table columns and layout | ora:admin-listings/admin-listings-table-layout.md | A | admin, editor | both | n/a | pixel widths unmeasured; type/duplicate filters, sort, Mark verified, renewal-reminder bulk have no step |
| Admin Location column renders region-level addresses | ora:admin-listings/admin-location-column-no-city.md | admin | admin, editor, other, guest | 1440+390 | gap | boundary + large-table steps unrun |
| Admin map picker mounts in wp-admin | ora:admin-listings/admin-map-picker-renders.md | admin | admin, editor, owner, guest | both | n | mount, drag, 390 not live |
| Admin header, hub tabs, notices (visible, once, scoped) | ora:admin-listings/admin-shell-header-and-notices.md | A | admin, editor, owner, guest | Y | light only (wp-admin default) | licence notice unlicensed render; Pro webhook notice invisible (expected-fail); pages-notice dismissal |
| Approve/reject row actions, rejected guard, bulk type edit | ora:admin-listings/listing-approve-reject-and-bulk-actions.md | A, R, C | admin, editor, owner, other, guest | P | light | mixed-type no-op, scale, 390, owner preview |
| Hard-delete cascades every listing table | ora:admin-listings/listing-delete-cascade.md | CLI, DB | admin | n/a | n/a | expected-fail: cleanup prints 0; leads owner decision; 2000+ rows |
| Missing Credits SDK notices | ora:admin-listings/missing-sdk-notices-agree.md | A | admin, editor, other, guest | 1440 | light | destructive steps never run; older-copy case |
| Pro admin writes run before output | pro:admin-listings/admin-writes-run-before-output.md | A | admin, editor, other, guest | 1440 | light | webhook save confirmation (expected-fail), needs/webhook actions not run |
| Dependency notices reach only the owner and never fatal | pro:admin-listings/dependency-notices-owner-only.md | admin | admin, editor, owner, guest | 1440 | gap | throwaway-site only, unrun; 390/dark |
| Hidden submenu stubs redirect cleanly | pro:admin-listings/hidden-submenu-redirects.md | admin | admin, other, guest | - | - | Playwright check unrun |
| Moderator bulk actions report the result (Pro) | pro:admin-listings/moderator-bulk-reports-result.md | admin | admin, moderator, editor, guest | partial | n/a | expected-fail: bulk reassign green on bad target |
| Front-end moderator queue block | pro:admin-listings/moderator-queue-block.md | F, B | moderator, other, guest, editor, admin | 1440/390 | light/dark | >perPage paging is an open owner decision |
| Moderators screen: promote, pause, bulk, paging, counters, self-protection | pro:admin-listings/moderators-management.md | admin, REST | admin, other, editor, guest | 1440+390 (step 17 unmeasured) | gap | REST parity step; retry; Pro Badges/Verification side boxes |
| Moderator sees the catalog minus others' queue | pro:admin-listings/moderator-sees-the-catalog.md | admin | admin, other, editor, guest | 1440+390 | gap | WooCommerce lockout (expected-fail) |
