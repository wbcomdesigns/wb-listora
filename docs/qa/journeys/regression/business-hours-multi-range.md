---
journey: business-hours-multi-range
plugin: wb-listora
priority: high
roles: [member, anonymous]
covers: [business_hours-split-shift, hours-slot-column, submission-hours-builder, hours-reader-parity, wb_listora_normalize_hours, BC-10180685898]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora active"
  - "A member account that can reach the Add Listing form"
  - "At least one published listing to edit"
estimated_runtime_minutes: 6
---

# A day can hold more than one time range, and every reader agrees what it says

A café that opens 08:00–12:00 and again 17:00–22:00 could only record one of the two. The single
range it did record made the "Open now" badge wrong for half the day, and the owner had no way to
express the break.

The storage has carried a `slot` column since 1.5.0. What this journey guards is the rest of the
chain — the builder that creates the second range, and **every reader agreeing on what the stored
shape means**.

Three shapes exist in stored data and all three must read identically everywhere:

| Shape | Written by |
|---|---|
| `[{day:1, open, close}]` | the canonical list / API imports |
| `[1 => {open, close}]` | the submission form, historically |
| `[1 => {ranges: [{open, close}, ...]}]` | the submission form now |

**The bug this exists to catch:** the detail template used to group hours with its own inline
logic. It understood the first two shapes and not the third, so a split shift indexed correctly
into `{prefix}listora_hours` (two rows, slots 0 and 1) while the listing page rendered Monday as
`–`. Storage right, display wrong, no error anywhere. Both now go through
`wb_listora_normalize_hours()`, and a second interpretation appearing anywhere is the regression.

## Setup

```bash
SITE=http://listora.local
LISTING=$(wp eval 'echo (int) get_posts(array("post_type"=>"listora_listing","posts_per_page"=>1,"fields"=>"ids","post_status"=>"publish"))[0];')
echo "listing: $LISTING"
```

## Steps

### 1. The builder can add a range, up to the cap and no further
- **Action**: open `$SITE/add-listing/?autologin=1`, reach Business Hours, click Monday's
  **+ Add another time** repeatedly.
- **Expect**: a second and third range appear; at the third the add control is **gone from the
  page and from the a11y tree** (`hidden`), not greyed. The cap is
  `Search_Indexer::max_hours_slots()` (default 3), filterable — it is not hardcoded in JS.
- **On fail**: suspect `data-max-slots` missing from `.listora-submission__hours-builder`, or
  `initBusinessHoursRanges()` in `src/blocks/listing-submission/view.js`.

### 2. Field names stay a contiguous list
- **Action**: after adding two ranges, inspect the input names.
- **Expect**: `meta_business_hours[1][ranges][0][open]`, `…[1][open]` — indexes 0,1 with no gap.
- **Why**: PHP receives a sparse array otherwise and the `slot` column no longer matches the
  posted order.

### 3. Removing the MIDDLE range renumbers and keeps the survivors' times
- **Action**: create three ranges with distinguishable times, delete the second.
- **Expect**: two remain, renumbered to slots 0 and 1, each keeping **its own** open/close values,
  and the aria-labels re-derived (`Monday opening time 2`, not `3`). The add control **returns**.
- **On fail**: `renumberHoursRanges()`. If the add control does not return, the renderer has gone
  back to omitting the button at the cap instead of rendering it `hidden` — that made the third
  range a one-way door.

### 4. A split shift round-trips to the index table
- **Action**:
  ```bash
  wp eval '
  $id = '"$LISTING"';
  update_post_meta( $id, "_listora_business_hours", array(
    1 => array( "ranges" => array(
      array( "open" => "08:00", "close" => "12:00" ),
      array( "open" => "17:00", "close" => "22:00" ) ) ),
    2 => array( "is_24h" => 1 ),
    3 => array( "closed" => 1 ) ) );
  ( new \WBListora\Search\Search_Indexer() )->index_listing( $id );
  global $wpdb;
  foreach ( $wpdb->get_results( "SELECT day_of_week, slot, open_time, close_time, is_closed, is_24h FROM {$wpdb->prefix}listora_hours WHERE listing_id = $id ORDER BY day_of_week, slot", ARRAY_A ) as $r ) { echo implode(" | ", $r), "\n"; }'
  ```
- **Expect** exactly four rows:
  ```
  1 | 0 | 08:00:00 | 12:00:00 | 0 | 0
  1 | 1 | 17:00:00 | 22:00:00 | 0 | 0
  2 | 0 |          |          | 0 | 1
  3 | 0 |          |          | 1 | 0
  ```
- **On fail**: `Search_Indexer::normalise_hours_meta()` no longer understands the `ranges` key, or
  `update_hours_index()` stopped assigning `slot`.

### 5. The listing page shows BOTH ranges (the reader-drift guard)
- **Action**: open the listing permalink, read the Business Hours card.
- **Expect**: `Monday  8:00 am – 12:00 pm, 5:00 pm – 10:00 pm`, `Tuesday  Open 24 Hours`,
  `Wednesday  Closed`.
- **On fail — this is the regression this journey is named for.** Monday showing `–` while step 4
  passes means a reader stopped using `wb_listora_normalize_hours()` and grew its own shape
  handling again. Suspect `wb_listora_render_hours()` in `blocks/listing-detail/render.php`.

### 6. Structured data carries every range — and exists at all for member-submitted hours
- **Action**:
  ```bash
  curl -s "$SITE/?p=$LISTING" | grep -o '"openingHoursSpecification":\[[^]]*\]'
  ```
- **Expect**: two `OpeningHoursSpecification` entries for Monday (08:00-12:00 and 17:00-22:00) plus
  Tuesday 00:00-23:59. Wednesday, being closed, is correctly absent.
