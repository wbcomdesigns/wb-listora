---
journey: site-css-and-theme-colour
plugin: wb-listora
priority: critical
roles: [anonymous, admin]
covers: [additional-css-applies, listora-overrides-layer, theme-brand-colour, theme-isolation-filter]
prerequisites:
  - "Site reachable at $SITE_URL; Reign active; Astra and Twenty Twenty-Four installed (previewed per browser, never switched site-wide)"
estimated_runtime_minutes: 15
---

# Site CSS reaches Listora; Listora follows the theme colour; themes still kept out

Card 10379292712 (Zoho #42025): 1.9.0's isolation reset blocked every ordinary customer rule on Listora classes, and themes without a bridge got the fallback blue.

## Steps

### 1. Additional CSS
- **Action**: Appearance > Customize > Additional CSS: `.listora-card{border-radius:0;background:#ffeecc}.listora-btn--primary{background:#000}.listora-card__title{font-size:40px}`; open /listings/ on Reign, Astra, Twenty Twenty-Four.
- **Expect**: card #ffeecc with square corners, title 40px, primary button black. Search input still has no theme border (themes still kept out).

### 2. Child theme wrapper
- **Action**: a stylesheet with `@layer listora-overrides{.listora-card{background:#e6f4ea}}`.
- **Expect**: applied on every theme. The same rule without the wrapper does not apply inside Listora (expected; documented).

### 3. Theme colour
- **Expect**: `--listora-primary` = theme.json primary or the theme's accent token (Astra `--ast-global-color-0`); never #2271b1 on Astra. Hover is a darker shade of that colour. Reign/BuddyX/BuddyX Pro/Twenty Twenty-Four computed styles unchanged from 1.9.0 (cards, titles, buttons, inputs, chips, links), light and dark.

### 4. Opt-out
- **Action**: `add_filter( 'wb_listora_theme_isolation', '__return_false' );`
- **Expect**: no listora-isolation.css, no .layer.css links, Listora still renders, customer CSS applies directly.
- **On fail**: `assets/css/listora-isolation.css` (layer order), `Theme_Defenses::layer_custom_css()`, `src/variables/colors.css` (`--listora-primary` chain).

## Pass criteria

ALL of the following hold:
1. A plain class rule in Additional CSS changes a Listora element.
2. A theme's bare element rules still do not.
3. Listora uses the theme's brand colour where the theme publishes one.
