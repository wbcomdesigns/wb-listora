---
journey: block-styles-versioned
plugin: wb-listora
priority: high
roles: [guest, owner, admin]
covers: [assets, blocks, cache-busting]
prerequisites:
  - "Combo site on BuddyX Pro (or Reign / BuddyX); Pro active for the Pro-block rows"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [n/a]
themes: [buddyx-pro light]
site_profile: required
supersedes: []
fails_on: "any build where blocks/*/style*.css?ver=1.0.0 is served"
---

# Block stylesheets are served under the plugin version

## Why
Every `block.json` says `"version": "1.0.0"` and core used that for the style URL, so `blocks/*/style*.css?ver=1.0.0` never changed between releases and returning visitors kept stale CSS after an upgrade.

Free calls `wb_listora_version_block_styles( $block_type, WB_LISTORA_VERSION )` after `register_block_type()`; Pro does the same with `WB_LISTORA_PRO_VERSION`. Since 2.0.0 the front-end file is the layered `style.layer.css`; the editor still loads `style.css`. Match on `blocks/<name>/style*.css`, not a fixed filename.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Pages that host the blocks: home, a {listing} page, the member dashboard, Add Listing, the Directory; Pro: Needs, Buy Credits, Compare.

## Steps

### 1. Every block style URL carries the plugin version
- **Action**: for each page: `curl -s <url> | grep -oE "(wb-listora|wb-listora-pro)/blocks/[a-z-]+/style[a-z.-]*\.css\?ver=[^'\" ]+" | sort -u`.
- **Expect**: each Free block URL ends in `?ver=<WB_LISTORA_VERSION>`, each Pro block URL in `?ver=<WB_LISTORA_PRO_VERSION>`; none reads `?ver=1.0.0`.
- **Positive control**: the version equals `grep "define( 'WB_LISTORA_VERSION'" wb-listora.php` (and the Pro constant), proving it is read from the constant, not hardcoded.

### 2. Editor side
- **Action**: as {admin} load a page in the block editor.
- **Expect**: the editor `style.css` also reads the plugin version.

### 3. Cache behaviour after a bump
- **Action**: build two versions; load as a returning visitor.
- **Expect**: the new `?ver=` is a new cache key and is requested. Not verified live (needs two release builds).

### 4. Absent block is not a defect
- **Expect**: a block missing from the list means its page does not render that block (nothing enqueued).

## Role ladder
| Rung | Action | Expect |
|---|---|---|
| {guest} | step 1 | versions as above |
| {owner} | step 1 | identical versions (URLs are not user dependent) |
| {admin} | steps 1-2 | identical; editor sheet versioned |

## Pass criteria
1. Every Free/Pro block style URL ends in its plugin version.
2. None reads `?ver=1.0.0`.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| `?ver=1.0.0` on a block style | `wb_listora_version_block_styles()` no longer called after `register_block_type()` | `includes/class-plugin.php` `register_blocks()` (~:719) |
| Pro block `?ver=1.0.0` | Pro block loop lost the call | `wb-listora-pro/includes/class-pro-plugin.php` (~:995) |

## Not verified
Step 3 (needs two builds); browser cache effect not tested. N/A rules: roles beyond reads, boundary input, timezone, scale.

## Cleanup
None.
