---
journey: credits-checkout-return-banner
group: credits-checkout
actor: customer
mode: browser
plugin: wb-listora
requires: wb-listora-pro
site_profile: required
priority: critical
roles: [member, other-member, guest, admin]
covers: [dashboard-credits, credits-sdk, post-checkout-poll, 10322940160, post-checkout-banner, 10322935144, credits, stripe-return, card-10258479636, checkout, stripe, paypal, woocommerce, memberpress, pmpro, monetization-toggle, dashboard, buddyx, card-10337030682, payment-claim-banner, paypal-return, claim-refusal-vs-transient]
prerequisites:
  - "Resolve placeholders from docs/qa/sites/<site>.json (see README)"
  - "Combo, Monetization on, the dashboard Credits tab reachable ($SITE_URL{dashboard}?tab=credits; force with an mu-plugin `add_filter( 'wb_listora_show_credits', '__return_true', 99 );` and remove it afterwards)"
  - "A member with a fractional balance on a 2-decimal currency"
  - "No gateway needed: the banner is driven by the query string; a Stripe test gateway only adds the live claim"
estimated_runtime_minutes: 20
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes: [ora:regression/credit-balance-major-units.md, ora:regression/credits-banner-dismissible.md, pro:regression/buy-credits-return-url.md, pro:regression/credit-checkout-integrity.md, ora:customer/credits-return-claim-honest-outcome.md]
fails_on: unrecorded (sentinel until shown to fail on a pre-fix commit)
---

# Returning from Stripe or PayPal reports what happened to the payment, shows the balance in credits and can always be closed

## Why
After Stripe/PayPal the member lands on the dashboard Credits tab with `?wbcom_credits=...`. The page must report what really happened to the payment, show the balance in major units, and the banner must always be closable.
When the claim fails the banner is rewritten with `replaceChildren()`: the dismiss control must survive that, otherwise an error cannot be closed and the URL brings it back.

## Setup
Resolve placeholders from docs/qa/sites/<site>.json (see README).
- {owner} (member), {other} (another member), {admin}. Dashboard URL: `wb_listora_get_dashboard_url()`.
- Success URL used below: `$SITE_URL{dashboard}?tab=credits&wbcom_credits=success&credits=50&gateway=stripe[&session_id=cs_test_unknown]`; PayPal: `gateway=paypal&token=EC-TEST&PayerID=ABC`.
- Claim answers are simulated with `page.route( '**/wbcom-credits/v1/wb-listora/claim/**' )`. REST nonce from the banner `data-rest-nonce`.
- Balance fixtures: credit the account while the poll runs with `\WBListoraPro\Features\Credit_System::add_credits( <owner id>, $CREDITS, "QA- poll probe", "qa", "qa-j" );`.

## Steps
### 1. An unknown session is an honest failure (real server)
- **Action**: as the member open `{dashboard}?tab=credits&wbcom_credits=success&credits=100&gateway=stripe&session_id=cs_test_unknown`.
- **Expect, first paint (curl or JS off)**: `div.listora-dashboard__credits-banner.listora-dashboard__credits-banner--success[role="status"][data-listora-credits-banner]` with "Payment received. Adding 100 credits to your account..." and the line "Confirming with your payment provider..."; a dismiss link `a[data-listora-credits-banner-dismiss]` ("Dismiss this message") pointing at `{dashboard}?tab=credits`.
- **Expect, within about 3 seconds (JS on)**: the class switches to `--error`, the banner content becomes a single `span[role="alert"]` reading "We could not find this payment on your account, so no credits were added. If you were charged, contact the site administrator." and the dismiss link is still there. The text "Payment received" must NOT remain.
- **Note**: with the gateway not configured the claim returns `409 {"code":"gateway_unavailable"}` (any status below 500 other than an expired nonce counts as "nothing is coming"), so the banner flips either way; with Stripe configured the refusal is the genuine "unknown session" answer.
- **On fail**: `src/blocks/user-dashboard/view.js:870` (failed branch) or the `claimOnce` mapping at line 827-866.

### 2. Already credited settles immediately
- **Action**: intercept the claim (`page.route( '**/wbcom-credits/v1/wb-listora/claim/**' )`) with `200 {"received":true,"already":true,"credits":100}`, then load the same URL with another `session_id`.
- **Expect**: the balance line reads "100 credits added." (`data-confirmed-text`) within about 3 seconds although the balance did not rise during this page view; the banner stays `--success`.

