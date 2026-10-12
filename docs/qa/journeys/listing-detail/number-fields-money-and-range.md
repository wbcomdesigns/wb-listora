---
journey: number-fields-money-and-range
group: listing-detail
actor: customer
mode: mixed
plugin: wb-listora
priority: high
roles: [guest, member-other, admin]
covers: [number-field-currency, field-range-pair, salary-range, jobposting-schema, card-aria-range, 2.0.0-money-range, bc-10393565775]
prerequisites:
  - "Site reachable at $SITE_URL; Job demo pack imported: listings $LISTING_JOB (salary_min < salary_max) and $LISTING_JOB_2; read `MIN`/`MAX`/`PERIOD` from `wp post meta get <id> _listora_salary_min` etc."
  - "Settings > General > Currency = USD at start (record the original, restore afterwards)"
  - "Step 5 runs only when the Pro Compare page exists"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: []
fails_on: [cf032112]
---

# A Number field can show as money, and a min/max pair reads as one range

## Why
Two unrelated chips ("$120K $300K") or raw "130000" read as errors on a job board, and a screen reader hearing a dash instead of "to" gets the range wrong. Structured data must carry raw numbers and the currency code for search engines.

2.0.0: a Number field has "Show as money (site currency)"; a field with `range_end` (min) joined to a partner with `range_start` (max) is shown once as "Salary $MINK - $MAXK" on cards, listing page and Compare, read out as "Salary $MINK to $MAXK". Every consumer goes through `wb_listora_field_display()`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Format helper: `K(n)` = compact thousands used by the plugin (e.g. 130000 -> `$130K`); compute expected strings from the read `MIN`/`MAX`.
- Cleanup: restore the Currency setting and the "Show as money" switch; restore any edited listing meta.

## Steps

### 1. The listing page shows one range row (guest, 1440)
- **Action**: open $LISTING_JOB.
- **Expect**: in `dl.listora-detail__quick-info` exactly one `<dt>Salary</dt><dd>$MINK - $MAXK</dd>` (en dash U+2013 with spaces); NO separate "Salary Min" / "Salary Max" rows; in the Details tab the same single "Salary" row plus "Salary Period: <PERIOD>".
- **Read-back**: positive control: edit a Job to min = max (e.g. 150000/150000) and see just "$150K"; a Job with only a minimum shows "$150K" under "Salary"; proves the dash is not hard-coded.
- **On fail**: `wb_listora_field_display()` (`includes/class-template-helpers.php` ~1385), `templates/blocks/listing-detail/tabs.php` (~174, ~292).

### 2. Directory cards (guest)
- **Action**: open the Directory filtered to the Job type (`listora_type={type}`).
- **Expect**: each card meta item shows the range text with `aria-hidden="true"`, preceded by `.listora-sr-only` "Salary $MINK to $MAXK"; one range per card, not two numbers.
- **Read-back**: a card whose salary is 140000 to 185000 has exactly one `.listora-card__meta` chip with visible text `$140K - $185K` (one element); the detail page shows one Salary row with the same text. **fails_on**: cf032112 (two unrelated chips). Not verified live.
- **On fail**: `templates/blocks/listing-card/card-content.php` (~117), `aria_label` in `wb_listora_prepare_card_data`.

### 3. Structured data carries the real numbers and currency
- **Action**: read the page's `application/ld+json` for the Job (JobPosting).
- **Expect**: `baseSalary` = `{"@type":"MonetaryAmount","currency":"USD","value":{"@type":"QuantitativeValue","minValue":MIN,"maxValue":MAX,"unitText":"YEAR"}}` (raw numbers, NOT "$130K"); plus `title`, `datePosted`, `jobLocation` (Place > PostalAddress), `employmentType`, and `jobLocationType` "TELECOMMUTE" for Remote.
- **Read-back**: when an SEO plugin owns JSON-LD (Yoast/Rank Math/AIOSEO/SEOPress) Listora prints none: record "SEO plugin active" for this step, not FAIL.

### 4. Money follows the site currency and the editor switch (admin)
- **Action**: Settings > General > Currency = EUR, Save; reload the listing and a card.
- **Expect**: "EUR-sign MINK - EUR-sign MAXK" on page and card (e.g. "€130K - €175K"); JSON-LD `currency` = "EUR". Restore USD.
- **Action**: Listing Types > Job (`admin.php?page=listora-listing-types&edit=job`) > open "Salary Min" > untick "Show as money (site currency)", Save Type.
- **Expect**: min renders as a plain number ("130,000"), max stays money, and the pair still joins as one row "130,000 - $175K" (range is independent of money). Re-tick and save.
- **On fail**: `assets/js/admin/type-editor.js` (~486 `currency` checkbox), `wb_listora_format_card_value()` number case.

### 5. Compare reads the same (Pro Compare present)
- **Action**: add two jobs to compare, open the Compare page.
- **Expect**: one "Salary" row per job with the range; no "Salary Min"/"Salary Max" rows.

### 6. Filtering is unaffected
- **Action**: Directory > type Job > open Filters.
- **Expect**: the filterable salary fields still appear as filters (the join is display-only: each bound keeps its own `filterable` flag) and applying one narrows the total. Record the control labels and the URL parameter produced (per-type controls are rendered client-side, so no fixed selector).
- **Read-back**: result `total` before vs after differs and equals the REST `/search` total for the same params.

### 7. Boundary and zero-decimal currency
- **Action**: set the Currency to a zero-decimal currency (JPY); set min `0`, then min/max both empty, then max below min.
- **Expect**: JPY prints without decimals; min `0` shows "0" (not blank); both empty hides the row entirely; max below min does not render a reversed range as valid (shows the single bound or a refused save). Restore USD. Not verified live (added in consolidation).

### 8. 390px and dark
- **Expect**: the range does not wrap mid-number ("$130K - $175K" stays together or wraps at the dash); card meta row does not overflow; no horizontal scroll; contrast holds in dark mode.

## Role ladder
| Rung | Expect |
|---|---|
| {guest} | steps 1-3, 6 |
| member-other | identical output |
| {admin} | step 4; steps 1-3 identical output |

## Pass criteria
1. One joined range row on card, page and Compare; never two rows.
2. Screen-reader text says "to"; structured data holds raw numbers and the currency code.
3. The currency symbol follows Settings > General; the money switch is per field.
4. Equal bounds and a missing max degrade to a single value.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Two rows (Min and Max) | field lost `range_end`/`range_start` (saved type overwrote props) | `Listing_Type_Defaults` (~999), `Migrator` salary range repair (~640) |
| "130000" instead of "$130K" | `currency` prop false | type editor "Show as money", `class-field.php` props |
| Dash read aloud | missing sr-only sibling | `card-content.php` |

## Not verified
- Dark mode, 390px, steps 5 (needs Compare page), 7 and the cf032112 read-back were not run live.

## Cleanup
- Set Settings > General > Currency back to the value recorded in the prerequisites (USD).
- Re-tick "Show as money (site currency)" on Salary Min in Listing Types > Job and Save Type.
- Restore the listing meta edited in step 7 (min `0`, empty, max below min) to the recorded values and re-index the listing.
