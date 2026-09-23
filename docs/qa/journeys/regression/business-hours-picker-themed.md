---
journey: business-hours-picker-themed
plugin: wb-listora
priority: normal
roles: [subscriber]
covers: [listing-submission, business-hours, flatpickr, dark-mode]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
covers_card: 10332302692
---

# Business Hours time picker renders cleanly on any theme

Regression sentinel for the second 1.8.0 QA bounce.

## Background

Two defects: a theme's `input[type=text]:focus` rule outranked the wrapper's border reset, so a second box appeared inside the time field on focus; and flatpickr's light-only panel opened white on dark themes. The focus rule now resets border and background too, and the panel (tagged `.listora-flatpickr` on ready, since it lives on body) is mapped to Listora tokens.

## Steps

### 1. Focus
- **Action**: Add Listing > a type with Business Hours > click an opening time.
- **Expect**: one focus outline around the clock-icon field, no inner box.

### 2. Panel
- **Expect**: panel background and text follow the site's light/dark tokens; no white panel or light divider on a dark theme.

### 3. 390px
- **Expect**: no horizontal scroll; the panel fits the viewport.

### 4. Other pickers untouched
- **Expect**: a non-Listora flatpickr on the same site keeps its own styling.
