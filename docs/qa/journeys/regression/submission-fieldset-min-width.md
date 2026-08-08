---
journey: submission-fieldset-min-width
plugin: wb-listora
priority: high
roles: [member]
covers: [BC-10163072337, submission-details-step, fieldset-overflow, ua-stylesheet-defence]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A listing type with at least one field group whose widest child is wider than the form column (Event's Schedule group is the repro)"
  - "Auto-login mu-plugin present (?autologin=1)"
estimated_runtime_minutes: 3
covers_card: 10163072337
---

# Submission field groups stay inside the form column (BC 10163072337 sentinel)

The browser's UA stylesheet sets `min-width: min-content` on `<fieldset>` — a
value no other element carries. A fieldset therefore REFUSES to shrink below its
widest child and bursts out of its container, no matter what width the container
has. `.listora-submission__fieldset` hit exactly this on the Details step:
measured at 1440px, the three Event fieldsets rendered **720px inside a 582px
parent**.

Fix: `min-width: 0` on `.listora-submission__fieldset` in
`blocks/listing-submission/style.css` and its hand-maintained RTL twin
`style-rtl.css`. The UA value is direction-neutral, so RTL overflows identically
without the twin — both files must carry it.

This is a *containment* guard, not a cosmetic one: overflow here pushes the
Continue button off-screen on narrow viewports, which blocks submission.

## Setup

- Site: `$SITE_URL`
- `playwright_navigate $SITE_URL/add-listing/?autologin=1`

## Steps

### 1. Reach the Details step at desktop
- **Action**: resize to 1440×900. Select the **Event** type → Continue. Fill
  `#listora-title`, `#listora-description`, pick the first `#listora-category`
  option → Continue.
- **Expect**: the `[data-step="details"]` panel is visible and renders three
  fieldsets — Schedule, Event Details, Recurrence.

### 2. Measure every visible fieldset against its parent
- **Action**: `browser_evaluate` — for each
  `.listora-submission__fieldset` with a non-zero width, read its
  `getBoundingClientRect().width`, its parent's width, and its computed
  `min-width`.
- **Expect**:
  - computed `min-width` is `0px` (NOT `min-content` / `auto`)
  - every fieldset width is **≤ its parent width** (578 ≤ 582 on the reference
    run — the 4px is the parent's padding, not overflow)
  - `document.documentElement.scrollWidth === clientWidth` (no horizontal page
    scroll)

### 3. Repeat at 390px
- **Action**: resize to 390×844, re-measure.
- **Expect**: fieldsets at 286 inside a 290 parent; `scrollWidth` stays 390. A
  fieldset wider than the viewport here is the original bug.

### 4. Repeat in dark mode
- **Action**: click the theme's light/dark toggle, re-measure at 1440.
- **Expect**: identical widths. Containment must not depend on the palette.

## Pass criteria

1. Computed `min-width` on `.listora-submission__fieldset` is `0px`
2. Every visible fieldset width ≤ its parent's width, at 1440px AND 390px
3. The page never scrolls horizontally on the Details step
4. Both `style.css` and `style-rtl.css` carry the declaration

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Fieldset wider than parent; computed `min-width: min-content` | the reset was dropped | `blocks/listing-submission/style.css` — `.listora-submission__fieldset { min-width: 0 }` |
| LTR fine, RTL overflows | RTL twin not updated alongside | `blocks/listing-submission/style-rtl.css` (hand-maintained — `bin/build-css.mjs` does NOT generate this one) |
| Overflow persists with `min-width: 0` present | a child has an intrinsic floor of its own (long `<input>` / `white-space: nowrap` row) | inspect the widest child; it needs its own `min-width: 0` or wrapping |
