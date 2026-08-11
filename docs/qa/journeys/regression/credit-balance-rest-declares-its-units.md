---
journey: credit-balance-rest-declares-its-units
plugin: wb-listora
priority: critical
roles: [member]
covers: [BC-10186487388, credits, money-mode, rest, app-contract]
prerequisites:
  - "Monetization ON, credits in money mode"
  - "Test at TWO currency exponents - USD (100) and JPY (1) or BHD (1000)"
estimated_runtime_minutes: 6
covers_card: 10186487388
---

# A balance that crosses the wire must say what unit it is in

The credit ledger stores integer **minor** units; every human-facing figure is
**major** units. The REST balance response carried a bare `balance` and named
neither, so each consumer picked an interpretation and one of them was wrong:
buying 50 credits displayed as 0.50.

An untyped number is the bug. `100` is a valid balance in both readings, so no
consumer can detect the mistake, and no single-currency test can either —
exponent 100 makes major and minor differ by a factor every reviewer recognises,
while exponent 1 makes them **identical**, so a JPY-only test passes with the
units confused.

The response is now explicit and additive:

| Field | Meaning |
|---|---|
| `balance` | unchanged, integer minor units in money mode |
| `balance_units` | `minor` or `credits` — never absent |
| `balance_money` | major units, money mode only |
| `currency` | resolved currency, money mode only |

## Steps

### 1. Money mode declares its units
```
GET /wbcom-credits/v1/balance
```
- **Fails if** `balance_units` is missing. A consumer that has to infer the unit
  will eventually infer wrong.
- **Fails if** `balance_units !== 'minor'` while money mode is on.
- Assert `balance_money * exponent === balance`, exactly.

### 2. Credits mode still says so
Money mode off:
- `balance_units === 'credits'`, `balance_money` and `currency` absent.
- **Fails if** the shape silently changes meaning between modes without the
  field that distinguishes them.

### 3. Two exponents, not one
Repeat step 1 under **USD (100)** and under **JPY (1)** (or BHD, 1000).
- **Fails if** the assertions only hold at one exponent. Exponent 1 is where a
  units bug hides completely — if the run only covers USD it proves less than it
  appears to.

### 4. The displayed figure matches the purchase
Buy a 50-credit pack end to end through the WooCommerce dummy gateway. The
dashboard must read **50**, not 0.50 and not 5000, and `balance_money` must
equal what the screen shows.

### 5. Additive only
The pre-existing `balance` field keeps its old value and type. Older app builds
read it directly.
- **Fails if** `balance` changed meaning — that breaks every shipped client at
  once, which is worse than the bug being fixed.

### 6. One top-up path
Assert credits were awarded through `Credits::award()`, the single money-mode
aware seam. A caller reaching `topup()` directly bypasses the conversion and
recreates the bug on a surface nobody re-checks.

## Test-data trap

A member with a **zero** balance satisfies every arithmetic assertion above at
any exponent, in either mode. Seed a non-zero, non-round balance (e.g. 12.34
major) before trusting a pass.
