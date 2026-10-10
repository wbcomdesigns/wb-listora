---
journey: styles-linked-not-imported
plugin: wb-listora
priority: high
roles: [anon]
covers: [10388619256]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
---

# Frontend styles are linked, not inline @import

1.9.0 printed every Listora stylesheet as an inline `<style>@import ... layer(listora)</style>`, which no cache plugin (LiteSpeed, WP Rocket, Autoptimize) can combine or defer: 23 render-blocking requests on a page with no Listora block (Zoho #42047). Each sheet now ships a `.layer.css` twin wrapped in `@layer listora` and is printed as an ordinary `<link>`.

## Steps

### 1. Plain post, default block loading
- **Action**: `curl -s $SITE_URL/<any blog post>/`
- **Expect**: zero matches for `@import url(".*wb-listora`; every Listora stylesheet is a `<link rel='stylesheet' ... .layer.css>`.
- **On fail**: `includes/core/class-theme-defenses.php` `layer_listora_styles()`; a missing twin -> `npm run build:css` (Free) / `npm run build:rtl` (Pro).

### 2. Same page with on-demand block assets off
- **Action**: mu-plugin returning false from `should_load_block_assets_on_demand` and `should_load_separate_core_block_assets`, then repeat step 1.
- **Expect**: still zero inline `@import`; block sheets appear as `.layer.css` links.

### 3. Cascade unchanged
- **Action**: home, directory and Add Listing at 1440: hash `getComputedStyle` of the first 600 `[class*=listora]` elements; move every `*.layer.css` aside (falls back to the 1.9.0 `@import`), hash again; restore.
- **Expect**: identical hashes. Theme overrides still behave as in 1.9.0.

## Pass criteria

ALL of the following hold:
1. No inline `@import` of a Listora sheet on any frontend page, Free or Pro.
2. Computed styles identical to the 1.9.0 layering.
3. `node bin/build-layered-css.mjs --check` passes in both plugins.
