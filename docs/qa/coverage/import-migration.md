# Coverage: import-migration

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Competitor hours are mapped on import; also: Migrated hours mapped or preserved raw | ora:import-migration/competitor-hours-are-mapped-on-import.md | C(eval), A, F | admin, guest | both (n.v.) | n.v. | no licensed Directorist/ListingPro install; real migration and timezone not run |
| Background import: async, progress REST, resume, terminal FAILED | ora:import-migration/background-import-lifecycle.md | A, R, C | admin, other, guest | - (1440 admin) | - | scale (2000+ rows), failed-run UI not run |
| Demo accounts cannot log in | ora:import-migration/demo-accounts-not-loginable.md | CLI, F (wp-login), R | guest, admin | n/a | n/a | fresh-import step unrun |
| Demo import quality (packs, dates, features, quiet, accounts, events) | ora:import-migration/demo-import-quality.md | A, C, F, R, E | admin, other, guest | Y | light/dark | upgrade-path steps 11-13, event re-import, timezone not run |
| Settings and listings import/export round trip | ora:import-migration/import-export-roundtrip.md | A, R, C | admin, editor, other, guest | Y | light | boundary CSV, scale, cross-run duplicates; Free CSV card absent while Pro active (Visual Import replaces it) |
| Competitor migration imports once, structured, silent, idempotent (5 sources) | ora:import-migration/migrate-from-competitor-plugin.md | A, C, R | admin, editor, other, guest | P (1440; 390 in step 9) | light/dark step 9 | hivepress and bdp rows never run live; ListingPro schema doc missing (expected-fail); scale untested; Free Migration tab UI (admin-ajax) has no browser step |
| Migration fires the submit hook with migration context | ora:import-migration/migration-listener-context.md | C, E | admin, owner, guest | - | - | BuddyPress, plan and moderator listeners not run live |
| Competitor migration (Pro UX over Free pipeline), HivePress source, idempotent re-run | pro:import-migration/competitor-migration.md | admin, REST | admin, owner, editor, guest | 1440+390 | gap | no competitor plugin on reference site: real run unrun; per-option (categories/images/reviews) effect unproven |
| Google Places import | pro:import-migration/google-places-import.md | admin, REST | admin, owner, editor, guest | 1440+390 | light/dark | needs API key; quota; N=0 |
| Migration preview REST envelope (Pro) | pro:import-migration/migration-preview-rest-contract.md | REST | admin, editor, author, subscriber, guest | n/a | n/a | wrong-typed input |
| Visual CSV import wizard incl. header-skip default | pro:import-migration/visual-import-csv.md | admin, REST, Action Scheduler | admin, owner, editor, guest | 1440+390 | light/dark | retry/boundary step unrun; dark/RTL, JSON/GeoJSON wizard, update-by-title unrun |
