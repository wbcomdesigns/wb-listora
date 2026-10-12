---
journey: credits-return-claim-honest-outcome
plugin: wb-listora
priority: critical
roles: [subscriber]
covers: [credits, stripe-return, card-10258479636]
prerequisites:
  - "Stripe configured (dummy test keys are enough) and one Direct Pack (100 credits) - Settings > Credits"
  - "Pro active for step 4 (Buy Credits block)"
  - "journey_owner persona"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Returning from Stripe or PayPal reports what actually happened to the payment

After checkout the gateway sends the member to the dashboard Credits tab with `?wbcom_credits=success&credits=N&gateway=stripe&session_id=cs_...` (PayPal: `gateway=paypal&token=<order id>&PayerID=...`). The page claims the session (`POST /wp-json/wbcom-credits/v1/wb-listora/claim/{gateway}`) and polls the balance. The contract:

1. The claim answer is honest: an unknown or foreign session turns the banner into an error; it never reads "Payment received. Adding N credits..." and then "will appear shortly".
2. A reload after crediting settles (a confirmed claim settles on the current balance even though it did not rise during the page view).
3. Buy Credits (Pro) sends a `return_url` so the gateway returns to the page that claims; claim and balance URLs come from `rest_url()` (no literal `/wp-json/`).

Claim outcomes (`src/blocks/user-dashboard/view.js:809-860`): `credited` = 2xx with `received` and no `error` (also `already`, `duplicate`); `pending` = 202, or a 5xx, or `rest_cookie_invalid_nonce`, or a network error: keep polling; **everything else is `failed`** (unknown or foreign session, amount mismatch, gateway no longer configured, 409 `gateway_unavailable`).

Roles: the member returning from checkout (actor); another member cannot claim someone else's session (refused, same error banner); logged out cannot claim (401); admin as control.

## Setup

- A member (QA site `qa_vendor_01`). The Credits tab reachable (see `credits-banner-dismissible`).
- Stripe configured (dummy test keys are enough) and one Direct Pack (100 credits) for steps 1 and 4; **on the QA site no gateway is configured**, so the claim returns 409 `gateway_unavailable` for any session (verified), which this contract treats as a failure.
- Build note: `src/blocks/user-dashboard/view.js` and `wb-listora-pro/src/blocks/credit-purchase/view.js` ship through their `build/` bundles.
- Playwright: use `page.route` to stub the claim for steps 2, 3, 4b, 4c.

## Steps

### 1. Unknown session is an honest failure (real server)
- **Action**: as the member open `$SITE_URL/my-listings/?tab=credits&wbcom_credits=success&credits=100&gateway=stripe&session_id=cs_test_unknown`.
- **Expect**: within ~3 s the banner switches to `listora-dashboard__credits-banner--error` and holds a `<span role="alert">` reading "We could not find this payment on your account, so no credits were added. If you were charged, contact the site administrator." It must NOT still say "Payment received.".
- **Note**: with the gateway NOT configured the real claim is 409 `gateway_unavailable`, which also yields this error banner. With the gateway configured and an unknown session the answer is a 4xx and the same banner appears.

### 2. Already credited settles immediately
- **Action**: stub the claim with `200 {"received":true,"already":true,"credits":100}`, load the URL with another session id.
- **Expect**: the balance status line reads "100 credits added." within ~3 s although the balance did not rise during the page view.

### 3. Provider not confirmed yet
- **Action**: stub the claim with `202 {"received":true,"pending":true}`, wait ~33 s.
- **Expect**: "Your payment provider has not confirmed this payment yet. Your credits will appear once it does - refresh in a moment." (`data-pending-text`).

### 4. Buy Credits returns to the claiming page (Pro)
- **Action**: open `$SITE_URL/buy-credits/`, intercept `POST .../checkout/stripe`, click the 100-credit **Buy with Stripe**.
- **Expect**: the request body contains `"return_url":"<dashboard url>?tab=credits"`; the button carries the same `data-return-url`.

### 4b. PayPal return claims with the token
- **Action**: with PayPal configured, open `$SITE_URL/my-listings/?tab=credits&wbcom_credits=success&credits=100&gateway=paypal&token=<order id>&PayerID=X`.
- **Expect**: a `POST .../claim/paypal` is sent with the token as the session id; for an order the site never created the error banner shows; a real approved sandbox order is captured by the SDK and credited.

### 4c. Every refusal is a failure, only transient errors poll
- **Action / Expect**: stub 409 `{code:"gateway_unavailable"}` then 400 `{error:"amount_or_currency_mismatch"}`: error banner with a `role="alert"` child for both. Stub 503: keeps polling ("Confirming with your payment provider..."), then the pending copy. Stub 200 `{received:true}` with the balance request failing (500), wait ~33 s: "100 credits added." (a confirmed claim is confirmed without a balance read).

### 5. REST URLs are resolved
- **Expect**: `[data-listora-credits-banner]` has `data-claim-url="$SITE_URL/wp-json/wbcom-credits/v1/wb-listora/claim/"` and `data-balance-url=".../balance"` (verified), built from `rest_url()` (sub-directory and `?rest_route=` installs keep working); no literal `/wp-json/` in the claim/balance calls of `build/blocks/user-dashboard/view.js`.

### 6. Positive control, role ladder
- **Control**: step 2 (a claim that succeeds shows "credits added") is the control for step 1: a banner that always errors would also pass step 1.
- **Another member**: claiming a session that belongs to a different account is refused and shows the same error (the SDK verifies the session against the account); no credits move.
- **Logged out**: `POST .../claim/stripe` returns 401.
- **Admin**: same behaviour on their own account.

## Teardown

Delete `wbcom_credits_gateway_settings_wb-listora` if you created it (never print it: it holds secrets), restore `wb-listora_credit_mappings` from backup, remove any probe ledger rows.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Unknown session still "Payment received" | claim answer ignored | `src/blocks/user-dashboard/view.js:809-860` (`claimOnce`), `:870` |
| Reload never settles | settle waits for a rise above the current balance | `view.js:896-915` (`settle`, `claim === 'credited'`) |
| Buy Credits returns to home | `return_url` missing | `wb-listora-pro/src/blocks/credit-purchase/view.js`, `templates/blocks/credit-purchase/credit-purchase.php` (`data-return-url`) |
| Literal `/wp-json/` | hardcoded REST base | `templates/blocks/user-dashboard/tab-credits.php:122-123` |

## Not verified

The claim route's 409 `gateway_unavailable` for a member on an unconfigured site was called live (both `stripe` and `paypal`), the logged-out 401 on `.../claim/stripe` was called, and the banner's `data-claim-url` / `data-balance-url` / `data-failed-text` / `data-pending-text` were read from the rendered page. Every page-behaviour step (1 to 4c, banner switching, polling) was NOT run in a browser; Stripe / PayPal were not configured.