### 3. The provider has not confirmed yet
- **Action**: intercept the claim with `202 {"received":true,"pending":true}` and wait about 33 seconds (10 polls, 3 seconds apart).
- **Expect**: "Your payment provider has not confirmed this payment yet. Your credits will appear once it does - refresh in a moment." (`data-pending-text`, translatable).

### 4. The banner carries the scale
- **Action**: open `$SITE_URL{dashboard}?tab=credits&wbcom_credits=success&credits=50&gateway=stripe`.
- **Expect**: `[data-listora-credits-banner]` has `data-balance-decimals="2"` and `data-start-balance` = rendered balance x 100 (`$BALANCE_MINOR` beside a card reading `$BALANCE credits`), `data-balance-url` = `$SITE_URL/wp-json/wbcom-credits/v1/wb-listora/balance`.

### 5. The card shows major units after the poll settles (the actual bug)
- **Action**: with the banner showing, credit the account while the poll runs (it retries 10 times, 3s apart, ~30s): `wp eval '\WBListoraPro\Features\Credit_System::add_credits( <uid>, 50, "QA-J1- poll probe", "qa", "qa-j1" );'`.
- **Expect**: the card settles on the new balance in MAJOR units with the currency's decimals (e.g. `$BALANCE_AFTER` / `$BALANCE_AFTER` with the currency decimals), and the status line (`[data-listora-credits-balance-status]`) swaps to "50 credits added." (only written after `settle()` ran, so it proves the poll wrote the value).
- **Conclusive check**: fetch `data-balance-url` yourself. It returns `balance: $BALANCE_MINOR_AFTER`, `balance_units: "minor"`, `balance_money: $BALANCE_AFTER` while the card shows $BALANCE_AFTER. If the card string equals the raw integer, the bug is back.

### 6. Zero-decimal and three-decimal currencies
- **Action**: switch the store currency to JPY (0 dp), then KWD (3 dp) in Settings; reload the banner URL.
- **Expect**: `data-balance-decimals` `0` / `3`, and the card agrees (`100` / `100.000`). This is where the old text-parsing `startBalance` silently broke: do not test USD only.

### 7. Reload does not re-inflate
- **Action**: reload with the banner still in the URL.
- **Expect**: the card still reads the major figure.

### 8. Nothing moves when the balance did not rise
- **Action**: open the success banner URL with no new credits (and with an unconfigured gateway the claim is refused, see step 17b).
- **Expect**: the card stays exactly as the server rendered it.

### 9. REST URLs are resolved, never hard-coded
- **Expect**: the banner's `data-claim-url` and `data-balance-url` equal `rest_url()` output (`$SITE_URL/wp-json/wbcom-credits/v1/wb-listora/claim/` and `.../balance` on a pretty-permalink site; `?rest_route=` form on plain permalinks); `grep -c "/wp-json/" build/blocks/user-dashboard/view.js` is `0`.

### 10. The control exists on all three states
- **Action**: visit the Credits tab with `?wbcom_credits=success&credits=50&gateway=stripe`, then `?wbcom_credits=cancel`, then `?wbcom_credits=error`.
- **Expect**: `[data-listora-credits-banner]` has `--success` / `--cancel` / `--error` modifier classes and each carries `a[data-listora-credits-banner-dismiss]` with `aria-label="Dismiss this message"` and a hit area of at least 40x40 (`min-width/min-height: 40px`).
- **Texts**: success "Payment received." + "Adding your credits..." ; cancel "Checkout canceled." + "No charge was made - pick a pack below to try again."; error "We couldn't process your purchase." + "Please try again or contact support if the issue persists."

### 11. Its href strips every purchase arg
- **Expect**: the `href` is `...{dashboard}?tab=credits` (keeps `tab=credits`, drops the rest). That is the no-JS path, so check the attribute, not just the click.
- **PayPal half**: with `gateway=paypal&token=EC-TEST&PayerID=ABC` the `href` drops `token` and `PayerID` too (a payment token must not be left in a URL the member may copy or bookmark).

### 12. Clicking closes it without losing the page
- **Expect**: the banner disappears, the URL loses the purchase args, and the rest of the tab (balance card, packs, history) is untouched.

### 13. Reload does not bring it back
- **Expect**: refreshing after dismissal shows no banner.

### 14. Back does not resurrect the dismissed view
- **Expect**: the dismissed URL is not a history entry. Back lands on whatever preceded the checkout return, not on the banner just closed. (If you visited the banner URL several times while testing, Back legitimately reaches an earlier visit; check it skipped the dismissed entry.)

