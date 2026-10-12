---
journey: template-view-data-gateway-button
plugin: wb-listora
priority: critical
roles: [subscriber, other-member, logged-out, administrator]
covers: [10259725381, template-loader-view-data, credit-purchase-block]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora-pro active (the credit-purchase block is Pro; page /buy-credits/ holds `wp:listora-pro/credit-purchase`)"
  - "FIXTURE (absent on wb-listora.local 2026-10-12: option `wbcom_credits_gateway_settings_wb-listora` is unset and packs link to WooCommerce, so the page shows `Buy Now` anchors): Stripe in test mode enabled with keys, and at least one DIRECT credit pack (adapter `direct`, not a WooCommerce product URL)"
  - "A member with a credit balance (QA-J1-member holds 5) - the previous `credituser` account does not exist here"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A configured gateway must reach the template

`wb_listora_get_template()` renders through `extract( $args )`, which creates flat variables but does not define `$view_data`. The loader now defines it (`includes/class-template-helpers.php:104-109`: `$view_data = array(); ... $view_data = $args;`, an explicit `view_data` key in `$args` still wins). Templates that read `$view_data['gateways']` or `['checkout_base']` depend on it. Before the fix Buy Credits rendered "Checkout unavailable" on every site with Stripe keyed and no buy button was drawn.

## Setup
- Member: `?autologin=QA-J1-member` (or another member with a balance).
- Confirm the gateway is really configured before trusting a PASS, since an unconfigured gateway also yields "Checkout unavailable" for a legitimate reason:
```bash
wp option get wbcom_credits_gateway_settings_wb-listora --format=json   # stripe.enabled true, secret key non-empty
```

## Steps

### 1. The loader defines `$view_data`
- **Action**: `grep -cF '$view_data = $args;' includes/class-template-helpers.php` (use `-F`; without it `$args` is read as an anchor).
- **Expect**: `1` (verified, line 109).

### 2. The block hands the template a configured gateway
- **Action**: `wp eval` with a `wb_listora_template_args` filter capturing the args for a template name containing `credit-purchase`, as the member, then `do_blocks( get_post( <BUY_CREDITS_PAGE_ID> )->post_content )`.
- **Expect**: `gateways` is a non-empty array containing an entry `id: stripe` with `label` and `gateways_configured: true`; `checkout_base` ends in `/wp-json/wbcom-credits/v1/wb-listora/checkout/`.
- **On fail**: not the loader bug: the block never resolved the gateway (`wb-listora-pro/blocks/credit-purchase/render.php:100-117`); stop here.

### 3. The rendered page shows a buy button, not the fallback
- **Action**: open `/buy-credits/` as the member.
```js
({
  buttons: document.querySelectorAll('[data-listora-credits-checkout]').length,
  unavailable: document.body.innerText.includes('Checkout unavailable')
})
```
- **Expect**: `buttons >= 1`, `unavailable` false. The pack tiles are `.listora-credits__pack`; the button is `button.listora-credits__pack-buy`. On this site today: 0 buttons and a `Buy Now` anchor per pack (pack URL path), which is the documented fallback, not a failure.
- **On fail**: regression of BC 10259725381: `$view_data` undefined in template scope.

### 4. The button carries the pack's own price
```js
[...document.querySelectorAll('[data-listora-credits-checkout]')].map(b => ({
  shown: b.closest('.listora-credits__pack').querySelector('.listora-credits__pack-price').textContent.trim(),
  cents: b.dataset.priceCents, nonce: !!b.dataset.restNonce, base: !!b.dataset.checkoutBase, gw: b.dataset.gateway
}))
```
- **Expect**: `cents` equals the displayed price x100 (9.99 -> 999) for every row; `nonce` and `base` true. A mismatch charges a different amount than displayed: release blocker.

### 5. Checkout actually opens
- **Action**: if billing details are asked first (Settings > Credits > Receipts, 2.0.0; the form renders above the grid via `wb_listora_render_credit_billing_form()`), complete them, then click "Buy with Stripe".
- **Expect**: navigation to `checkout.stripe.com` showing the same credits and amount. A 503 from `POST /wbcom-credits/v1/wb-listora/checkout/stripe` means the SDK has no pricing registered; error text on the page is Listora's wording, not the SDK's.

### 6. Gateway-off fallback
- **Action**: set `stripe.enabled = false`, reload as the member.
- **Expect**: a pack with its own `url` shows `a.listora-credits__pack-buy` "Buy Now" pointing at it, opening in the same tab (2.0.0); a direct-only pack shows "Checkout unavailable" (member) or "No payment method configured." (admin). Restore the option afterwards.

### 7. Mobile
- **Action**: 390x844 reload.
- **Expect**: packs stack, buttons full width and >= 40px tall, `document.documentElement.scrollWidth <= clientWidth`.

### 8. Role ladder
| Actor | Expect |
|---|---|
| Member | steps 3-7 |
| Another member | same page, own balance only |
| Logged out | page shows a sign-in prompt / no balance and no checkout call succeeds without the nonce |
| Admin | "No payment method configured." in the unconfigured case (control) |

## Pass criteria
1. The loader defines `$view_data`.
2. With a gateway configured, at least one buy button renders and "Checkout unavailable" is absent.
3. Every button's `data-price-cents` matches its displayed price.
4. Clicking reaches Stripe Checkout with the same amount.
5. With the gateway disabled, packs fall back to their own URL and no buy button is drawn.
6. No horizontal overflow at 390px.

## Fail diagnostics
| Symptom | File |
|---|---|
| "Checkout unavailable" with a configured gateway (step 2 ok) | `includes/class-template-helpers.php:104-109` |
| `gateways: []` | `wb-listora-pro/blocks/credit-purchase/render.php:100-117` |
| `data-price-cents` disagrees with the label | `wb-listora-pro/templates/blocks/credit-purchase/credit-purchase.php:60-67, 119-124` |
| Click 503 | pricing registration in `wb-listora.php` |
| BuddyPress profile or the submission wizard break instead | other `$view_data` self-injecting callers: `blocks/listing-submission/render.php:599` |

## Unit-level twin
`tests/unit/TemplateViewDataTest.php` pins the loader contract: run `composer phpunit -- --filter TemplateViewDataTest` first.

## Not verified
Steps 1 and the fallback side of 3 / 6 (page shows `Buy Now` and no `data-listora-credits-checkout` for QA-J1-member, no fallback text) were run via curl on 2026-10-12. Steps 2, 4, 5, 7 and 8 need the Stripe fixture and were not run; no test-mode Stripe is configured on the site.

Verified via: curl/DOM, grep.
