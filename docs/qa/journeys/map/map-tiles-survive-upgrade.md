---
journey: map-tiles-survive-upgrade
group: map
actor: system
mode: cli
plugin: wb-listora
priority: high
roles: [admin]
covers: [BC-10202831116, map-tiles, migration, Migrator-1.6.0]
prerequisites:
  - "WP-CLI (wp eval) on a site where you may flip settings and restore them"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
supersedes:
  - ora:regression/map-tiles-survive-upgrade.md
fails_on: unrecorded
site_profile: required
---

# Removing the OSM default tile fallback does not blank existing maps

## Why
1.6.0 removed the hard-coded OpenStreetMap public tile fallback. The upgrade makes the old behaviour explicit (`Migrator::migrate_1_6_0()` -> `preserve_implicit_tile_source()`, `includes/db/class-migrator.php:198`, body `:415-445`) instead of dropping it; fresh installs keep `map_tile_url` empty (`wb-listora.php:736`) and render no raster layer until the owner or the setup wizard picks one.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Save `wp option get wb_listora_settings --format=json` (the whole option) to restore it at the end. No visitor surface of its own; the visitor effect is step 5.

## Steps

1. **OSM provider, tile URL empty (simulated pre-1.6.0)**: set `map_provider=osm`, `map_tile_url=''`, run `\WBListora\DB\Migrator::migrate_1_6_0()`. **Expect**: `map_tile_url` = `https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png` and a non-empty `map_tile_attribution`; the map still renders. Fails if it stays empty.
2. **Owner already chose a server**: `map_tile_url=https://tiles.example.test/{z}/{x}/{y}.png`, run again. **Expect**: URL untouched, attribution untouched.
3. **Google provider, URL empty**: run again. **Expect**: untouched (`""`).
4. **Fresh install default**: `wb_listora_get_default_settings()['map_tile_url']` is `""`; with provider osm and an empty URL `wb_listora_get_map_tiles()` returns `url ''` and the map has no raster layer (`includes/class-template-helpers.php:1634-1658`).
5. **Visible to the owner and the app**: Settings > Maps shows "Map tile URL" and "Tile attribution" (`#wb_listora_map_tile_url`, `#wb_listora_map_tile_attribution`); a value saved there reaches the Directory map (`data-tile-url` on `#listora-detail-map`, `mapConfig.tileUrl` / `mapConfig.tileAttribution`) and `GET /listora/v1/settings/maps`.
6. **Idempotent** (added): run the migration twice; the second run changes nothing. Not verified live.

## Role ladder
| Actor | Expect |
|---|---|
| {admin} | steps 1-6 (Settings > Maps requires `manage_listora_settings`) |
| {guest} | sees only the resulting tiles (step 5 via the public map) |

## Pass criteria
1. An implicit OSM source becomes explicit with attribution.
2. An explicit or Google configuration is untouched.
3. Fresh installs render no raster layer until configured.

## Fail diagnostics
| Symptom | File |
|---|---|
| Map blank after upgrade | `includes/db/class-migrator.php:198`, `:415-445` |
| Tile fields missing in Settings | `includes/admin/class-map-settings-tab.php` |
| Tiles from nowhere on fresh install | `includes/class-template-helpers.php:1634-1658` |

## Not verified
Real upgrade from a pre-1.6.0 database; steps run via `wp eval` against the live option.

## Cleanup
Restore the whole `wb_listora_settings` option to the saved copy (compare it, do not restore three keys by hand).
