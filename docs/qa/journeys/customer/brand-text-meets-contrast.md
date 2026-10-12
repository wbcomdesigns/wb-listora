---
journey: brand-text-meets-contrast
plugin: wb-listora
priority: high
roles: [guest, owner, admin]
covers: [BC-10208336512, primary-text-token, aa-contrast, brand-escape-hatch]
prerequisites:
  - "Primary theme (BuddyX Pro) with its default accent (light mode), plus a second pass in dark mode and with a DARK accent"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, unbridged]
site_profile: required
supersedes: []
fails_on: "secondary button / type badge / active tab text in raw brand colour below 4.5:1"
---

# Brand-coloured text is readable whatever brand the site picked

## Why
A brand colour is chosen to stand out on a surface, not to be legible as 11px text on one. The theme's default accent measures 3.87:1 on white (computed 2026-10-12), under the 4.5:1 AA floor, so every secondary button, type badge, active tab and count rendered in it failed on every site that never changed the accent.

Listora cannot dictate a site's brand and should not. It uses `--listora-primary-text`, a darkened derivation, for small brand-coloured TEXT; backgrounds and borders keep the true brand. Definition (`src/variables/colors.css`): `color-mix(in srgb, var(--listora-primary) var(--listora-primary-text-mix), var(--listora-primary-text-toward))` with 65% toward `#000000` in light schemes; dark schemes flip the target to `#ffffff` at 60% (`assets/css/listora-base.css`) because a darkened brand on a dark surface read 1.7:1.

Reference numbers for the default accent: raw brand on white 3.87:1; derived text on white 7.64:1; on an 18% wash of the brand the raw brand is 3.04:1 and the derived text 6.0:1; dark scheme derived text on the dark surface 6.95:1.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Measure against the COMPOSITED backdrop (these labels sit on a tinted wash of their own colour, which makes the ratio WORSE than on plain white; a probe assuming white reports a false pass).

## Steps

### 1. Light-mode listing page
- **Action**: measure the type badge, secondary buttons ("Save", "Report") and "Add to Compare" on a {listing} page.
- **Expect**: each >= 4.5:1 (`color: var(--listora-primary-text)`; `src/components/button.css`, `src/components/badge.css`).

### 2. Member dashboard
- **Action**: as {owner} on the dashboard measure the active nav item and its count badge.
- **Expect**: >= 4.5:1 (the count on an 18% wash of its own colour was 2.71:1 before; `blocks/user-dashboard/style.css`).

### 3. Directory
- **Action**: measure the Search button and the active type pill.
- **Expect**: text on its own tint >= 4.5:1.

### 4. `--listora-fg-muted` is not mapped to the theme's muted colour
- **Expect**: Listora's own value in both BuddyX bridges (`assets/css/themes/buddyx-pro.css`, `buddyx.css`): the theme's muted token is tuned to recede, while Listora uses fg-muted for real information.

### 5. Dark accent
- **Action**: change the theme accent to a dark colour (for example `#1d3557`) and re-measure.
- **Expect**: still passing; the derivation is close to a no-op (`#1d3557` -> `#132239`, 15.96:1) and does not turn a dark brand into black mud.

### 6. Backgrounds and borders still use the true brand
- **Expect**: `--listora-primary` on fills and borders, so the directory still looks like the host site.

### 7. Dark-mode pass
- **Action**: dark colour scheme, steps 1-3 again.
- **Expect**: derived text is the lightened variant and meets >= 4.5:1 on the dark surface. At 390 nothing clips.

### 8. Escape hatch (opt-in)
- **Action**: add `:root { --listora-button-bg: var(--listora-primary-text); }` (customiser snippet); re-measure `.listora-btn--primary` (background reads `var(--listora-button-fill, var(--listora-button-bg, var(--listora-primary)))`).
- **Expect**: white text on the darkened fill reaches AA (7.64:1 for the default accent) and WITHOUT the rule the default still matches the theme's button. Both halves matter. **Positive control**: the rule is the only difference between the two states.

### Deliberate, not open
White text on a brand BACKGROUND (primary buttons, badge pills, active pagination) measures 3.87:1 with the default accent and stays that way ON PURPOSE (owner decision 2026-08-18): darkening it would stop Listora buttons matching the theme's own, which fail identically. Do not "fix" this later; owners who must pass an audit use step 8.

## Role ladder
Presentation only; identical for every viewer. Measure once as {guest} (listing, directory) and once as {owner} (dashboard); numbers do not depend on role. {admin} control not needed.

## Pass criteria
1. All brand-coloured TEXT on its real backdrop >= 4.5:1 in light and dark, default and dark accent.
2. Fills/borders keep the true brand; fg-muted is Listora's.
3. The escape hatch works and stays opt-in.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Brand text < 4.5:1 | component reads `--listora-primary` for text | `src/components/button.css` (~:132,140), `badge.css` (~:77,149), `theme-hardening.css` |
| Dashboard count < 4.5:1 | | `blocks/user-dashboard/style.css` |
| Muted text too light | bridge maps fg-muted to theme token | `assets/css/themes/buddyx-pro.css` (~:61-66), `buddyx.css` (~:81-84) |
| Dark mode text too dark | dark target not flipped | `assets/css/listora-base.css` (~:172-173, 225-226) |

## Not verified
All contrast values are computed from the token definitions and the default accent, not measured in a browser with composited backgrounds; the escape hatch and the dark pass were not applied to a live site.

## Cleanup
Remove the escape-hatch snippet and restore the theme accent.
