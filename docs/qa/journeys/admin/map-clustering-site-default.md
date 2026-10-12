---
journey: map-clustering-site-default
plugin: wb-listora
priority: high
roles: [admin, moderator, guest, member]
covers: [maps, settings, listing-map-block, card-10230658539]
prerequisites:
  - "The Directory page ($SITE_URL/listings/) with a listing-map block carrying no showClustering attribute, and enough geocoded listings to cluster (mappable count read from the page)"
  - "Two extra QA pages: a listing-map block with {\"showClustering\":true} and one with {\"showClustering\":false} (delete afterwards)"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes: []
site_profile: required
---

# The Marker clustering setting reaches the website; a per-map choice still wins

## Why
An owner who turns clustering off expects pins on every map that has not chosen otherwise; a map whose editor picked On or Off must keep its choice whatever the site setting says.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Setting: Listora > Settings > Maps > Options > "Marker clustering" ("Group nearby markers into clusters"), key `map_clustering` in `wb_listora_settings` (`includes/admin/class-map-settings-tab.php:184`). Block attribute `showClustering` has no default (`blocks/listing-map/block.json:39`); `blocks/listing-map/render.php:21-39` uses the attribute when set, else the site setting. Filter `wb_listora_map_block_clustering`. Inspector: "Marker Clustering" select (Use site setting / On for this map / Off for this map).
- Record `map_clustering` to restore. `$MAPPABLE` = markers on the Directory map.

## Steps

### 1. Site off, block unset
- **Action**: Settings > Maps, untick Marker clustering, Save changes. Open `/listings/` logged out.
- **Expect**: `document.querySelectorAll('.marker-cluster').length === 0` and one `.leaflet-marker-icon` per mappable listing (`$MAPPABLE`).

### 1b. Positive control: site on, block unset
- **Action**: tick it again, save, reload.
- **Expect**: `.marker-cluster` count > 0.

### 2. Site off, block explicitly On
- **Action**: with the site setting off, open the page whose block has `{"showClustering":true}`.
- **Expect**: cluster badges appear.

### 3. Site on, block explicitly Off
- **Action**: site setting on; open the page with `{"showClustering":false}`.
- **Expect**: individual pins, no `.marker-cluster`; `/listings/` still clusters.

### 4. Editor
- **Action**: open the explicit page in the block editor, Block sidebar > Map Controls.
- **Expect**: select reads "Off for this map"; choose "Use site setting", save: the saved block comment no longer contains `showClustering`.

### 5. REST / app half
- **Action**: `GET /listora/v1/settings/app-config`.
- **Expect**: carries `map_clustering` equal to the saved setting (`includes/rest/class-settings-controller.php`); flip the setting and the value follows (read-back).

### 6. Role ladder
- Guest and logged-in members get identical map markup (not role-dependent). `{admin}` (control) changes the setting; a Listora `{moderator}` without `manage_listora_settings` must not see Settings > Maps.

### 7. Scale and 390px
- With 2,000 geocoded QA listings the map still renders clustered with the marker cap notice only when capped (see `map-notice-only-when-capped`). At 390: map renders, `documentElement.scrollWidth <= innerWidth`. Not verified live.

## Role ladder
See step 6.

## Pass criteria
1. A map with no choice follows the site setting both ways.
2. A map with a choice keeps it against both site values.
3. The app config reflects the setting.

## Fail diagnostics
| Symptom | File |
|---|---|
| Attribute precedence wrong | `blocks/listing-map/render.php:21` |
| Cluster group creation | `src/blocks/listing-map/view.js` |
| App config missing the key | `includes/rest/class-settings-controller.php` |

## Not verified
Steps 2, 3 second half, 4, 5 and 7 were not executed (need the explicit pages / block editor); steps 1, 1b ran via Playwright on the Directory with the option flipped by `wp eval` and restored.

## Cleanup
Delete the QA pages; restore `map_clustering`.
