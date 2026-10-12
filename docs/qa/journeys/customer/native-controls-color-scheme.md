---
journey: native-controls-color-scheme
plugin: wb-listora
priority: high
roles: [owner, guest, admin]
covers: [BC-9895778531, BC-9919496983, date-picker, color-scheme, dark-mode-gate]
prerequisites:
  - "Theme that sets `data-bx-mode` on `<html>` (BuddyX / BuddyX Pro; toggle cycles light / dark / auto)"
  - "A listing type with date / time fields ({type} = Event: 3 native date/time inputs on the Details step)"
  - "Auto-login mu-plugin present"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark]
site_profile: required
supersedes: []
fails_on: "color-scheme undeclared (normal) on the root; bare prefers-color-scheme: dark block (BC 9919496983)"
---

# Native date/time pickers follow the page's colour mode, not the OS

## Why
`color-scheme` is the only thing that tells the browser how to paint controls it owns (calendar popup, clock, spinners, scrollbars). Undeclared, a dark page showed a white picker (BC 9895778531) and a light page flipped dark under an OS-dark visitor (BC 9919496983).

Listora declares it on the same gates as the palette in `assets/css/listora-base.css`: `:root { color-scheme: light }`; `dark` on `[data-listora-dark], [data-theme="dark"], [data-bx-mode="dark"]`, the `:root[...]` twins, `body.dark-scheme` / `body.dark-mode`; and `dark` inside the `[data-bx-mode="auto"]` + `prefers-color-scheme: dark` block. Same declarations in `listora-base-rtl.css` and the layered files. A bare `@media (prefers-color-scheme: dark)` must never be added.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Sign in as {owner}; Add Listing, choose {type}, Continue, fill Title / Category / Description, Continue: the Details step shows `input[type="date"]`, `input[type="datetime-local"]`, `input[type="time"]` (3 on the default Event type).
- Theme toggle: `localStorage['bx-color-mode']` is `light`, `dark` or `auto`; `<html data-bx-mode>` mirrors it (`auto` also sets `light-mode` / `dark-mode` from the OS). The wizard autosaves a draft once Title is filled.

## Steps

### 1. Light mode: root declares a scheme
- **Action**: toggle to light; `getComputedStyle( document.documentElement ).colorScheme`.
- **Expect**: `document.documentElement.dataset.bxMode === 'light'` and `colorScheme === 'light'` (not `normal`).

### 2. Light mode: every native control inherits it
- **Action**: for every date / datetime-local / time input read computed `colorScheme`.
- **Expect**: all `light`.

### 3. Dark mode: the chain flips together
- **Action**: toggle to dark.
- **Expect**: `bxMode === 'dark'`, root `colorScheme === 'dark'`, every input `dark`.

### 4. OS dark must NOT flip a light-mode page
- **Action**: toggle to light, `page.emulateMedia( { colorScheme: 'dark' } )`, re-measure without reload, then reload and measure.
- **Expect**: `bxMode` stays `light`; root and inputs stay `light`. Control: in `auto` with OS dark the root becomes `dark` through the gated block.

### 5. Open picker in dark mode
- **Action**: in theme-dark click a `type="date"` input and screenshot to `~/work-artifacts/screenshots/YYYY-MM/`.
- **Expect**: the popup is dark and legible, not a white card on dark chrome. (The driver cannot screenshot the native popup; manual check.)

### 6. Boundary input
- **Action**: enter an out-of-range date, an empty required date, and a keyboard-typed date.
- **Expect**: the field validates with the visible error styling in both colour modes. Not verified live.

### 7. 390px
- **Expect**: inputs full width, >= 40px tall, no horizontal scroll.

### 8. Source parity
- **Action**: `grep -c color-scheme assets/css/listora-base.css assets/css/listora-base-rtl.css assets/css/listora-base.layer.css assets/css/listora-base-rtl.layer.css`; `grep -rn prefers-color-scheme assets/css`.
- **Expect**: >= 10 in each layer file; every `prefers-color-scheme` hit sits under `[data-bx-mode="auto"]`.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | all steps |
| {guest} | sign-in prompt, no inputs |
| {admin} (control) | same Event form; Settings pages use `admin.css` and are out of scope |

## Pass criteria
1. Root `color-scheme` is `light` or `dark`, never `normal`.
2. Light page: all date/time inputs `light`; theme-dark: all `dark`.
3. OS dark alone does not flip a theme-light page.
4. All four base files carry the declarations.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Popup black on a light page | `color-scheme` undeclared again | `assets/css/listora-base.css` (~:118) |
| Page dark, picker light | dark gate got the palette but not `color-scheme` | same (~:138 block) |
| RTL only | RTL twin not updated | `assets/css/listora-base-rtl.css` (hand-maintained; `bin/build-css.mjs` builds variables/components twins only) |
| Step 4 fails | a bare `prefers-color-scheme: dark` block was added | `grep -rn prefers-color-scheme assets/css` |

## Not verified
Step 5 (open native popup) cannot be screenshotted by the driver; steps 6-7 not run. Steps 1-4 observed as an author-role member at 1440 on 2026-10-12.

## Cleanup
Delete the autosaved draft listing; reset the theme colour mode.
