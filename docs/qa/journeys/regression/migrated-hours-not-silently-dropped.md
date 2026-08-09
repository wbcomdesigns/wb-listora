---
journey: migrated-hours-not-silently-dropped
plugin: wb-listora
priority: normal
roles: [admin]
covers: [migration-hours-shape, wb_listora_migrated_hours_unreadable, _listora_migrated_hours_raw, BC-10184420962]
prerequisites:
  - "wb-listora active"
  - "A source plugin with listings to migrate, OR the reflection probe below"
estimated_runtime_minutes: 3
---

# A migration that cannot read the source's hours says so

The four competitor migrators pass the source plugin's own hours structure straight into
`_listora_business_hours` with no mapping. None of those structures is one of the three shapes
Listora reads, so the value indexed to zero rows, rendered nothing, matched no "Open now" search
and emitted no structured data — while the import reported a clean success.

**This journey does not test a mapping.** Writing one per source needs each source's value format
documented against a real export first (card #10184420962). It tests that the failure is no longer
silent.

## Steps

### 1. Unreadable hours are withheld, preserved, counted and announced
- **Action**: migrate listings from Directorist / GeoDirectory / ListingPro / BDP that have
  business hours. Or run the probe:
  ```bash
  wp eval-file docs/qa/fixtures/migrated-hours-probe.php
  ```
  which exercises the three competitor shapes and the three readable ones in one pass and prints
  `stored_normally` / `raw_preserved` per case.
- **Expect**, for a listing whose source hours Listora cannot interpret:
  - `_listora_business_hours` is **not** set — a hours field populated with something nothing can
    read looks configured while behaving as empty.
  - `_listora_migrated_hours_raw` holds the original value verbatim, so a mapper written later can
    backfill without a re-import.
  - `migrate_all()`'s returned `$stats` carries `unreadable_hours` and a message naming the source
    and the count.
- **On fail**: `Migration_Base::store_migrated_hours()` is being bypassed — check that the meta
  loop in `create_listing()` still routes `business_hours` through it.

### 2. Readable hours are untouched
- **Action**: run the same path with each shape Listora does read — the canonical list
  `[{day, open, close}]`, the day-keyed dict `[1 => {open, close}]`, and `[1 => {ranges: [...]}]`.
- **Expect**: all three stored normally, `_listora_migrated_hours_raw` never written, the count
  stays 0.
- **Why**: this guard must be invisible to every source that already produces a Listora shape,
  including the CSV / JSON / GeoJSON importers.
- **On fail**: **STOP** — the guard is rejecting valid hours, which loses data the old code kept.

### 3. A site can convert its own format at import time
- **Action**: hook `wb_listora_migrated_hours_unreadable` and write a mapping for one source.
- **Expect**: it fires with `( $post_id, $value, $source_slug )` for each unreadable listing.
- **Why**: a site owner with one known source should not have to wait for a migrator to ship a
  mapper.

## Pass criteria

- Unreadable source hours: real field unset, raw value preserved, counted, reported in `messages`.
- All three readable Listora shapes stored normally and never diverted.
- `wb_listora_migrated_hours_unreadable` fires once per diverted listing.
- Older Free with no `wb_listora_normalize_hours()`: falls back to the previous store-everything
  behaviour rather than dropping hours.

## State restored

Delete any listings created by the migration test.
