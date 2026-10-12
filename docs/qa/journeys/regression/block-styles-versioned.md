---
journey: block-styles-versioned
plugin: wb-listora
priority: high
roles: [anonymous, subscriber, administrator]
covers: [assets, blocks]
prerequisites:
  - "Combo site on BuddyX Pro (or Reign / BuddyX)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [REST/HTML only]
themes: [buddyx-pro light]
changed: true
---

# Block stylesheets are served under the plugin version

## Background

Every `block.json` says `"version": "1.0.0"` and core used that for the style URL, so `blocks/*/style*.css?ver=1.0.0` never changed between releases and returning visitors kept cached CSS. Free fixes it with `wb_listora_version_block_styles( $block_type, WB_LISTORA_VERSION )` called after `register_block_type()` (`includes/class-plugin.php:719`); Pro does the same in its block loop with `WB_LISTORA_PRO_VERSION` (`wb-listora-pro/includes/class-pro-plugin.php:995`).

Since 2.0.0 the front-end block stylesheet is the layered file `style.layer.css` (Listora stylesheets load as linked layered files); the old `style.css` is still shipped for the editor. Match on `blocks/<name>/style*.css` as below, not on a fixed filename.

## Steps

### 1. Every block style URL carries the plugin version
- **Action**: load each page below (any viewer) and collect every `blocks/*/style*.css` URL in the HTML:
  `curl -s <url> | grep -oE "(wb-listora|wb-listora-pro)/blocks/[a-z-]+/style[a-z.-]*\.css\?ver=[^'\" ]+" | sort -u`
  - `/` (listing-card, listing-categories, listing-featured, listing-grid, listing-search)
  - a listing, e.g. `/listing/fitlab-performance-gym/` (listing-card, listing-detail)
  - `/my-listings/` (listing-card, listing-grid, user-dashboard)
  - `/add-listing/` (listing-card, listing-submission)
  - `/listings/` (listing-card, listing-grid, listing-map, listing-search)
  - Pro blocks: `/needs/` (needs-grid), `/buy-credits/` (credit-purchase), `/compare-listings/` (comparison); and the Post-a-need page if the site maps one.
- **Expect** (verified 2026-10-12 as a subscriber): each Free block URL ends in `?ver=<WB_LISTORA_VERSION>` and each Pro block URL in `?ver=<WB_LISTORA_PRO_VERSION>` (both `1.9.0` in the working tree today). None reads `?ver=1.0.0`.
- **POSITIVE CONTROL**: the version appears at all: change nothing, confirm the value equals `grep "define( 'WB_LISTORA_VERSION'" wb-listora.php` / the Pro constant, i.e. the number is read from the constant and not hardcoded.

### 2. Role ladder
- The stylesheet URLs are not user-dependent. Check once logged out and once as subscriber (done) and confirm identical versions; as administrator, also load a page in the block editor and confirm the editor-side `style.css` also reads the plugin version.

### 3. Cache behaviour
- After a version bump, a returning visitor's browser requests the new URL (a new `?ver=` is a new cache key). Not exercised: needs two release builds.

## Fail diagnostics
- `?ver=1.0.0` on a block style: `wb_listora_version_block_styles()` is no longer called after `register_block_type()` in `Plugin::register_blocks()` (`includes/class-plugin.php:719`) or the Pro block loop (`wb-listora-pro/includes/class-pro-plugin.php:995`).
- A block missing from the list: its page does not render the block (nothing enqueued), not a defect.

Verified via: curl against the live pages listed (HTML grep). The browser cache effect was not tested.
