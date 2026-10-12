---
journey: business-hours-multiple-ranges
plugin: wb-listora
priority: high
roles: [subscriber, guest, admin]
covers: [business_hours-split-shift, hours-slot-column, submission-hours-builder, hours-reader-parity, wb_listora_normalize_hours, flatpickr-time-picker, firefox-time-input, picker-theming, BC-10180685898, bc-10350895542, bc-10332302692, "#9856828615"]
prerequisites:
  - "Site reachable at $SITE_URL; wb-listora active; a member {owner} that can reach Add Listing"
  - "A throwaway {type} listing created for this run (QA-J1- prefix); never edit real listings"
  - "Step 4/6/8: Firefox available for the native-spinner check (mark that step skipped with a manual_required entry on a Chromium-only run)"
  - "Step 9 (Pro): wb-listora-pro active"
estimated_runtime_minutes: 14
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1512, 1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes:
  - "ora:regression/business-hours-multi-range.md"
  - "ora:regression/business-hours-firefox.md"
  - "ora:regression/business-hours-picker-themed.md"
fails_on: [649ab2a0]
---

# A day can hold more than one time range, the picker works on every browser and theme, and every reader agrees what the hours say

## Why
A cafe open 08:00-12:00 and 17:00-22:00 could record only one range, so the "Open now" badge was wrong for half the day; one reader hand-rolling shape logic shows `-` while storage is correct, and Google never shows opening hours. The picker must also work in Firefox and on dark/unknown themes.

Three shapes exist in stored data and must read identically everywhere:
| Shape | Written by |
|---|---|
| `[{day:1, open, close}]` | canonical list / API imports |
| `[1 => {open, close}]` | submission form, historically |
| `[1 => {ranges: [{open, close}, ...]}]` | submission form now |

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Throwaway listing:
  ```bash
  LISTING=$(wp post create --post_type=listora_listing --post_title="QA-J1-hours" --post_status=publish --porcelain)
  wp post term set $LISTING listora_listing_type {type}   # a type with business_hours: business, restaurant, healthcare, place
  ```
- Cleanup: `wp post delete $LISTING --force` (its `${PREFIX}listora_hours` rows go with it). If step 4 ran against a real listing, capture `_listora_business_hours` first and restore it, then re-run `index_listing()`.

## Steps

### 1. The builder adds ranges up to the cap and no further
- **Action**: as {owner} Add Listing > a type with Business Hours > Details; click Monday's **+ Add another time** (`button.listora-submission__hours-add[data-day="1"]`) repeatedly.
- **Expect**: a second and third range appear; at the cap the add control is `hidden` (gone from page and a11y tree), not greyed. Cap = `Search_Indexer::max_hours_slots()` (default 3, filterable), served as `data-max-slots` on `.listora-submission__hours-builder`.
- **On fail**: `initBusinessHoursRanges()` (`src/blocks/listing-submission/view.js:1406`).

### 2. Field names stay a contiguous list
- **Action**: after adding two ranges inspect input names.
- **Expect**: `meta_business_hours[1][ranges][0][open]`, `...[1][open]`: indexes 0,1 with no gap (a sparse PHP array desyncs the `slot` column).

### 3. Removing the middle range renumbers and keeps each survivor's times
- **Action**: create three ranges with distinguishable times, delete the second.
- **Expect**: two remain at slots 0 and 1, each keeping its own open/close; aria-labels re-derived (`Monday opening time 2`, not `3`); the add control returns.
- **On fail**: `renumberHoursRanges()` (`view.js:1354`); an add control that never returns means the renderer omits the button at the cap instead of rendering it `hidden`.

### 4. The picker is flatpickr, not the native Firefox spinner
- **Action**: console `[!!document.querySelector('link[href*="flatpickr"]'), typeof window.flatpickr]`; click the first `.listora-submission__hours-input` (aria-label "Monday opening time 1") in Firefox.
- **Expect**: `[true, 'function']` (assets `flatpickr.min.js?ver=4.6.13` and its CSS enqueued by `blocks/listing-submission/render.php:22-23`); a `.flatpickr-calendar.listora-flatpickr` panel opens with hour and minute fields, 24h, 15-minute steps, no AM/PM.
- **On fail**: regression of #9856828615 round 2: `initBusinessHoursPickers()` options `enableTime`, `noCalendar`, `dateFormat: 'H:i'`, `time_24hr`, `minuteIncrement: 15` (`view.js:1247-1253`).

### 5. Pick 09:00; inputs attach independently; Closed takes no times
- **Action**: set 09:00 in the panel; click away; click Monday's closing input; tick Monday "Closed".
- **Expect**: value `09:00`, panel closes on click-away; the second click opens the panel for THAT input (both carry `data-listora-flatpickr-attached="1"`); with Closed ticked Monday's inputs are disabled and no panel opens (picker attaches to enabled inputs only).
- **On fail**: idempotent attach broken (`view.js:1244` selector must exclude attached inputs).

