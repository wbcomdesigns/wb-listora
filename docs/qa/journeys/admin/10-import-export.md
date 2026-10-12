---
journey: admin-import-export
plugin: wb-listora
priority: normal
roles: [administrator, qa_editor, member-other, anonymous]
covers: [import-export-tab, csv-export, csv-import-roundtrip, settings-export-import, json-geojson-import-rest, background-import-progress]
prerequisites:
  - "Site reachable at $SITE_URL; admin ?autologin=1"
  - "At least 5 published Restaurant listings with addresses (demo pack restaurant)"
  - "Scratch-friendly: the import step creates listings; delete them afterwards (title prefix `J20 Roundtrip`)"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
---

# Export and re-import: settings as JSON, listings as CSV (JSON/GeoJSON by API)

Settings > Import / Export (`admin.php?page=listora-settings&tab=import-export`) has two cards. **Plugin Settings**: "Download JSON" (Export Settings) and "Upload & Import" (replace current settings; only files from this plugin version). **Listings Data**: "Export CSV" with a Listing type filter ("All types"), and "Import Listings" (Listing type select "Select a type...", CSV file, a column-mapping step, "Import CSV", large files run in the background with a progress bar). JSON and GeoJSON listing imports exist as REST routes (`/import/json`, `/import/geojson`), not as screen controls; there is no JSON/GeoJSON listing export (CSV is the export format). WP-CLI: `wp listora export` and `wp listora import <file> --type=<type> [--dry-run]`.

## Steps

### 1. The tab (administrator, 1440)
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=import-export&autologin=1`.
- **Expect**: the two cards above with the listed labels; element ids `#listora-csv-export-type`, `#listora-csv-export-btn`, `#listora-csv-import-type`, `#listora-csv-import-file`, `#listora-csv-import-btn`, `#listora-import-file`.
- **On fail**: `Import_Export_Tabs` (`includes/admin/class-import-export-tabs.php`).

### 2. Export Restaurants as CSV
- **Action**: Listing type = Restaurant, "Export CSV".
- **Expect**: a download named `listora-export-<YYYY-MM-DD-HHMMSS>.csv`; header row begins `ID, Title, Description, Status, Author, Date, Type, Categories, Tags, Location, URL, Address, Phone, Email, Website,` then the type's own fields (Cuisine, Price Range, Business Hours, Reservations, Delivery Available, Takeout Available, Menu URL, Photo Gallery, Social Links, Video URL); one data row per published Restaurant (CLI twin: `wp listora export --type=restaurant --output=/tmp/r.csv` gives the same rows).
- **Expect**: the `Author` column holds the author's DISPLAY name (for a member with no chosen display name that equals their login; record it for the owner, it is an export for the site owner, not a public surface); there is no author email, plan, expiry, demo or consent column.

### 3. Round trip through the importer
- **Action**: edit the CSV: remove the `ID` column values, prefix 3 titles with `J20 Roundtrip `, keep the rest; Import Listings: type Restaurant, choose the file, check the mapping step (Title -> title, Address -> address, Phone -> phone ...), "Import CSV".
- **Expect**: a progress bar "Importing listings in the background..." with "N listings imported"; done state; the 3 new listings exist as drafts/pending per moderation settings; their phone, address, categories and cuisine equal the source rows.
- **Dry run**: `wp listora import /tmp/r.csv --type=restaurant --dry-run` reports the rows it would create and creates none.
- **On fail**: `CSV_Importer`, `Background_Import::queue_file` (`includes/import-export/`), `GET /listora/v1/import/progress/<run_id>` (`admin/demo-import-background-progress.md` has the polling contract).

### 4. No duplicates, no data loss
- **Expect**: importing the same file a second time: record whether it creates duplicates (the resume-safe hash map applies inside one run; cross-run behaviour is the owner's contract to confirm); geo rows exist for each new listing (`SELECT COUNT(*) FROM wp_listora_geo WHERE listing_id IN (<new ids>)`) and the Directory map shows them after reindex.
- **Cleanup**: delete the `J20 Roundtrip` listings.

### 5. Settings export/import
- **Action**: "Download JSON"; change Listings per page; "Upload & Import" the downloaded file.
- **Expect**: status line on `#listora-import-status` reports success; the setting returns to the exported value; importing a malformed or other-version file shows an error and changes nothing.
- **Expect**: the export has no secrets that should not leave the site (check for `captcha_secret_key`, `google_maps_key`: they are settings values; record whether they are exported and flag it to the owner).

### 6. API-only formats
- **Action**: as admin `wp.apiFetch({path:'/listora/v1/import/geojson',method:'POST',data:{...}})` with a one-feature FeatureCollection (`geometry.type = Point`, `coordinates = [lng, lat]`, `properties.title`, `properties.type`).
- **Expect**: 200 with counts; the listing exists with that lat/lng in `wp_listora_geo`. Send a body that is not a FeatureCollection: an error response (4xx), never a fatal or a white page. Record the route's exact argument names from `GET /wp-json/listora/v1` (route `/import/geojson`).

### 7. Role ladder
| Rung | Action | Expect |
|---|---|---|
| administrator | steps 1-6 | pass |
| qa_editor | the tab URL | not allowed; `GET /listora/v1/export/csv` -> 403 |
| member-other | `GET /listora/v1/export/csv` | 403 |
| anonymous | same | 401 |

### 8. 390px
- **Expect**: the two cards stack; the file inputs and buttons are >= 40px; the mapping table scrolls inside its container, not the page.

## Pass criteria

1. CSV export has the documented columns and no private fields.
2. CSV import round-trips title, terms, phone, address and type fields, in the background with progress.
3. Settings JSON export/import round-trips and rejects bad files.
4. Import/export endpoints are admin-only.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Empty CSV | status filter or type slug mismatch | `CSV_Exporter` (default `publish`) |
| Mapping step blank | type not selected / `get_mappable_fields` | `class-settings-page.php` (`wbListoraSettings` mappable fields) |
| Import never finishes | AS runner not draining | `Background_Import`, `admin/demo-import-background-progress.md` |
