---
journey: admin-import-export
plugin: wb-listora
priority: normal
roles: [administrator]
covers: [import-export-tab, csv-roundtrip, json-roundtrip, geojson-roundtrip]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin user (?autologin=1)"
  - "At least 5 published listings with geo coords"
estimated_runtime_minutes: 5
---

# Admin exports + reimports listings (round-trip integrity)

Admin opens Import/Export tab, exports all listings as JSON, deletes a listing, reimports, verifies counts + meta + geo coords match. Round-trip is the contract: zero data loss.

## Setup

- Site: `$SITE_URL`
- Admin: `?autologin=1`
- Snapshot:
  ```bash
  wp post list --post_type=listora_listing --post_status=publish --format=count > /tmp/baseline_count.txt
  ```

## Steps

### 1. Open Import/Export
- **Action**: `playwright_navigate $SITE_URL/wp-admin/admin.php?page=listora-settings&tab=import&autologin=1`
- **Expect**: tab renders with Export + Import sections, format dropdown (CSV / JSON / GeoJSON)

### 2. Export JSON
- **Action**: format = JSON → click Export
- **Expect**: file download `wb-listora-export-YYYY-MM-DD.json` containing array of all listings

### 3. Verify export shape
- **Action**: open downloaded file
- **Expect**: each entry has `id`, `title`, `type`, `meta` object, `geo` { lat, lng }, `services` array, `hours` object

### 4. Delete one listing
- **Action**: capture a listing ID (`DEL_ID`), then trash it:
  ```bash
  wp post delete $DEL_ID --force
  ```

### 5. Reimport the JSON
- **Action**: in Import section → upload the exported JSON → click Import
- **Expect**: progress bar / processing message → success notice with import counts

### 6. Verify deleted listing restored
- **Action**:
  ```sql
  SELECT ID, post_title FROM wp_posts WHERE ID=$DEL_ID OR post_title=(<original title>);
  ```
- **Expect**: 1 row exists (either same ID restored OR new ID with matching content). `_listora_geo` meta carries correct lat/lng.

### 7. Verify count
- **Action**:
  ```bash
  wp post list --post_type=listora_listing --post_status=publish --format=count
  ```
- **Expect**: matches baseline count

### 8. Verify geo round-trip
- **Action**:
  ```sql
  SELECT lat, lng FROM wp_listora_geo WHERE listing_id=(SELECT ID FROM wp_posts WHERE post_title=<original> LIMIT 1);
  ```
- **Expect**: matches the exported lat/lng (within float precision)

### 9. Verify CSV format also round-trips
- **Action**: repeat steps 2-7 with CSV
- **Expect**: same outcome (CSV may be lossy on some nested fields — acceptable per documentation)

### 10. Verify GeoJSON format
- **Action**: export GeoJSON
- **Expect**: spec-compliant `FeatureCollection` with `geometry.type='Point'`, `coordinates=[lng,lat]`, `properties` carrying listing meta

## Pass criteria

1. Export produces valid JSON / CSV / GeoJSON
2. Reimport round-trips every field including geo
3. Counts match baseline after delete + reimport
4. CSV may have documented field omissions but core data round-trips
5. GeoJSON validates against the spec

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Export downloads empty file | export handler doesn't query | `class-import-export.php::export` |
| Reimport doesn't restore | unique-id collision OR missing field | `class-import-export.php::import_record` |
| Geo not preserved | geo table not exported | export must include `wp_listora_geo` rows |
| GeoJSON invalid | wrong key order (lng,lat not lat,lng) | spec compliance check |
