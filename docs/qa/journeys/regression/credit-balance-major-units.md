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
---

# The balance card never shows minor units

Regression sentinel for the post-checkout balance poll.

## Background

The SDK's balance route returns the **raw ledger integer**, which under money mode is MINOR units — `11500` for 115.00 credits. That is deliberate and documented (card 10192062898). The dashboard balance card renders MAJOR units.

The post-checkout poll read the route and wrote its value straight into the card, so a member returning from Stripe saw their balance inflated ~100x (~1000x on a 3-decimal currency like KWD). It did not recover on reload, because reloading with the success banner still present re-ran the poll.

Two contributing details worth knowing:

- The fork's additive `balance_money` / `balance_units` / `currency` fields were removed when Free synced to upstream SDK 1.6.0, so the route no longer exposes a major-unit value for JS to use.
- `startBalance` was derived by stripping punctuation from the rendered text — `"100.00"` became `10000`, which *happens* to equal minor units at 2 decimals and silently does not at 0 or 3. The server now states both the scale and the start balance outright.

## Steps

### 1. The banner carries the scale
- **Action**: return to `?tab=credits&wbcom_credits=success`.
- **Expect**: the banner has `data-balance-decimals` (2 for USD) and `data-start-balance` in **minor** units matching the rendered card (`6500` beside a card reading `65.00`).

### 2. The card shows major units after the poll settles — the actual bug
- **Action**: load the success banner, then credit the account while the poll is running (it retries ~10 times over ~30s).
- **Expect**: the card settles on the new balance in MAJOR units with the currency's decimals, e.g. `115.00`. The status line swaps to "50 credits added." — that line only appears once `settle()` has run, so it is the proof the poll wrote the value rather than the server.
- **Conclusive check**: fetch `data-balance-url` yourself. The route must return `11500` while the card shows `115.00`. If those two strings match, the bug is back.

### 3. Zero-decimal and three-decimal currencies
- **Action**: switch the store currency to JPY (0 dp), then KWD (3 dp).
- **Expect**: `100` and `100.000` respectively. This is where the old text-parsing `startBalance` silently broke, so do not test USD only.

### 4. Reload does not re-inflate
- **Action**: reload with the success banner still in the URL.
- **Expect**: the card still reads the major figure. The original bug was sticky across reloads for exactly this reason.

### 5. Nothing moved when the balance did not rise
- **Expect**: returning with the banner but no new credits leaves the card exactly as the server rendered it.

## Testing this locally

The credits surfaces are gated on `wb_listora_should_show_member_credits()`, which requires a real purchase path — so on a sandbox with no registered gateway the tab does not render at all. Force it with an mu-plugin:

```php
add_filter( 'wb_listora_show_credits', '__return_true', 99 );
```

That makes the surface reachable without changing any of the code under test. Remove it afterwards.

## Automated coverage

None — the conversion lives in the block's view script and the meaningful assertion is the rendered card. Walk it in a browser.