- **Why this step exists**: `format_hours_schema()` skips any entry with no `day` key, and the
  day-keyed dict the submission form posts has none — so **every listing whose hours a member
  entered published an empty `openingHoursSpecification`**. The hours rendered fine on the page, so
  the only symptom was Google never showing opening hours for those listings. Regression check:
  ```bash
  wp eval '$g=new \WBListora\Schema\Schema_Generator(); $m=new ReflectionMethod($g,"format_hours_schema"); $m->setAccessible(true);
  $dict=array(1=>array("open"=>"09:00","close"=>"17:00"));
  echo "raw: ", json_encode($m->invoke($g,$dict)), " normalized: ", json_encode($m->invoke($g,wb_listora_normalize_hours($dict))), "\n";'
  ```
  `raw` may be `[]`; `normalized` must not be.
- **On fail**: the generator's call site stopped passing through `wb_listora_normalize_hours()`.

### 7. The submission preview agrees with the published page
- **Action**: set `submission_form_style` to `single_form`, open Add Listing, give Monday two
  ranges and mark Tuesday Closed. Read the preview panel.
- **Expect**: `Monday 08:00 – 12:00, 17:00 – 22:00` and `Tuesday Closed` — the same string the
  listing page will show.
- **Why**: the preview parses input NAMES with a regex. It matched `business_hours[1][open]` but
  not `business_hours[1][ranges][0][open]`, so every day showed `–` while `Closed` / `Open 24
  hours` still matched — the section looked populated with every time blank, on the last screen
  before the member publishes.
- **On fail**: the optional `ranges` segment is missing from the pattern in
  `appendBusinessHoursPreview()`.

### 8. (Pro) Google Places import keeps both shifts
- **Action**:
  ```bash
  wp eval '$g=new \WBListoraPro\Features\Google_Places(); $m=new ReflectionMethod($g,"parse_google_hours"); $m->setAccessible(true);
  $p=array(
    array("open"=>array("day"=>1,"time"=>"0800"),"close"=>array("day"=>1,"time"=>"1200")),
    array("open"=>array("day"=>1,"time"=>"1700"),"close"=>array("day"=>1,"time"=>"2200")));
  foreach($m->invoke($g,$p) as $h){ if(empty($h["closed"])) echo $h["day"],": ",$h["open"],"-",$h["close"],"\n"; }'
  ```
- **Expect**: two Monday lines, `08:00-12:00` and `17:00-22:00`.
- **Why**: Google returns one period per opening block, so a lunch break arrives as two periods
  with the same `open.day`. Assigning `$hours[$day]['open']` overwrote, and the import silently
  kept only the evening shift.
- **Also assert**: a single-shift payload still returns exactly 7 entries, one per day, unchanged.
- **On fail**: `parse_google_hours()` went back to assigning per day instead of collecting.

### 9. Single-range listings are untouched (additive proof)
- **Action**:
  ```bash
  wp eval-file bin/hours-grouping-diff.php
  ```
  (or re-derive: group every listing's stored hours the old way and via
  `wb_listora_normalize_hours()`, compare the rendered cell text per day).
- **Expect**: every listing that does **not** use the `ranges` shape renders byte-identically.
  Only `ranges`-shaped listings may differ, and only from wrong to right.
- **On fail**: **STOP — release blocker.** Existing customer listings changed what they display.

### 10. Presentation holds at both widths
- **Action**: screenshot the builder at 1512px and at 390px.
- **Expect**: at 1512 the day, "Open 24 hours" and "Closed" share one line with **"Closed" fully
  inside the card**, ranges below at full width. At 390 the toggles sit on one line under the day,
  and the page does **not** scroll horizontally.
- **Why**: "Closed" clipped off the right edge is the defect the card layout was rebuilt for; it
  came from a no-wrap flex row, and a viewport `@media` query cannot catch it because the card is
  ~540px wide inside a 1512px viewport.

## Pass criteria

ALL must hold:
- Add stops at `max_hours_slots()`; the control is hidden, not disabled, and returns after a remove.
- Input names form a contiguous `ranges[0..n]` list after any add or remove.
- Removing a middle range preserves the survivors' own times and re-labels them.
- A split shift produces one row per slot in `{prefix}listora_hours`.
- The listing page renders every range, joined with `, `.
- `openingHoursSpecification` is non-empty for day-keyed-dict hours and carries one entry per range.
- The submission preview renders the same string as the published page.
- (Pro) A two-period Google Places day imports as two ranges; a one-period day is unchanged.
- No non-`ranges` listing changes its rendered hours.
- No horizontal scroll at 390px; "Closed" visible at 1512px.
- No new notices in `wp-content/debug.log`.

## Fail diagnostics

| Symptom | Suspect |
|---|---|
| Monday renders `–` but step 4 passes | A reader hand-rolling shape logic instead of `wb_listora_normalize_hours()` |
| Sparse `ranges[0], ranges[2]` posted | `renumberHoursRanges()` not running after remove |
| Add control never returns after a remove | Renderer omitting the button at the cap instead of `hidden` |
| Cap is 3 even when filtered | JS reading a literal instead of `data-max-slots` |
| "Closed" clipped at desktop | `.listora-submission__hours-card` grid areas replaced by a flex row |
| Existing listing's hours changed | **Release blocker** — normalisation is no longer additive |

## State restored

Step 4 overwrites `_listora_business_hours` on `$LISTING`. Capture it first
(`wp eval 'echo json_encode(get_post_meta($id,"_listora_business_hours",true));'`) and restore
after, then re-run `index_listing()` so the index matches the meta again.
