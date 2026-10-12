# Coverage: settings-setup

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Essential and showcase pages created, adopted, not resurrected | ora:settings-setup/setup-pages-created-adopted-once.md | A C | admin, editor, guest | Y/- | - | needs pristine DB for steps 1-4; multisite step unrun; overlaps pages-are-created-once step 4 (deleted page stays deleted) |
| Setup wizard run, session, done heading, replay protection | ora:settings-setup/setup-wizard-first-run.md | A | admin, editor, guest | Y/Y | n | replay steps unrun (rewrite settings) |
| Listing types CRUD, built-ins, Services switch | ora:settings-setup/listing-types-crud.md | A, R, F | admin, editor, owner, other, guest | Y | light/dark (frontend) | boundary input, read-backs for cf032112/81d650a5 |
| Pages created once, Create page recovers | ora:settings-setup/pages-are-created-once.md | A, CLI | admin, editor, guest | both | n/a | Pro lifecycle steps scratch-site only |
| Pages-review notice X persists | ora:settings-setup/pages-review-notice-dismiss-persists.md | A | admin, editor | both | n/a | X click unrun |
| Save/reset confirmation survives redirect | ora:settings-setup/save-and-reset-confirm-success.md | A, R | admin, editor, guest | Y | light | click-testing, 390 visibility |
| One form and one Save per settings tab | ora:settings-setup/settings-one-save-per-tab.md | A | admin, editor, guest | both | partly | saves unperformed on shared site |
| Settings tabs save independently, clamp, reset, docs links | ora:settings-setup/settings-tabs-save-validate-reset.md | A, R, F | admin, editor, guest | Y | light | live docs anchors (store 403), page-mapping step, 390 |
| Taxonomy CRUD and term pages | ora:settings-setup/taxonomy-crud.md | A, F, CLI | admin, editor, guest | 1440 (390 hub added) | not run | scale step unrun |
| Type editor "Selected only" filter does not alter saved ids | ora:settings-setup/type-editor-selected-only-saves.md | admin, REST | admin, editor, owner, guest | 1440+820 | gap | empty selection, retry unrun |
| Pro feature defaults on fresh activation | pro:settings-setup/feature-defaults-on-fresh-activation.md | admin, CLI | admin, editor, member | 1440 | n | activator reactivation not live |
| Feature toggles: screen rows, isolation matrix, OFF cancels jobs, ON flushes URLs, tab-controlled keys survive | pro:settings-setup/feature-toggles.md | admin, REST, frontend, CLI | admin, editor, owner, other, guest | 1440+390 asserted (steps 13) | light/dark asserted, unrun | front-end OFF states on shared site; seo_pages ON |
| Licence deactivation clears every option | pro:settings-setup/license-deactivation-actually-deactivates.md | A, CLI | admin, editor, guest | both | n/a | store release call skipped on .local |
| License screen truthful on both surfaces, never gates features, no extra PII, throttled; also: licence key never blank when active (get_key falls back to the SDK key; wizard shows the key) | pro:settings-setup/license-lifecycle.md | admin, wizard, Plugins modal, CLI | admin, editor, owner, other, guest | 1440+390 asserted | asserted, unrun | non-local host steps; SDK option holds customer_email (expected-fail) |
| Pro setup wizard | pro:settings-setup/pro-setup-wizard.md | A | admin, editor, guest | Y/Y | n | no real license activation by design |
| Pro welcome banner leaves a working directory alone | pro:settings-setup/pro-welcome-banner-respects-live-site.md | A | admin, second admin, editor, guest | P | light | steps 2-5 need scratch site |
| White label: brand scope, two rows differ, app branding, never touches identifiers | pro:settings-setup/white-label-branding.md | admin, Plugins screen, privacy tools, REST app-config, email | admin, editor, owner, other, guest | 1440+390 | light/dark | whole-screen grep (step 6) unrun; multisite |
| wp-admin features use the curated checkbox grid | ora:settings-setup/admin-features-checkbox-grid.md | A, R | admin, editor, owner, other, guest | 1440 | n.v. | click-tests n.v.; belongs to admin-listings (listing editor metabox) |
| Page registry heals a deleted/trashed mapping | ora:settings-setup/page-registry-heals-stale-mapping.md | C(eval), F | admin, guest, owner | n/a | n/a | nothing-to-adopt, Free-only key n.v. |
| Legal rows: draft vs not set | ora:settings-setup/privacy-policy-url-draft-state.md | A R | admin | Y/Y | n | steps 2-5 not toggled |
