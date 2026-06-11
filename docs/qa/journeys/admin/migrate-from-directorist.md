---
journey: migrate-from-directorist
plugin: wb-listora
priority: high
roles: [administrator]
covers: [geodirectory-migrator, migration-base, term-helper, migration-context-arg, schema-mapping]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Directorist plugin available on disk (does not need to be active — DB-direct read)"
  - "At least 1 Directorist listing in at_biz_dir / wp_posts"
estimated_runtime_minutes: 10
covers_doc: migrate-from-directorist
---

# Migrate from Directorist → Listora

Functional sentinel for `docs/website/migrate-from-directorist.md`. Verifies the `Directorist_Migrator` correctly reads source data + maps fields per `audit/architecture/competitor-schemas/directorist.md`, and produces clean Listora listings.

## Setup

- Site: `$SITE_URL`
- Source: a WordPress install (live or imported DB dump) that has Directorist listings.
- Captured: `SOURCE_LISTING_COUNT` = total GD listings before migration.

## Steps

### 1. Schema audit exists
- **Action**: `[ -f wb-listora/audit/architecture/competitor-schemas/directorist.md ] && echo 'schema:doc-exists'`
- **Expect**: `schema:doc-exists`. The mapping rules are CODIFIED. No guesswork.

### 2. Migrator class loadable
- **Action**: `wp eval "echo class_exists('WBListora\ImportExport\Directorist_Migrator') ? '1' : '0';"`
- **Expect**: `1`.

### 3. Dry-run reports source row count
- **Action**: `wp listora migrate --from=directorist --dry-run --path=$WP`
- **Expect**: stdout reports `Would migrate N listings` where N matches `SOURCE_LISTING_COUNT`.

### 4. Run migration
- **Action**: `wp listora migrate --from=directorist --path=$WP`
- **Expect**:
  - Exit code 0.
  - `Migrated N listings` in stdout.
  - `wp post list --post_type=listora_listing --post_status=any --format=count --path=$WP` increases by ~N.

### 5. Sample listing fidelity check (random spot check)
- **Action**: pick a random newly-migrated listora_listing. Compare core fields against the source GD listing.
- **Expect**:
  - Title matches.
  - Address (meta `_listora_address`) is structured (city/state/country populated, not raw concat).
  - Lat/lng populated in `wp_listora_geo` table.
  - Listing type set (mapped per schema doc — per the schema doc's mapping table or per-category from GD).
  - Categories migrated as `listora_listing_cat` terms (look for the term, not the GD slug).

### 6. Migration context arg fires correctly (silence Free's downstream listeners)
- **Action**:
  ```
  wp eval "
  // Confirm the wb_listora_listing_submitted action fires with context.source='migration'
  // (Free's Notifications + Pro's BP integration both gate on this to suppress notifications during bulk imports)
  global \$wpdb;
  echo \$wpdb->get_var(\"SELECT COUNT(*) FROM {\$wpdb->prefix}options WHERE option_name = 'wb_listora_migration_notifications_sent'\");
  "
  ```
- **Expect**: `0`. Migration MUST NOT trigger per-listing emails (would spam owners).

### 7. _listora_migrated_from meta populated
- **Action**:
  ```
  wp eval "global \$wpdb; echo \$wpdb->get_var(\"SELECT COUNT(*) FROM {\$wpdb->prefix}postmeta WHERE meta_key = '_listora_migrated_from' AND meta_value LIKE '%geodirectory%'\");"
  ```
- **Expect**: matches the migrated count. Every migrated listing carries the trail back to its origin.

### 8. Idempotency — re-running migration is safe
- **Action**: run the migrate command a SECOND time.
- **Expect**: stdout reports `Skipped N (already migrated)`. No duplicate listora_listing rows created. The Migrated_From_Tracker correctly identifies previously-migrated source IDs.

### 9. Frontend renders the migrated listings
- **Action**: visit `$SITE_URL/listings/` anonymously.
- **Expect**: directory grid shows the migrated listings with correct titles, type badges, addresses.

## Notes

- Schema mapping decisions are documented at `audit/architecture/competitor-schemas/directorist.md`. Changes to Directorist's schema (their next major release) require updating that doc + this journey's expected fields.
- Migration runs INSIDE WordPress, in batches via the activator's `Migration_Base` chunk pattern. Long migrations chain via Action Scheduler (each chunk schedules the next via `as_schedule_single_action`).
- Pro's Visual Importer wraps Free's migrators with a UI — the data pipeline is identical. The visual-importer journey at `wb-listora-pro/docs/qa/journeys/regression/csv-import-skip-header-default.md` covers the UI side.
