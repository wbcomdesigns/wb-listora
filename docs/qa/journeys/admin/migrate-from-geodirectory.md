---
journey: migrate-from-geodirectory
plugin: wb-listora
priority: high
roles: [administrator, qa_editor, member-other, anonymous]
covers: [geodirectory-migrator, migration-base, term-helper, migration-context-arg, migrated-from-tracker, schema-mapping, cli-migrate]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "GeoDirectory plugin available on disk (does not need to be active — DB-direct read)"
  - "At least 1 GeoDirectory listing in gd_place_detail / wp_posts"
estimated_runtime_minutes: 10
covers_doc: migrate-from-geodirectory
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default; buddyx-pro light for the directory check]
---

# Migrate from GeoDirectory → Listora

Functional sentinel for `docs/website/migrate-from-geodirectory.md`. Verifies the `Geodirectory_Migrator` correctly reads source data + maps fields per `audit/architecture/competitor-schemas/geodirectory.md`, and produces clean Listora listings.

## Setup

- Site: `$SITE_URL`
- Source: a WordPress install (live or imported DB dump) that has GeoDirectory listings.
- Captured: `SOURCE_LISTING_COUNT` = total GeoDirectory listings before migration.

## Steps

### 1. Schema audit exists
- **Action**: `[ -f wb-listora/audit/architecture/competitor-schemas/geodirectory.md ] && echo 'schema:doc-exists'`
- **Expect**: `schema:doc-exists`. The mapping rules are CODIFIED. No guesswork.

### 2. Migrator class loadable
- **Action**: `wp eval "echo class_exists('WBListora\ImportExport\Geodirectory_Migrator') ? '1' : '0';"`
- **Expect**: `1`.

### 3. Dry run reports the source row count and writes nothing
- **Action**: `wp listora migrate --from=geodirectory --dry-run --path=$WP`
- **Expect**: `Dry run -- no data will be written.` then a "Migration Results" block (`Imported: N`, `Skipped: 0`, `Errors: 0`, `Total: N`) and `Dry run complete. N listings would be imported.` with N = `SOURCE_LISTING_COUNT`; the listing count and the email log are unchanged afterwards.
- **Negative control**: on a site where the source plugin's data is absent the command exits non-zero with `GeoDirectory data not found on this site.` (or warns `No listings found to migrate.`), so a green dry run above is real data and not an empty source.

### 4. Run migration
- **Action**: `wp listora migrate --from=geodirectory --path=$WP`
- **Expect**:
  - Exit code 0.
  - The results block `Imported: N`, `Skipped`, `Errors: 0`, `Total` and a final `Success:` line.
  - `wp post list --post_type=listora_listing --post_status=any --format=count --path=$WP` increases by ~N.

### 5. Sample listing fidelity check (random spot check)
- **Action**: pick a random newly-migrated listora_listing. Compare core fields against the source GeoDirectory listing.
- **Expect**:
  - Title matches.
  - Address (meta `_listora_address`) is structured (city/state/country populated, not raw concat).
  - Lat/lng populated in `wp_listora_geo` table.
  - Listing type set (mapped per schema doc — likely "place" or per-category from GD).
  - Categories migrated as `listora_listing_cat` terms (look for the term, not the GD slug).

### 6. Migration is silent (context `migration` suppresses per-listing mail and credits)
- **Action**: record `MAIL_BASE = SELECT COALESCE(MAX(id),0) FROM wp_listora_email_log` before step 4; after it run `SELECT event_key, COUNT(*) FROM wp_listora_email_log WHERE id > MAIL_BASE GROUP BY event_key`.
- **Expect**: no `listing_submitted`, `listing_pending_admin` or `listing_approved` rows for the migrated listings; no credit ledger rows (`wb_listora_listing_submitted` was fired with a 4th argument `context['source'] = 'migration'`; see `regression/migrator-context-arg.md`).

### 7. _listora_migrated_from meta populated
- **Action**:
  ```
  wp eval "global \$wpdb; echo \$wpdb->get_var(\"SELECT COUNT(*) FROM {\$wpdb->prefix}postmeta WHERE meta_key = '_listora_migrated_from' AND meta_value LIKE 'geodirectory:%'\");"
  ```
- **Expect**: matches the migrated count. The value is `<source>:<source id>` (written only by `Migrated_From_Tracker::set`).

### 8. Idempotency — re-running migration is safe
- **Action**: run the migrate command a SECOND time.
- **Expect**: the results block shows `Imported: 0` and `Skipped: N` (already migrated). No duplicate listora_listing rows created. The Migrated_From_Tracker correctly identifies previously-migrated source IDs.

### 9. Frontend renders the migrated listings
- **Action**: visit `$SITE_URL/listings/` anonymously.
- **Expect**: directory grid shows the migrated listings with correct titles, type badges, addresses.

## Role ladder

| Rung | Action | Expect |
|---|---|---|
| administrator (WP-CLI as admin / `manage_options`) | steps 1-9 | pass |
| qa_editor | Settings > Migration screen / `POST` migration REST | not allowed (admin only) |
| member-other | the migrated listing's page | public pages show the listings like any others; no `_listora_migrated_from` in public REST `listora_meta` |
| anonymous | `/listings/` | migrated listings visible when published |

## Notes

- Schema mapping decisions are documented at `audit/architecture/competitor-schemas/geodirectory.md`. Changes to GeoDirectory's schema (their next major release) require updating that doc + this journey's expected fields.
- Migration runs INSIDE WordPress, in batches via the activator's `Migration_Base` chunk pattern. Long migrations chain via Action Scheduler (each chunk schedules the next via `as_schedule_single_action`).
- Since 2.0.0 competitor migrations started from WB Listora Pro run again, in the background one batch at a time (`wb_listora_queue_migration_import()` -> Action Scheduler; poll `GET /listora/v1/import/progress/<run_id>`, contract in `admin/demo-import-background-progress.md`). Pro's Visual Importer wraps Free's migrators with a UI — the data pipeline is identical. The visual-importer journey at `wb-listora-pro/docs/qa/journeys/regression/csv-import-skip-header-default.md` covers the UI side.