### 6. The picker renders cleanly on any theme
- **Action**: focus Monday's opening input; open the panel; toggle the theme's dark mode and reopen; repeat on the unknown theme.
- **Expect**: ONE focus outline on the clock-icon wrapper (`.listora-submission__hours-input-wrap` carries the ring; computed `border` of `input.listora-submission__hours-input` is `0`/none); the panel background and text follow the light/dark tokens and change with the scheme (a white panel in dark mode is the defect); at 390 the panel fits the viewport.
- **Read-back**: a non-Listora flatpickr on the same site keeps its own styling (rules scoped to `.listora-flatpickr`, tagged on ready `view.js:1257-1260`).

### 7. A split shift round-trips to the index table
- **Action**:
  ```bash
  wp eval '$id='"$LISTING"'; update_post_meta($id,"_listora_business_hours",array(1=>array("ranges"=>array(array("open"=>"08:00","close"=>"12:00"),array("open"=>"17:00","close"=>"22:00"))),2=>array("is_24h"=>1),3=>array("closed"=>1)));
  (new \WBListora\Search\Search_Indexer())->index_listing($id); global $wpdb;
  foreach($wpdb->get_results("SELECT day_of_week,slot,open_time,close_time,is_closed,is_24h FROM {$wpdb->prefix}listora_hours WHERE listing_id=$id ORDER BY day_of_week,slot",ARRAY_A) as $r){echo implode(" | ",$r),"\n";}'
  ```
- **Expect** exactly four rows: `1|0|08:00:00|12:00:00|0|0`, `1|1|17:00:00|22:00:00|0|0`, `2|0| | |0|1`, `3|0| | |1|0`.
- **On fail**: `Search_Indexer::normalise_hours_meta()` lost the `ranges` key or `update_hours_index()` stopped assigning `slot`.

### 8. Saved hours persist from the wizard
- **Action**: complete the wizard publishing Monday 09:00 open.
- **Expect**: `SELECT open_time FROM ${PREFIX}listora_hours WHERE listing_id=<id> AND day_of_week=1 AND slot=0` = `09:00:00`.

### 9. The listing page shows BOTH ranges (reader-drift guard)
- **Action**: as {guest} open the permalink, read the Business Hours card (`table.listora-hours-table`).
- **Expect**: Monday `8:00 am - 12:00 pm, 5:00 pm - 10:00 pm`, Tuesday `Open 24 Hours`, Wednesday `Closed` (`--closed`), unset days `-` (`--na`). The "Open now" badge uses the same normalised hours.
- **On fail**: this is the regression the journey is named for: Monday `-` while step 7 passes means a reader stopped using `wb_listora_normalize_hours()` (`wb_listora_render_hours()` `blocks/listing-detail/render.php:39`).

### 10. Structured data carries every range
- **Action**: `curl -s "$SITE_URL/?p=$LISTING" | grep -o '"openingHoursSpecification":\[[^]]*\]'`.
- **Expect**: two entries for Monday (08:00-12:00, 17:00-22:00) plus Tuesday 00:00-23:59; closed Wednesday absent.
- **Read-back** (day-keyed-dict regression): `format_hours_schema()` of raw `array(1=>array("open"=>"09:00","close"=>"17:00"))` may be `[]` but the `wb_listora_normalize_hours()` result must not be; the generator call site must normalise first.

### 11. The submission preview agrees with the published page
- **Action**: set `submission_form_style` to `single_form`, open Add Listing, give Monday two ranges, Tuesday Closed; read the preview.
- **Expect**: `Monday 08:00 - 12:00, 17:00 - 22:00` and `Tuesday Closed`, the string the page shows.
- **On fail**: `appendBusinessHoursPreview()` regex missing the optional `ranges` segment.

### 12. A field-group tab is never an empty panel; hours render from every stored shape (bc-10350895542)
- **Action**: as {guest} open four listings of a type whose "Place Details" group holds only hours: (a) unset, (b) canonical list, (c) older day-keyed single range, (d) current `ranges`; plus one whose hours normalise to zero ranges (all days closed with empty times).
- **Expect**: (a) and the zero-range listing have NO "Place Details" tab and no empty `#panel-place-details`; (b)-(d) show the tab with a non-empty panel listing the open days with times; never empty, never "Closed" for all seven days of a listing with real hours.
- **Read-back**: `document.querySelector('#panel-place-details')?.innerText.trim().length > 0` or the tab is absent; negative control: (a) proves the absence branch fires.
- **fails_on**: 649ab2a0 (parent of 4b95d7fc). Not verified live.

