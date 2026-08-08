---
journey: native-controls-color-scheme
plugin: wb-listora
priority: high
roles: [member]
covers: [BC-9895778531, BC-9919496983, date-picker, color-scheme, dark-mode-gate]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A theme that sets [data-bx-mode] (BuddyX) — the gate the plugin's dark mode rides on"
  - "A listing type with date/datetime/time fields (Event)"
  - "Auto-login mu-plugin present (?autologin=1)"
estimated_runtime_minutes: 4
covers_card: 9895778531
---

# Native date/time pickers follow the page, not the OS (BC 9895778531 sentinel)

`color-scheme` is the only thing that tells the browser how to paint the controls
IT owns — the calendar popup on `<input type="date">`, the clock on
`type="time"`, spinners, scrollbars. No plugin CSS can restyle those popups.

Undeclared, `color-scheme` follows the **OS**. WB Listora deliberately does NOT
follow the OS (BC 9919496983: dark mode is gated on the theme's `[data-bx-mode]`
attribute, never a bare `prefers-color-scheme` query). Those two policies
disagreed: a tester on OS dark mode saw a light page with a **black** calendar
popup. That is BC 9895778531 — reported as a date-picker theming issue on the
Event submission form; root cause was one missing property.

Fix: declare `color-scheme` on the SAME gates the palette uses in
`assets/css/listora-base.css` + its hand-maintained RTL twin — `light` on
`:root`, `dark` on `[data-listora-dark], [data-theme="dark"], [data-bx-mode="dark"]`,
and `dark` inside the `[data-bx-mode="auto"]` + `prefers-color-scheme: dark`
block. Native controls can then never disagree with the page.

**This journey is also the sentinel for BC 9919496983**: if a bare
`@media (prefers-color-scheme: dark)` is ever added, step 4 fails.

## Setup

- Site: `$SITE_URL`
- `playwright_navigate $SITE_URL/add-listing/?autologin=1`
- Reach the Details step for the **Event** type (see
  `submission-fieldset-min-width.md` steps 1 for the exact walk).

## Steps

### 1. Light mode — root declares a scheme
- **Action**: `browser_evaluate` — read
  `getComputedStyle(document.documentElement).colorScheme` and
  `document.documentElement.dataset.bxMode`.
- **Expect**: `bxMode` is `light`; `colorScheme` is `light` (NOT `normal` — that
  is the undeclared state the bug lived in).

### 2. Light mode — every native control inherits it
- **Action**: `browser_evaluate` — for every
  `input[type="date"], input[type="datetime-local"], input[type="time"]`, read
  its computed `colorScheme`.
- **Expect**: all report `light`. On the Event Details step that is 7 inputs
  (start/end date+time, recurrence end, check-in/out, deadline).

### 3. Dark mode — the whole chain flips together
- **Action**: click the theme's light/dark toggle. Re-read both measurements.
- **Expect**: `bxMode` is `dark`, root `colorScheme` is `dark`, and **every**
  date/time input reports `dark`. A control still reporting `light` here is the
  regression.

### 4. OS dark must NOT flip a light-mode page (BC 9919496983 guard)
- **Action**: `page.emulateMedia({ colorScheme: 'dark' })` with the theme toggle
  back on **light**, reload, re-measure.
- **Expect**: `bxMode` stays `light`, root `colorScheme` stays `light`, all
  inputs stay `light`. The OS preference alone must change nothing — dark is
  reached only via the theme attribute.

### 5. Screenshot the open picker in dark mode
- **Action**: back in theme-dark, click a `type="date"` input to open the native
  calendar; screenshot to
  `~/Documents/work-artifacts/screenshots/YYYY-MM/`.
- **Expect**: the popup is dark and legible against the dark page — not a white
  card on dark chrome, and not black text on black.

## Pass criteria

1. Root `color-scheme` is a declared value (`light` / `dark`), never `normal`
2. Light page ⇒ every date/time/datetime input computes `light`
3. Theme-dark page ⇒ every one of them computes `dark`
4. OS dark alone does NOT flip a theme-light page (BC 9919496983 holds)
5. `listora-base.css` and `listora-base-rtl.css` both carry the declarations

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Picker popup black on a light page | `color-scheme` undeclared again — controls fell back to the OS | `assets/css/listora-base.css` — `:root { color-scheme: light }` |
| Page dark, picker light | the dark gate got the palette but not `color-scheme` | same file — the `[data-listora-dark], [data-theme="dark"], [data-bx-mode="dark"]` block |
| RTL site shows the mismatch, LTR does not | RTL twin not updated | `assets/css/listora-base-rtl.css` (hand-maintained — `bin/build-css.mjs` only generates the variables/components twins) |
| Step 4 fails: OS dark flips a light page | a bare `prefers-color-scheme: dark` block was added | grep `prefers-color-scheme` — every occurrence must be gated on `[data-bx-mode="auto"]` |
