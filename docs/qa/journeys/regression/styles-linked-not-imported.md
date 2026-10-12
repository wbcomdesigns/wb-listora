---
journey: styles-linked-not-imported
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [10388619256]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Node available (for the --check scripts)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Frontend styles are linked, not inline @import

1.9.0 printed every Listora stylesheet as an inline `<style>@import ... layer(listora)</style>`, which no cache plugin (LiteSpeed, WP Rocket, Autoptimize) can combine or defer. Each sheet now ships a `.layer.css` twin wrapped in `@layer listora` and is printed as an ordinary `<link rel="stylesheet">`.

## Steps

### 1. Plain post and Directory, default block loading
- **Action**: `curl -s $SITE_URL/sample-page/` and `curl -s $SITE_URL/listings/` as a logged-out visitor.
- **Expect**: zero matches for `@import url(` anywhere in the HTML; every `<link>` whose href is under `/wp-content/plugins/wb-listora/` or `/wb-listora-pro/` ends in `.layer.css`, with one named exception: `id='listora-isolation-css'` -> `assets/css/listora-isolation.css` (intentionally not layered). 2026-10-12 reference: Directory prints 13 `.layer.css` Listora links and 1 isolation link.
- **Positive control**: the Directory page has at least one Listora `.layer.css` link (proves the pattern matched something).
- **On fail**: `includes/core/class-theme-defenses.php:131` `layer_listora_styles()`; a missing twin falls back to the inline `@import` branch at line 145 -> run `npm run build:css` (Free) / `npm run build:rtl` (Pro).

### 2. Same pages with on-demand block assets off
- **Action**: mu-plugin returning false from `should_load_block_assets_on_demand` and `should_load_separate_core_block_assets`, repeat step 1.
- **Expect**: still zero inline `@import`; block sheets appear as `.layer.css` links.

### 3. Cascade unchanged
- **Action** (browser, 1440 and 390, light and dark): hash `getComputedStyle` of the first 600 `[class*=listora]` elements on home, Directory and Add Listing; move every `*.layer.css` aside (falls back to the 1.9.0 `@import`), hash again; restore.
- **Expect**: identical hashes.

### 4. Build guard
- **Action**: `node bin/build-layered-css.mjs --check` in the Free plugin root; `node ../wb-listora/bin/build-layered-css.mjs . --check` in the Pro root (Pro has no script of its own; its `build:rtl` calls Free's).
- **Expect**: "44 layered twins checked in wb-listora" and "22 layered twins checked in wb-listora-pro", exit 0.

## Role ladder
Single-role by design (a head-level output, identical for every visitor). Spot-check once as a logged-in member and as admin: no `@import` either.

## Pass criteria
1. No inline `@import` of a Listora sheet on any frontend page, Free or Pro.
2. Computed styles identical to the 1.9.0 layering.
3. Both `--check` commands pass.

## Not verified
Steps 2 and 3 (mu-plugin toggle, computed-style hash) were not run. Steps 1 and 4 were run (curl, node) on 2026-10-12.

Verified via: curl/DOM, node.
