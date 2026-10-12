---
journey: competitor-hours-are-mapped-on-import
plugin: wb-listora
priority: high
roles: [admin]
covers: [BC-10184420962, migration, business-hours, geodirectory, directorist, listingpro]
prerequisites:
  - "GeoDirectory installed and active for the verified path"
  - "Directorist Business Hours extension (PAID) for the Directorist path - not available on the QA site"
  - "ListingPro theme (PREMIUM) for the ListingPro path - not available on the QA site"
estimated_runtime_minutes: 8
covers_card: 10184420962
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Competitor hours are mapped, not passed through

Every competitor stores opening hours in its own shape, and none is one of the three shapes `wb_listora_normalize_hours()` understands. The migrators once handed the source value straight to `_listora_business_hours`, the normaliser rejected it, and the import produced **zero** rows in `{prefix}listora_hours`: no hours on the listing, no "Open now" match, no `openingHoursSpecification`, no error.

`WBListora\ImportExport\Hours_Mapper` (`includes/import-export/class-hours-mapper.php`) converts each family into the canonical shape: a list of entries with an integer `day` (0 = Sunday) plus `open` / `close`, or a `closed` / `is_24h` state flag. Unmappable payloads are kept verbatim as `_listora_migrated_hours_raw` and counted in the migrator's summary.

Single-role journey: it exercises a mapper class and the admin/WP-CLI migration, which only an administrator can run (Listora > Import / Migration; Pro starts competitor migrations, one background batch at a time). There is no end-user role ladder; the member-visible result (hours on the listing page, "Open now") is step 5.

## Verification status per source (read before trusting a green run)

| Source | Hours come from | Verified against a live install? |
|---|---|---|
| GeoDirectory | free plugin | mapper verified with the parsed `geodir_schema_to_array()` shape; the raw string needs GeoDirectory active |
| Directorist | PAID Business Hours extension | no |
| ListingPro | PREMIUM theme | no |
| Business Directory Plugin | owner-defined custom field, no fixed shape | n/a |
| HivePress | no hours in the free plugin | no |

The `from_day_keyed()` path (Directorist `_bdbh`, ListingPro `business_hours`) is built from documented key names and is best-effort until run against a licensed install. Do not report the card as fully verified on the GeoDirectory result alone.

## Setup

- `wp eval-file` scripts only; the mapper is a static class, no source plugin needs to be installed for steps 1 to 4.
- GeoDirectory is NOT active on the QA site (`function_exists('geodir_schema_to_array')` is false), so the mapper receives the already-parsed array form there. With GeoDirectory active, `from_geodirectory()` also accepts the raw stored string.

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
- **Expect** 5 entries, in this order: `day 1 09:00-17:00`, `day 2 09:00-12:00`, `day 2 13:00-17:00`, `day 3 09:00-17:00`, `day 0 closed`.
- The Tuesday split shift survives as two entries (collapsing it loses the lunch break).
- Sunday is `closed: true`, not a range (GeoDirectory writes the literal word `Closed` in `opens`).
- **Before/after contrast**: passing the stored string `["Mo 09:00-17:00","Tu 09:00-12:00,13:00-17:00","We 09:00-17:00","Su Closed"],["UTC":"+0"]` straight to `wb_listora_normalize_hours()` accepts **0** entries. That is the regression this journey exists for. (Without GeoDirectory active, `from_geodirectory()` of that raw string also returns `[]`: expected, it has no parser to call.)

### 2. Day-keyed family (unverified against a licensed install)
```php
\WBListora\ImportExport\Hours_Mapper::from_day_keyed( array(
  'monday'    => array( array( 'start' => '09:00', 'close' => '17:00' ) ),
  'tuesday'   => array( array( 'start' => '09:00', 'close' => '12:00' ),
                        array( 'start' => '13:00', 'close' => '17:00' ) ),
  'wednesday' => array( array( 'closed' => '1' ) ),
  'thursday'  => array( array( 'start' => '08:00 AM', 'close' => '06:30 PM' ) ),
  'friday'    => array( array( 'enable247hour' => '1' ) ),
) );
```
- **Expect** 6 entries: Monday 09:00-17:00, Tuesday two ranges, Wednesday `{"day":3,"closed":true}`, Thursday `08:00-18:30` (12-hour converted), Friday `{"day":5,"is_24h":true}`; `wb_listora_normalize_hours()` accepts all 6.

### 3. Nothing is silently lost
- **Action**: `Hours_Mapper::from_day_keyed( 'garbage' )` returns `[]`; run a migrator fixture whose hours cannot be mapped.
- **Expect**: the migrator stores the original under `_migrated_hours_raw` (written to post meta `_listora_migrated_hours_raw` by `Migration_Base::store_migrated_hours()`), leaves `business_hours` unset, and increments the "unreadable hours" count in the summary.

### 4. Day-index safety
- `day_index()` accepts short names, full names and numeric keys, and treats a numeric key as already being Listora's 0 = Sunday. A source that numbers days differently needs its own mapper method: an off-by-one shifts every listing's hours by a day and nothing downstream can detect it. Assert Monday maps to 1 and Sunday to 0 for both names and numeric `"1"` / `"0"`.

### 5. Positive control (end to end)
- **Action**: import one listing with hours through the GeoDirectory migrator fixture (or CSV with a canonical `business_hours` value) and open its page.
- **Expect**: the hours table shows Monday 09:00-17:00 and Tuesday with two ranges, Sunday "Closed", and the Open now badge agrees with the Open now search (2.0.0 fix for split hours and overnight spans). This proves "hours present" is detectable, so the step 1 "0 entries before" contrast is meaningful.

## What would close the unverified rows

A licensed Directorist Business Hours install and a ListingPro site, each with a listing carrying a split shift, a closed day and a 24-hour day. Capture the real `_bdbh` / `_lp_listingpro_options['business_hours']` values, add them to step 2 as fixtures, and re-run.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| 0 rows in `listora_hours` after import | migrator stopped calling the mapper | `includes/import-export/class-geodirectory-migrator.php:521-531`, `class-directorist-migrator.php:376`, `class-listingpro-migrator.php:246` |
| Split shift collapsed | range merge in mapper | `includes/import-export/class-hours-mapper.php:67-123` |
| Sunday shown as a range | `Closed` literal not handled | `includes/import-export/class-hours-mapper.php:100-108` |
| Hours shifted one day | numeric day convention | `includes/import-export/class-hours-mapper.php:189` (`day_index`) |
| Unmappable hours lost silently | raw copy dropped | `includes/import-export/class-migration-base.php:470-485` |

## Not verified

Steps 1 (parsed shape) and 2 and the `[]` for garbage were run live via `wp eval-file` on 2026-10-12. Step 3's migrator fixture, step 4's numeric keys and step 5 (the listing page and Open now) were NOT run. No competitor plugin is installed, so no migration was run end to end.
