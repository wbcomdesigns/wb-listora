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
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Each credit pack offers only the checkout that can sell it, on both buy surfaces

Buy Credits once drew the site's Stripe / PayPal buttons on every pack. On a WooCommerce pack that checkout is rejected by the SDK (only direct-pack credit amounts are priced), and the member saw the raw API message "Credits 50 out of bounds [100..100]." The dashboard had its own pack builder and got it right, so the two surfaces disagreed.

`wb_listora_get_purchasable_credit_packs()` (`includes/class-template-helpers.php:607`) is now the one builder for every buy surface: Direct packs carry their gateways + `price_cents` ("Buy with Stripe" / "Buy with PayPal"); WooCommerce packs "Buy Now" straight to checkout with the item in the cart (simple, purchasable products) or to the product page; subscription / membership packs "Subscribe". Checkout errors show member copy from `data-error-text`; the raw message goes to the console. Unit coverage: `tests/unit/PurchasableCreditPacksTest.php`.

Roles: a member (actor); logged out (login prompt on `/buy-credits/`); admin is the control (sees the same member-facing buttons; pack setup in Settings > Credits).

## Setup

- Pro active, Monetization on, the Buy Credits page published (`$SITE_URL/buy-credits/`, page id in option `wb_listora_pro_credits_page`).
- At least one WooCommerce credit mapping (QA site: product 3627) AND one Direct pack (Settings > Credits > "Add Direct Pack"). The QA site has NO Direct pack and no gateway, so the Direct/Stripe half needs that set up first.
- Stripe (and/or PayPal) configured with dummy test keys.

## Steps

### 1. Buy Credits buttons per pack
- **Action**: as a member open `$SITE_URL/buy-credits/`.
- **Expect**: the WooCommerce pack card (`.listora-credits__pack`) shows only **Buy Now** (`a.listora-credits__pack-buy`) linking to `$SITE_URL/checkout/?add-to-cart=<product id>` (verified: `.../checkout/?add-to-cart=3627`); the Direct pack shows **Buy with Stripe** / **Buy with PayPal** buttons (`.listora-credits__pack-buttons`); no WooCommerce pack shows a gateway button.

### 2. Dashboard agrees
- **Action**: open `$SITE_URL/my-listings/?tab=credits` (the dashboard page; `wb_listora_get_dashboard_url()`).
- **Expect**: the same buttons on the same packs as step 1 (`.listora-dashboard__credit-pack`; verified: "Buy Now" -> `.../checkout/?add-to-cart=3627`).

### 3. Checkout failure copy
- **Action**: with dummy Stripe keys click **Buy with Stripe** on the Direct pack (billing details first if asked, see `credit-checkout-asks-billing`).
- **Expect**: the checkout request returns an error (502 with dummy keys) and the card shows "We could not start checkout for this pack. Please try again, or contact the site administrator if it keeps happening."; the raw SDK message appears only in the console.

### 4. Positive control and role ladder
- **Control**: the Direct pack's gateway buttons in step 1 are the positive control for "no gateway button on the WooCommerce pack". A page where NO pack shows any button proves nothing.
- **Logged out**: `$SITE_URL/buy-credits/` shows "Sign in to purchase credits" and "You must be logged in to browse and buy credit packs." with a **Log In** link and no packs (verified).
- **Admin**: sees the same buttons as a member; pack and mapping edits live in Settings > Credits.

### 5. 390px
- **Expect**: no horizontal scroll on `/buy-credits/` and the dashboard Credits tab; pack buttons >= 40px tall; `.listora-credits__grid` collapses from 3 columns.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Gateway button on a WooCommerce pack | pack builder gives every pack the gateways | `includes/class-template-helpers.php:664-736` (only `case 'direct':` sets `gateways`) |
| Surfaces disagree | one surface has its own builder again | `wb-listora-pro/templates/blocks/credit-purchase/credit-purchase.php`, `templates/blocks/user-dashboard/tab-credits.php` |
| Raw SDK text on the card | `data-error-text` unused | `credit-purchase.php:127`, `tab-credits.php:365` |

## Not verified

Steps 1 (WooCommerce half) and 2 and the logged-out view were read live with curl as `qa_vendor_01` and anonymous. The Direct pack / Stripe half (steps 1 right half, 3) was NOT run: no Direct pack or gateway exists on the QA site. Step 5 was NOT run.
