---
journey: migrate-from-competitor-plugin
plugin: wb-listora
priority: high
roles: [administrator, editor, member-other, anonymous]
covers: [directorist-migrator, listingpro-migrator, geodirectory-migrator, hivepress-migrator, bdp-migrator, migration-base, term-helper, migrated-from-tracker, schema-mapping, cli-migrate, migration-context-arg]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI at $WP_PATH"
  - "The source plugin's data present in the database (the plugin need not be active: the migrators read the DB directly) with at least 1 source listing"
  - "Run once per source in the Sources table; skip a source whose data is absent and record it as not run"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default; buddyx-pro light for the directory check]
supersedes:
  - ora:admin/migrate-from-directorist.md
  - ora:admin/migrate-from-listingpro.md
  - ora:admin/migrate-from-geodirectory.md
site_profile: required
---

# A competitor-plugin migration imports every listing once, silently, with structured data

## Why
An owner moving from another directory plugin loses listings, addresses or categories, or floods members with approval mail, if a migrator maps fields wrongly or fires the normal submit side-effects. The five migrators share one pipeline, so this is one journey run per source.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Sources table (the class is resolved with `wb_listora_get_migrators()`, never hard-coded):

| Source key | Source data | Schema doc under `audit/architecture/competitor-schemas/` | Tracker prefix |
|---|---|---|---|
| `directorist` | Directorist listings (`at_biz_dir` posts) | `directorist.md` | `directorist:` |
| `listingpro` | ListingPro `listing` posts | `listingpro.md` (missing today, see step 1) | `listingpro:` |
| `geodirectory` | GeoDirectory `gd_place_detail` rows | `geodirectory.md` | `geodirectory:` |
| `hivepress` | HivePress listings | `hivepress.md` | `hivepress:` |
| `bdp` | Business Directory Plugin listings | `wpbdp.md` | `bdp:` |

- Capture `SOURCE_COUNT` (source listings), `LISTORA_BEFORE` (`wp post list --post_type=listora_listing --post_status=any --format=count`) and `MAIL_BASE` (`SELECT COALESCE(MAX(id),0) FROM {prefix}listora_email_log`).
- Run on a scratch site: the migration writes listings.

## Steps

### 1. The mapping rules are written down
- **Action**: `[ -f audit/architecture/competitor-schemas/<doc> ] && echo schema:doc-exists` for the source.
- **Expect**: `schema:doc-exists`. `expected-fail: ListingPro has no schema doc` (migrator exists and loads in step 2; record a documentation gap, do not fail the journey for it).

### 2. The migrator is registered and loadable
- **Action**: `wp eval 'var_dump( array_keys( wb_listora_get_migrators() ) ); echo class_exists( get_class( wb_listora_get_migrator( "<source key>" ) ) ) ? 1 : 0;'`
- **Expect**: the source key is listed and the class loads (`1`).

### 3. Dry run reports the source count and writes nothing
- **Action**: `wp listora migrate --from=<source key> --dry-run --path=$WP_PATH`
- **Expect**: `Dry run -- no data will be written.`, a "Migration Results" block (`Imported: N`, `Skipped: 0`, `Errors: 0`, `Total: N`) and `Dry run complete. N listings would be imported.` with N = `SOURCE_COUNT`.
- **Read-back**: listing count equals `LISTORA_BEFORE`; email log max id equals `MAIL_BASE`.
- **Negative control**: with the source data absent the command exits non-zero with `<Source> data not found on this site.` (or warns `No listings found to migrate.`), so a green dry run is real data.

### 4. Run the migration
- **Action**: `wp listora migrate --from=<source key> --path=$WP_PATH`
- **Expect**: exit 0; results block with `Errors: 0` and a final `Success:` line; the listing count rises by `Imported`.
- **Read-back**: `Imported + Skipped = SOURCE_COUNT`.

### 5. Sample fidelity (3 random migrated listings)
- **Action**: compare each with its source listing.
- **Expect**: title equal; `_listora_address` is structured (city, state, country populated, not one concatenated string); a `{prefix}listora_geo` row with lat/lng; listing type mapped per the schema doc; categories exist as `listora_listing_cat` terms (the term, not the source slug).
- **Expect**: business hours are mapped or preserved raw, see `admin/migrated-hours-not-silently-dropped.md`.

### 6. The migration is quiet
- **Action**: `SELECT event_key, COUNT(*) FROM {prefix}listora_email_log WHERE id > MAIL_BASE GROUP BY event_key`.
- **Expect**: no `listing_submitted`, `listing_pending_admin` or `listing_approved` rows for the migrated listings and no credit-ledger rows (the hook carries `context.source = migration`; listener-level contract in `system/migration-listener-context.md`).

### 7. Provenance is recorded
- **Action**: `SELECT COUNT(*) FROM {prefix}postmeta WHERE meta_key='_listora_migrated_from' AND meta_value LIKE '<tracker prefix>%'`.
- **Expect**: equals `Imported`; the value is `<source>:<source id>` and is written only by `Migrated_From_Tracker::set`.

### 8. Re-running is idempotent
- **Action**: run step 4 a second time.
- **Expect**: `Imported: 0`, `Skipped: SOURCE_COUNT`; no new `listora_listing` rows.

### 9. The directory shows them
- **Action**: open `$SITE_URL/listings/` as {guest} at 1440 and 390, light and dark.
- **Expect**: published migrated listings render with title, type badge and address; no horizontal scroll at 390.

### 10. Background path from Pro (combo only)
- **Action**: start the same source from Pro's Visual Importer.
- **Expect**: one batch at a time through Action Scheduler (`wb_listora_queue_migration_import()`); progress via `GET /listora/v1/import/progress/<run_id>` (contract in `admin/background-import-lifecycle.md`); the data written is identical to step 4. Not verified live.

### 11. Scale (added)
- **Action**: migrate a source with 2000+ listings.
- **Expect**: the run chains in chunks (never one request), memory stays bounded, `Total` equals the source count. Not verified live.

## Role ladder

| Rung | Action | Expect |
|---|---|---|
| {admin} | steps 1-11 | pass |
| {editor} | Settings > Migration screen, `POST` of the migration REST routes | refused (needs `manage_listora_settings`): 403 |
| {other} | a migrated listing's public page | renders like any listing; no `_listora_migrated_from` in the public REST `listora_meta` |
| {guest} | `/listings/` | migrated published listings visible; background-import REST routes answer 401 |

## Pass criteria
1. Dry run writes nothing and reports N; the real run imports N with 0 errors.
2. Fields are structured and fidelity holds on the sample.
3. No notification mail or credits movement from a migration.
4. Provenance meta is set and a second run imports 0.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Source count differs from `Total` | source query filter | `includes/import-export/class-<source>-migrator.php` |
| Address is one string | address mapper | `includes/import-export/class-migration-base.php` |
| Duplicates on rerun | tracker lookup | `Migrated_From_Tracker` |
| Approval mail sent | context arg dropped | `class-<source>-migrator.php` (`wb_listora_listing_submitted` fire) |

## Not verified
Steps 10 and 11, and the hivepress and bdp rows (no journey existed for them before; rows added from the registry).

## Cleanup
Delete the migrated listings (`_listora_migrated_from` set) and their email-log rows with `id > MAIL_BASE`.
