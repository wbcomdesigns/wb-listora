---
journey: missing-sdk-notices-agree
plugin: wb-listora
priority: normal
roles: [administrator]
covers: [bundled-sdk, admin-notices, degrade]
prerequisites:
  - "Free + Pro active; a THROWAWAY install (this journey renames a bundled SDK folder; do not run on a shared site)"
estimated_runtime_minutes: 5
covers_card: 10331467363
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light]
changed: true
---

# A missing Credits SDK produces one clear message per plugin, with the same fix

With `libs/wbcom-credits-sdk/src` absent (an incomplete upload), Free's `wb_listora_require_bundled_lib()` (call at `wb-listora.php:1495`, notice text at `:978`) reports an incomplete package, Free's older-copy notice must stay silent (`wb_listora_render_credits_sdk_outdated_notice()` `:1206` returns when `\Wbcom\Credits\Credits` is not loaded, `:1217`), and Pro (`wb-listora-pro/wb-listora-pro.php:199`) says it is switched off with the same reinstall fix.

Verified via: source read only. The folder rename was NOT performed on this shared site (it takes Pro offline and the plugin's credits with it). See `## Not verified`.

## Steps

### 1. Control - healthy install
- **Expect**: no Credits SDK notice on Listora > Settings or the WP dashboard.

### 2. Rename `wp-content/plugins/wb-listora/libs/wbcom-credits-sdk/src` (e.g. to `src.off`)
- **Expect** on Settings and the WP dashboard (as an administrator): exactly two notices:
  - "WB Listora: the bundled Credits SDK could not be loaded from a complete package, ..." (Free).
  - "WB Listora Pro is switched off because WB Listora is missing its bundled Credits SDK. Reinstall WB Listora from a complete zip to turn Pro back on." (Pro).
- No "another plugin" / "older copy" wording; no critical error; front end returns 200; no fatal in `debug.log`.

### 3. Restore the folder
- **Expect**: both notices disappear on the next load.

### 4. Role ladder
| Actor | Sees the notices |
|---|---|
| Administrator | yes (`activate_plugins`) |
| Moderator / editor | no (cap-gated) |
| Member / logged out | no; front end still renders |

## Not covered
The genuine "older copy loaded by another plugin" notice (`wb-listora.php:1226-1235`) fires only when an older SDK class is already loaded; reproducing it needs an older Wbcom plugin bundling SDK < 1.5.0.

## Not verified
Steps 2-3 in this pass (destructive). Run on a throwaway install.

## Fail diagnostics
- "older copy" shown with the SDK missing: `wb-listora.php:1217` lost its `class_exists` gate.
- Pro says "update WB Listora": `wb-listora-pro/wb-listora-pro.php:199` text.
