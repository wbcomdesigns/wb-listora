---
slug: brand-text-meets-contrast
priority: high
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
---

# Brand-coloured text is readable whatever brand the site picked

A brand colour is chosen to stand out on a surface, not to be legible as 11px
text on one. BuddyX's default #ee4036 measures 3.62:1 on white — under the
4.5:1 AA floor — so every secondary button, type badge, active tab and count
rendered in it failed on every site that never changed the theme accent.

Listora cannot dictate a site's brand colour and should not. It uses
`--listora-primary-text`, a darkened derivation, for small brand-coloured
TEXT, while backgrounds and borders keep the true brand.

## Steps

1. On a light-mode listing page, measure the type badge, secondary buttons
   ("Save", "Report"), and "Add to Compare".
   - **Expect:** every one ≥ 4.5:1.
   - Measure against the COMPOSITED backdrop: these labels sit on a tinted
     wash of their own colour, which lifts the background toward the text and
     makes the ratio WORSE than the same colour on plain white. A probe that
     assumes a white backdrop reports a false pass.
2. Member dashboard: the active nav item and its count badge.
   - **Expect:** ≥ 4.5:1. The count on an 18% wash of its own colour was the
     worst measurement on that screen at 2.71:1.
3. Directory: the Search button and the active type pill.
4. Confirm `--listora-fg-muted` is NOT mapped to the theme's muted/tagline
   colour in either BuddyX bridge.
   - **Expect:** Listora's own value. A theme's muted colour is tuned to
     recede against the theme's backgrounds; Listora paints its own and uses
     fg-muted for real information.
5. Change the theme accent to a DARK colour and re-measure.
   - **Expect:** still passing, and the derivation is close to a no-op — it
     must not turn a dark brand into black mud.
6. Confirm backgrounds and borders still use the true brand, so the directory
   still looks like the site it is installed on.

## Deliberate, not open

White text on a brand-coloured BACKGROUND (primary buttons, badge pills, active
pagination) measures 3.87:1 with BuddyX's default accent, and stays that way ON
PURPOSE. Owner decision, 2026-08-18.

It is not fixable by darkening text — the BACKGROUND would have to darken, and
Listora's buttons would then stop matching the theme's own buttons, which use
the same colour and fail identically. Diverging would fix our audit while making
every install look inconsistent, for a colour the site owner chose.

**Do not "fix" this in a later pass.** An owner who must pass an audit opts in:

    :root { --listora-button-bg: var(--listora-primary-text); }

7. Assert that rule reaches AA when applied, and that the DEFAULT still matches
   the theme. Both halves matter — the escape hatch has to work, and it has to
   stay opt-in.
