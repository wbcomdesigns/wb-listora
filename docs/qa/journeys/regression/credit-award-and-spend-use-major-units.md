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
---

# Credits are awarded and spent in MAJOR units, never the ledger's minor units (BC 10186487388 sentinel)

Listora registers the Credits SDK in **money mode**, so the ledger column holds
integer **MINOR** units — a 7.40 balance is stored as `740`. Every human-facing
number in the product (a product mapping's "50 credits", `featured_credit_cost`,
`low_credit_threshold`, a displayed balance) is in **MAJOR** units.

The bug class is any call site that hands a major-unit figure to a raw ledger
primitive, or prints a raw ledger integer as if it were credits. It is off by the
currency exponent — **100x for USD, 1000x for BHD/KWD, and invisible for JPY**,
which is why a 0-decimal test currency proves nothing here.

It has now been found three separate times: `Consumer` (AUDIT-M), then the
payment gateways, then — via this card — all five payment-source adapters plus
nine reader sites in Free and Pro. The branch was hand-rolled at each call site
every time. It now lives in exactly one place, `Credits::award()`, and the
readers all go through `balance_money()`.

**If this journey fails, do not fix the call site alone — re-run the sweep in
step 5.** A single wrong site means the class is back.

## Setup

- `playwright_navigate $SITE_URL/?autologin=qa_vendor_01`
- Record the starting balance shown on the dashboard credits tab.

## Steps

### 1. A Woo purchase credits the mapped amount, not 1/100th of it
- **Action**: add the 50-credit product to the cart, check out via COD, then set
  the order to `processing`/`completed`.
- **Expect**: the dashboard balance increases by **50.00**, not 0.50.
- **DB assert**: the new `topup` ledger row's amount is **5000** for a 2-decimal
  currency (50 x 100), not 50.
- **Fails if**: an adapter calls `Credits::topup()` instead of `Credits::award()`.

### 2. The displayed balance is credits, not cents
- **Action**: load the user-dashboard block and the listing-submission block.
- **Expect**: both show the same figure as step 1 (e.g. `50.00`-ish), never a
  number ~100x larger.
- **Fails if**: a render.php reads `Credits::get_balance()` instead of
  `Credits::balance_money()`.

### 3. Featuring a listing charges the configured price
- **Action**: as the member, feature a listing with `featured_credit_cost = 5`.
- **Expect**: balance drops by **5.00**. The ledger shows `hold` then `deduct`,
  each **500** minor units.
- **Fails if**: the feature path uses `hold()`/`deduct()` rather than the
  `_money()` variants — it would charge 0.05 and leak revenue.

### 4. The insufficient-credits gate compares like with like
- **Action**: drop the member's balance below `featured_credit_cost`, retry.
- **Expect**: HTTP 402 with a balance in the response that matches what the
  dashboard shows.
- **Fails if**: the gate compares a minor-unit balance against a major-unit cost
  — it then lets a member start a purchase they cannot complete.

### 5. Static sweep — the class, not the instance
Run from the Free plugin root. **Both must return nothing**:

```bash
# No raw award primitive outside the SDK's own money-aware internals.
grep -rn "Credits::topup(" --include='*.php' . ../wb-listora-pro \
  | grep -v 'libs/wbcom-credits-sdk/src/Credits.php' | grep -vE ':\s*(//|\*)'

# No raw balance read in product code — money mode makes it minor units.
grep -rn "Credits::get_balance(" --include='*.php' . ../wb-listora-pro \
  | grep -v 'libs/' | grep -vE ':\s*(//|\*)'
```

Both were verified to return nothing on `1.4.2` at the time this guard landed.
The trailing `grep -vE` drops `//` and `*` comment lines, which legitimately name
the old primitives when explaining why they are not used.

`libs/wbcom-credits-sdk/src/Consumer.php` legitimately calls the raw primitives
inside its own `is_money()` branches — that is why the sweep excludes `libs/`.

## Known gap — not covered by this journey

`GET /wbcom-credits/v1/balance` (the SDK's own REST surface, registered for every
consumer) still returns the raw ledger integer. Correcting it changes a public
response value's unit, so it needs a contract-version bump rather than a silent
edit. Listora's app reads `listora/v1`, not this namespace.
