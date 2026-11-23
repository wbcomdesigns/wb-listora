---
journey: css-concat-keeps-listora-styled
group: theme-presentation
actor: customer
mode: browser
plugin: wb-listora
priority: high
roles: [guest]
covers: [card-10536417765, theme-isolation, css-concatenation]
prerequisites:
  - "Page Optimize active with CSS concatenation on (the same concat code WordPress.com runs)"
  - "A page with the Listora search and grid blocks, e.g. /listings/"
estimated_runtime_minutes: 5
last_verified: unrecorded
verified_against: free 2.1.2
viewports: [1440, 390]
themes: [reign, buddyx]
fails_on: unrecorded
site_profile: required
---

# Listora stays styled when the host combines stylesheets

## Why
Hosts that concatenate CSS build bundles from each stylesheet's src and never run `style_loader_tag`, which is where Listora moves its CSS into `@layer listora`. Unlayered Listora CSS then loses to the isolation reset and every block renders with browser defaults. `Theme_Defenses::keep_listora_styles_out_of_concat()` opts Listora handles out through `css_do_concat`.

## Steps

### 1. Listora handles are not bundled
Open `/listings/` as a guest. In the page source, no `/_static/??` bundle URL contains `wb-listora`; Listora stylesheets are linked on their own, as `.layer.css` twins or `@import ... layer(listora)`.

### 2. Blocks are styled
`getComputedStyle(document.querySelector('.listora-search__bar')).display` is `flex`; search button and cards have their Listora radius and colours, at 1440 and 390.

### 3. The rest of the site is still bundled
Theme and other plugin stylesheets still load from `/_static/??` bundles.

## Pass
All three steps hold. Fails on 2.1.1 and the first 2.1.2 zip.