### 13. (Pro) Google Places import keeps both shifts
- **Action**:
  ```bash
  wp eval '$g=new \WBListoraPro\Features\Google_Places(); $m=new ReflectionMethod($g,"parse_google_hours"); $m->setAccessible(true);
  $p=array(array("open"=>array("day"=>1,"time"=>"0800"),"close"=>array("day"=>1,"time"=>"1200")),array("open"=>array("day"=>1,"time"=>"1700"),"close"=>array("day"=>1,"time"=>"2200")));
  foreach($m->invoke($g,$p) as $h){ if(empty($h["closed"])) echo $h["day"],": ",$h["open"],"-",$h["close"],"\n"; }'
  ```
- **Expect**: two Monday lines `08:00-12:00` and `17:00-22:00`; a single-shift payload still returns exactly 7 entries, one per day, unchanged.
- **On fail**: `wb-listora-pro/includes/features/class-google-places.php:335` (`parse_google_hours()` assigning per day instead of collecting).

### 14. Single-range listings are untouched (additive proof)
- **Action**: `wp eval-file bin/hours-grouping-diff.php` (groups every listing's stored hours the old way and via `wb_listora_normalize_hours()`, compares rendered cell text per day); on a large site run it in batches.
- **Expect**: every listing not using the `ranges` shape renders byte-identically; only `ranges` listings may differ, from wrong to right. Record `listings with hours`, `identical`, `changed` from the run, never assume.
- **On fail**: STOP, release blocker: existing customer listings changed what they display.

### 15. Presentation holds at both widths
- **Action**: screenshot the builder at 1512 and 390.
- **Expect**: at 1512 the day, "Open 24 hours" and "Closed" share one line with "Closed" fully inside the card, ranges below at full width; at 390 the toggles sit on one line under the day and the page does not scroll horizontally.
- **On fail**: `.listora-submission__hours-card` grid areas replaced by a flex row (a viewport `@media` cannot catch it: the card is ~540px in a 1512px viewport).

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | builds the hours (steps 1-6, 8, 11) and sees them on the published page |
| {guest} / another member | steps 9, 10, 12 are public and identical |
| {admin} / {editor} (control) | the wp-admin listing editor renders the same builder through the shared field renderer; its saved shape is read by the same `wb_listora_normalize_hours()` |
| role without submit cap | "You cannot add listings" message, no builder (see wizard journey step 15) |

## Pass criteria
1. Add stops at `max_hours_slots()` (control hidden, returns after a remove); input names are a contiguous `ranges[0..n]` list; removing a middle range keeps survivors' own times and relabels.
2. flatpickr (24h, 15-minute) opens in Firefox, attaches per input, ignores Closed days, and follows light/dark tokens with one focus ring.
3. A split shift is one row per slot; the page, the JSON-LD and the preview render every range with the same text; zero-range listings show no empty tab.
4. (Pro) Two-period Google days import as two ranges.
5. No non-`ranges` listing changes its rendered hours; no horizontal scroll at 390; "Closed" visible at 1512; no new `debug.log` notices.

## Fail diagnostics
| Symptom | Suspect |
|---|---|
| Monday `-` but step 7 passes | A reader hand-rolling shape logic instead of `wb_listora_normalize_hours()` |
| Sparse `ranges[0], ranges[2]` posted | `renumberHoursRanges()` not running after remove |
| Add control never returns | Renderer omitting the button at the cap instead of `hidden` |
| Cap is 3 even when filtered | JS reading a literal instead of `data-max-slots` |
| Native spinner on Firefox | flatpickr not attached (`view.js:1238`) or CSS/JS enqueue missing |
| Same panel for all inputs | non-idempotent attach (`view.js:1244`) |
| Value does not persist | format mismatch (24h vs 12h): `dateFormat` must stay `H:i` |
| Inner box on focus / white panel on dark | theme `input[type=text]:focus` outranks reset; panel not mapped to tokens |
| "Closed" clipped at desktop | grid areas replaced by flex row |
| Existing listing's hours changed | release blocker: normalisation not additive |

## Not verified
- Steps 1-3, 5, 6, 8, 11 and 15 need a browser; step 4 needs Firefox; steps 7, 9, 10, 13 and 14 were executed on 2026-10-12 via wp eval/curl; step 12 and the dark/unknown theme runs were not run live.

## Cleanup
- `wp post delete $LISTING --force` for the `QA-J1-hours` listing; its `${PREFIX}listora_hours` rows go with it.
- If step 4 ran against a real listing instead, restore the captured `_listora_business_hours` and re-run `index_listing()`.
- Set `submission_form_style` (step 11) back to its original value and delete the Google Places probe output (step 13 writes nothing to the database).
