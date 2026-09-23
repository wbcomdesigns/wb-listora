---
journey: template-view-data-gateway-button
plugin: wb-listora
priority: critical
roles: [subscriber, administrator]
covers: [10259725381, template-loader-view-data, credit-purchase-block]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora-pro active (the credit-purchase block is Pro)"
  - "A member user with a credit balance, e.g. `credituser`"
  - "Stripe configured in test mode in the `wbcom_credits_gateway_settings_wb-listora` option (enabled + publishable + secret key)"
  - "At least one direct credit pack whose checkout is the gateway (not a WooCommerce product URL)"
estimated_runtime_minutes: 6
---

# A configured gateway must reach the template

`wb_listora_get_template()` renders through `extract( $args )`, which creates the flat variables (`$gateways`) but does not define `$view_data` itself. Templates that read `$view_data['gateways']` therefore read an undefined variable and get nothing.

The customer-visible result was that Buy Credits rendered "Checkout unavailable" on **every** site, with Stripe enabled and keyed, and no buy button was ever drawn. Nothing errored: the block assembled correct data, handed it over, and the template quietly took its fallback branch.

This is a whole class of bug, not one screen. Three other callers had already hit it and each papered over it with a `$view_data['view_data'] = $view_data;` self-injection line; credit-purchase's `render.php` is the one that did not. Any future template that reads `$view_data[...]` depends on the loader still defining it.

## Setup

- Site: `$SITE_URL`
- Member: `credituser` (autologin via `?autologin=credituser`)
- Buy Credits page: the page containing the `wp:listora-pro/credit-purchase` block

Confirm the gateway really is configured before trusting a PASS — an unconfigured gateway also produces "Checkout unavailable", for a legitimate reason, and would make this journey pass for the wrong cause:

```bash
wp option get wbcom_credits_gateway_settings_wb-listora --format=json
# stripe.enabled must be true and secret_key non-empty
```

## Steps

### 1. The loader defines `$view_data`
- **Action**: `grep -cF '$view_data = $args;' includes/class-template-helpers.php`
- **Expect**: `1`
- **Note**: `-F` is required. Without it grep reads `$args` as an end-of-line anchor followed by literal `args`, which never matches and reports a false regression.
- **On fail**: `includes/class-template-helpers.php` — the `$view_data = $args;` assignment inside the `is_array( $args )` guard is gone.

### 2. The block hands the template a configured gateway
- **Action**:
  ```bash
  wp eval '
  $GLOBALS["t"] = null;
  add_filter( "wb_listora_template_args", function( $a, $n ) {
      if ( false !== strpos( $n, "credit-purchase" ) ) { $GLOBALS["t"] = $a; }
      return $a;
  }, 10, 2 );
  wp_set_current_user( <MEMBER_ID> );
  do_blocks( get_post( <BUY_CREDITS_PAGE_ID> )->post_content );
  echo json_encode( array(
      "gateways"   => $GLOBALS["t"]["gateways"] ?? "ABSENT",
      "configured" => $GLOBALS["t"]["gateways_configured"] ?? "ABSENT",
  ) );'
  ```
- **Expect**: `gateways` is a non-empty array containing `{"id":"stripe"}` and `configured` is `true`
- **On fail**: this is NOT the loader bug — the block never resolved the gateway. Inspect `wb-listora-pro/blocks/credit-purchase/render.php` and the SDK's `get_available()`. Stop here; the rest of this journey cannot be interpreted.

### 3. The rendered page shows a buy button, not the fallback
- **Action**: `playwright_navigate $SITE_URL/buy-credits/?autologin=credituser`, then
  ```js
  ({
    buttons: document.querySelectorAll('[data-listora-credits-checkout]').length,
    unavailable: document.body.innerText.includes('Checkout unavailable')
  })
  ```
- **Expect**: `buttons` >= 1 and `unavailable` is `false`
- **On fail**: regression of BC 10259725381. `$view_data` is undefined in template scope again — see `includes/class-template-helpers.php` and `wb-listora-pro/templates/blocks/credit-purchase/credit-purchase.php:66-69`.

### 4. The button carries the pack's own price, not a derived one
- **Action**:
  ```js
  [...document.querySelectorAll('[data-listora-credits-checkout]')].map(b => ({
    shown: b.closest('.listora-credits__pack').querySelector('.listora-credits__pack-price').textContent.trim(),
    cents: b.dataset.priceCents,
    nonce: !!b.dataset.restNonce,
    base:  !!b.dataset.checkoutBase
  }))
  ```
- **Expect**: for every row, `cents` equals the displayed price x100 (USD 9.99 -> 999), and `nonce` and `base` are both `true`
- **On fail**: a money bug, not a rendering one. A mismatch means the member is charged an amount other than the one displayed — treat as a release blocker.

### 5. Checkout actually opens
- **Action**: click one "Buy with Stripe" button
- **Expect**: navigation to `checkout.stripe.com`, and the Stripe page shows the same credits and amount as the pack (e.g. "100 credits / US$9.99")
- **On fail**: the button renders but the REST checkout call fails. Check `POST /wbcom-credits/v1/wb-listora/checkout/stripe` in the network panel — a 503 means the SDK has no pricing registered.

### 6. The gateway-off path still falls back correctly
- **Action**: disable Stripe (`stripe.enabled = false` in the option), reload Buy Credits as `credituser`
- **Expect**: a pack with its own `url` shows "Buy Now" pointing at that URL; a direct-only pack shows "Checkout unavailable" (member) or "No payment method configured." (admin). Restore the option afterwards.
- **On fail**: the fix broke the legacy fallback. See the gateways / `$pack_url` / unavailable precedence in `credit-purchase.php:99-147`.

### 7. Mobile
- **Action**: resize to 390x844, reload
- **Expect**: packs stack, buttons are full width and >= 40px tall, `document.documentElement.scrollWidth === 390`

## Pass criteria

ALL of the following hold:
1. The loader defines `$view_data`.
2. With a gateway configured, at least one buy button renders and "Checkout unavailable" is absent.
3. Every button's `data-price-cents` matches its displayed price.
4. Clicking a button reaches Stripe Checkout showing the same amount.
5. With the gateway disabled, packs fall back to their own URL and no buy button is drawn.
6. No horizontal overflow at 390px.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| "Checkout unavailable" while step 2 shows a configured gateway | `$view_data` undefined in template scope | `includes/class-template-helpers.php` (the `$view_data = $args;` line) |
| Step 2 shows `gateways: []` | gateway not resolved at all — not this regression | `wb-listora-pro/blocks/credit-purchase/render.php:115-156` |
| Button present but `data-price-cents` disagrees with the label | price derived instead of read from the pack | `wb-listora-pro/templates/blocks/credit-purchase/credit-purchase.php:99-126` |
| Click 503s | SDK pricing not registered in product code | `wb-listora.php` pricing registration |
| BP profile loops or the submission wizard break instead | the loader change altered an existing self-injecting caller | `class-buddy-press-integration.php:784`, `blocks/listing-submission/render.php:602` |

## Unit-level twin

`tests/unit/TemplateViewDataTest.php` pins the loader contract without a browser. Run it first — if it fails, this journey will too and the browser run is wasted:

```bash
composer phpunit -- --filter TemplateViewDataTest
```
