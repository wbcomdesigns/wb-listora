---
journey: competitor-hours-are-mapped-on-import
plugin: wb-listora
priority: high
roles: [admin, guest]
covers: [BC-10184420962, migration, business-hours, geodirectory, directorist, listingpro, Hours_Mapper, card-10184420962, wb_listora_migrated_hours_unreadable, _listora_migrated_hours_raw, migration-hours-shape]
prerequisites:
  - "Steps 1-4: `wp eval-file` only; the mapper is a static class, no source plugin needs to be installed"
  - "Step 5 end-to-end: GeoDirectory (verified path), Directorist Business Hours extension (PAID) and ListingPro theme (PREMIUM) when available"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/competitor-hours-are-mapped-on-import.md
  - ora:admin/migrated-hours-not-silently-dropped.md
fails_on: unrecorded
extends: admin/migrate-from-competitor-plugin.md
site_profile: required
---

# Competitor hours are mapped to the canonical shape on import, and hours Listora cannot read are withheld, preserved, counted and announced

## Why
Every competitor stores opening hours in its own shape, none of which `wb_listora_normalize_hours()` understands. Migrators once passed the source value straight to `_listora_business_hours`; the normaliser rejected it and the import produced zero rows in `${PREFIX}listora_hours` with no error. `WBListora\ImportExport\Hours_Mapper` (`includes/import-export/class-hours-mapper.php`) converts each family; unmappable payloads are kept verbatim as `_listora_migrated_hours_raw` and counted. The guard half is `Migration_Base::store_migrated_hours()`, which refuses any value `wb_listora_normalize_hours()` cannot read, keeps it in `_listora_migrated_hours_raw`, counts it in `migrate_all()` `$stats['unreadable_hours']` and fires `wb_listora_migrated_hours_unreadable`; this journey owns both halves (BDP stores free text and goes straight to the raw meta).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Verification status per source: GeoDirectory (free plugin) verified with the parsed `geodir_schema_to_array()` shape, the raw string needs GeoDirectory active; Directorist (PAID Business Hours extension), ListingPro (PREMIUM theme) not verified against a live install; Business Directory Plugin (owner-defined custom field, no fixed shape) n/a; HivePress (no hours in the free plugin) no. The `from_day_keyed()` path (Directorist `_bdbh`, ListingPro `business_hours`) is built from documented key names and is best-effort until run against a licensed install. Do not report the card as fully verified on the GeoDirectory result alone.
- Without GeoDirectory active (`function_exists('geodir_schema_to_array')` false) the mapper receives the already-parsed array form; with it active `from_geodirectory()` also accepts the raw stored string.

## Steps

### 1. GeoDirectory: parsed shape
```php
$parsed = array( 'hours' => array(
  'Mo' => array( array( 'opens' => '09:00', 'closes' => '17:00' ) ),
  'Tu' => array( array( 'opens' => '09:00', 'closes' => '12:00' ), array( 'opens' => '13:00', 'closes' => '17:00' ) ),
  'We' => array( array( 'opens' => '09:00', 'closes' => '17:00' ) ),
  'Su' => array( array( 'opens' => 'Closed', 'closes' => '' ) ),
) );
$mapped = \WBListora\ImportExport\Hours_Mapper::from_geodirectory( $parsed );
count( wb_listora_normalize_hours( $mapped ) );
```
- **Expect** 5 entries, in order: `day 1 09:00-17:00`, `day 2 09:00-12:00`, `day 2 13:00-17:00`, `day 3 09:00-17:00`, `day 0 closed`. The Tuesday split shift survives as two entries; Sunday is `closed: true`, not a range (GeoDirectory writes the literal word `Closed` in `opens`).
- **Before/after contrast**: passing the stored string `["Mo 09:00-17:00","Tu 09:00-12:00,13:00-17:00","We 09:00-17:00","Su Closed"],["UTC":"+0"]` straight to `wb_listora_normalize_hours()` accepts **0** entries. That is the regression. (Without GeoDirectory active, `from_geodirectory()` of that raw string also returns `[]`.)

### 2. Day-keyed family (Directorist, ListingPro; unverified against a licensed install)
```php
\WBListora\ImportExport\Hours_Mapper::from_day_keyed( array(
  'monday'    => array( array( 'start' => '09:00', 'close' => '17:00' ) ),
  'tuesday'   => array( array( 'start' => '09:00', 'close' => '12:00' ), array( 'start' => '13:00', 'close' => '17:00' ) ),
  'wednesday' => array( array( 'closed' => '1' ) ),
  'thursday'  => array( array( 'start' => '08:00 AM', 'close' => '06:30 PM' ) ),
  'friday'    => array( array( 'enable247hour' => '1' ) ),
) );
```
- **Expect** 6 entries: Monday 09:00-17:00, Tuesday two ranges, Wednesday `{"day":3,"closed":true}`, Thursday `08:00-18:30` (12-hour converted), Friday `{"day":5,"is_24h":true}`; `wb_listora_normalize_hours()` accepts all 6.

