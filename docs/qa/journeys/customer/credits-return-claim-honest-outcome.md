---
journey: credits-return-claim-honest-outcome
plugin: wb-listora
priority: critical
roles: [subscriber, other-member, anonymous, administrator]
covers: [credits, stripe-return, paypal-return, payment-claim-banner, card-10258479636]
prerequisites:
  - "Stripe configured (test keys are enough) and one Direct Pack - Settings > Credits. On a site with no gateway the claim returns 409 `gateway_unavailable` for any session, which this contract treats as a failure"
  - "Pro active for step 4 (Buy Credits block)"
  - "A member {owner} who can reach the dashboard Credits tab"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: []
requires: "wb-listora-pro (step 4 only; skip on Free-only)"
---

# Returning from Stripe or PayPal reports what actually happened to the payment

## Why
After checkout the member lands on the dashboard Credits tab; a banner that says "Payment received" for an unknown or foreign session, or never settles after reload, misleads someone about money they paid.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Return URL: `?wbcom_credits=success&credits=N&gateway=stripe&session_id=cs_...` (PayPal: `gateway=paypal&token=<order id>&PayerID=...`). The page claims the session (`POST /wp-json/wbcom-credits/v1/{site slug}/claim/{gateway}`) and polls the balance.
- Claim outcomes (`src/blocks/user-dashboard/view.js:809-860`): `credited` = 2xx with `received` and no `error` (also `already`, `duplicate`); `pending` = 202, 5xx, `rest_cookie_invalid_nonce` or network error: keep polling; everything else is `failed` (unknown/foreign session, amount mismatch, gateway no longer configured, 409 `gateway_unavailable`).
- Playwright: use `page.route` to stub the claim for steps 2, 3, 4b, 4c. Build note: `src/blocks/user-dashboard/view.js` and Pro `src/blocks/credit-purchase/view.js` ship through their `build/` bundles.

## Steps

### 1. Unknown session is an honest failure (real server)
- **Action**: as {owner} open `$SITE_URL/my-listings/?tab=credits&wbcom_credits=success&credits=100&gateway=stripe&session_id=cs_test_unknown`.
- **Expect**: within ~3 s the banner switches to `listora-dashboard__credits-banner--error` with `<span role="alert">` "We could not find this payment on your account, so no credits were added. If you were charged, contact the site administrator." It must NOT still say "Payment received."
- **Read-back**: the claim answer is 4xx (configured gateway) or 409 `gateway_unavailable` (none); balance unchanged, no ledger rows.

### 2. Already credited settles immediately
- **Action**: stub the claim with `200 {"received":true,"already":true,"credits":100}`, load the URL with another session id.
- **Expect**: status line "100 credits added." within ~3 s although the balance did not rise during the page view.

### 3. Provider not confirmed yet
- **Action**: stub `202 {"received":true,"pending":true}`, wait ~33 s.
- **Expect**: "Your payment provider has not confirmed this payment yet. Your credits will appear once it does - refresh in a moment." (`data-pending-text`).

### 4. Buy Credits returns to the claiming page (Pro)
- **Action**: open `$SITE_URL/buy-credits/`, intercept `POST .../checkout/stripe`, click the 100-credit **Buy with Stripe**.
- **Expect**: request body contains `"return_url":"<dashboard url>?tab=credits"`; the button carries the same `data-return-url`.

### 4b. PayPal return claims with the token
- **Action**: with PayPal configured open `...&gateway=paypal&token=<order id>&PayerID=X`.
- **Expect**: `POST .../claim/paypal` is sent with the token as the session id; an order the site never created shows the error banner; a real approved sandbox order is captured by the SDK and credited.

### 4c. Every refusal is a failure, only transient errors poll
- **Action**: stub 409 `{code:"gateway_unavailable"}`, 400 `{error:"amount_or_currency_mismatch"}`, 503, and 200 `{received:true}` with the balance request failing (500).
- **Expect**: first two: error banner with a `role="alert"` child; 503: keeps polling ("Confirming with your payment provider...") then the pending copy; last: after ~33 s "100 credits added." (a confirmed claim is confirmed without a balance read).

### 5. REST URLs are resolved
- **Expect**: `[data-listora-credits-banner]` has `data-claim-url="$SITE_URL/wp-json/wbcom-credits/v1/<slug>/claim/"` and `data-balance-url=".../balance"` built from `rest_url()` (sub-directory and `?rest_route=` installs keep working); no literal `/wp-json/` in `build/blocks/user-dashboard/view.js`.
- **Read-back**: view source of the banner attributes.

### 6. Positive control and role ladder
- **Control**: step 2 (a claim that succeeds shows "credits added") proves step 1 is not a banner that always errors.
- **Another member**: claiming a session that belongs to a different account is refused with the same error banner; no credits move.
- **Logged out**: `POST .../claim/stripe` returns `401`.
- **Admin**: same behaviour on their own account.
- **Read-back**: ledger rows unchanged for the other member.

### 7. 390 and dark
- **Expect**: banner fits, no horizontal scroll, text readable. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} returning from checkout | steps 1-5 |
| {other} claiming someone else's session | error banner, no credits |
| {guest} | `401` on claim |
| {admin} (control) | same on their own account |

## Pass criteria
1. Unknown/foreign/refused claims show the error banner, never "Payment received".
2. A confirmed claim settles even if the balance did not rise; transient errors poll then show the pending copy.
3. Buy Credits sends `return_url`; claim and balance URLs come from `rest_url()`.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Unknown session still "Payment received" | claim answer ignored | `src/blocks/user-dashboard/view.js:809-860` (`claimOnce`), `:870` |
| Reload never settles | settle waits for a rise | `view.js:896-915` (`settle`, `claim === 'credited'`) |
| Buy Credits returns to home | `return_url` missing | `wb-listora-pro/src/blocks/credit-purchase/view.js`, `templates/blocks/credit-purchase/credit-purchase.php` (`data-return-url`) |
| Literal `/wp-json/` | hardcoded base | `templates/blocks/user-dashboard/tab-credits.php:122-123` |

## Not verified
Gateway not configured on the QA site: 409 `gateway_unavailable` (stripe and paypal) and the logged-out 401 were called live; banner `data-*` attributes were read from the rendered page; every page-behaviour step was NOT run in a browser.

## Cleanup
Delete `wbcom_credits_gateway_settings_<site slug>` if you created it (never print it: it holds secrets), restore `<site slug>_credit_mappings` from backup, remove probe ledger rows.
