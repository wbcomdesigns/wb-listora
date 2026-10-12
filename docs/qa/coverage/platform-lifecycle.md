# Coverage: platform-lifecycle

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Recurring Free cron hooks never hold duplicate pending actions; sweep only on scheduling requests | ora:platform-lifecycle/cron-recurring-dedupe.md | CLI, REST, admin | admin, guest, system | - | - | activation race itself not reproducible |
| Cron jobs scheduled once, no AS notices, maintenance side-effects | ora:platform-lifecycle/cron-schedule-and-maintenance.md | C, R, F | system, admin, guest | - | - | steps 4-7, 9 not run on shared site |
| Health Check, Site Health, Rebuild Search Index | ora:platform-lifecycle/health-check-and-reindex.md | A, C | admin, editor, other, guest | Y | light | destructive card steps, table-coverage gap (expected-fail) |
| Install and activation | ora:platform-lifecycle/installation-and-activation.md | C, A, R | admin, editor, guest | 1440 | light | upgrade-from-previous step |
| Term/email helper consolidation (Free canonical, Pro via functions) | ora:platform-lifecycle/term-helper-consolidation.md | CLI, email | admin | n | n | Mailpit AltBody not run |
| Translations render, not just compile | ora:platform-lifecycle/translations-render-not-just-compile.md | F, A | guest, owner, admin | Y | light/dark | JS bundle path, RTL |
| Uninstall removes everything, both orders, no collateral; also: Uninstall removes every status | ora:platform-lifecycle/uninstall-deletes-everything-both-orders.md | C, A | admin, editor | - | - | multisite, look-alike rows, restore-after-uninstall; destructive, never run on shared site |
| Health Check reports the search index as usable | ora:platform-lifecycle/health-check-search-index-usable.md | A | admin, editor, guest | 1440 | n.v. | destructive steps n.v. |
| Activating/deactivating Pro leaves no blank page, offers pages once | ora:platform-lifecycle/pro-upgrade-and-downgrade.md | A, F | admin, editor, guest | 1440/390 | light/dark | steps 1, 2, 5, 8 not run |
| Trigger labels translate (no early __()) | ora:platform-lifecycle/trigger-labels-translate.md | C, A | guest, admin | 1440 | n/a | steps 3-5 not run |
| Legacy hook names no longer fire (Pro) | pro:platform-lifecycle/legacy-hook-names-no-longer-fire.md | hooks | admin | n/a | n/a | exhaustive twin grep |
| Pro recurring jobs on Action Scheduler, lazy arming, need expiry, webhook log trim, licence check | pro:platform-lifecycle/pro-cron-coverage.md | CLI, admin, REST | system, admin, owner, editor, guest | - | - | digest ON case; timezone matrix; step 6 probe unrun |
| Pro uninstall in both orders keeps data unless opted in | pro:platform-lifecycle/pro-uninstall-both-orders.md | CLI | admin, system, editor | - | - | throwaway site only; F, G, multisite unrun |
| Health Check cards Essential pages, Permalinks, PHP version, Memory limit show warn or fail with a working fix link | ora:platform-lifecycle/health-check-and-reindex.md | A | admin | 1440/390 | n.v. | no step forces these four states (see _group.md Not covered) |
| Upgrade migration runs once and keeps data (older `wb_listora_db_version`) | ora:platform-lifecycle/installation-and-activation.md | CLI, A | admin | - | - | step 10 not verified live |
