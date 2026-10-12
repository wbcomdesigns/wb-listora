---
journey: credit-amounts-in-major-units
plugin: wb-listora
requires: wb-listora-pro
site_profile: required
priority: critical
roles: [member, other-member, guest, editor, admin]
covers: [BC-10186487388, credits, money-mode, minor-units, woocommerce-adapter, featured-upgrade, renewal, 10186487388, rest, app-contract, bc-10393844890, csv-export, dashboard, emails, credits-sdk, card-10337030904]
prerequisites:
  - "Resolve placeholders from docs/qa/sites/<site>.json (see README)"
  - "Monetization ON, credits in money mode (`Credits::is_money( 'wb-listora' )` true)"
  - "Test at TWO currency exponents: USD (100) and JPY (1) or KWD/BHD (1000), switching Settings > General currency between runs"
  - "A WooCommerce product mapped to credits and an offline gateway (COD) so an order can reach processing"
  - "Auto-login mu-plugin present (?autologin=$LOGIN)"
estimated_runtime_minutes: 18
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes: [ora:regression/credit-award-and-spend-use-major-units.md, ora:regression/credit-balance-rest-declares-its-units.md, pro:regression/credit-units-everywhere.md]
fails_on: unrecorded (sentinel until shown to fail on a pre-fix commit)
---

# Credits are awarded, spent, stored and shown in the unit the member sees, never the ledger's minor units

## Why
The ledger stores integer minor units (7.40 is `740`); every figure a human or client reads is major units. A call site that mixes them is off by the currency exponent (100x USD, 1000x KWD, invisible on JPY).
The branch lives once in the SDK `*_money()` primitives and `Credits::balance_money()`; if a step fails, re-run the static sweep (step 5), one wrong site means the class is back.
The balance REST route keeps `balance` as the raw ledger integer by contract and adds `balance_units`; the Listora app reads `listora/v1` for display figures.

## Setup
Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Free + Pro active, Monetization on, a 2-decimal store currency. {owner} is a member with a known, NON-ROUND, non-zero balance (a zero balance satisfies every arithmetic assertion at any exponent). Record `Credits::balance_money( 'wb-listora', <owner id> )`.
- Step 1 needs a product mapped to $MAPPED_CREDITS credits (option `wb-listora_credit_mappings`, shape `{"woocommerce":{"$PRODUCT_ID":$MAPPED_CREDITS}}`) and COD/Dummy; step 3 needs `Settings > Credit Costs > Featured` set to a non-zero whole number (restore afterwards).
- REST nonce: `curl -b <jar> $SITE_URL/wp-admin/admin-ajax.php?action=rest-nonce`. The REST calls can also run as `wp eval` with `wp_set_current_user( <owner id> ); rest_do_request(...)`.
- The balance-route fields (`balance_units`, `balance_money`, `currency`) come from a `rest_request_after_callbacks` filter in Free `wb-listora.php` (the bundled SDK does not send them; the route stays live while Monetization is off). If they vanish check that filter, then `wb_listora_credits_ready()`.
- `{owner}` also needs a ledger of more than one page (>20 rows) for step 14.

