---
journey: credit-checkout-asks-billing
plugin: wb-listora
priority: critical
roles: [member]
covers: [credits, credits-sdk, checkout, stripe, paypal, dashboard, credit-purchase-block, card-10356281481]
prerequisites:
  - "Combo, Monetization on, a Direct credit pack with a price, Stripe (test mode) or PayPal connected"
  - "A member with no billing_first_name / billing_last_name / billing_country user meta (any new account)"
estimated_runtime_minutes: 8
---

# A member with no billing details can buy credits by Stripe or PayPal

Regression sentinel for `wb_listora_render_credit_billing_form()`, `assets/js/credit-billing.js`, and the checkout handlers in `src/blocks/user-dashboard/view.js` (Free) and `src/blocks/credit-purchase/view.js` (Pro).

## Background

Card 10356281481. The credits SDK bundled in 1.9.0 refuses to start a checkout until the buyer's required billing fields are on their account (`400 billing_incomplete`). Neither plugin rendered those fields or sent a `billing` object, so every Stripe and PayPal purchase failed for every member without hand-entered billing meta, and the card showed only a generic "please try again".

## Steps

Run steps 1-4 on BOTH surfaces: dashboard > Credits, and the page with Pro's Credit Purchase block.

### 1. First click asks for billing
Click **Buy with Stripe** on a Direct pack.
- **Expect**: no request is sent; a "Billing details" form appears above the packs with First name, Last name, Email (pre-filled), Company, VAT / GST number, Country; focus is in the first empty required field.

### 2. Gaps are named
Click Buy again with the form empty.
- **Expect**: no request; "Please fill in the highlighted fields."; first name, last name and country are marked invalid.

### 3. Purchase proceeds
Fill first name, last name, country. Click Buy.
- **Expect**: `POST /wbcom-credits/v1/wb-listora/checkout/stripe` carries a `billing` object and returns 200 with a `url`; the browser goes to the hosted checkout. The three values are saved to the member's `billing_*` user meta.

### 4. Asked once
Come back to the page.
- **Expect**: no billing form; one click goes straight to the hosted checkout.

### 5. Layout
At 1280 the fields sit in two columns; at 390 in one, each control at least 40px tall, no horizontal scroll.

### 6. Restore
Delete the member's `billing_*` user meta.

## Fail diagnostics
- Generic "We could not start checkout" and `billing_incomplete` in the console -> the template no longer calls `wb_listora_render_credit_billing_form()`, or the view script no longer calls `window.listoraCreditBilling.collect()`.
- Form never appears -> `assets/js/credit-billing.js` is not enqueued (the helper enqueues it when it renders).
