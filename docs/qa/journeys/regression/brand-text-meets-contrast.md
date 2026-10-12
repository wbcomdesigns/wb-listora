---
journey: brand-text-meets-contrast
plugin: wb-listora
priority: high
roles: [anonymous, subscriber, administrator]
covers:
  - BC 10208336512
likely_files:
  - src/variables/colors.css
  - src/components/theme-hardening.css
  - src/components/badge.css
  - src/components/button.css
  - blocks/user-dashboard/style.css
  - assets/css/themes/buddyx.css
  - assets/css/themes/buddyx-pro.css
prerequisites:
  - "BuddyX Pro active with its default accent #ee4036 (light mode), plus a second pass in dark mode and with a DARK accent"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Brand-coloured text is readable whatever brand the site picked

A brand colour is chosen to stand out on a surface, not to be legible as 11px text on one. BuddyX's default `#ee4036` measures 3.87:1 on white (computed 2026-10-12), under the 4.5:1 AA floor, so every secondary button, type badge, active tab and count rendered in it failed on every site that never changed the theme accent.

Listora cannot dictate a site's brand colour and should not. It uses `--listora-primary-text`, a darkened derivation, for small brand-coloured TEXT, while backgrounds and borders keep the true brand. Definition (`src/variables/colors.css:76-78`): `color-mix(in srgb, var(--listora-primary) var(--listora-primary-text-mix), var(--listora-primary-text-toward))` with mix 65% toward `#000000` in light schemes; dark schemes flip the target to `#ffffff` at 60% (`assets/css/listora-base.css:172-173, 225-226`) because a darkened brand on a dark surface read 1.7:1.

Reference numbers for the default accent (computed): `#ee4036` on white 3.87:1; derived text `#9b2a23` on white 7.64:1; on an 18% wash of the brand the true brand is 3.04:1 and the derived text 6.0:1; dark scheme derived `#f4837c` on `#1a1a1a` 6.95:1.

## Steps

1. **Light-mode listing page**: measure the type badge, secondary buttons ("Save", "Report") and "Add to Compare".
   - **Expect**: every one >= 4.5:1 (`color: var(--listora-primary-text)`, `src/components/button.css:132,140`, `src/components/badge.css:77,149`).
   - Measure against the COMPOSITED backdrop: these labels sit on a tinted wash of their own colour, which lifts the background toward the text and makes the ratio WORSE than the same colour on plain white. A probe that assumes a white backdrop reports a false pass.
2. **Member dashboard** (`/my-listings/`): the active nav item and its count badge.
   - **Expect**: >= 4.5:1. The count on an 18% wash of its own colour was the worst measurement on that screen at 2.71:1 (`blocks/user-dashboard/style.css`).
3. **Directory** (`/listings/`): the Search button and the active type pill.
   - **Expect**: text on its own tint >= 4.5:1.
4. **`--listora-fg-muted` is NOT mapped to the theme's muted colour** in either BuddyX bridge.
   - **Expect**: Listora's own value. `assets/css/themes/buddyx-pro.css:61-66` and `buddyx.css:81-84` explain why: BuddyX Pro's `--bx-color-fg-muted` resolves to `#757575`, tuned to recede on the theme's backgrounds, while Listora uses fg-muted for real information.
5. **Dark colour accent**: change the theme accent to a dark colour (e.g. `#1d3557`) and re-measure.
   - **Expect**: still passing; the derivation is close to a no-op (`#1d3557` -> `#132239`, 15.96:1) and must not turn a dark brand into black mud.
6. **Backgrounds and borders still use the true brand** (`--listora-primary`), so the directory still looks like the site it is installed on.
7. **Dark mode pass**: with the dark colour scheme on, steps 1-3 again: derived text is the lightened variant and meets >= 4.5:1 on the dark surface.

## Deliberate, not open

White text on a brand-coloured BACKGROUND (primary buttons, badge pills, active pagination) measures 3.87:1 with BuddyX's default accent, and stays that way ON PURPOSE. Owner decision, 2026-08-18. It cannot be fixed by darkening text: the BACKGROUND would have to darken, and Listora's buttons would then stop matching the theme's own buttons, which use the same colour and fail identically. **Do not "fix" this in a later pass.** An owner who must pass an audit opts in:

    :root { --listora-button-bg: var(--listora-primary-text); }

8. **Escape hatch**: add that rule (customiser snippet) and re-measure a primary button (`.listora-btn--primary`, background reads `var(--listora-button-fill, var(--listora-button-bg, var(--listora-primary)))`, `src/components/button.css:117`).
   - **Expect**: white text on the darkened fill reaches AA (>= 4.5:1; `#9b2a23` vs white is 7.64:1) and the DEFAULT without the rule still matches the theme's button. Both halves matter: the escape hatch has to work, and it has to stay opt-in. POSITIVE CONTROL: the rule is the only difference between the two states.

## Role ladder
Presentation only; identical for every viewer. Control: measure once logged out (listing, directory) and once as a member (dashboard); the numbers do not depend on role.

## Not verified
All contrast values above are computed from the token definitions and the default accent, not measured in a browser with composited backgrounds; the escape-hatch rule and the dark pass were not applied to the live site in this run.
