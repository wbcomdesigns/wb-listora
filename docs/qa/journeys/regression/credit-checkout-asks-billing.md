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
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A member with no billing details can buy credits by Stripe or PayPal

Regression sentinel for `wb_listora_render_credit_billing_form()` (`includes/class-template-helpers.php:524`), `assets/js/credit-billing.js`, and the checkout handlers in `src/blocks/user-dashboard/view.js` (Free dashboard Credits tab) and `src/blocks/credit-purchase/view.js` (Pro Buy Credits block).

## Background

The Credits SDK refuses to start a checkout until the buyer's required billing fields are on their account (`400 billing_incomplete`). Neither surface once rendered those fields or sent a `billing` object, so every Stripe / PayPal purchase failed for every member without hand-entered billing meta, with a generic "please try again". Now both surfaces render a `fieldset[data-listora-credit-billing]` (hidden) when the member's required fields are missing; the first Buy click reveals it, the second sends the values as `billing` on the checkout request.

Roles: a brand-new member with no billing meta (the actor); a member with complete billing (control: no form); logged out (no Credits surface, the Buy Credits page asks to log in); admin is not special here.

## Setup

- Pro active, Monetization on, a **Direct** credit pack with a price (Settings > Credits > "Add Direct Pack"), and Stripe (test mode, dummy keys are enough) or PayPal connected. On the QA site NO gateway is connected and the only pack is a WooCommerce mapping, so the Stripe/PayPal buttons do not render there: this journey needs a gateway configured first (`wbcom_credits_gateway_settings_wb-listora`; never print it, it holds secrets).
- A member with no `billing_first_name`, `billing_last_name`, `billing_country` user meta (any fresh account; QA site `qa_vendor_01` has none).
- Two surfaces to repeat steps 1-4 on: the dashboard `$SITE_URL/my-listings/?tab=credits` and the Buy Credits page `$SITE_URL/buy-credits/` (Pro block).

## Steps

### 1. First click asks for billing
- **Action**: click **Buy with Stripe** on a Direct pack.
- **Expect**: no checkout request is sent; `[data-listora-credit-billing]` loses `hidden`: legend "Billing details", intro "We need these once for your receipt. They are saved to your account. Fill them in, then choose Buy again.", fields First name, Last name, Email (pre-filled), Company (optional), VAT / GST number (optional), Country ("Select a country"), plus address fields where the SDK schema requires them; focus lands in the first empty required field.

### 2. Gaps are named
- **Action**: click Buy again with the form empty.
- **Expect**: no request; `.listora-credit-billing__error[role=alert]` reads "Please fill in the highlighted fields."; first name, last name and country carry `aria-invalid="true"`.

### 3. Purchase proceeds
- **Action**: fill First name, Last name, Country; click Buy.
- **Expect**: `POST /wp-json/wbcom-credits/v1/wb-listora/checkout/stripe` carries a `billing` object and returns 200 with a `url`; the browser goes to the hosted checkout. The three values are saved to the member's `billing_*` user meta. A PayPal pack behaves the same with `/checkout/paypal`.

### 4. Asked once
- **Action**: come back to the page.
- **Expect**: no billing form in the DOM (`wb_listora_render_credit_billing_form()` returns early when `Billing::missing()` is false); one click goes straight to the hosted checkout.

### 5. Positive control and role ladder
- **Control**: step 4's member (complete billing) proves the form is conditional, not always rendered; on the QA site `GET $SITE_URL/buy-credits/` as `qa_vendor_01` already contains the fieldset (verified), so a member WITH meta must not.
- **Logged out**: `$SITE_URL/buy-credits/` shows "Sign in to purchase credits" / "You must be logged in to browse and buy credit packs." with a **Log In** link and no billing fieldset (verified with curl).
- **Error copy**: a failing checkout (dummy keys) shows member copy from `data-error-text` ("We could not start checkout for this pack. Please try again, or contact the site administrator if it keeps happening."); the raw SDK message goes to the console only.

### 6. Layout
- **Expect**: at 1280 the fields sit in two columns (`.listora-credit-billing__grid`); at 390 in one, each control at least 40px tall, no horizontal page scroll.

### 7. Restore
- Delete the member's `billing_*` user meta and the test gateway settings.

## Fail diagnostics

- Generic "We could not start checkout" and `billing_incomplete` in the console: the template no longer calls `wb_listora_render_credit_billing_form()` (`templates/blocks/user-dashboard/tab-credits.php:315`, `wb-listora-pro/templates/blocks/credit-purchase/credit-purchase.php:62`), or the view script no longer calls `window.listoraCreditBilling.collect()` (`src/blocks/user-dashboard/view.js:601`, `wb-listora-pro/src/blocks/credit-purchase/view.js:64`).
- Form never appears: `assets/js/credit-billing.js` not enqueued (the helper enqueues it, `class-template-helpers.php:534`), or `reveal()` finds no `[data-listora-credit-billing]`.
- Values not sent: `billing` object dropped from the checkout body in the two view scripts above (`:638` / `:119` handle the `billing_incomplete` retry).

## Not verified

Steps 1 to 4 and 6 need a connected gateway, which the QA site lacks; they were NOT run. Verified live with curl: `/buy-credits/` and `/my-listings/?tab=credits` as `qa_vendor_01` (no billing meta) render `.listora-credit-billing` with the legend and grid classes, and the pack card shows only "Buy Now" (WooCommerce mapping).
