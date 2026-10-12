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
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---

# A balance that crosses the wire must say what unit it is in

The credit ledger stores integer **minor** units; every human-facing figure is **major** units. An untyped number is the bug: `100` is a valid balance in both readings, so no consumer can detect the mistake, and no single-currency test can either (exponent 100 makes major and minor differ by a factor everyone recognises, exponent 1 makes them identical, so a JPY-only test passes with the units confused).

The route is `GET /wp-json/wbcom-credits/v1/wb-listora/balance` (the slug is part of the path; there is no slug-less `/balance`). Its response is additive:

| Field | Meaning |
|---|---|
| `balance` | unchanged, integer minor units in money mode |
| `balance_units` | `minor` or `credits`, never absent |
| `balance_money` | major units, money mode only |
| `currency` | resolved currency, money mode only |

Where the fields come from (2.0.0): **Free**, not Pro. `wb-listora.php` adds them with a `rest_request_after_callbacks` filter on `/wbcom-credits/v1/wb-listora/balance` (and `/history`), because the bundled SDK 1.10 does not send them and the route stays live while Monetization is off. If they vanish check that filter first, then `wb_listora_credits_ready()`.

Roles: the member (own balance only), logged out (401), admin as control (own balance too: the route is per current user).

## Setup

- Credits SDK ready, money mode on (`\Wbcom\Credits\Credits::is_money( 'wb-listora' )` true). Test at TWO exponents: USD (100) and JPY (1) or KWD/BHD (1000), switching Settings > General currency between runs.
- A member with a non-zero, non-round balance (QA site: `qa_vendor_01`, e.g. 73.00 at the time of the run). A zero balance satisfies every arithmetic assertion at any exponent: do not trust a pass on a zero balance.
- REST nonce: `curl -b <jar> $SITE_URL/wp-admin/admin-ajax.php?action=rest-nonce`.

## Steps

### 1. Money mode declares its units
- **Action**: `GET /wp-json/wbcom-credits/v1/wb-listora/balance` as the member.
- **Expect** (QA site, USD): `{"user_id":6,"balance":7300,"enabled":true,"can_buy":true,"balance_units":"minor","balance_money":73,"currency":"USD"}`.
- **Fails if** `balance_units` is missing, is not `minor` in money mode, or `balance_money * exponent !== balance` exactly.

### 2. Credits mode still says so
- **Action**: run on a site with money mode off (Free-only, or a store currency with no minor unit handled as credits).
- **Expect**: `balance_units === "credits"`, `balance_money` and `currency` absent.
- **Fails if** the shape changes meaning between modes without the field that distinguishes them.

### 3. Two exponents, not one
- **Action**: repeat step 1 under USD (100) and under JPY (1) or KWD (1000).
- **Expect**: assertions hold at both; at JPY `balance === balance_money`, at KWD `balance === balance_money * 1000`.
- **Fails if** assertions only hold at one exponent.

### 4. The displayed figure matches the purchase
- **Action**: top up 50 credits through the real path (`Credit_System::add_credits( <uid>, 50, ... )`, or buy the 50-credit pack end to end through the Dummy gateway), then read the dashboard card and the route.
- **Expect**: the card reads +50 (not 0.50, not 5000) and `balance_money` equals what the screen shows.

### 5. Additive only
- **Expect**: `balance` keeps its old value and type (integer minor units). Older app builds read it directly. `balance` changing meaning breaks every shipped client.

### 6. One top-up path
- **Action**: `grep -rn "Credits::topup(" --include='*.php' . ../wb-listora-pro | grep -v 'libs/' | grep -v '/tests/'` returns nothing (see `credit-award-and-spend-use-major-units` step 5).

### 7. Role ladder and positive control
- **Control**: step 1 for the member is the positive control for the next two.
- **Logged out**: the route returns 401 and no balance.
- **Another member**: returns that member's own `balance`, never the first member's.
- **History**: `GET /wp-json/wbcom-credits/v1/wb-listora/history` carries the same additive fields after `entries`.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| `balance_units` missing | filter not registered / `wb_listora_credits_ready()` false | `wb-listora.php:1026-1056` and the second filter near `:1425-1450` |
| Wrong `balance_money` | route reading `get_balance` not `balance_money` | `wb-listora/libs/wbcom-credits-sdk/src/Credits.php:1150` (`balance_money`) |
| Card disagrees with route | card rendering raw integer | `blocks/user-dashboard/render.php:402` |

## Not verified

Step 1 (USD, route output and 401 for logged out) and the history route's `entries` were run live with curl as `qa_vendor_01`. Steps 2 (money mode off) and 3 (JPY / KWD) were NOT run: switching currency or money mode on the shared QA site was not done. Step 4's card was read ("Credit Balance 77 credits") but the Dummy-gateway purchase was not run.
