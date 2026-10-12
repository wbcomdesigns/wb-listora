# Coverage: area F1-admin

Surfaces: F frontend, A admin, R REST, E email, B block, S shortcode, C CLI. Viewport column: 1440/390 asserted (Y), partial (P), not applicable (-). Theme column: light/dark. Gaps are open items; "Not verified live" steps are listed in each journey's `## Not verified`.

| behaviour | owner journey | surfaces | roles | 1440/390 | light/dark | gaps |
|---|---|---|---|---|---|---|
| Competitor migration imports once, structured, silent, idempotent (5 sources) | ora:admin/migrate-from-competitor-plugin.md | A, C, R | admin, editor, other, guest | P (1440; 390 in step 9) | light/dark step 9 | hivepress and bdp rows never run live; ListingPro schema doc missing (expected-fail); scale untested |
| Migration fires the submit hook with migration context | ora:system/migration-listener-context.md | C, E | admin, owner, guest | - | - | BuddyPress, plan and moderator listeners not run live |
| Background import: async, progress REST, resume, terminal FAILED | ora:admin/background-import-lifecycle.md | A, R, C | admin, other, guest | - (1440 admin) | - | scale (2000+ rows), failed-run UI not run |
| Demo import quality (packs, dates, features, quiet, accounts, events) | ora:admin/demo-import-quality.md | A, C, F, R, E | admin, other, guest | Y | light/dark | upgrade-path steps 11-13, event re-import, timezone not run |
| Admin header, hub tabs, notices (visible, once, scoped) | ora:admin/admin-shell-header-and-notices.md | A | admin, editor, owner, guest | Y | light only (wp-admin default) | licence notice unlicensed render; Pro webhook notice invisible (expected-fail); pages-notice dismissal |
| Missing Credits SDK notices | ora:admin/missing-sdk-notices-agree.md | A | admin, editor, other, guest | 1440 | light | destructive steps never run; older-copy case |
| Save/reset confirmation survives redirect | ora:admin/save-and-reset-confirm-success.md | A, R | admin, editor, guest | Y | light | click-testing, 390 visibility |
| Settings tabs save independently, clamp, reset, docs links | ora:admin/settings-tabs-save-validate-reset.md | A, R, F | admin, editor, guest | Y | light | live docs anchors (store 403), page-mapping step, 390 |
| Listing types CRUD, built-ins, Services switch | ora:admin/listing-types-crud.md | A, R, F | admin, editor, owner, other, guest | Y | light/dark (frontend) | boundary input, read-backs for cf032112/81d650a5 |
| Approve/reject row actions, rejected guard, bulk type edit | ora:admin/listing-approve-reject-and-bulk-actions.md | A, R, C | admin, editor, owner, other, guest | P | light | mixed-type no-op, scale, 390, owner preview |
| Status emails (approve/reject) logged once | ora:admin/listing-status-emails.md | E, R, A | admin, editor, owner, guest | 1440 | light | saved-template shell, SMTP failure, filters |
| Email log, retention, resend, test send (UI, REST, CLI) | ora:admin/email-log-and-test.md | A, R, C, E | admin, editor, other, guest | Y | light | scale, resend failure branch |
| Email templates editor and branded shell | ora:admin/email-templates-editor.md | A, E | admin, editor, owner | Y | light | Pro marketing mail, boundary input |
| Add listing from wp-admin | ora:admin/add-listing-from-wp-admin.md | A, F, R | admin, editor, author, contributor, owner, guest | P | light | retry, timezone, lifecycle, service-category three-entry read-back |
| Health Check, Site Health, Rebuild Search Index | ora:admin/health-check-and-reindex.md | A, C | admin, editor, other, guest | Y | light | destructive card steps, table-coverage gap (expected-fail) |
| Settings and listings import/export round trip | ora:admin/import-export-roundtrip.md | A, R, C | admin, editor, other, guest | Y | light | boundary CSV, scale, cross-run duplicates |
| Role capability matrix | ora:admin/role-cap-matrix.md | A, R, F, C | all six rungs | 1440 | light | deleted-author, stripped-role case |
| Install and activation | ora:admin/installation-and-activation.md | C, A, R | admin, editor, guest | 1440 | light | upgrade-from-previous step |
| Uninstall removes everything, both orders, no collateral; also: Uninstall removes every status | ora:admin/uninstall-deletes-everything-both-orders.md | C, A | admin, editor | - | - | multisite, look-alike rows, restore-after-uninstall; destructive, never run on shared site |
| Free view count: record once, show to owner/admin only | ora:customer/owner-sees-view-count.md | F, R, A | guest, owner, other, admin | Y | light/dark | timezone near midnight |
| Listing detail file field renders as image, never raw id | ora:customer/listing-detail-file-field-renders-as-image.md | F | guest, other, admin | Y | light/dark | logo-on-tab is a live bug (expected-fail); non-image files |
| Edit permission one rule (form, POST, PUT) | ora:customer/edit-permission-same-on-form-and-rest.md | F, R | admin, editor, owner, other, guest | 1440 | light | boundary, lifecycle seeds |
| Open-now badge equals search filter | ora:customer/open-now-badge-matches-search.md | F, R | guest, owner, admin | Y | light | nothing run live |
| Cron jobs scheduled once, no AS notices, maintenance side-effects | ora:system/cron-schedule-and-maintenance.md | C, R, F | system, admin, guest | - | - | steps 4-7, 9 not run on shared site |
| Contact-form REST nonce gate for authenticated apps | ora:system/rest-nonce-gate-authenticated-app-writes.md | R | guest, subscriber, admin | - | - | boundary, parallel |
| Translations render, not just compile | ora:system/translations-render-not-just-compile.md | F, A | guest, owner, admin | Y | light/dark | JS bundle path, RTL |
| Styles linked, not inline @import | ora:system/styles-linked-not-inline-imported.md | F | guest, owner, admin | Y | light/dark | cascade-hash step, CSS lint live |
| Pro admin writes run before output | pro:admin/admin-writes-run-before-output.md | A | admin, editor, other, guest | 1440 | light | webhook save confirmation (expected-fail), needs/webhook actions not run |
| Pro welcome banner leaves a working directory alone | pro:admin/pro-welcome-banner-respects-live-site.md | A | admin, second admin, editor, guest | P | light | steps 2-5 need scratch site |
| Webhook subscriber UI matches the trigger registry | pro:admin/webhook-subscriber-ui-matches-registry.md | A, R | admin, other, guest | Y | light | end-to-end delivery not run, boundary |
