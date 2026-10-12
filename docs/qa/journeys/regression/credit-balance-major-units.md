---
journey: credit-balance-major-units
plugin: wb-listora
priority: high
roles: [subscriber]
covers: [dashboard-credits, credits-sdk, post-checkout-poll]
prerequisites:
  - "Credits in money mode, a direct pack sold via Stripe/PayPal"
  - "A member whose balance has a fractional part (e.g. 115.00, not a round integer of minor units)"
estimated_runtime_minutes: 6
covers_card: 10322940160
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The balance card never shows minor units

Regression sentinel for the post-checkout balance poll on the dashboard Credits tab.

## Background

The SDK's balance route returns the **raw ledger integer**, which under money mode is MINOR units (`11500` for 115.00 credits). The dashboard balance card renders MAJOR units. The post-checkout poll once wrote the route's value straight into the card, so a member returning from Stripe saw their balance inflated ~100x (~1000x on KWD), and it did not recover on reload because the success banner re-ran the poll.

Two details that matter:

- `balance_money` / `balance_units` / `currency` are added to the route by Free's `rest_request_after_callbacks` filter in `wb-listora.php` (the SDK itself does not send them). The card does not depend on them; it formats `balance / 10^decimals`.
- The server states the scale and start balance outright (`data-balance-decimals`, `data-start-balance`), instead of the client stripping punctuation from rendered text (`"100.00"` -> `10000` happens to equal minor units at 2 decimals and silently does not at 0 or 3).

Single-role journey with a role check: the member sees their own balance; another member and logged out cannot read it (401); admin is the control for the ledger.

## Setup

- A member whose balance has a fractional or non-round part, on a 2-decimal currency (QA site: `qa_vendor_01`, USD).
- The Credits tab reachable: `$SITE_URL/my-listings/?tab=credits` (on the QA site the surface is on because a WooCommerce mapping is configured). If it is not, force it with an mu-plugin `add_filter( 'wb_listora_show_credits', '__return_true', 99 );` and remove it afterwards.

## Steps

### 1. The banner carries the scale
- **Action**: open `$SITE_URL/my-listings/?tab=credits&wbcom_credits=success&credits=50&gateway=stripe`.
- **Expect**: `[data-listora-credits-banner]` has `data-balance-decimals="2"` and `data-start-balance` = rendered balance x 100 (`8300` beside a card reading `83 credits`), `data-balance-url` = `$SITE_URL/wp-json/wbcom-credits/v1/wb-listora/balance`.

### 2. The card shows major units after the poll settles (the actual bug)
- **Action**: with the banner showing, credit the account while the poll runs (it retries 10 times, 3s apart, ~30s): `wp eval '\WBListoraPro\Features\Credit_System::add_credits( <uid>, 50, "QA-J1- poll probe", "qa", "qa-j1" );'`.
- **Expect**: the card settles on the new balance in MAJOR units with the currency's decimals (e.g. `133` / `133.00`), and the status line (`[data-listora-credits-balance-status]`) swaps to "50 credits added." (only written after `settle()` ran, so it proves the poll wrote the value).
- **Conclusive check**: fetch `data-balance-url` yourself. It returns `balance: 13300`, `balance_units: "minor"`, `balance_money: 133` while the card shows 133. If the card string equals the raw integer, the bug is back.

### 3. Zero-decimal and three-decimal currencies
- **Action**: switch the store currency to JPY (0 dp), then KWD (3 dp) in Settings; reload the banner URL.
- **Expect**: `data-balance-decimals` `0` / `3`, and the card agrees (`100` / `100.000`). This is where the old text-parsing `startBalance` silently broke: do not test USD only.

### 4. Reload does not re-inflate
- **Action**: reload with the banner still in the URL.
- **Expect**: the card still reads the major figure.

### 5. Nothing moves when the balance did not rise
- **Action**: open the success banner URL with no new credits (and with an unconfigured gateway the claim is refused, see `credits-return-claim-honest-outcome`).
- **Expect**: the card stays exactly as the server rendered it.

### 6. Positive control and role ladder
- **Control**: step 2 is the control for step 5: the same poll must move the card when credits do arrive.
- **Another member / logged out**: `GET /wbcom-credits/v1/wb-listora/balance` with another member's cookie returns that member's own balance; logged out returns 401.

## Cleanup

Delete the probe ledger rows (`note LIKE 'QA-J1-%'`) and restore the store currency.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Card ~100x too large after return | poll writes the route integer unscaled | `src/blocks/user-dashboard/view.js:760-775` (`formatBalance`) and `:934-950` (`settle`) |
| `data-start-balance` wrong at 0 or 3 dp | scale derived from text | `templates/blocks/user-dashboard/tab-credits.php:132-134` |
| Fields missing from the route | filter not loaded or SDK not ready | `wb-listora.php:1026-1056` and the second filter near `:1425` (`wb_listora_credits_ready()`) |

## Not verified

The banner attributes (step 1) and the route fields (step 2 conclusive check: `balance 7300, balance_units minor, balance_money 73, currency USD` for the member at the time) were read live with curl. The poll running in a browser, steps 3 and 4 (currency switch) and the 390 layout were NOT run. Note: Free registers the same REST filter twice (`wb-listora.php:1026` and near `:1425`); harmless (the first wins, the second only adds missing keys) but one is dead code.
