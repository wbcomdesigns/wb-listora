---
journey: migrated-hours-not-silently-dropped
plugin: wb-listora
priority: normal
roles: [administrator, editor, anonymous]
covers: [migration-hours-shape, wb_listora_migrated_hours_unreadable, _listora_migrated_hours_raw, hours-mapper, BC-10184420962]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI at $WP_PATH"
  - "A source plugin with listings to migrate, OR the probe fixture docs/qa/fixtures/migrated-hours-probe.php"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light]
supersedes:
  - ora:regression/migrated-hours-not-silently-dropped.md
fails_on: unknown (card BC-10184420962, pre-fix commit not recorded)
extends: admin/migrate-from-competitor-plugin.md
site_profile: required
---

# A migration that cannot read the source's hours says so, and the readable shapes are mapped

## Why
Owners lose opening hours silently when a source stores them in a shape Listora cannot read. Two layers in `includes/import-export/`: `Hours_Mapper` converts Directorist, ListingPro and GeoDirectory hours into the canonical `[{day,open,close}]` / `{day,closed}` list (BDP stores free text and goes to the raw meta); `Migration_Base::store_migrated_hours()` refuses any value `wb_listora_normalize_hours()` cannot read, keeps it in `_listora_migrated_hours_raw`, counts it in `migrate_all()` `$stats['unreadable_hours']` and fires `wb_listora_migrated_hours_unreadable`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Scratch site (the probe creates and deletes its own draft).

## Steps

### 1. Unreadable hours are withheld, preserved, counted and announced
- **Action**: `wp eval-file docs/qa/fixtures/migrated-hours-probe.php`.
- **Expect**:
```
directorist string day keys  stored_normally=NO    raw_preserved=yes
geodirectory string list     stored_normally=NO    raw_preserved=yes
listingpro blob              stored_normally=NO    raw_preserved=yes
canonical list               stored_normally=yes   raw_preserved=no
day-keyed dict (form)        stored_normally=yes   raw_preserved=no
ranges (form, split shift)   stored_normally=yes   raw_preserved=no
dropped count: 3 (expect 3)
```
- **Expect** in a real run: `$stats['unreadable_hours']` and the message "N listing(s) had business hours in a <Source> format Listora could not read...".

### 2. Readable hours are untouched (STOP on failure)
- **Expect**: the three readable shapes store normally, `_listora_migrated_hours_raw` is never written, the count stays 0.

### 3. The mapper produces canonical hours
- **Action**: `wp eval 'echo json_encode( WBListora\ImportExport\Hours_Mapper::from_day_keyed( array( "monday" => array( "open" => "09:00", "close" => "17:00" ) ) ) );'`
- **Expect**: `[{"day":1,"open":"09:00","close":"17:00"}]`, and `wb_listora_normalize_hours()` of it is non-empty. `from_geodirectory( array( "hours" => array( "Mo" => array( array( "opens" => "09:00", "closes" => "17:00" ) ), "Su" => array( array( "opens" => "Closed", "closes" => "" ) ) ) ) )` returns Monday plus `{"day":0,"closed":true}`.

### 4. A site can convert its own format at import time
- **Action**: hook `wb_listora_migrated_hours_unreadable`.
- **Expect**: fires with `( $post_id, $value, $source_slug )` once per unreadable listing.

### 5. The listing page
- **Action**: open a mapped listing and an unreadable one as {guest} at 1440 and 390.
- **Expect**: the mapped listing shows its schedule and matches the "Open now" search filter; the unreadable one shows no hours block and `_listora_migrated_hours_raw` holds the original. Not verified live.

### 6. Timezone (added)
- **Action**: set site timezone to UTC+14, then UTC-12, reload the mapped listing, then restore UTC.
- **Expect**: the listed day and open/close times are unchanged (hours are wall-clock, not shifted); the "Open now" badge follows site time. Not verified live.

## Role ladder

| Rung | Action | Expect |
|---|---|---|
| {admin} | steps 1-6 (Settings > Migration, WP-CLI, background import from Pro) | pass |
| {editor} | background-import REST routes | 403 `listora_forbidden` |
| {guest} | same routes | 401 |
| {guest} | step 5 public pages | same output as {admin} |

## Pass criteria
Unreadable: the real field is unset, raw preserved, counted, reported. Readable: stored normally, never diverted. The hook fires once per diverted listing. Without `wb_listora_normalize_hours()` the guard falls back to store-everything.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Unreadable value stored as hours | guard bypassed | `class-migration-base.php` (`business_hours` meta loop must route through `store_migrated_hours()`) |
| Readable hours diverted | normalizer too strict | `wb_listora_normalize_hours()` |
| Mapper returns empty | source shape changed | `class-hours-mapper.php` |

## Not verified
A real competitor migration (no source plugin on the reference site), steps 5 and 6.

## Cleanup
Delete listings created by a migration test; the probe removes its own draft.
