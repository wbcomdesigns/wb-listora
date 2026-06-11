---
journey: business-hours-firefox
plugin: wb-listora
priority: high
roles: [subscriber]
covers: [business-hours-flatpickr, firefox-time-input]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "tester user exists with submission cap"
  - "Firefox available on the host running this journey (manual fallback if not)"
estimated_runtime_minutes: 3
---

# Business Hours flatpickr on Firefox sentinel

Submission wizard's Business Hours field must surface a flatpickr time picker (24h, 15-min increments) instead of Firefox's native numeric spinner. Pre-fix #9856828615 round 2: Firefox showed native spinner because flatpickr v3.x in vendored asset wasn't initialising on Firefox. Round 2 fix: vendored 4.6.13 + idempotent attach via `data-listora-flatpickr-attached`.

## Setup

- Site: `$SITE_URL`
- User: `tester`
- Browser: Firefox preferred. If Chromium-only Playwright session, mark this journey `skipped` with `manual_required[]` entry pointing here.

## Steps

### 1. Open Add Listing → Business → Continue to Details
- **Action**: navigate `$SITE_URL/add-listing/?autologin=tester`, pick Business, continue
- **Expect**: Details step renders with Business Hours field

### 2. Verify flatpickr CSS + JS loaded
- **Action**: `browser_evaluate "[!!document.querySelector('link[href*=flatpickr]'), typeof window.flatpickr]"`
- **Expect**: `[true, 'function']`
- **On fail**: vendored asset not enqueued. See `blocks/listing-submission/render.php` flatpickr enqueue.

### 3. Click a time input (Monday opening)
- **Action**: click the first `.listora-submission__hours-input`
- **Expect** (Firefox-specific):
  - Flatpickr dropdown panel opens (not native spinner)
  - Panel shows hour + minute selectors with 15-min increments
- **On fail**: regression of #9856828615 round 2. Check `src/blocks/listing-submission/view.js` `initBusinessHoursPickers` function; must call flatpickr with `enableTime + noCalendar + dateFormat H:i + minuteIncrement 15`.

### 4. Pick 09:00
- **Action**: click 09:00 in the dropdown
- **Expect**: input value becomes `09:00`, dropdown closes

### 5. Click another time input
- **Action**: click Monday closing time input
- **Expect**: flatpickr opens for that input (not the Monday-opening one). Each input attaches independently.
- **On fail**: idempotent attach broken — `data-listora-flatpickr-attached` flag not preventing double-attach

### 6. Verify saved hours persist
- **Action**: complete wizard → verify `_listora_hours` row in `wp_listora_hours` for the new listing
- **Expect**: opening_time = 09:00 stored as `09:00:00`

## Pass criteria

1. Flatpickr CSS + JS loaded on Details step
2. Clicking time inputs opens flatpickr dropdown (NOT Firefox native spinner)
3. 15-min increments enforced
4. Multiple inputs attach independently (idempotent)
5. Saved values persist correctly to DB

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Native spinner appears on Firefox | flatpickr not attached | `src/blocks/listing-submission/view.js` initBusinessHoursPickers |
| Flatpickr CSS missing | enqueue not registered | `blocks/listing-submission/render.php` |
| Same flatpickr opens for all inputs | non-idempotent attach | flag check `data-listora-flatpickr-attached` |
| Input value doesn't persist | format mismatch (24h vs 12h) | flatpickr config dateFormat must match server expectation |