## Steps
### 1. A purchase credits the mapped amount, not 1/100th of it
- **Action**: add the 50-credit product to the cart, check out via COD, set the order to `processing` / `completed`. Quick equivalent without a storefront: `wp eval '\WBListoraPro\Features\Credit_System::add_credits( <uid>, 50, "QA-J1- probe", "qa", "qa-j1" );'`.
- **Expect**: the balance increases by **50**, not 0.50. Ledger: the new `topup` row in `${PREFIX}listora_credit_ledger` has `amount = 5000` (50 x 100).
- **Fails if**: an adapter or payment path calls `Credits::topup()` instead of `Credits::topup_money()` (see `wb-listora-pro/includes/features/class-credit-system.php:399` and the SDK's `Gateways/Fulfilment.php:55`).

### 2. The displayed balance is credits, not cents
- **Action**: open `$SITE_URL{dashboard}?tab=credits` (the dashboard page is `wb_listora_get_dashboard_url()`; on the QA site `{dashboard}`) and the Add Listing page.
- **Expect**: the "Credit Balance" card reads the same figure as step 1 (e.g. `$BALANCE credits`), never one ~100x larger; the success banner's `data-start-balance` is that figure x 10^`data-balance-decimals`.
- **Fails if**: a render.php reads `Credits::get_balance()` instead of `balance_money()` (`blocks/user-dashboard/render.php:402`, `blocks/listing-submission/render.php:496`).

### 3. Featuring a listing charges the configured price
- **Action**: as the member with `featured_credit_cost = 5` and a balance >= 5, feature an owned listing (dashboard, or `POST /listora/v1/listings/{id}/feature`).
- **Expect**: balance drops by **5.00**; the ledger shows `hold` then `deduction`, each **500** minor units.
- **Fails if**: the feature path uses `hold()` / `deduct()` instead of the `_money()` variants (`includes/rest/class-listings-controller.php:1666-1690`): it would charge 0.05.

### 4. The insufficient-credits gate compares like with like
- **Action**: with the balance below `featured_credit_cost`, repeat step 3.
- **Expect**: HTTP 402 `listora_insufficient_credits`, `data.balance` equal to the dashboard figure, `data.cost` = 5.
- **Fails if**: the gate compares a minor-unit balance against a major-unit cost.

### 5. Static sweep: the class, not the instance
Run from the Free plugin root. Both must return nothing:
```bash
# No raw award primitive outside the SDK and tests.
grep -rn "Credits::topup(" --include='*.php' --exclude-dir=node_modules --exclude-dir=vendor . ../wb-listora-pro \
  | grep -v 'libs/' | grep -v '/tests/' | grep -vE ':[0-9]+:\s*(//|\*)'

# No raw balance read in product code (money mode makes it minor units).
grep -rn "Credits::get_balance(" --include='*.php' --exclude-dir=node_modules --exclude-dir=vendor . ../wb-listora-pro \
  | grep -v 'libs/' | grep -vE ':[0-9]+:\s*(//|\*)'
```
`libs/wbcom-credits-sdk` legitimately calls the raw primitives inside its own `is_money()` branches (`Gateways/Fulfilment.php:56`, `Consumer.php:381`), which is why the sweep excludes `libs/`.

### 6. Money mode declares its units
- **Action**: `GET /wp-json/wbcom-credits/v1/wb-listora/balance` as the member.
- **Expect** (QA site, USD): `{"user_id":$OWNER_ID,"balance":$BALANCE_MINOR,"enabled":true,"can_buy":true,"balance_units":"minor","balance_money":$BALANCE,"currency":"USD"}`.
- **Fails if** `balance_units` is missing, is not `minor` in money mode, or `balance_money * exponent !== balance` exactly.

### 7. Credits mode still says so
- **Action**: run on a site with money mode off (Free-only, or a store currency with no minor unit handled as credits).
- **Expect**: `balance_units === "credits"`, `balance_money` and `currency` absent.
- **Fails if** the shape changes meaning between modes without the field that distinguishes them.

### 8. Two exponents, not one
- **Action**: repeat step 6 under USD (100) and under JPY (1) or KWD (1000).
- **Expect**: assertions hold at both; at JPY `balance === balance_money`, at KWD `balance === balance_money * 1000`.
- **Fails if** assertions only hold at one exponent.

### 9. The displayed figure matches the purchase
- **Action**: top up 50 credits through the real path (`Credit_System::add_credits( <uid>, 50, ... )`, or buy the 50-credit pack end to end through the Dummy gateway), then read the dashboard card and the route.
- **Expect**: the card reads +50 (not 0.50, not 5000) and `balance_money` equals what the screen shows.

### 10. Additive only
- **Expect**: `balance` keeps its old value and type (integer minor units). Older app builds read it directly. `balance` changing meaning breaks every shipped client.

### 11. Pack price at JPY and KWD, not only the balance (bc-10393844890)
- **Action** (administrator): set currency JPY, then KWD; create a Direct pack priced 2500 (JPY) / 2.500 (KWD) in Settings > Credits; as a member open Buy Credits and run `wp eval 'print_r( wb_listora_get_purchasable_credit_packs() );'` (the REST pack list reads the same helper).
- **Expect**: JPY: Buy Credits card, the helper's `price` and the stored `price_cents` all read 2,500 (stored `2500`, exponent 1), never 25; KWD: 2.500 and stored `2500` (exponent 3), never 25.
- **Read-back**: `wp option get wb_listora_pro_credit_packs --format=json | jq '.. | .price_cents? // empty'` equals the typed figure times 10^exponent; POSITIVE CONTROL: USD 25.00 stores `2500`.
- fails_on: unknown. Not verified live.

### 12. REST history is in credits and paged
- **Action**: as {owner} `GET /wp-json/listora/v1/credits?per_page=2&page=1`.
- **Expect**: 200; `balance` equals the dashboard balance ($BALANCE); `history` has 2 rows; the newest hold row `amount` is `-2`, never `-200`; each row has `type_label` ("On hold") and `note_label`. Headers `X-WP-Total` = the member's ledger row count and `X-WP-TotalPages` = ceil(count / 2). `page=2` returns the next two rows (different ids).
- **Role ladder**: logged out -> 401 `listora_unauthorized`; {other} sees only their own rows (different `user_id`, never user $OWNER_ID); admin ({admin}) sees admin's own ledger, not {owner}'s.
- **On fail**: `includes/rest/class-credits-controller.php:217` (`Money::to_major`), `:283` (total pages).

### 13. Transactions CSV is in credits (admin)
- **Action**: as admin open Listora > Transactions (`?page=listora-transactions`), click **Export CSV**.
- **Expect**: first line `ID,Date,"User ID",Username,Email,Type,"Amount (credits)",Note,"Item ID"`; a hold row for {owner} reads `-2`, not `-200`.
- **Role ladder**: {editor} (no `manage_listora_settings`) opening the same export URL gets "You do not have permission to access this page."; member gets the same.
- **On fail**: `includes/admin/class-transactions-page.php:901` header, `:856` export handler.

### 14. Dashboard history pages (big ledger)
- **Action**: as {owner} open `{dashboard}?tab=credits`.
- **Expect**: heading "Transaction History"; a `nav.listora-pagination` with `aria-label="Transaction history pagination"`, numbered links `1` and `2`, status text "Page 1 of 2", and a **Next** link to `?tab=credits&credits_page=2` (20 rows per page). Page 2 keeps the Credits tab open and shows the remaining 13 rows.
- **Positive control**: a member with 3 ledger rows ({other} or a new member) shows **no** pager at all (the helper renders nothing below 2 pages).
- **Mobile 390**: `document.documentElement.scrollWidth <= 390` (measured 375) and Previous / numbers / Next are each >= 40px tall (measured 40).
- **On fail**: `wb-listora/templates/blocks/user-dashboard/tab-credits.php:498` (pager call), `wb-listora/blocks/user-dashboard/render.php:406` (`credits_page`), `wb-listora/includes/class-render-helpers.php:333` (`wb_listora_render_pagination`).

## Role ladder
| Actor | Expect |
|---|---|
| {owner} | steps 1-14 on their own balance and ledger |
| {other} | `GET /wbcom-credits/v1/wb-listora/balance` and `GET /listora/v1/credits` return that member's own figures only, never {owner}'s; no way to read another user's balance |
| {guest} | balance route and `/listora/v1/credits` return 401 (`listora_unauthorized`); dashboard shows the login prompt |
| {editor} / {other} | Transactions CSV export refused ("You do not have permission to access this page.") |
| {admin} (control) | sees ledger rows and Transactions CSV; admin top-up uses the same `topup_money()`; the balance route returns the admin's own balance |
Positive control: step 5's sweep must be able to find something, `grep -rn "Credits::balance_money(" ...` lists about 10 readers in Free; step 6 for {owner} is the control for steps 7-10.

## Pass criteria
1. A purchase credits the mapped amount (ledger `amount` = credits x exponent), not 1/100th.
2. Every displayed figure (dashboard card, REST history, CSV, pager) equals the same major-unit balance at USD and at JPY/KWD.
3. Featuring charges the configured price; the insufficient-credits gate compares like with like.
4. The sweep in step 5 returns nothing; `balance` stays the raw integer and `balance_units` is always present.
5. Other members and guests cannot read the balance.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| `balance_units` missing | filter not registered / `wb_listora_credits_ready()` false | `wb-listora.php:1026-1056` and the second filter near `:1425-1450` |
| Wrong `balance_money` | route reading `get_balance` not `balance_money` | `wb-listora/libs/wbcom-credits-sdk/src/Credits.php:1150` (`balance_money`) |
| Card disagrees with route | card rendering raw integer | `blocks/user-dashboard/render.php:402` |
| History amount 100x | `to_major` dropped | `includes/rest/class-credits-controller.php:217` |
| CSV 100x | export writes `l.amount` raw | `includes/admin/class-transactions-page.php:901` and the row loop below it |
| No pager past 20 rows | `credit_ledger_pages` not computed | `wb-listora/blocks/user-dashboard/render.php:406` |
| No second alert after top-up | `rearm_low_balance` unhooked | `includes/notifications/class-credit-notifier.php:42,55` |

## Not verified
Steps added or condensed by the consolidation are `Not verified live`; step numbers inside the per-source notes use the OLD numbering, the map is given.
- From `credit-award-and-spend-use-major-units` (old step -> step here: 1->1, 2->2, 3->3, 4->4, 5->5): Step 1 was verified with `Credit_System::add_credits()` on a throwaway member (ledger row 5000, `balance_money` 50; user and rows deleted), not through a WooCommerce order. Steps 2 and 5 were run (balance card read as "$BALANCE credits"; sweep returns only SDK lines and one test file when `libs/` and `/tests/` are not excluded). Steps 3 and 4 were checked in source only: the QA site has `featured_credit_cost = 0`.
- From `credit-balance-rest-declares-its-units` (old step -> step here: 1->6, 2->7, 3->8, 4->9, 5->10, 8->11): Step 6 (USD, route output and 401 for logged out) and the history route's `entries` were run live with curl as `{owner}`. Steps 7 (money mode off) and 3 (JPY / KWD) were NOT run: switching currency or money mode on the shared QA site was not done. Step 9's card was read ("Credit Balance $BALANCE credits") but the Dummy-gateway purchase was not run.
- From `credit-units-everywhere` (old step -> step here: 1->12, 2->13, 3->14): (no note recorded)

## Cleanup
Delete the probe ledger rows (`note LIKE 'QA-%'`), restore the store currency, the featured cost and the mapping option, remove the throwaway user.
