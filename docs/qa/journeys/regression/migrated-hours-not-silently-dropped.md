---
journey: migrated-hours-not-silently-dropped
plugin: wb-listora
priority: normal
roles: [admin]
covers: [migration-hours-shape, wb_listora_migrated_hours_unreadable, _listora_migrated_hours_raw, hours-mapper, BC-10184420962]
prerequisites:
  - "wb-listora active; WP-CLI"
  - "A source plugin with listings to migrate, OR the probe fixture docs/qa/fixtures/migrated-hours-probe.php"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
changed: true
---

# A migration that cannot read the source's hours says so (and 3 of 4 sources are now mapped)

Two layers (both in Free, `includes/import-export/`):
1. **Mapping** - `Hours_Mapper` (`class-hours-mapper.php`) converts Directorist (`from_day_keyed`, `class-directorist-migrator.php:372`), ListingPro (`from_day_keyed`, `class-listingpro-migrator.php:242`) and GeoDirectory (`from_geodirectory`, `class-geodirectory-migrator.php:522`) hours into the canonical `[{day,open,close}]` / `{day,closed}` list. BDP stores free text ("Mon-Fri 9-5") and routes it to the raw meta (`class-bdp-migrator.php:439-444`).
2. **Guard** - `Migration_Base::store_migrated_hours()` (`class-migration-base.php:470`) refuses any `business_hours` value `wb_listora_normalize_hours()` cannot read, stores it in `_listora_migrated_hours_raw`, counts it and reports it in `migrate_all()` `$stats` (`unreadable_hours` + message, `:337-349`) and fires `wb_listora_migrated_hours_unreadable`.

Verified via: `wp eval-file docs/qa/fixtures/migrated-hours-probe.php` (output below) and direct `Hours_Mapper` calls. A real competitor migration was not run (no source plugin on this site).

## Steps

### 1. Unreadable hours are withheld, preserved, counted and announced
- **Action**: `wp eval-file docs/qa/fixtures/migrated-hours-probe.php`.
- **Expect** (observed):
```
directorist string day keys  stored_normally=NO    raw_preserved=yes
geodirectory string list     stored_normally=NO    raw_preserved=yes
listingpro blob              stored_normally=NO    raw_preserved=yes
canonical list               stored_normally=yes   raw_preserved=no
day-keyed dict (form)        stored_normally=yes   raw_preserved=no
ranges (form, split shift)   stored_normally=yes   raw_preserved=no
dropped count: 3 (expect 3)
```
- A real run with an unreadable source also returns `$stats['unreadable_hours']` and a message "N listing(s) had business hours in a <Source> format Listora could not read..." (`:339-349`).
- **Fail**: `store_migrated_hours()` bypassed: the `business_hours` meta loop at `class-migration-base.php:433` must keep routing through it.

### 2. Readable hours are untouched (STOP on failure)
- **Expect**: the three readable shapes store normally, `_listora_migrated_hours_raw` is never written, count stays 0. A failure here loses data the old code kept.

### 3. The mapper produces readable hours (new in 2.0.0 line)
- **Action**: `wp eval 'echo json_encode( WBListora\ImportExport\Hours_Mapper::from_day_keyed( array( "monday" => array( "open" => "09:00", "close" => "17:00" ) ) ) );'`
- **Expect**: `[{"day":1,"open":"09:00","close":"17:00"}]`, and `wb_listora_normalize_hours()` of that is non-empty. `from_geodirectory( array( "hours" => array( "Mo" => array( array( "opens" => "09:00", "closes" => "17:00" ) ), "Su" => array( array( "opens" => "Closed", "closes" => "" ) ) ) ) )` returns Monday plus `{"day":0,"closed":true}`.

### 4. A site can convert its own format at import time
- **Action**: hook `wb_listora_migrated_hours_unreadable`.
- **Expect**: fires with `( $post_id, $value, $source_slug )` once per unreadable listing (`class-migration-base.php:496`).

### 5. After import, the listing page
- **Expect**: a mapped listing shows the schedule on its page and matches the "Open now" search; an unreadable one shows no hours block (and `_listora_migrated_hours_raw` holds the original).

## Role ladder
Admin-only operation (Listora > Settings > Migration, or `wp listora` / background import started from Pro). Logged-out and member roles have no entry point; a member calling the background-import REST routes gets 403 `listora_forbidden`, logged-out 401 (`includes/import-export/class-background-import.php:1008-1025`).

## Pass criteria
Unreadable: real field unset, raw preserved, counted, reported. Readable: stored normally, never diverted. Hook fires once per diverted listing. Without `wb_listora_normalize_hours()` the guard falls back to store-everything.

## State restored
Delete listings created by the migration test (the probe deletes its own draft).
