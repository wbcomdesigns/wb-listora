---
journey: credit-award-and-spend-use-major-units
plugin: wb-listora
priority: critical
roles: [member, admin]
covers: [BC-10186487388, credits, money-mode, minor-units, woocommerce-adapter, featured-upgrade, renewal]
prerequisites:
  - "Site reachable at $SITE_URL with Free + Pro active"
  - "Store currency is a 2-decimal currency (USD/EUR) — the bug is invisible at 0 decimals"
  - "WooCommerce active, one product mapped to 50 credits via wb-listora_credit_mappings"
  - "COD (or any offline gateway) enabled so an order can reach processing without a real payment"
  - "featured_credit_cost set to a non-zero whole number (e.g. 5)"
  - "Auto-login mu-plugin present (?autologin=1)"
estimated_runtime_minutes: 12
covers_card: 10186487388
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Credits are awarded and spent in MAJOR units, never the ledger's minor units

Listora registers the Credits SDK in **money mode**, so the ledger column holds integer **minor** units (a 7.40 balance is stored as `740`). Every human-facing number (a product mapping's "50 credits", `featured_credit_cost`, `low_credit_threshold`, a displayed balance) is in **major** units.

The bug class is any call site that hands a major-unit figure to a raw ledger primitive, or prints a raw ledger integer as credits. It is off by the currency exponent: 100x for USD, 1000x for BHD/KWD, and invisible for JPY, which is why a 0-decimal test currency proves nothing. The branch now lives in one place: the SDK's `Credits::topup_money()` / `hold_money()` / `deduct_*_money()`, and every reader goes through `Credits::balance_money()`.

**If this journey fails, do not fix the call site alone: re-run the static sweep in step 5.** One wrong site means the class is back.

Roles: the member (buyer / spender) is the actor; another member and logged out must not see or move this member's balance; admin is the control (Credits settings, ledger).

## Setup

- Free + Pro active, Monetization on, a 2-decimal store currency (QA site: USD, `Credits::is_money('wb-listora')` true).
- A member with a known balance (QA site: `qa_vendor_01`, user 6). Record `Credits::balance_money( 'wb-listora', 6 )`.
- For step 1 a WooCommerce product mapped to 50 credits (QA site: product 3627, option `wb-listora_credit_mappings` = `{"woocommerce":{"3627":50}}`) and an offline gateway (COD) or the Dummy gateway so an order can reach processing.
- For step 3 `Settings > Credit Costs > Featured` set to a non-zero whole number (QA site default: 0, so step 3 needs it set and restored).

## Steps

### 1. A purchase credits the mapped amount, not 1/100th of it
- **Action**: add the 50-credit product to the cart, check out via COD, set the order to `processing` / `completed`. Quick equivalent without a storefront: `wp eval '\WBListoraPro\Features\Credit_System::add_credits( <uid>, 50, "QA-J1- probe", "qa", "qa-j1" );'`.
- **Expect**: the balance increases by **50**, not 0.50. Ledger: the new `topup` row in `{prefix}listora_credit_ledger` has `amount = 5000` (50 x 100).
- **Fails if**: an adapter or payment path calls `Credits::topup()` instead of `Credits::topup_money()` (see `wb-listora-pro/includes/features/class-credit-system.php:399` and the SDK's `Gateways/Fulfilment.php:55`).

### 2. The displayed balance is credits, not cents
- **Action**: open `$SITE_URL/my-listings/?tab=credits` (the dashboard page is `wb_listora_get_dashboard_url()`; on the QA site `/my-listings/`) and the Add Listing page.
- **Expect**: the "Credit Balance" card reads the same figure as step 1 (e.g. `77 credits`), never one ~100x larger; the success banner's `data-start-balance` is that figure x 10^`data-balance-decimals`.
- **Fails if**: a render.php reads `Credits::get_balance()` instead of `balance_money()` (`blocks/user-dashboard/render.php:402`, `blocks/listing-submission/render.php:496`).

### 3. Featuring a listing charges the configured price
- **Action**: as the member with `featured_credit_cost = 5` and a balance >= 5, feature an owned listing (dashboard, or `POST /listora/v1/listings/{id}/feature`).
- **Expect**: balance drops by **5.00**; the ledger shows `hold` then `deduction`, each **500** minor units.
- **Fails if**: the feature path uses `hold()` / `deduct()` instead of the `_money()` variants (`includes/rest/class-listings-controller.php:1666-1690`): it would charge 0.05.

### 4. The insufficient-credits gate compares like with like
- **Action**: with the balance below `featured_credit_cost`, repeat step 3.
- **Expect**: HTTP 402 `listora_insufficient_credits`, `data.balance` equal to the dashboard figure, `data.cost` = 5.
- **Fails if**: the gate compares a minor-unit balance against a major-unit cost.

### 5. Static sweep: the class, not the instance
Run from the Free plugin root. Both must return nothing:
```bash
# No raw award primitive outside the SDK and tests.
grep -rn "Credits::topup(" --include='*.php' --exclude-dir=node_modules --exclude-dir=vendor . ../wb-listora-pro \
  | grep -v 'libs/' | grep -v '/tests/' | grep -vE ':[0-9]+:\s*(//|\*)'

# No raw balance read in product code (money mode makes it minor units).
grep -rn "Credits::get_balance(" --include='*.php' --exclude-dir=node_modules --exclude-dir=vendor . ../wb-listora-pro \
  | grep -v 'libs/' | grep -vE ':[0-9]+:\s*(//|\*)'
```
`libs/wbcom-credits-sdk` legitimately calls the raw primitives inside its own `is_money()` branches (`Gateways/Fulfilment.php:56`, `Consumer.php:381`), which is why the sweep excludes `libs/`.

### 6. Positive control and role ladder
- **Control**: step 5's sweep is only meaningful if it can find something: `grep -rn "Credits::balance_money(" ...` must list the readers (about 10 hits in Free).
- **Another member**: `GET /wbcom-credits/v1/wb-listora/balance` returns that member's own balance only; there is no way to read another user's.
- **Logged out**: the balance route returns 401; the dashboard shows the login prompt.
- **Admin**: sees ledger rows and can top up members; the admin top-up uses the same `topup_money()`.

## Known gap

`GET /wbcom-credits/v1/wb-listora/balance` keeps `balance` as the raw ledger integer by contract (see `credit-balance-rest-declares-its-units`); Listora's app reads `listora/v1` for display figures.

## Not verified

Step 1 was verified with `Credit_System::add_credits()` on a throwaway member (ledger row 5000, `balance_money` 50; user and rows deleted), not through a WooCommerce order. Steps 2 and 5 were run (balance card read as "77 credits"; sweep returns only SDK lines and one test file when `libs/` and `/tests/` are not excluded). Steps 3 and 4 were checked in source only: the QA site has `featured_credit_cost = 0`.
