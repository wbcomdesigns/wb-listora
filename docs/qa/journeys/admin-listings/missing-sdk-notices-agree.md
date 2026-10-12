---
journey: missing-sdk-notices-agree
group: admin-listings
actor: admin
mode: mixed
plugin: wb-listora
priority: normal
roles: [administrator, editor, member-other, anonymous]
covers: [bundled-sdk, admin-notices, degrade, "card 10331467363"]
prerequisites:
  - "Free + Pro active; a THROWAWAY install (this journey renames a bundled SDK folder; never run on a shared site)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light]
supersedes:
  - ora:regression/missing-sdk-notices-agree.md
fails_on: unknown (card 10331467363, no pre-fix sha recorded; not exercised live)
site_profile: required
---

# A missing Credits SDK produces one clear message per plugin, with the same fix

## Why
With `libs/wbcom-credits-sdk/src` absent (an incomplete upload), Free's `wb_listora_require_bundled_lib()` reports an incomplete package, Free's older-copy notice must stay silent, and Pro says it is switched off with the same reinstall fix. Contradicting messages send the owner chasing the wrong cause.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Rename is destructive: it takes Pro and credits offline until restored.

## Steps

### 1. Control: healthy install
- **Expect**: no Credits SDK notice on Listora > Settings or the WP dashboard.

### 2. Rename `wp-content/plugins/wb-listora/libs/wbcom-credits-sdk/src` (e.g. to `src.off`)
- **Expect** as {admin} on Settings and the WP dashboard: exactly two notices:
  - Free: "WB Listora: the bundled Credits SDK could not be loaded from a complete package, ..."
  - Pro: "WB Listora Pro is switched off because WB Listora is missing its bundled Credits SDK. Reinstall WB Listora from a complete zip to turn Pro back on."
- No "another plugin" / "older copy" wording; no critical error; front end returns 200; no fatal in `debug.log`.

### 3. Restore the folder
- **Expect**: both notices disappear on the next load.

### 4. The genuine older-copy case (added)
- **Action**: load an older Wbcom plugin bundling SDK < 1.5.0 first.
- **Expect**: only then does Free show the "older copy loaded by another plugin" notice. Not verified live.

## Role ladder

| Actor | Sees the notices |
|---|---|
| {admin} | yes (`activate_plugins`) |
| {editor} | no (cap-gated) |
| {other} / {guest} | no; front end still renders |

## Pass criteria
Step 2 shows exactly the two messages with the same reinstall fix; step 3 clears them; front end stays 200 throughout.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| "older copy" shown with SDK missing | `class_exists` gate lost | `wb-listora.php` `wb_listora_render_credits_sdk_outdated_notice()` |
| Pro says "update WB Listora" | Pro notice text | `wb-listora-pro/wb-listora-pro.php` |

## Not verified
Steps 2-4 (destructive; source read only).

## Cleanup
Restore the renamed folder.
