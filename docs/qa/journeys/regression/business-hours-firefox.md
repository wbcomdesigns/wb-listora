---
journey: business-hours-firefox
plugin: wb-listora
priority: high
roles: [subscriber]
covers: [business-hours-flatpickr, firefox-time-input]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member who can submit (tester or subscriber2; the `tester` user exists as a subscriber)"
  - "Firefox available on the host running this journey (manual fallback if not)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Business Hours flatpickr on Firefox sentinel

The submission wizard's Business Hours field must surface a flatpickr time picker (24h, 15-minute increments) instead of Firefox's native numeric `input[type=time]` spinner. Pre-fix #9856828615 round 2: Firefox showed the native spinner because the vendored flatpickr 3.x was not initialising there. Fix: vendored 4.6.13 (`assets/vendor/flatpickr/flatpickr.min.js`, enqueued with `ver=4.6.13` by `blocks/listing-submission/render.php:22-23`) plus an idempotent attach via `data-listora-flatpickr-attached` (`src/blocks/listing-submission/view.js:1238-1262`).

## Setup
- Site: `$SITE_URL`, user `tester` (`?autologin=tester`).
- Browser: Firefox. On a Chromium-only run mark this journey `skipped` with a `manual_required[]` entry pointing here: the native control it guards against only exists in Firefox.

## Steps

### 1. Open Add Listing, choose a type with Business Hours, continue to Details
- **Action**: `$SITE_URL/add-listing/?autologin=tester`, pick **Business** (also Restaurant, Healthcare, Place carry a `business_hours` field), continue.
- **Expect**: the Details step shows the Business Hours builder (`#listora-hours-builder` with seven `.listora-submission__hours-card` rows).

### 2. flatpickr CSS and JS are loaded
- **Action**: console `[!!document.querySelector('link[href*="flatpickr"]'), typeof window.flatpickr]`
- **Expect**: `[true, 'function']`. (Served HTML 2026-10-12: `flatpickr/flatpickr.min.layer.css?ver=4.6.13` and `flatpickr/flatpickr.min.js?ver=4.6.13` are in the Add Listing page.)
- **On fail**: enqueue missing: `blocks/listing-submission/render.php:22-23`.

### 3. Click a time input (Monday opening)
- **Action**: click the first `.listora-submission__hours-input` (aria-label "Monday opening time 1").
- **Expect** (Firefox): a flatpickr panel (`.flatpickr-calendar.listora-flatpickr`) opens, NOT Firefox's native time spinner; it shows hour and minute fields with 15-minute steps and 24h time (no AM/PM).
- **On fail**: regression of #9856828615 round 2. `initBusinessHoursPickers()` must call flatpickr with `enableTime`, `noCalendar`, `dateFormat: 'H:i'`, `time_24hr`, `minuteIncrement: 15` (`view.js:1247-1253`).

### 4. Pick 09:00
- **Action**: set 09:00 in the panel.
- **Expect**: input value becomes `09:00` and the panel closes on click-away.

### 5. Click another time input (Monday closing)
- **Expect**: flatpickr opens for THAT input, not the opening one. Each input attaches independently (`data-listora-flatpickr-attached="1"` on both).
- **On fail**: idempotent attach broken (`:1244` selector must exclude already-attached inputs).

### 6. POSITIVE CONTROL: a day that is Closed takes no times
- **Action**: tick Monday "Closed".
- **Expect**: Monday's time inputs are disabled and no panel opens on click. (Proves the picker attaches to enabled inputs only.)

### 7. Saved hours persist
- **Action**: complete the wizard and publish; read `SELECT day_of_week, slot, open_time, close_time FROM {prefix}listora_hours WHERE listing_id=<id>`.
- **Expect**: Monday slot 0 `open_time` = `09:00:00` (table name is `{prefix}listora_hours`, not `wp_listora_hours`).

### 8. Role ladder
Member-only surface (needs the submit capability). Control: administrator gets the same builder on Add Listing; a `customer`-role member sees the "You cannot add listings" message instead (see `add-listing-no-permission-message.md`); logged out sees the login prompt.

## Pass criteria
1. flatpickr CSS + JS loaded on the Details step.
2. Clicking time inputs opens the flatpickr panel (not Firefox's native spinner); 24h, 15-minute steps.
3. Multiple inputs attach independently (idempotent).
4. Saved values persist correctly to `{prefix}listora_hours`.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Native spinner on Firefox | flatpickr not attached | `src/blocks/listing-submission/view.js:1238` |
| flatpickr CSS missing | enqueue not registered | `blocks/listing-submission/render.php:22-23` |
| Same panel for all inputs | non-idempotent attach | `view.js:1244` flag check |
| Value does not persist | format mismatch (24h vs 12h) | flatpickr `dateFormat` must stay `H:i` |

Verified via: served Add Listing HTML as a member (assets, builder, input names, aria-labels) and source. The Firefox behaviour itself requires Firefox and was not run in this pass.
