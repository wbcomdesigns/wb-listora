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
---

# Competitor hours must be mapped, not passed through (BC 10184420962)

Every competitor stores opening hours in its own shape, and none is one of the
three `wb_listora_normalize_hours()` understands. The migrators handed the
source value straight to `_listora_business_hours`, so the normaliser rejected
it and the import produced **zero** rows in `listora_hours` — no hours on the
listing, no "Open now" match, no `openingHoursSpecification`, and no error.

`Hours_Mapper` converts each family into the canonical shape: a list of entries
carrying an integer `day` (0 = Sunday) plus `open`/`close`, or a `closed` /
`is_24h` state flag.

## Verification status per source — read this before trusting a green run

| Source | Hours available in | Verified against a live install? |
|---|---|---|
| **GeoDirectory** | free plugin | **YES** — mapped from its own parser's output |
| Directorist | **paid** Business Hours extension | no |
| ListingPro | **premium** theme | no |
| Business Directory Plugin | owner-defined custom field, no fixed shape | n/a |
| HivePress | no hours in the free plugin | no |

Only GeoDirectory could be exercised end-to-end on the QA site. The
`from_day_keyed()` path that serves Directorist and ListingPro is built from
their documented key names and is **best-effort until someone runs it against a
licensed install**. Do not report this card as fully verified on the strength of
the GeoDirectory result alone.

## Steps

### 1. GeoDirectory — the verified path
GeoDirectory stores a schema.org-ish string in its detail table:

```
["Mo 09:00-17:00","Tu 09:00-12:00,13:00-17:00","We 09:00-17:00","Su Closed"],["UTC":"+0"]
```

Run the mapper on that exact value and feed the result to the normaliser:

```php
$mapped = \WBListora\ImportExport\Hours_Mapper::from_geodirectory( $stored );
count( wb_listora_normalize_hours( $mapped ) );
```

- **Expect** 5 entries: `day=1 09:00-17:00`, `day=2 09:00-12:00`,
  `day=2 13:00-17:00`, `day=3 09:00-17:00`, `day=0 CLOSED`.
- **The split shift on Tuesday must survive as two entries.** Collapsing it to
  one is the failure that loses a lunch break.
- **Sunday must come back as `closed`, not as a range.** GeoDirectory writes the
  literal word `Closed` in the `opens` field, not a flag.
- **Before/after check:** passing the raw string through, as the migrator used
  to, yields **0** accepted entries. That contrast is the regression this
  journey exists for.

### 2. Day-keyed family — unverified path
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
- **Expect** 6 entries, with Thursday converted from 12-hour to `08:00-18:30`,
  Wednesday `closed`, Friday `is_24h`.

### 3. Nothing is silently lost
When a payload cannot be mapped, the migrator stores the original under
`_migrated_hours_raw` rather than discarding it, so the existing
migrated-hours reporting can surface it to the owner. Assert that an
unmappable payload leaves that key set and `business_hours` unset.

### 4. Day-index safety
`day_index()` accepts short names, full names and numeric keys, and treats a
numeric key as ALREADY being Listora's 0=Sunday convention. A source that
numbers days differently must get its own mapper method — an off-by-one shifts
every listing's hours by a day and nothing downstream can detect it.

## What would close the unverified rows

A licensed Directorist Business Hours install and a ListingPro site, each with a
listing carrying a split shift, a closed day and a 24-hour day. Capture the real
`_bdbh` / `_lp_listingpro_options['business_hours']` values, add them to step 2
as fixtures, and re-run.