### 15. The copy never runs under the control
- **Action**: look at the longest variant (error / cancel, three lines at narrow widths).
- **Expect**: text stays clear of the control; the banner reserves inline-end padding for it. Compare rects, do not eyeball.

### 16. A FAILED claim is still dismissible (the bounced case)
- **Action**: `?wbcom_credits=success&credits=100&gateway=stripe&session_id=cs_test_unknown`. Wait ~2 s for the claim to come back (409 `gateway_unavailable` when no gateway is configured, as on the QA site; 409 or 4xx for an unknown session otherwise).
- **Expect**: the banner turns `--error`, a fresh `<span role="alert">` says "We could not find this payment on your account, so no credits were added. If you were charged, contact the site administrator." **and the dismiss control is still there**. Click it: banner gone, URL clean, reload stays clean.
- **Never**: an error banner with no close control.
- **On fail**: `src/blocks/user-dashboard/view.js:870-894` (the `claim === 'failed'` branch must re-append the dismiss node in `replaceChildren( alert, dismiss )`) and the rebuilt `build/blocks/user-dashboard/view.js`.

### 17. The same for PayPal
- **Action**: `?wbcom_credits=success&credits=100&gateway=paypal&token=EC-TESTTOKEN&PayerID=ABC123`.
- **Expect**: a `POST .../wbcom-credits/v1/wb-listora/claim/paypal` is sent, the claim fails, the banner rewrites to the error, the dismiss survives and its `href` strips `token` + `PayerID`.

### 17b. Every refusal is a failure; only transient errors poll
- **Action**: stub the claim (`page.route`) with 409 `{code:"gateway_unavailable"}`, 400 `{error:"amount_or_currency_mismatch"}`, 503, and 200 `{received:true}` with the balance request failing (500). A real approved PayPal sandbox order (`gateway=paypal&token=<order id>&PayerID=X`) is claimed with the token as the session id and credited; an order the site never created shows the error banner.
- **Expect**: the first two: error banner with a `role="alert"` child; 503: keeps polling ("Confirming with your payment provider...") then the pending copy ("Your payment provider has not confirmed this payment yet. Your credits will appear once it does - refresh in a moment."); the last: after ~33 s "100 credits added." (a confirmed claim is confirmed without a balance read). Claim outcomes: `src/blocks/user-dashboard/view.js:809-860` (`credited` = 2xx with `received` and no `error`; `pending` = 202, 5xx, `rest_cookie_invalid_nonce` or network error; everything else `failed`).

### 18. Layout and themes
- **Expect** at 390px: banner text wraps, dismiss target is at least 40px, no horizontal page scroll; in dark mode the banner colours come from tokens and stay readable (success, error and balance line).
- **Also**: the dismiss control stays in the corner and is >= 40x40; on the error state at 390 its bounding box does not overlap the `[role="alert"]` text, which wraps to about four lines.

### 19. The way back to a saved listing survives the gateway
- **Action**: from Add Listing with an unpaid plan, go to Buy Credits (the wizard saves a draft and appends `listora_return`).
- **Expect**: the Buy Credits page shows "Your listing is saved." "Come back to it once you have the credits you need." with "Back to your listing"; the Direct buy buttons' `data-return-url` is `{dashboard}?tab=credits&listora_return=<encoded draft URL>`; opening that URL shows "Your listing is saved." and "Back to your listing" linking to the draft (`templates/blocks/user-dashboard/tab-credits.php:55-58`). An off-site `listora_return` is dropped (same-site guard `wp_validate_redirect`). The request-side half (`POST .../checkout/stripe` carries `return_url` = `<dashboard url>?tab=credits`, the button's `data-return-url`) is `pro:credits-checkout/buy-credits.md` step 12.

## Role ladder
| Actor | Opens the success URL | Expect |
|---|---|---|
| {owner} | steps 1-19 | banner, claim, major-unit card and dismiss as above |
| {other} with {owner}'s `session_id` | success URL | error banner: the claim is account-scoped, "We could not find this payment on your account..."; no credits added |
| {guest} | success URL | "Please log in to view your dashboard." with a Log In button; no banner markup and no claim request; `GET .../balance` is 401 |
| {admin} (control) | bogus session | error banner (admins get no special claim); same dismiss control |
Control: step 10 (a banner exists and closes) beside `?tab=credits` with no query (0 matches of `data-listora-credits-banner`) shows dismissal removes state rather than hiding a permanent element; step 5 is the control for step 8 (the same poll moves the card when credits do arrive). `GET .../balance` as {other} returns {other}'s own balance.

