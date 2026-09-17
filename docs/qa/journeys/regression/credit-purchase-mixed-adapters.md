---
journey: credit-purchase-mixed-adapters
plugin: wb-listora
priority: critical
roles: [subscriber]
covers: [credits, buy-credits, user-dashboard, card-10309975260]
prerequisites:
  - "Pro active, monetization on, /buy-credits/ page published"
  - "At least one WooCommerce credit mapping AND one Direct pack (Settings > Credits > Add Direct Pack)"
  - "Stripe (and/or PayPal) configured - dummy test keys are enough"
estimated_runtime_minutes: 5
---

# Each credit pack offers only the checkout that can sell it - on both buy surfaces

Buy Credits drew the site's Stripe/PayPal buttons on every pack. On a WooCommerce pack that
checkout is rejected by the SDK (only direct-pack credit amounts are priced), and the member saw the
raw API message "Credits 50 out of bounds [100..100]." The dashboard had its own pack builder and got
it right, so the two surfaces disagreed (card 10309975260).

`wb_listora_get_purchasable_credit_packs()` is now the one builder: direct packs carry their
gateways + price_cents; WooCommerce packs "Buy Now" straight to checkout with the item in the cart;
subscription/membership packs "Subscribe". Checkout errors show member copy from `data-error-text`;
the raw message goes to the console. PHPUnit: `tests/unit/PurchasableCreditPacksTest.php`.

## Steps

### 1. Buy Credits buttons per pack
- **Action**: as a member open `/buy-credits/`
- **Expect**: WooCommerce packs show only "Buy Now" linking to `/checkout/?add-to-cart=<product>`; the Direct pack shows "Buy with Stripe" / "Buy with PayPal"; no WooCommerce pack shows a gateway button

### 2. Dashboard agrees
- **Action**: open `/my-listings/?tab=credits`
- **Expect**: the same buttons on the same packs as step 1

### 3. Checkout failure copy
- **Action**: with dummy Stripe keys click "Buy with Stripe" on the Direct pack
- **Expect**: the request returns an error (502 with dummy keys) and the card shows "We could not start checkout for this pack…"; the raw SDK message appears only in the console

### 4. 390px
- **Expect**: no horizontal scroll on `/buy-credits/`
