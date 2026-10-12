---
journey: submission-fieldset-min-width
plugin: wb-listora
priority: high
roles: [member, logged-out, admin]
covers: [BC-10163072337, submission-details-step, fieldset-overflow, ua-stylesheet-defence]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A listing type with at least one field group whose widest child is wider than the form column (Event's Schedule group is the repro)"
  - "Member session: ?autologin=QA-J1-member"
estimated_runtime_minutes: 4
covers_card: 10163072337
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Submission field groups stay inside the form column (BC 10163072337 sentinel)

The browser UA stylesheet sets `min-width: min-content` on `<fieldset>`, so a fieldset refuses to shrink below its widest child. `.listora-submission__fieldset` carries `min-width: 0` (`blocks/listing-submission/style.css:341`) and the hand-maintained RTL twin carries it too (`style-rtl.css:341`). Containment guard: overflow pushes Continue off-screen on narrow phones.

## Steps

### 1. Reach the Details step at desktop
- **Action**: `$SITE_URL/add-listing/` at 1440x900. Pick **Event**, Continue, fill `#listora-title`, `#listora-description` (textarea), choose the first real `[name="category"]` option, Continue.
- **Expect**: `.listora-submission__step[data-step="details"]` visible with `.listora-submission__fieldset` groups. Restaurant (2026-10-12 reference) renders three.

### 2. Measure every visible fieldset against its parent
- **Action**: `browser_evaluate`, for each `.listora-submission__fieldset` with `offsetWidth > 0` read `getBoundingClientRect().width`, parent width, `getComputedStyle(f).minWidth`.
- **Expect**: computed `min-width` is `0px`; every fieldset width <= parent width (reference run Restaurant: 586 inside 590; the delta is parent padding); `document.documentElement.scrollWidth <= clientWidth`.
- **Positive control**: `getComputedStyle(document.createElement('fieldset')).minWidth` on a bare fieldset in the same page, appended to `body` and measured, is the UA `min-content` / `auto`; this proves the reset, not the UA, is what holds.

### 3. Repeat at 820 and 390
- **Expect** at 390: fieldset width <= parent width (about 286 in 290), `scrollWidth <= clientWidth`, the Continue button inside the viewport and >= 40px tall.

### 4. Dark mode
- **Action**: toggle the theme's dark mode (`html[data-bx-mode="dark"]`), re-measure at 1440.
- **Expect**: identical widths.

### 5. Role ladder
Member: steps 1-4. Logged out: `/add-listing/` shows the sign-in prompt and no fieldset (the member run is the positive control). Admin: same form (control). Edit mode (`/my-listings/` > Edit) renders the same fieldsets single-form: measure once there too.

## Pass criteria
1. Computed `min-width` on `.listora-submission__fieldset` is `0px`.
2. Every visible fieldset <= parent width at 1440 and 390.
3. No horizontal page scroll on the Details step.
4. `style.css` and `style-rtl.css` both carry the declaration.

## Fail diagnostics
| Symptom | File |
|---|---|
| Fieldset wider than parent, `min-width: min-content` | `blocks/listing-submission/style.css:322-342` |
| LTR fine, RTL overflows | `blocks/listing-submission/style-rtl.css:322-342` (hand-maintained; `bin/build-css.mjs` does not generate it) |
| Overflow persists with the reset | a child with its own intrinsic floor: give it `min-width: 0` or wrapping |

## Not verified
Run on 2026-10-12 with Restaurant (not Event) at 1440 light only: three fieldsets, `min-width: 0px`, widths <= parent. Event's Schedule/Recurrence groups, 820, 390, dark and RTL were not measured on the Details step; the `style-rtl.css` declaration was checked by grep.

Verified via: Playwright MCP (Chromium), grep.
