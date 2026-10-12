---
journey: import-export-roundtrip
plugin: wb-listora
priority: normal
roles: [administrator, editor, member-other, anonymous]
covers: [import-export-tab, csv-export, csv-import-roundtrip, settings-export-import, json-geojson-import-rest, background-import-progress]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} session; WP-CLI at $WP_PATH"
  - "At least 5 published listings of {type} with addresses (demo pack for {type})"
  - "Scratch-friendly: the import creates listings; delete them afterwards (title prefix QA Roundtrip)"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
supersedes: []
site_profile: required
---

# Export and re-import: settings as JSON, listings as CSV (JSON/GeoJSON by API)

## Why
An owner who exports and re-imports must get the same directory back, and the export must not leak private fields. Settings > Import / Export (`admin.php?page=listora-settings&tab=import-export`) has **Plugin Settings** ("Download JSON", "Upload & Import") and **Listings Data** ("Export CSV" with a type filter, "Import Listings": type select, CSV file, column-mapping step, "Import CSV", large files in the background with progress). JSON and GeoJSON listing imports exist only as REST routes (`/import/json`, `/import/geojson`); there is no JSON/GeoJSON listing export. WP-CLI: `wp listora export`, `wp listora import <file> --type=<type> [--dry-run]`. Competitor migrations: `admin/migrate-from-competitor-plugin.md`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).

## Steps

### 1. The tab
- **Action**: open the tab as {admin}.
- **Expect**: the two cards with their labels; ids `#listora-csv-export-type`, `#listora-csv-export-btn`, `#listora-csv-import-type`, `#listora-csv-import-file`, `#listora-csv-import-btn`, `#listora-import-file`.

### 2. Export one type as CSV
- **Action**: Listing type = {type}, "Export CSV".
- **Expect**: a download `listora-export-<YYYY-MM-DD-HHMMSS>.csv`; header begins `ID, Title, Description, Status, Author, Date, Type, Categories, Tags, Location, URL, Address, Phone, Email, Website,` then the type's own fields; one data row per published listing of the type (`SELECT COUNT(*)` of published listings of the type equals the rows; CLI twin `wp listora export --type=<type> --output=$SCRATCH/r.csv` gives the same rows). Positive control: filter "All types" yields more rows.
- **Privacy**: the `Author` column holds the author's DISPLAY name; there is no author email, plan, expiry, demo or consent column.

### 3. Round trip through the importer
- **Action**: edit the CSV: blank the `ID` values, prefix 3 titles `QA Roundtrip `; Import Listings: type {type}, the file, check the mapping (Title -> title, Address -> address, Phone -> phone ...), "Import CSV".
- **Expect**: progress bar "Importing listings in the background..." with "N listings imported"; done state; 3 new listings as drafts/pending per moderation settings; phone, address, categories and type fields equal the source rows.
- **Dry run**: `wp listora import $SCRATCH/r.csv --type=<type> --dry-run` reports the rows it would create and creates none. Polling contract: `admin/background-import-lifecycle.md`.

### 4. No duplicates, no data loss
- **Action**: import the same file a second time.
- **Expect**: record whether duplicates are created (the resume-safe hash map applies inside one run; the cross-run contract is the owner's to confirm); geo rows exist per new listing (`SELECT COUNT(*) FROM ${PREFIX}listora_geo WHERE listing_id IN (<new ids>)`) and the Directory map shows them after reindex.

### 5. Settings export/import
- **Action**: "Download JSON"; change Listings per page; "Upload & Import" the file.
- **Expect**: `#listora-import-status` reports success; the setting returns to the exported value; a malformed or other-version file shows an error and changes nothing.
- **Secrets**: check whether `captcha_secret_key` and `google_maps_key` are exported and flag it to the owner.

### 6. API-only formats
- **Action**: as {admin} `wp.apiFetch({path:'/listora/v1/import/geojson',method:'POST',data:{...}})` with a one-feature FeatureCollection (`geometry.type=Point`, `coordinates=[lng,lat]`, `properties.title`, `properties.type`); argument names from `GET /wp-json/listora/v1`.
- **Expect**: 200 with counts; the listing exists with that lat/lng in `listora_geo`. A body that is not a FeatureCollection returns 4xx, never a fatal.

### 7. Boundary input (added)
- **Action**: CSV with an empty file, header only, 10,000 rows, a 1 MB description, BOM, semicolon delimiter, quotes/emoji in cells, a row with a wrong column count.
- **Expect**: clean per-row errors, the rest imported, no timeout/fatal. Not verified live.

### 8. Scale (added)
- **Action**: export and import 2000+ listings.
- **Expect**: export streams (no memory exhaustion), import runs in chunks. Not verified live.

### 9. 390px
- **Expect**: the two cards stack; file inputs and buttons >= 40 px; the mapping table scrolls inside its container, not the page.

## Role ladder

| Rung | Action | Expect |
|---|---|---|
| {admin} | steps 1-9 | pass |
| {editor} | the tab URL; `GET /listora/v1/export/csv` | not allowed; 403 |
| {other} | `GET /listora/v1/export/csv` | 403 |
| {guest} | same | 401 |

## Pass criteria
1. CSV export has the documented columns and no private fields.
2. CSV import round-trips title, terms, phone, address and type fields, in the background with progress.
3. Settings JSON export/import round-trips and rejects bad files.
4. Import/export endpoints are admin-only.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Empty CSV | status filter or type slug mismatch | `CSV_Exporter` (default `publish`) |
| Mapping step blank | type not selected / `get_mappable_fields` | `includes/admin/class-settings-page.php` (`wbListoraSettings` mappable fields), `includes/admin/class-import-export-tabs.php` |
| Import never finishes | AS runner not draining | `Background_Import` |

## Not verified
Steps 4 (cross-run duplicates), 7, 8.

## Cleanup
Delete the `QA Roundtrip` listings.
