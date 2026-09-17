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
---

# Returning from Stripe reports what actually happened to the payment

After checkout Stripe sends the member to the dashboard Credits tab with `?wbcom_credits=success&
credits=N&gateway=stripe&session_id=cs_…`. The page claims the session (`POST /wbcom-credits/v1/
wb-listora/claim/{gateway}`) and polls the balance. Card 10258479636 bounced three ways:

1. The claim answer was ignored - an unknown or foreign session still read "Payment received. Adding
   100 credits…" and then "will appear shortly".
2. A reload after crediting never settled (it waited for the balance to rise above a total that
   already included the credits).
3. Buy Credits (Pro) sent no `return_url`, so Stripe returned to the home page where nothing claims -
   without a webhook the credits never landed. Claim/balance also used a hardcoded `/wp-json/`.

**Build note:** `wb-listora/src/blocks/user-dashboard/view.js` and
`wb-listora-pro/src/blocks/credit-purchase/view.js` → their `build/` bundles.

## Steps

### 1. Unknown session is an honest failure (real server)
- **Action**: as journey_owner open `/my-listings/?tab=credits&wbcom_credits=success&credits=100&gateway=stripe&session_id=cs_test_unknown`
- **Expect**: within ~3s the banner switches to `listora-dashboard__credits-banner--error`, `role="alert"`, text "We could not find this payment on your account, so no credits were added…". It must NOT still say "Payment received".
- **Note**: with Stripe NOT configured the claim returns 409 `gateway_unavailable` and the page polls instead - configure the gateway first or this step proves nothing.

### 2. Already credited settles immediately
- **Action**: intercept the claim (`page.route`) with `200 {"received":true,"already":true,"credits":100}`, load the same URL with another session id
- **Expect**: balance line "100 credits added." within ~3s, although the balance did not rise during the page view

### 3. Provider not confirmed yet
- **Action**: intercept the claim with `202 {"received":true,"pending":true}`, wait ~33s
- **Expect**: "Your payment provider has not confirmed this payment yet…" (translatable, from `data-pending-text`)

### 4. Buy Credits returns to the claiming page (Pro)
- **Action**: open `/buy-credits/`, intercept `POST …/checkout/stripe`, click the 100-credit "Buy with Stripe"
- **Expect**: request body contains `"return_url":"<dashboard>?tab=credits"`; the button carries the same `data-return-url`

### 5. REST URLs are resolved
- **Expect**: the banner's `data-claim-url` / `data-balance-url` equal `rest_url()` output - no literal `/wp-json/` in `build/blocks/user-dashboard/view.js` claim/balance calls

## Teardown
Delete `wbcom_credits_gateway_settings_wb-listora` (never print it - it holds secrets) and restore `wb-listora_credit_mappings` from backup.
