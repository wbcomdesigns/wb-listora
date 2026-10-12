---
journey: site-css-and-theme-colour
plugin: wb-listora
priority: critical
roles: [anonymous, admin]
covers: [additional-css-applies, listora-overrides-layer, theme-brand-colour, theme-isolation-filter]
prerequisites:
  - "Site reachable at $SITE_URL; BuddyX Pro active (the site's theme); Astra and Twenty Twenty-Five (or -Four) installed, previewed per browser, never switched site-wide"
estimated_runtime_minutes: 15
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, Astra, Twenty Twenty-Five]
changed: true
---

# Site CSS reaches Listora; Listora follows the theme colour; themes still kept out

Card 10379292712 (Zoho #42025). Architecture: Listora's CSS ships as linked layered files (`assets/css/*.layer.css`, 2.0.0) under `@layer listora, listora-overrides;` (`assets/css/listora-isolation.css:41`). A site's Additional CSS is copied into `listora-overrides` by `Theme_Defenses::layer_custom_css()` (`includes/core/class-theme-defenses.php:106`), so plain class rules win over Listora defaults while a theme's bare element rules stay out. Colour: `--listora-primary` chain `var(--brand, var(--wp--preset--color--primary, var(--ast-global-color-0, var(--global-palette1, var(--theme-palette-color-1, var(--bb-primary-color, var(--e-global-color-primary, #2271b1)))))))` (`src/variables/colors.css:20`), hover `color-mix(... 85%, #000)`. Opt-out filter `wb_listora_theme_isolation` (`class-theme-defenses.php:90`).

Verified via: source read plus the live Directory's stylesheet links (`listora-components.layer.css` is linked, not `@import`ed). The Additional CSS rendering on three themes was NOT run in a browser here.

## Steps

### 1. Additional CSS
- **Action**: Appearance > Customize > Additional CSS: `.listora-card{border-radius:0;background:#ffeecc}.listora-btn--primary{background:#000}.listora-card__title{font-size:40px}`; open `$SITE_URL/listings/` on BuddyX Pro, Astra, Twenty Twenty-Five.
- **Expect**: `.listora-card` computes `background-color: rgb(255,238,204)` and square corners; title 40px; primary button black. The search input (`#listora-keyword`) still shows no theme border (themes kept out). Positive control: remove the CSS and the card returns to the Listora default.

### 2. Child-theme wrapper
- **Action**: a stylesheet with `@layer listora-overrides{.listora-card{background:#e6f4ea}}`.
- **Expect**: applied on every theme. The same rule without the wrapper does not beat Listora inside Listora roots (expected; documented).

### 3. Theme colour
- **Expect**: `getComputedStyle(document.documentElement).getPropertyValue('--listora-primary')` resolves to theme.json primary or the theme's accent token (Astra `--ast-global-color-0`); never `#2271b1` on Astra. Hover is a darker shade. BuddyX / BuddyX Pro / Twenty Twenty-Four computed styles unchanged for cards, titles, buttons, inputs, chips, links, light and dark. Also check Kadence (`--global-palette1`), Blocksy (`--theme-palette-color-1`), BuddyBoss (`--bb-primary-color`) and Elementor kits (`--e-global-color-primary`) per the 2.0.0 note.

### 4. Opt-out
- **Action**: `add_filter( 'wb_listora_theme_isolation', '__return_false' );` (temporary mu-plugin).
- **Expect**: no `listora-isolation*.css`, no `.layer.css` links, Listora still renders, customer CSS applies directly.

### 5. Role ladder
Public styling: anonymous and a logged-in member (author1) see identical computed styles; admin as control (admin bar must not change `.listora-card` styles).

## Pass criteria
1. A plain class rule in Additional CSS changes a Listora element. 2. A theme's bare element rules still do not. 3. Listora uses the theme's brand colour where the theme publishes one.

## Fail diagnostics
`assets/css/listora-isolation.css` (layer order, `:41`), `includes/core/class-theme-defenses.php:90,106`, `src/variables/colors.css:20`.
