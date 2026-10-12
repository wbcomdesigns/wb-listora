---
journey: map-tiles-survive-upgrade
plugin: wb-listora
priority: high
roles: [administrator]
covers:
  - BC 10202831116
likely_files:
  - includes/class-template-helpers.php
  - includes/db/class-migrator.php
  - includes/admin/class-map-settings-tab.php
prerequisites:
  - "WP-CLI (wp eval) on a site where you may flip settings and restore them"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
changed: true
---

# Removing the OSM default does not blank existing maps

1.6.0 removed the hard-coded OpenStreetMap public tile fallback. The upgrade makes the old behaviour explicit instead of dropping it: `Migrator::migrate_1_6_0()` calls `preserve_implicit_tile_source()` (`includes/db/class-migrator.php:198`, body `:415-445`). Fresh installs keep `map_tile_url` empty (`wb-listora.php:736`) and render no raster layer until the owner or the setup wizard picks one (`class-map-settings-tab.php` presets: OpenStreetMap, MapTiler, Stadia).

Verified via: `wp eval` calling `\WBListora\DB\Migrator::migrate_1_6_0()` against the live option with the original value restored afterwards. Admin-only, no visitor surface of its own, so no role ladder; the visitor effect is step 5.

## Steps

1. **OSM provider, tile URL empty** (simulated pre-1.6.0): set `map_provider=osm`, `map_tile_url=''`, run the migration.
   - **Expect**: `map_tile_url` = `https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png` and a non-empty `map_tile_attribution`; the map still renders. **Fail if** it stays empty.
2. **Owner already chose a server**: `map_tile_url=https://tiles.example.test/{z}/{x}/{y}.png`, run again.
   - **Expect**: URL untouched, attribution untouched (observed: attribution stays empty).
3. **Google provider, URL empty**: run again.
   - **Expect**: untouched (`""`).
4. **Fresh install default**: `wb_listora_get_default_settings()['map_tile_url']`.
   - **Expect**: `""`. With provider osm and an empty URL `wb_listora_get_map_tiles()` returns `url ''` and the map has no raster layer (`includes/class-template-helpers.php:1634-1658`).
5. **Visible to the owner and the app**: Settings > Maps shows "Map tile URL" and "Tile attribution" fields (`#wb_listora_map_tile_url`, `#wb_listora_map_tile_attribution`); a value saved there reaches the Directory map (`data-tile-url` on `#listora-detail-map`, `mapConfig.tileUrl` / `mapConfig.tileAttribution`) and `GET /listora/v1/settings/maps`.

## Cleanup
Restore `map_provider`, `map_tile_url`, `map_tile_attribution` to the original values (the script must compare the whole `wb_listora_settings` option to the saved copy).
