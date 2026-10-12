---
journey: credit-purchase-paths-agree
plugin: wb-listora
priority: critical
roles: [member, admin]
covers: [10222287836, 10208510192]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WB Listora Pro active, Monetization feature ON"
  - "WooCommerce active with at least one published product"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---

# Every credit gate gives the same answer

Several functions answer "can a member buy credits here?", each once built from a different subset of the same signals; on a real site they disagreed and the one that gated the member Credits UI did not count adapter mappings, so a site selling credits through a mapped WooCommerce product hid the Credits tab from members who could genuinely buy. The fix was applied to one function and missed the others: **a partial fix is the expected failure mode here**, so this journey asserts that every gate agrees, in three configurations, instead of checking one screen.

The answer comes from the Credits SDK (`Credits::purchase_paths()`), which reads the gateway registry, the adapter registry and the `{slug}_credit_mappings` option, plus the SDK's off-site purchase URL. Free exposes it as `wb_listora_credit_purchase_paths()` (`wb-listora.php:367`); Pro adds `pack_url` through the `wb_listora_credit_purchase_paths` filter (`Monetization::add_pack_purchase_path`).

Roles: a member (the Credits tab appears or not); admin / site owner configures; logged out never sees credit chrome.

## Setup

- Pro active, Monetization feature ON (`wb_listora_pro_features.monetization` true). WooCommerce active with one published product (`PRODUCT_ID`; QA site 3627).
- Record the starting values (the option names are exact; a typo silently yields an empty list and the journey passes for the wrong reason):
  ```bash
  wp option get wb-listora_credit_mappings --format=json   # MAPPINGS_BEFORE  (hyphen, then underscore)
  wp option get wb_listora_pro_credit_packs --format=json  # PACKS_BEFORE  (legacy; its URLs no longer count)
  wp option get wb_listora_credit_purchase_url             # URL_BEFORE (an off-site URL counts as external_url)
  ```
- **Every check below must run in a fresh process** (a new `wp eval`, or a new request). The SDK registry caches the purchase URL for the request, so changing options and re-reading in the same process gives stale answers.
- Helper `show.php` (`wp eval-file show.php`):
  ```php
  $p = wb_listora_credit_purchase_paths(); $s = wb_listora_get_monetization_status();
  echo json_encode( $p ), ' free:', var_export( wb_listora_has_credit_purchase_path(), true ),
    ' pro:', var_export( \WBListoraPro\Pro_Plugin::has_purchase_path(), true ),
    ' show:', var_export( wb_listora_should_show_member_credits(), true ), ' state:', $s['state'], "\n  msg: ", $s['owner_message'], "\n";
  ```

## Steps

### 1. Configure a mapping-only purchase path
- **Action** (nested shape is the stored shape; a flat list of `{adapter,item_id,...}` rows is also accepted by the SDK):
  ```bash
  wp option update wb-listora_credit_mappings '{"woocommerce":{"PRODUCT_ID":50}}' --format=json
  wp option update wb_listora_pro_credit_packs '[]' --format=json
  wp option delete wb_listora_credit_purchase_url
  ```

### 2. All four gates agree that the site can sell
- **Action**: `wp eval-file show.php`.
- **Expect** (QA site): `{"gateway":false,"mapping":true,"external_url":false,"pack_url":false} free:true pro:true show:true state:ready`, `msg: Members can buy credits through a product mapped to credits.`
- **Fails if** any of `free`, `pro`, `show` dissents: that answer stopped reading the composite (`wb-listora.php:367`, `wb-listora-pro/includes/class-pro-plugin.php:1192`, `wb-listora.php:451`).

### 3. The Credits tab renders for a member
- **Action**: as a member open `$SITE_URL/my-listings/` (the dashboard page).
- **Expect**: the sidebar has a **Credits** nav item (`a#dash-tab-credits`, label "Credits") beside Overview, My Listings, Reviews, My Claims, Leads, Favorites, Profile.