## Pass criteria
1. An unrecognised session becomes an error banner with `role="alert"` text, never "will appear shortly"; an unconfirmed payment says so in Listora words.
2. The card shows major units at 0, 2 and 3 decimals after the poll settles and after reload.
3. Every banner state (success/cancel/error, failed claim, PayPal) has a working dismiss link that strips the purchase args including `token` and `PayerID`; Back does not resurrect it.
4. Claim and balance URLs come from `rest_url()`; Buy Credits return link survives the gateway (off-site `listora_return` dropped).

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Card ~100x too large after return | poll writes the route integer unscaled | `src/blocks/user-dashboard/view.js:760-775` (`formatBalance`) and `:934-950` (`settle`) |
| `data-start-balance` wrong at 0 or 3 dp | scale derived from text | `templates/blocks/user-dashboard/tab-credits.php:132-134` |
| Fields missing from the route | filter not loaded or SDK not ready | `wb-listora.php:1026-1056` and the second filter near `:1425` (`wb_listora_credits_ready()`) |
| No control | markup removed | `templates/blocks/user-dashboard/tab-credits.php:191-210` |
| href keeps `token` / `PayerID` | arg list shortened | `tab-credits.php:198-200` |
| Control gone after a failed claim | `replaceChildren()` drops it | `src/blocks/user-dashboard/view.js:870-894` |
| Back returns to the banner | `pushState` used | `src/blocks/user-dashboard/view.js:696-716` |
| Text under the control / <40px | CSS | `blocks/user-dashboard/style.css:1322-1345` |
| Banner never leaves "Payment received" | claim result ignored | `wb-listora/src/blocks/user-dashboard/view.js:827-880` |
| Reload never settles | settle ignores a confirmed claim | `wb-listora/src/blocks/user-dashboard/view.js` (`settle`) |
| Stripe returns to `/` | `return_url` missing | `wb-listora-pro/src/blocks/credit-purchase/view.js:105`, `wb-listora/includes/class-template-helpers.php:343` |
| 401 on claim or balance | nonce not sent | `tab-credits.php:119` (`data-rest-nonce`) and `restNonce()` in the view script |
| Add to cart on the credit product with Monetization off | SDK copy predates 1.7.2's adapter gate, or `Credits::checkout_enabled()` not consulted | `libs/wbcom-credits-sdk/src/Adapters/WooCommerce.php`, `src/Credits.php:154` |
| 200 or a checkout URL with Monetization off | `wbcom_credits_checkout_enabled` not hooked or SDK too old | `wb-listora-pro/includes/class-pro-plugin.php:118`, `libs/wbcom-credits-sdk/wbcom-credits-sdk.php:304` |
| `{buy_credits}` is a 404 | page unmapped, not the gate | `wb-listora-pro/blocks/credit-purchase/render.php:44-60` |
| No "Back to your listing" after a Direct purchase | `return_url` built without the helper | `wb-listora/includes/class-template-helpers.php:343` |

## Not verified
Steps added or condensed by the consolidation are `Not verified live`; step numbers inside the per-source notes use the OLD numbering, the map is given.
- From `credit-balance-major-units` (old step -> step here: 1->4, 2->5, 3->6, 4->7, 5->8): The banner attributes (step 4) and the route fields (step 5 conclusive check: `balance $BALANCE_MINOR, balance_units minor, balance_money $BALANCE, currency USD` for the member at the time) were read live with curl. The poll running in a browser, steps 6 and 7 (currency switch) and the 390 layout were NOT run. Note: Free registers the same REST filter twice (`wb-listora.php:1026` and near `:1425`); harmless (the first wins, the second only adds missing keys) but one is dead code.
- From `credits-banner-dismissible` (old step -> step here: 1->10, 2->11, 3->12, 4->13, 5->14, 6->15, 8->16, 9->17): Steps 10 and 11 (classes, attributes, texts and the `href` for success/cancel/error and the PayPal query) and the no-banner control were read live with curl as `{owner}`. The click, reload, Back, rect and 390 checks (steps 12 to 10) and the claim request in steps 16 to 17 (a 409 was confirmed by curl, not through the page) were NOT run in a browser.
- From `buy-credits-return-url` (old step -> step here: 1->1, 2->2, 3->3, 5->9, 7->18): (no note recorded)
- From `credit-checkout-integrity` (old step -> step here: 3->19): (no note recorded)

## Cleanup
Delete the probe ledger rows (`note LIKE 'QA-%'`), restore the store currency, delete `wbcom_credits_gateway_settings_wb-listora` if created (never print it, it holds secrets).
