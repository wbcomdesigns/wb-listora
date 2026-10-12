---
journey: monetization-is-discoverable
plugin: wb-listora
priority: high
roles: [administrator]
covers:
  - BC 10208510255
likely_files:
  - includes/admin/class-admin.php
  - ../wb-listora-pro/includes/class-monetization.php
prerequisites:
  - "Free + Pro active; Pro feature 'monetization' ON (Settings > Features)"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark]
changed: true
---

# An owner can find monetization without already knowing where it is

Monetization spans a Settings tab (packs), a CPT (plans), a screen (coupons) and another (transactions), so discovery is the dashboard setup checklist, which carries the ORDER: packs, then a way to pay, then a plan, then verify. Free builds the checklist (7 items, `includes/admin/class-admin.php:1035` filter `wb_listora_onboarding_checklist`); Pro appends three through `Monetization::add_monetization_checklist_items()` (`wb-listora-pro/includes/class-monetization.php:31`, wired at `class-pro-plugin.php:70`): "Credit packs created", "Payment method connected", "Pricing plan published". Hidden while monetization is off.

Verified via: `wp eval` of the checklist filter and `wb_listora_get_monetization_status()` with the Pro feature toggled off and restored (`wb_listora_pro_features[monetization]`). The rendered admin dashboard was not screenshotted in this pass.

## Steps

1. **Monetization ON.** Open Listora > Dashboard (`wp-admin/admin.php?page=listora`).
   - **Expect**: the checklist ends with Credit packs created / Payment method connected / Pricing plan published, in that order, after Free's seven items (10 total). On the demo site all three are done (state `ready`, one published plan).
   - **Expect**: "Credit packs created" links to `admin.php?page=listora-settings&tab=credits#credits`; "Pricing plan published" to `edit.php?post_type=listora_plan`.
2. **A pack flips the first item.** With no sellable pack the item is NOT done (`has_credit_pack_for_sale()` `:94`, reading `wb_listora_get_purchasable_credit_packs()`); create a pack in Settings > Credits > Payments; reload.
   - **Expect**: it becomes done and "Payment method connected" is the first incomplete step.
3. **Payment method agrees with the member screens.** Configure a pack with an external product URL and no gateway.
   - **Expect**: "Payment method connected" is DONE (a member can pay; it reads `wb_listora_get_monetization_status()['state'] === 'ready'`, the same resolver as Buy Credits and the member dashboard). Fail if the checklist says not-done while the member dashboard offers a working Buy Now.
4. **Monetization OFF** (Settings > Features, "Monetization").
   - **Expect**: the three items are GONE (7 items, not 10; observed 0 Pro items) and `wb_listora_get_monetization_status()` is `disabled`. Turn it back on and the three return (control).
5. **Every incomplete item deep-links to the screen that completes it.** An item that can never become done is a defect (BC 10186092511).
6. **Role ladder.** The dashboard (`view_listora_dashboard`, virtual cap granted to anyone with `manage_listora_settings` or `edit_listora_listings`, `includes/core/class-capabilities.php:255-272`) shows the checklist to administrators and moderators alike; members and logged-out users cannot open wp-admin's Listora menu. Not browser-checked per role in this pass.
   - **Visibility caveats**: dismissing the checklist hides it for the site (`wb_listora_onboarding_dismissed`, `includes/admin/class-admin.php:1062`), and when every item is done it collapses to one line instead of ten rows, so reset the option and use a state with an incomplete item when asserting rows.
7. **390px.** Checklist rows stack, tap targets >= 40px, no horizontal scroll.

## Fail diagnostics
`wb-listora-pro/includes/class-pro-plugin.php:70` (hook), `class-monetization.php:48-70` (items), `:94` (`has_credit_pack_for_sale`), `:127` (`resolve_monetization_status`).
