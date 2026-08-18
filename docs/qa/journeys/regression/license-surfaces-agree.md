---
slug: license-surfaces-agree
priority: high
covers:
  - BC 10194327160
likely_files:
  - wb-listora-pro/includes/features/class-license.php
---

# Both licence activation surfaces report the same state

Pro has two activation surfaces that write different options: the EDD SL SDK's
"Manage License" modal on plugins.php (`wb-listora-pro_license_key`) and Pro's
own wizard field (`wb_listora_pro_license`). `is_valid()` always read both;
`get_key()` did not. Activating on plugins.php therefore produced a wizard
that reported the licence ACTIVE above an EMPTY key field — which reads as a
failed activation, and invites the owner to activate a second time.

## Steps

1. Activate the licence from **Plugins → WB Listora Pro → Manage License**.
2. Open **Listora → Pro Setup**, licence step.
   - **Expect:** the status reads active AND the key field is populated.
   - **Fail if:** it reports active with a blank key.
3. Reverse the order — deactivate, then activate from the wizard field.
   - **Expect:** plugins.php reflects the same active state.
4. Assert directly:
   `wp eval 'use WBListoraPro\Features\License; var_dump( License::is_valid(), License::get_key() );'`
   - **Expect:** `true` and a non-empty string agree with each other in every
     activation path.
