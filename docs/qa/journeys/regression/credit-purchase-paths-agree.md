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
---

# Every credit gate gives the same answer

Three functions used to answer "can a member buy credits here?", each built from a different subset of the same signals, and on a real site they disagreed. The one that gated the member Credits UI was the one that did not count adapter mappings — so a site selling credits through a mapped WooCommerce product hid the Credits tab from members who could genuinely buy (BC 10222287836).

That same bug had already been found and fixed once in a different function (BC 10208510192) and the fix did not reach the others. **This journey exists because a partial fix is the expected failure mode here.** It asserts that every gate agrees, in three configurations, rather than checking one screen.

The answer now comes from the Credits SDK (`Credits::purchase_paths()`), so it also guards the fallback path in `wb_listora_credit_purchase_paths()`.

## Setup

- Site: `$SITE_URL`
- Test user: any member (autologin via `?autologin=<username>`)
- Fixtures needed:
  - A published WooCommerce product (note its ID as `PRODUCT_ID`)
  - Monetization ON: `wb_listora_pro_features.monetization` is `true`
- Record the starting values so the site can be restored:
  ```bash
  wp option get wb-listora_credit_mappings --format=json   # → MAPPINGS_BEFORE
  wp option get wb_listora_pro_credit_packs --format=json  # → PACKS_BEFORE
  ```

## Steps

### 1. Configure a mapping-only purchase path

- **Action**:
  ```bash
  wp option update "wb-listora_credit_mappings" \
    '[{"adapter":"woocommerce","item_id":PRODUCT_ID,"item_label":"Credit Pack","credits":50}]' --format=json
  ```
- **Expect**: option written.
- **On fail**: the option key is `wb-listora_credit_mappings` — hyphen then underscore. A key typo here silently produces an empty mapping list and the rest of the journey passes for the wrong reason.

### 2. All four gates agree that the site can sell

- **Action**:
  ```bash
  wp eval '
  $p = wb_listora_credit_purchase_paths();
  echo wp_json_encode( $p ), "\n";
  echo wb_listora_has_credit_purchase_path() ? "free:TRUE\n" : "free:FALSE\n";
  echo \WBListoraPro\Pro_Plugin::has_purchase_path() ? "pro:TRUE\n" : "pro:FALSE\n";
  echo wb_listora_should_show_member_credits() ? "show:TRUE\n" : "show:FALSE\n";
  echo wb_listora_get_monetization_status()["state"], "\n";'
  ```
- **Expect**: `mapping` is `true`; `free:TRUE`, `pro:TRUE`, `show:TRUE`, state `ready`. **All four must agree** — one dissenting `FALSE` is the regression, whichever it is.
- **On fail**: whichever answer dissents has stopped reading the composite. Check `wb-listora.php::wb_listora_credit_purchase_paths()` and `wb-listora-pro/includes/class-pro-plugin.php::has_purchase_path()`.

### 3. The Credits tab renders for a member

- **Action**: `playwright_navigate $SITE_URL/my-listings/?autologin=<username>`
- **Expect**: the dashboard sidebar contains a **Credits** tab alongside Overview / My Listings / Reviews / Favorites / Profile.
- **On fail**: `blocks/user-dashboard/render.php` gates on `wb_listora_should_show_member_credits()`. If step 2 said `show:TRUE` and the tab is still absent, the block is not reading that helper.

### 4. A mapping alone is enough — no credit packs required

- **Action**:
  ```bash
  wp option update wb_listora_pro_credit_packs '[]' --format=json
  wp eval 'echo wb_listora_get_monetization_status()["state"], "\n";'
  ```
- **Expect**: `ready`. **Not `no_packs`.**
- **On fail**: `resolve_monetization_status()` has gone back to returning `no_packs` before it considers mappings. That told owners of a working, purchasable site that credits were "not on sale yet" with nothing to fix.

### 5. The owner is told WHICH route is live

- **Action**: `wp eval 'echo wb_listora_get_monetization_status()["owner_message"], "\n";'`
- **Expect**: names the route, e.g. *"Members can buy credits through a product mapped to credits."* — not the bare *"Members can buy credits."*
- **On fail**: the `ready` branch stopped reading `wb_listora_credit_purchase_paths()`. An owner who switches a gateway off and still sees a green notice cannot otherwise tell what is keeping checkout open.

### 6. NEGATIVE — an unsellable site still hides the UI

This is the most important step. The behaviour being replaced existed to stop members being shown an empty storefront, and a composite that became permissive would be a worse bug than the one it fixed.

- **Action**:
  ```bash
  wp option update "wb-listora_credit_mappings" '[]' --format=json
  wp option update wb_listora_pro_credit_packs '[]' --format=json
  wp option delete wb_listora_credit_purchase_url
  wp eval '
  echo wp_json_encode( wb_listora_credit_purchase_paths() ), "\n";
  echo wb_listora_should_show_member_credits() ? "show:TRUE\n" : "show:FALSE\n";'
  ```
- **Expect**: **every** route `false`, and `show:FALSE`.
- **On fail**: the gate has become permissive. Members will be shown a Credits tab and Buy CTAs on a site that cannot take payment.

### 7. And the tab is gone from the dashboard

- **Action**: `playwright_navigate $SITE_URL/my-listings/?autologin=<username>`
- **Expect**: **no** Credits tab in the sidebar.
- **On fail**: same as step 6 — the member-facing gate is not honouring the composite.

### 8. Restore

- **Action**:
  ```bash
  wp option update "wb-listora_credit_mappings" 'MAPPINGS_BEFORE' --format=json
  wp option update wb_listora_pro_credit_packs 'PACKS_BEFORE' --format=json
  ```
- **Expect**: site returns to its starting configuration.

## Notes

- **Adapter availability is half the contract.** A mapping counts only when its adapter reports `is_available()`. A mapping to `woocommerce` with WooCommerce deactivated must NOT count — worth spot-checking if this journey is ever extended.
- **Do not re-introduce a hardcoded adapter list.** Free used to check adapters by hand (`wc_get_product`, `pmpro_url`, …) and covered four of the SDK's five, so `woo_memberships` mappings were invisible. The composite asks each adapter instead. If a future change reintroduces a literal list of adapter slugs in either plugin, that is the regression this note exists to catch.
