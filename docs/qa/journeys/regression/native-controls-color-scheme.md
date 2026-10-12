---
journey: native-controls-color-scheme
plugin: wb-listora
priority: high
roles: [member]
covers: [BC-9895778531, BC-9919496983, date-picker, color-scheme, dark-mode-gate]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "BuddyX / BuddyX Pro (sets data-bx-mode on <html>; its toggle cycles light / dark / auto)"
  - "A listing type with date / time fields (Event: 3 native date/time inputs on the Details step)"
  - "Auto-login mu-plugin present (?autologin=<login>)"
estimated_runtime_minutes: 6
covers_card: 9895778531
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark]
changed: true
---

# Native date/time pickers follow the page, not the OS (BC 9895778531 sentinel)

`color-scheme` is the only thing that tells the browser how to paint the controls it owns (calendar popup, clock, spinners, scrollbars). Listora declares it on the SAME gates as the palette in `assets/css/listora-base.css`: `:root { color-scheme: light }` (`:118`), `dark` on `[data-listora-dark], [data-theme="dark"], [data-bx-mode="dark"]`, the `:root[...]` twins and `body.dark-scheme` / `body.dark-mode` (`:136-152`), and `dark` inside the `[data-bx-mode="auto"]` + `prefers-color-scheme: dark` block (`:197-204`). The same declarations exist in `listora-base-rtl.css` and the layered files `listora-base.layer.css` / `listora-base-rtl.layer.css`. A bare `@media (prefers-color-scheme: dark)` must never be added (BC 9919496983).

Verified via: Playwright as author1 at 1440 on the Event Details step (note: the wizard autosaves a draft listing once Title is filled; delete it afterwards) (computed `color-scheme` read from every date/time input; theme toggle `.bx-color-mode-toggle__btn`, OS scheme via `page.emulateMedia`). The open native popup (step 5) cannot be screenshotted by the driver and was not checked.

## Setup
- `/add-listing/` as author1, choose **Event**, Continue, fill Title / Category / Description, Continue: the Details step shows `input[type="date"]`, `input[type="datetime-local"]`, `input[type="time"]` (3 on the default Event type; recurrence/check-in/deadline fields appear only when the type defines them).
- Theme toggle: `localStorage['bx-color-mode']` is `light`, `dark` or `auto`; `<html data-bx-mode>` mirrors it (`auto` also sets the `light-mode` / `dark-mode` class from the OS).

## Steps

### 1. Light mode - root declares a scheme
- **Action**: toggle to light; `getComputedStyle( document.documentElement ).colorScheme`.
- **Expect**: `document.documentElement.dataset.bxMode === 'light'` and `colorScheme === 'light'` (not `normal`, the undeclared state the bug lived in).

### 2. Light mode - every native control inherits it
- **Action**: for every date / datetime-local / time input read `getComputedStyle( el ).colorScheme`.
- **Expect**: all `light` (observed: 3 of 3).

### 3. Dark mode - the chain flips together
- **Action**: toggle to dark.
- **Expect**: `bxMode === 'dark'`, root `colorScheme === 'dark'`, every input `dark` (observed).

### 4. OS dark must NOT flip a light-mode page (BC 9919496983 guard)
- **Action**: toggle back to light, `page.emulateMedia( { colorScheme: 'dark' } )`, re-measure without reloading, then reload and measure again.
- **Expect**: `bxMode` stays `light`, root and inputs stay `light` (observed after emulate). Control: in `auto` with OS dark the root becomes `dark` through the gated block.

### 5. Open picker in dark mode
- **Action**: in theme-dark click a `type="date"` input and screenshot to `~/work-artifacts/screenshots/YYYY-MM/`.
- **Expect**: the popup is dark and legible, not a white card on dark chrome.

### 6. Role ladder
Member only on the form. Logged out gets the sign-in prompt (no inputs); admin (control) sees the same Event form; the Settings pages use admin.css and are out of scope here.

### 7. 390px
- **Expect**: inputs full width, >= 40px tall, no horizontal scroll.

## Pass criteria
1. Root `color-scheme` is `light` or `dark`, never `normal`. 2. Light page: all date/time inputs `light`. 3. Theme-dark: all `dark`. 4. OS dark alone does not flip a theme-light page. 5. `listora-base.css`, `listora-base-rtl.css` and both `.layer.css` copies carry the declarations (`grep -c color-scheme` >= 10 in each layer file).

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Popup black on a light page | `color-scheme` undeclared again | `assets/css/listora-base.css:118` |
| Page dark, picker light | dark gate got the palette but not `color-scheme` | same file `:138` block |
| RTL only | RTL twin not updated | `assets/css/listora-base-rtl.css` (hand-maintained; `bin/build-css.mjs` generates variables/components twins only) |
| Step 4 fails | a bare `prefers-color-scheme: dark` block was added | `grep -rn prefers-color-scheme assets/css` - every hit must sit under `[data-bx-mode="auto"]` |