### 4. A mapping alone is enough: no credit packs required
- **Expect**: step 2 already ran with packs `[]`: state is `ready`, **not** `no_packs`.
- **Fails if** `resolve_monetization_status()` returns `no_packs` before considering mappings (it told owners of a purchasable site that credits were "not on sale yet").

### 5. The owner is told WHICH route is live
- **Expect**: the owner message names the route: "Members can buy credits through a product mapped to credits." With an external URL as well it reads "...through a product mapped to credits, an external purchase page or a pack linking to an external checkout." (the QA site's normal state).
- **Fails if** it falls back to the bare "Members can buy credits."

### 6. NEGATIVE: an unsellable site still hides the UI
The most important step: the old behaviour existed to stop members seeing an empty storefront, and a composite that became permissive would be worse than the bug it fixed.
- **Action** (then a NEW process):
  ```bash
  wp option update wb-listora_credit_mappings '[]' --format=json
  wp option update wb_listora_pro_credit_packs '[]' --format=json
  wp option delete wb_listora_credit_purchase_url
  wp eval-file show.php
  ```
- **Expect**: `{"gateway":false,"mapping":false,"external_url":false,"pack_url":false} free:false pro:false show:false state:no_packs`, `msg: Credits are not on sale yet. Connect a payment path (Stripe, PayPal or a payment plugin) and add a credit pack so members have something to buy.`
- **Fails if** any route is true or `show` is true: members would see a Credits tab and Buy CTAs on a site that cannot take payment.

### 7. And the tab is gone from the dashboard
- **Action**: as the member open `$SITE_URL/my-listings/`.
- **Expect**: no `a#dash-tab-credits` in the sidebar.

### 8. Restore
- **Action**: `wp option update wb-listora_credit_mappings '<MAPPINGS_BEFORE>' --format=json`, same for `wb_listora_pro_credit_packs`, and `wp option update wb_listora_credit_purchase_url '<URL_BEFORE>'` if it was set. Re-run `show.php` and confirm the starting state.

### 9. Positive control and role ladder
- **Control**: step 2 (all true) beside step 6 (all false) in fresh processes proves the gates move together.
- **Logged out**: neither the Credits tab nor `/buy-credits/` packs render (`/buy-credits/` shows "Sign in to purchase credits").
- **Admin**: Settings > Credits stays reachable in both states (owners configure credits while members see nothing).

## Notes

- **Adapter availability is half the contract.** A mapping counts only when its adapter reports `is_available()`: a mapping to `woocommerce` with WooCommerce deactivated must NOT count (spot-check by deactivating WooCommerce between steps 2 and 3).
- **Do not re-introduce a hardcoded adapter list** in either plugin; the composite asks each adapter.
- An off-site `wb_listora_credit_purchase_url` (host different from the site) counts as `external_url`; a same-site URL does not.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| One gate dissents | not reading the composite | `wb-listora.php:367` (`wb_listora_credit_purchase_paths`), `:451` (`should_show_member_credits`), `wb-listora-pro/includes/class-monetization.php:315` / `class-pro-plugin.php:1192` (`has_purchase_path`) |
| `no_packs` with a mapping | status resolved before mappings | `wb-listora.php` / `includes/class-template-helpers.php:847` (`wb_listora_get_monetization_status`) |
| Tab present with nothing sellable | gate permissive | `wb-listora-pro/includes/class-pro-plugin.php:114` (`gate_credit_surfaces_on_monetization`), `blocks/user-dashboard/render.php` |

## Not verified

Steps 1, 2, 4, 5, 6 and 8 were run live with wp-cli in separate processes on 2026-10-12 and the QA site's options (`{"woocommerce":{"3627":50}}`, one legacy pack, `https://example.com/buy-credits`) were restored and re-read. Steps 3 and 7 (dashboard nav in the browser) and the WooCommerce-deactivated spot-check were NOT run.
