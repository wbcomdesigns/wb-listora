---
journey: block-styles-versioned
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [assets, blocks]
prerequisites:
  - "Combo, Reign or BuddyX theme"
estimated_runtime_minutes: 3
---

# Block stylesheets are served under the plugin version

## Background

Found in the 1.9.0 overlay audit. Every block.json says `"version": "1.0.0"` and core used that for the style URL, so `blocks/*/style.css?ver=1.0.0` never changed between releases and returning visitors kept the cached CSS.

## Steps

### 1. Every block style URL carries the plugin version
Load `/`, a listing, `/my-listings/`, `/add-listing/`, and each Pro block page (needs, post a need, buy credits, compare). Collect every `blocks/*/style*.css` URL in the HTML.
- **Expect**: each ends in `?ver=<WB_LISTORA_VERSION>` (Free blocks) or `?ver=<WB_LISTORA_PRO_VERSION>` (Pro blocks). None reads `?ver=1.0.0`.

## Fail diagnostics
- `?ver=1.0.0` on a block style -> `wb_listora_version_block_styles()` is no longer called after `register_block_type()` in `Plugin::register_blocks()` (Free) or the Pro block loop in `class-pro-plugin.php`.
