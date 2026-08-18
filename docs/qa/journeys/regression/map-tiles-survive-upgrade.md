---
slug: map-tiles-survive-upgrade
priority: high
covers:
  - BC 10202831116
likely_files:
  - includes/class-template-helpers.php
  - includes/db/class-migrator.php
  - includes/admin/class-settings-page.php
---

# Removing the OSM default does not blank existing maps

1.6.0 removed the hardcoded OpenStreetMap public tile fallback — shipping a
product that silently leans on someone else's infrastructure, at volumes their
usage policy does not permit, is not a defensible default, and no owner could
see or change it.

Removing it alone would have taken every existing map blank on upgrade: a
silent break on a surface nobody re-checks after a plugin update. So the
upgrade makes the old behaviour EXPLICIT rather than dropping it.

## Steps

1. Simulate a pre-1.6.0 site: `map_provider` = osm, `map_tile_url` unset.
   Run the 1.6.0 migration.
   - **Expect:** `map_tile_url` now holds the OSM URL and the map still
     renders. The owner can now SEE what it uses, in Settings → Map.
   - **Fail if:** it stays empty — that is a silent regression for every
     existing install.
2. Simulate an owner who already chose a tile server. Run the migration.
   - **Expect:** their URL is untouched.
3. Simulate a Google-provider site. Run the migration.
   - **Expect:** untouched — those sites never rendered raster tiles from
     this setting.
4. FRESH install, no migration: leave the tile URL blank.
   - **Expect:** empty, and the map renders no raster layer. This is the
     point of the card — new installs must not ship pointed at OSM public.
5. Confirm Settings → Map exposes the tile URL and attribution fields, and
   that a value entered there reaches both the web map and `/settings/maps`.