### 3. Nothing is silently lost
- **Action**: `Hours_Mapper::from_day_keyed( 'garbage' )` returns `[]`; run a migrator fixture whose hours cannot be mapped.
- **Expect**: the migrator stores the original in post meta `_listora_migrated_hours_raw` (`Migration_Base::store_migrated_hours()`), leaves `business_hours` unset, and increments the "unreadable hours" count in the summary.

### 4. Day-index safety
- **Expect**: `day_index()` accepts short names, full names and numeric keys, and treats a numeric key as already Listora's 0 = Sunday. Assert Monday maps to 1 and Sunday to 0 for names and numeric `"1"` / `"0"`. A source that numbers days differently needs its own mapper method: an off-by-one shifts every listing's hours by a day and nothing downstream can detect it.

### 5. Positive control (end to end)
- **Action**: import one listing with hours through the GeoDirectory migrator fixture (or CSV with a canonical `business_hours`) and open its page.
- **Expect**: the hours table shows Monday 09:00-17:00, Tuesday with two ranges, Sunday "Closed", and the Open now badge agrees with the Open now search (split hours and overnight spans, 2.0.0). **Read-back**: rows exist in `${PREFIX}listora_hours` for the listing (proves "hours present" is detectable, so step 1's "0 entries before" contrast is meaningful).

### 6. Timezone and overnight (added)
- **Action**: map a source entry whose close is earlier than open (overnight) and view the listing with the site timezone at UTC+14 then UTC-12 (restore UTC).
- **Expect**: the mapped entry keeps `close < open`, the listed day and open/close times are unchanged (wall-clock, not shifted), and the Open now badge follows the listing's effective zone. Not verified live.

### 7. Unreadable hours are withheld, preserved, counted and announced
- **Action**: `wp eval-file docs/qa/fixtures/migrated-hours-probe.php` (scratch site; the probe creates and deletes its own draft).
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
- **Positive control (STOP on failure)**: the three readable shapes store normally, `_listora_migrated_hours_raw` is never written, the count stays 0.

### 8. A site can convert its own format at import time
- **Action**: hook `wb_listora_migrated_hours_unreadable`.
- **Expect**: it fires with `( $post_id, $value, $source_slug )` once per unreadable listing.

### 9. The listing page for a mapped and an unreadable listing
- **Action**: open a mapped listing and an unreadable one as {guest} at 1440 and 390.
- **Expect**: the mapped listing shows its schedule and matches the "Open now" search filter; the unreadable one shows no hours block and `_listora_migrated_hours_raw` holds the original. Not verified live.

## Role ladder
| Actor | Expect |
|---|---|
| {admin} | runs the migration (Listora > Import / Migration; Pro starts competitor migrations, one background batch at a time) and WP-CLI steps |
| {guest} | step 5 result only: hours on the listing page and Open now |

## Pass criteria
1. Each competitor family maps to the canonical list; split shifts, closed and 24h days survive.
2. Unmappable payloads are preserved and counted, never dropped silently.
3. Day numbering is Sunday = 0 for every family.
- Hours the mapper cannot read are never dropped silently: kept in `_listora_migrated_hours_raw`, counted, and announced through the hook.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| 0 rows in `listora_hours` after import | migrator stopped calling the mapper | `includes/import-export/class-geodirectory-migrator.php:521-531`, `class-directorist-migrator.php:376`, `class-listingpro-migrator.php:246` |
| Split shift collapsed | range merge in mapper | `class-hours-mapper.php:67-123` |
| Sunday shown as a range | `Closed` literal not handled | `class-hours-mapper.php:100-108` |
| Hours shifted one day | numeric day convention | `class-hours-mapper.php:189` (`day_index`) |
| Unmappable hours lost silently | raw copy dropped | `class-migration-base.php:470-485` |

## Not verified
Steps 1, 2 and the `[]` for garbage were run live via `wp eval-file`. Step 3's migrator fixture, step 4's numeric keys, step 5 and added step 6 were not run. No competitor plugin is installed, so no migration ran end to end; closing the unverified rows needs a licensed Directorist Business Hours install and a ListingPro site with a split shift, a closed day and a 24-hour day (capture the real `_bdbh` / `_lp_listingpro_options['business_hours']` values as step 2 fixtures).

## Cleanup
Delete the imported fixture listing.
