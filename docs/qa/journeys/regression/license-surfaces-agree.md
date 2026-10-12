---
journey: license-surfaces-agree
plugin: wb-listora
priority: high
roles: [admin, editor, anonymous]
covers:
  - BC 10194327160
  - pro-license
likely_files:
  - wb-listora-pro/includes/features/class-license.php
prerequisites:
  - "Pro active. Do not deactivate the shared QA site licence: steps 1 and 3 need a disposable site or a test key"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# Both licence activation surfaces report the same state

Pro has two activation surfaces writing different options: the EDD SL SDK "Manage License" modal on plugins.php (`wb-listora-pro_license_key` / SDK status `License::SDK_KEY_OPTION`, `License::SDK_STATUS_OPTION`) and Pro's own wizard field (`License::OPTION_KEY`, `wb_listora_pro_license`). `License::is_valid()` (`class-license.php:434`) reads both; `License::get_key()` (`:523`) now falls back to the SDK key, so a wizard that reports ACTIVE never shows an empty key. The wizard's activation also writes the SDK option (`includes/admin/class-setup-wizard.php:469`).

Verified via: `wp eval-file` on the live site 2026-10-12: `is_valid=true key_len=32`, own option status active, SDK status "valid": both surfaces agree. Activation/deactivation round trips NOT run.

## Steps

1. **Activate from Plugins > WB Listora Pro > Manage License.**
2. Open Listora > **Pro Setup** (`admin.php?page=listora-pro-setup`, license step, field `#listora-pro-license-key`).
   - **Expect**: "License is already active." with the key's first 12 characters and `...`, not a blank key field.
   - **Fail if** it reports active over an empty key.
3. **Reverse**: deactivate, then activate from the wizard field.
   - **Expect**: plugins.php reflects the same active state.
4. **Assert directly**: `wp eval 'use WBListoraPro\Features\License; var_dump( License::is_valid(), strlen( License::get_key() ) );'`
   - **Expect**: `true` and a non-zero length, in every activation path. POSITIVE CONTROL: with both options empty, `false` and `0`.
5. **Role ladder**: administrator and anyone with `manage_listora_settings` can open Pro Setup; editor without it cannot; member and anonymous cannot. Admin is the actor.

## Fail diagnostics

| Symptom | File |
|---|---|
| Active with blank key | `wb-listora-pro/includes/features/class-license.php:523` `get_key()` fallback |
| Wizard activation not seen on plugins.php | `wb-listora-pro/includes/admin/class-setup-wizard.php:456-469` |

## Not verified

Steps 1-3 (would change the live licence).
