---
journey: site-css-and-layer-overrides
group: theme-presentation
actor: system
mode: mixed
plugin: wb-listora
priority: critical
roles: [admin, guest, owner]
covers: [additional-css-applies, listora-overrides-layer, styles-linked-not-imported, theme-isolation-optout, theme-brand-colour, token-parity, 2.0.0-css, card-10379292712, bc-10351203844]
prerequisites:
  - "Site reachable at $SITE_URL; the primary theme from the site profile (a theme with a Listora bridge) plus the unbridged theme, previewed per browser or on a scratch copy, never switched site-wide on a shared run"
  - "Save the current Additional CSS first: `wp eval 'echo wp_get_custom_css();' > <scratch>/css-backup.css` (restore at the end)"
estimated_runtime_minutes: 15
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, unbridged (twenty-series), Astra]
site_profile: required
supersedes: [ora:customer/2.0-site-css-and-layer-overrides.md, ora:regression/site-css-and-theme-colour.md]
fails_on: 06a37d67
---

# The site's own CSS reaches Listora blocks, themes stay out, and Listora follows the theme colour

## Why
1.9.x isolation hid every ordinary customer rule on Listora classes, so owners could not restyle the directory. 2.0.0: the site's Additional CSS is printed a second time inside `@layer listora-overrides` (declared after Listora's own layer), child themes wrap their rules in that layer, theme rules still cannot restyle Listora, Listora sheets are separate `<link>` layered files (not inline `@import`) so cache plugins can combine them, and `--listora-primary` follows the theme brand colour.

Colour chain: `var(--brand, var(--wp--preset--color--primary, var(--ast-global-color-0, var(--global-palette1, var(--theme-palette-color-1, var(--bb-primary-color, var(--e-global-color-primary, #2271b1)))))))`; hover `color-mix(... 85%, #000)`. Opt-out filter `wb_listora_theme_isolation`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Restore Additional CSS and delete mu-plugins in Cleanup.

## Steps

### 1. Stylesheets are linked layered files ({guest}, 1440)
- **Action**: run step 1 of `theme-presentation/styles-linked-not-inline-imported.md`, which owns "every Listora sheet is a `<link>` ending `.layer.css`, zero inline `@import`" (plus the on-demand-assets variant and the build guard).
- **Expect**: here only the extra list holds: variables, components, base and each block sheet are `.layer.css`, next to the unlayered `listora-isolation.css` and the theme bridge `themes/<theme>.layer.css`.

### 2. Additional CSS applies to Listora blocks ({admin} sets it)
- **Action**: Appearance > Customize > Additional CSS (or `wp eval 'wp_update_custom_css_post(".listora-card{border-radius:0;background:#ffeecc}.listora-card__title{font-size:40px}.listora-btn--primary{background:#000}");'`), publish; open the Directory logged out on the primary theme, Astra and the unbridged theme.
- **Expect** (first `.listora-card`): `background-color: rgb(255, 238, 204)`, `border-radius: 0px`; `.listora-card__title` 40px; `.listora-btn--primary` `rgb(0, 0, 0)`. The page HTML contains the CSS twice: plain and inside `@layer listora-overrides { ... }`.
- **Positive control**: without the Additional CSS the card is the token colour.
- **Negative control (themes stay out)**: the theme styles bare `input[type=text]` globally; `.listora-search__input` keeps Listora's own border (compare with a plain theme search field).

### 3. Child-theme rules apply when wrapped in the layer
- **Action**: mu-plugin `add_action( 'wp_head', function () { echo '<style id="qa-child">@layer listora-overrides{.listora-card{outline:3px solid rgb(0,128,0)}}</style><style id="qa-plain">.listora-card{box-shadow:0 0 0 4px rgb(255,0,0)}</style>'; }, 99 );`
- **Expect**: green outline present; the unwrapped red 4px ring is NOT applied (isolation reset neutralises unlayered theme rules on Listora classes).
- **Edge**: Additional CSS containing `@import` or `@charset` is not duplicated into the layer (invalid file); such a site wraps rules itself.

### 4. Opt-out
- **Action**: mu-plugin `add_filter( 'wb_listora_theme_isolation', '__return_false' );`
- **Expect**: unlayered `.css` versions, no `listora-isolation*.css`, no `.layer.css` links, no `@layer listora-overrides` copy; Listora still renders; theme/site CSS applies directly (1.8 behaviour).

### 5. Brand colour follows the theme, hover darkens
- **Action**: read computed `--listora-primary` on `.listora-block` and `.listora-btn--primary` background; hover.
- **Expect**: equals the theme accent (primary theme through its bridge; Astra `--ast-global-color-0`; unbridged theme.json primary), never the `#2271b1` fallback when the theme declares one; hover is a darker shade (lower L*). Also check Kadence (`--global-palette1`), Blocksy (`--theme-palette-color-1`), BuddyBoss (`--bb-primary-color`) and Elementor kits (`--e-global-color-primary`) per the 2.0.0 note. Light and dark come from tokens.

### 6. Computed styles unchanged after removing the CSS (regression eye)
- **Expect**: with Additional CSS removed, card/title/button/chips/links computed styles on the primary and unbridged themes equal those recorded before step 2 (spot-check 6 properties each); dark mode unchanged.

### 7. 390px and RTL
- **Expect**: no horizontal scroll; with `html[dir=rtl]` the `-rtl` layered twins load (`style-rtl.layer.css`) and the same Additional CSS still applies.

### 8. Every token a stylesheet reads is declared, per theme
- **Action**: `cd $WP_PATH/wp-content/plugins/wb-listora && python3 bin/token-parity-check.py .` and `python3 bin/token-parity-check.py ../wb-listora-pro --with .`; then on the primary and unbridged themes read computed `--listora-primary`, `--listora-surface`, `--listora-border`, `--listora-text` on `.listora-block`.
- **Expect**: both parity runs exit 0 with no undeclared `--listora-*` name (only the script's KNOBS may be undeclared); each computed token is a non-empty colour in light and dark.
- **Positive control**: `python3 bin/token-parity-check.py --self-test` exits 0 and reports its undeclared fixture. fails_on: 06a37d67 (58 tokens read but never declared after the v1 to v2 migration). Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {guest} | steps 1-7 (public CSS) |
| {owner} | identical computed styles (no per-role CSS) |
| {admin} | identical; admin bar does not change `.listora-card`; wp-admin Listora screens are unlayered and unaffected by Additional CSS |

## Pass criteria
1. Listora sheets are linked layered files; no `@import`.
2. A plain class rule in Additional CSS and `@layer listora-overrides` rules win on Listora classes; unlayered theme rules do not.
3. The opt-out filter restores unlayered behaviour.
4. Brand colour and hover come from the theme; nothing changes once the CSS is removed.
5. Token parity passes.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Additional CSS ignored | `wp_get_custom_css` filter not running (isolation off/admin) | `includes/core/class-theme-defenses.php` `layer_custom_css()` (~:106) |
| Inline `@import` back | linked twin missing, fallback import used | `layer_listora_styles` / `keep_listora_styles_linked`; `bin/build-layered-css.mjs` |
| Theme border on search input | isolation reset broken | `assets/css/listora-isolation.css` (layer order ~:41) |
| Brand colour stuck at fallback | colour chain | `src/variables/colors.css` (~:20) |
| Opt-out ignored | filter name | `class-theme-defenses.php` (~:90) |

## Not verified
Steps 2-7 not run on three themes live; step 8 not run live.

## Cleanup
`wp eval 'wp_update_custom_css_post(file_get_contents("<scratch>/css-backup.css"));'`; delete the mu-plugins; restore the previewed theme.
