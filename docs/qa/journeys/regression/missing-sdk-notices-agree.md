---
journey: missing-sdk-notices-agree
plugin: wb-listora
priority: normal
roles: [administrator]
covers: [bundled-sdk, admin-notices, degrade]
prerequisites:
  - "Free + Pro active; a throwaway install (this journey removes a bundled SDK folder)"
estimated_runtime_minutes: 4
covers_card: 10331467363
---

# A missing Credits SDK produces one clear message per plugin, with the same fix

Regression sentinel for the SDK degrade notices.

## Background

With `libs/wbcom-credits-sdk/src` missing (an incomplete upload), three notices appeared: Free's correct "reinstall from a complete zip", Free's "another plugin is loading an older copy" (wrong - nothing else is loaded), and Pro's "update WB Listora" (wrong - Free already passed Pro's version check). The older-copy notice now requires a loaded copy; Pro's says it is switched off and gives the same reinstall fix.

## Steps

### 1. Control - healthy install
- **Expect**: no Credits SDK notice on Listora → Settings.

### 2. Remove `wp-content/plugins/wb-listora/libs/wbcom-credits-sdk/src` (rename it)
- **Expect** on Settings and the WP dashboard: exactly two notices - "WB Listora: the bundled Credits SDK could not be loaded from a complete package..." and "WB Listora Pro is switched off because WB Listora is missing its bundled Credits SDK. Reinstall WB Listora from a complete zip...". No "another plugin" / "older copy" text. No critical error; front end 200; no fatal in debug.log.

### 3. Restore the folder
- **Expect**: the notices disappear.

## Not covered here
The genuine "older copy loaded by another plugin" notice still fires when an older SDK class is loaded; that path was not changed. Reproducing it needs an older Wbcom plugin bundling SDK < 1.5.0.

## Fail diagnostics
- "older copy" shown with the SDK missing → `wb_listora_render_credits_sdk_outdated_notice()` lost its `class_exists` gate (`wb-listora.php`).
- Pro says "update WB Listora" → `wb_listora_pro_check_free()` notice text (`wb-listora-pro.php`).
