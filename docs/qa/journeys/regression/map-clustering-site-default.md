---
journey: map-clustering-site-default
plugin: wb-listora
priority: high
roles: [administrator, anonymous]
covers: [maps, settings, listing-map-block, card-10230658539]
prerequisites:
  - "The Directory page (/listings/) with a listing-map block carrying no showClustering attribute, and enough geocoded listings to cluster (demo: 79 mappable)"
  - "Two extra pages for steps 2-3: a listing-map block with {\"showClustering\":true} and one with {\"showClustering\":false} (create as QA-J1- pages, delete afterwards)"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Marker clustering setting reaches the website; a per-map choice still wins

Setting: Listora > Settings > Maps > Options > "Marker clustering" ("Group nearby markers into clusters"), key `map_clustering` in `wb_listora_settings` (`includes/admin/class-map-settings-tab.php:184`). Block attribute `showClustering` has no default in `blocks/listing-map/block.json:39`; `blocks/listing-map/render.php:21-39` uses the attribute when set, else the site setting. Escape hatch filter `wb_listora_map_block_clustering`. Inspector: "Marker Clustering" select (Use site setting / On for this map / Off for this map).

Verified via: Playwright on `/listings/` (steps 1 and 3 first half) with the option flipped by `wp eval` and restored. Steps 2, 3 second half and 4 not executed (need the explicit pages / block editor).

## Steps

### 1. Site off, block unset
- **Action**: Settings > Maps, untick Marker clustering, Save changes. Open `/listings/` logged out.
- **Expect**: `document.querySelectorAll('.marker-cluster').length === 0` and one `.leaflet-marker-icon` per mappable listing (79 observed).

### 1b. Positive control: site on, block unset
- **Action**: tick it again, save, reload.
- **Expect**: `.marker-cluster` count > 0 (3 observed on demo data at the default zoom).

### 2. Site off, block explicitly On
- **Action**: with the site setting off, open the page whose block has `{"showClustering":true}`.
- **Expect**: cluster badges appear.

### 3. Site on, block explicitly Off
- **Action**: site setting on; open the page with `{"showClustering":false}`.
- **Expect**: individual pins, no `.marker-cluster`; `/listings/` still clusters.

### 4. Editor
- **Action**: open the explicit page in the block editor, Block sidebar > Map Controls.
- **Expect**: select reads "Off for this map"; choose "Use site setting", save: the saved block comment no longer contains `showClustering`.

### 5. Role ladder
Anonymous and logged-in members get identical map markup (clustering is not role-dependent). Admin (control) changes the setting; a Listora moderator without `manage_listora_settings` must not see Settings > Maps.

### 6. 390px
- **Expect**: map renders, no horizontal page scroll (documentElement.scrollWidth <= innerWidth).

## Pass criteria
1. A map with no choice follows the site setting both ways. 2. A map with a choice keeps it against both site values.

## Fail diagnostics
`blocks/listing-map/render.php:21` (attribute precedence), `src/blocks/listing-map/view.js` (cluster group creation), `includes/rest/class-settings-controller.php` app-config `map_clustering` for the app half.
