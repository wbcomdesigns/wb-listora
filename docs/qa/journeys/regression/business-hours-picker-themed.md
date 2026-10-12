---
journey: business-hours-picker-themed
plugin: wb-listora
priority: normal
roles: [subscriber]
covers: [listing-submission, business-hours, flatpickr, dark-mode]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member who can submit (subscriber2 / tester)"
estimated_runtime_minutes: 5
covers_card: 10332302692
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Business Hours time picker renders cleanly on any theme

Sentinel for the second 1.8.0 QA bounce.

## Background

Two defects: a theme's `input[type=text]:focus` rule outranked the wrapper's border reset, so a second box appeared inside the time field on focus; and flatpickr's light-only panel opened white on dark themes. The focus rule now resets border and background too, and the panel (tagged `.listora-flatpickr` on ready, because it lives on `<body>`: `src/blocks/listing-submission/view.js:1257-1260`) is mapped to Listora tokens. The field wrapper is `.listora-submission__hours-input-wrap` holding a clock icon and `input.listora-submission__hours-input`.

## Steps

### 1. Focus
- **Action**: Add Listing > a type with Business Hours (Business, Restaurant, Healthcare, Place) > Details, click Monday's opening time.
- **Expect**: ONE focus outline around the clock-icon field, no inner box (computed `border` of the input is `0`/none, the wrapper carries the ring).

### 2. Panel
- **Expect**: the `.flatpickr-calendar.listora-flatpickr` panel's background and text follow the site's light/dark tokens; no white panel or light divider on a dark theme.
- **POSITIVE CONTROL**: toggle the theme's dark mode and reopen: the panel's computed background changes with the scheme (a panel that stays white in dark mode is the defect).

### 3. 390px
- **Expect**: no horizontal page scroll; the panel fits the viewport (not clipped at the right edge).

### 4. Other pickers untouched
- **Expect**: a non-Listora flatpickr on the same site keeps its own styling (the rules are scoped to `.listora-flatpickr`, so a panel without that class is unchanged).

### 5. Role ladder
Member-only surface. Control: administrator sees the same field on Add Listing. A user without the submit capability gets the no-permission message, and a logged-out visitor the login prompt.

### 6. Themes
Run on buddyx-pro light and dark and on a second unknown theme (e.g. twentytwentyfive, which is installed) since the focus rule collision is theme-specific.

Verified via: source and the served Add Listing markup (wrapper/input classes, flatpickr assets). Focus ring, panel colours and 390px need a browser and were not run in this pass.
