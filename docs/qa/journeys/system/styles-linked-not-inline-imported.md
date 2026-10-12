---
journey: styles-linked-not-inline-imported
plugin: wb-listora
priority: high
roles: [anonymous, member-owner, administrator]
covers: [10388619256, bc-10356500955, layered-css, cache-plugin-combinable, css-lint]
prerequisites:
  - "Site reachable at $SITE_URL; Node available for the --check scripts"
  - "Free and Pro source trees available for the build guard"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged default theme]
supersedes:
  - ora:regression/styles-linked-not-imported.md
fails_on: step 5 fails on bfcde0a7 (parent of 59bde4bb); steps 1-4 pre-fix sha unknown
site_profile: required
---

# Frontend styles are linked, not inline `@import`

## Why
1.9.0 printed every Listora stylesheet as an inline `<style>@import ... layer(listora)</style>`, which no cache plugin (LiteSpeed, WP Rocket, Autoptimize) can combine or defer. Each sheet now ships a `.layer.css` twin wrapped in `@layer listora` and prints as an ordinary `<link rel="stylesheet">`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).

## Steps

### 1. Plain post and Directory, default block loading
- **Action**: `curl -s $SITE_URL/<a plain page>/` and `curl -s $SITE_URL/listings/` as {guest}.
- **Expect**: zero matches for `@import url(`; every `<link>` whose href is under `/wp-content/plugins/wb-listora/` or `/wb-listora-pro/` ends in `.layer.css`, with one named exception `id='listora-isolation-css'` -> `assets/css/listora-isolation.css` (intentionally not layered).
- **Positive control**: the Directory page has at least one Listora `.layer.css` link (the pattern matched something).
- **On fail**: `includes/core/class-theme-defenses.php` `layer_listora_styles()`; a missing twin falls back to the inline `@import` branch: run `npm run build:css` (Free) / `npm run build:rtl` (Pro).

### 2. On-demand block assets off
- **Action**: mu-plugin returning false from `should_load_block_assets_on_demand` and `should_load_separate_core_block_assets`, repeat step 1.
- **Expect**: still zero inline `@import`; block sheets appear as `.layer.css` links.

### 3. Cascade unchanged
- **Action** (browser, 1440 and 390, light and dark): hash `getComputedStyle` of the first 600 `[class*=listora]` elements on home, Directory and Add Listing; move every `*.layer.css` aside (falls back to the 1.9.0 `@import`), hash again; restore.
- **Expect**: identical hashes.

### 4. Build guard
- **Action**: `node bin/build-layered-css.mjs --check` in the Free root; `node ../wb-listora/bin/build-layered-css.mjs . --check` in the Pro root (Pro's `build:rtl` calls Free's script).
- **Expect**: "<n> layered twins checked in wb-listora" and "<n> layered twins checked in wb-listora-pro" (n equals the number of source sheets), exit 0.

### 5. Every shipped stylesheet is well-formed and its rules land on rendered elements (card 10356500955)
- **Action**: for each `assets/css/*.css`, `assets/css/admin/*.css`, `src/components/*.css` (Free) and Pro's `assets/css`, `blocks/*/style.css`, count `/*` vs `*/` outside strings with a node one-liner; then on the Add Listing page and Pro's Remove credits modal read `getComputedStyle` of `.listora-form-field`, its `label` and `.listora-form-field__control`.
- **Expect**: lint prints nothing (exit 0); `.listora-form-field` has the primitive's `display` and `gap` (not `display: block; gap: normal`); the label sits above its control at 1440 and 390.
- **Read-back**: counts equal for every file; negative control: append `/* open` to a copy of `form-field.css` and the lint reports it. Not verified live.

### 6. Logged-in output (role spot-check)
- **Expect**: as {owner} and {admin} the head also has no `@import` (both with and without the admin bar).

## Role ladder
Head-level output, identical for every visitor; {guest} is the control, {owner}/{admin} spot-checked in step 6.

## Pass criteria
1. No inline `@import` of a Listora sheet on any frontend page, Free or Pro.
2. Computed styles identical to the 1.9.0 layering.
3. Both `--check` commands pass.
4. All stylesheets are balanced and their rules apply.

## Fail diagnostics

| Symptom | File |
|---|---|
| Inline `@import` present | `includes/core/class-theme-defenses.php` (fallback branch), missing `.layer.css` twin |
| Twin count mismatch | `bin/build-layered-css.mjs` |
| Rules do not land | unbalanced comment in the component CSS (`src/components/form-field.css`) |

## Not verified
Steps 2, 3 (mu-plugin toggle, hash) and 5; steps 1 and 4 ran (curl, node) on 2026-10-12.

## Cleanup
Remove the step 2 mu-plugin; restore any moved `.layer.css` files.
