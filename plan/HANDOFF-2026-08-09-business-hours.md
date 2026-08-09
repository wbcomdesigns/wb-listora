# Handoff — business hours multi-range wave (2026-08-09)

State at handoff: **Free and Pro both clean and pushed on branch `1.4.2`.**
`composer ci:no-journeys` green in both repos, including Pro's architecture invariants.

| Repo | Branch | Head |
|---|---|---|
| `wb-listora` | `1.4.2` | `2fb34df` |
| `wb-listora-pro` | `1.4.2` | `5c2f394` |

---

## The one thing to carry forward

**Every reader of `business_hours` must go through `wb_listora_normalize_hours()`.**

Three shapes exist in stored data and all three must read identically everywhere:

| Shape | Written by |
|---|---|
| `[{day:1, open, close}]` | canonical list / API imports / Pro's Google Places |
| `[1 => {open, close}]` | the submission form, historically |
| `[1 => {ranges: [{open, close}, ...]}]` | the submission form now (split shifts) |

This wave found **five** readers with five interpretations. Four were wrong, and every one of them
failed silently — storage correct, one surface wrong, no error anywhere. That is the failure mode
to expect if a sixth reader is added: it will not throw, it will just quietly show or publish
nothing.

If you add a consumer of this meta, call the helper. If you add a *producer*, make sure it emits
one of the three shapes above and cap its ranges with `wb_listora_max_hours_slots()`.

---

## What shipped

### Feature
A day holds up to **3** time ranges (`wb_listora_max_hours_slots`, filterable). Builder with
add/remove per day; the cap and every aria-label pattern come off the builder's data attributes so
the JS owns no limit and no English string. Removing a middle range renumbers the survivors — PHP
receives a sparse array otherwise and the `slot` column stops matching the posted order.

The add control is rendered `hidden` at the cap, **not omitted**. Omitting it left nothing to
un-hide after a remove, which made the third range a one-way door.

### Bugs fixed (four of the five were pre-existing and silent)

| Where | What was wrong |
|---|---|
| `blocks/listing-detail/render.php` | Own inline grouping; didn't know `ranges`. Split shift indexed as two rows while the page rendered Monday as `–`. |
| `includes/schema/class-schema-generator.php` | Skips entries with no `day` key. **Every member-submitted listing published zero `openingHoursSpecification`.** Invisible — hours rendered fine on the page. |
| `src/blocks/listing-submission/view.js` | Preview regex required `business_hours[day][key]`; the new `[ranges][slot][key]` names matched nothing but the checkboxes, so every day showed `–`. **Regression introduced during this wave** and caught only by the sweep. |
| Pro `includes/features/class-google-places.php` | Google sends one period per opening block; `$hours[$day]['open'] =` overwrote, so a lunch break imported as the evening shift only. |
| `includes/import-export/class-migration-base.php` | Competitor migrators pass source shapes through unmapped → hours dropped entirely, import reports success. Made **loud**, not mapped — see open items. |

### Presentation
`.listora-submission__hours-card` was a no-wrap flex row, so **"Closed" was clipped off the right
edge even with a single range**. Rebuilt as a two-row grid, `[day · state · toggles]` over
`[ranges]`.

A viewport `@media` query cannot fix this class of bug — the card is ~540px wide inside a 1512px
viewport, so the 640px query never fired for it. If you touch this layout, verify by measuring the
**card**, not the window.

Other measures: day height 148px → 102px, week 1030px → 761px, hairline between days, and at
≤480px the card drops its own inline padding (page + form + fieldset + card each add one, ~136px of
a 390px screen).

### New public surface
- `wb_listora_normalize_hours( $hours )` — the one shape interpretation.
- `wb_listora_max_hours_slots()` — the one cap; Pro consumes it via the global, never the class (INV-3).
- `Search_Indexer::normalise_hours_meta()` made public (consume via the global above).
- Action `wb_listora_migrated_hours_unreadable( $post_id, $value, $source_slug )`.
- Meta `_listora_migrated_hours_raw` — preserved source value for a later backfill.

---

## Open items

### 1. Competitor migrator hours mappings — BC 10184420962 (in Bugs)
Directorist / GeoDirectory / ListingPro / BDP each pass the source plugin's own hours structure
straight into `_listora_business_hours`. Imported listings lose their hours entirely.

**Deliberately not fixed here.** `audit/architecture/competitor-schemas/` does not document the
hours *value format* for any source — `geodirectory.md` names the column and never its format, and
there is no `listingpro.md`. Writing four mappings from memory against customer data is exactly
what our rules forbid.

Next steps, in order:
1. Document the hours value format per source against a **real export**, under
   `audit/architecture/competitor-schemas/<slug>.md`.
2. Add a per-source `extract_business_hours()` returning the canonical shape; split shifts map to
   `ranges` now that a day holds 3.
3. A backfill pass converting existing `_listora_migrated_hours_raw` values, so already-migrated
   sites do not have to re-import. **This is why the raw value is preserved** — do not drop that
   meta key before the backfill ships.

### 2. Manifest not refreshed
This wave added 1 fired action (`wb_listora_migrated_hours_unreadable`), 2 global helpers, 1 public
static method and 1 meta key. `audit/manifest.json` has not been updated. Per CLAUDE.md this is a
targeted delta, not a full regenerate — the deterministic generator is not to be committed for this
plugin.

### 3. Not run
`/wp-plugin-smoke combo` has not been run for this wave, so `docs/qa/.last-smoke-pass.json` is
stale and `bin/build-release.sh` will refuse to package. Required before tagging.

---

## How to verify what shipped

```bash
# Existing listings unaffected by the reader change (the additive proof)
wp eval-file bin/hours-grouping-diff.php
# expect: every non-`ranges` listing byte-identical

# Migration guard: 3 competitor shapes diverted, 3 Listora shapes untouched
wp eval-file docs/qa/fixtures/migrated-hours-probe.php
```

Journeys:
- `docs/qa/journeys/regression/business-hours-multi-range.md` (10 steps)
- `docs/qa/journeys/regression/migrated-hours-not-silently-dropped.md` (3 steps)

Runbook rows: `D.business-hours-multi-range`, `D.migrated-hours-not-dropped`.

## Basecamp

| Card | State |
|---|---|
| 10180685898 — multi-range days | **Ready for Testing**, two comments carrying the full test steps |
| 10184420962 — migrator hours mappings | **Bugs**, partial-fix comment with the three remaining items |
