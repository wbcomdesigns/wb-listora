---
group: credits-checkout
plugin: wb-listora
scope: What a member sees of their credits on the dashboard (balance, packs, history, post-checkout banner), how credit amounts are stored and exposed, and the ledger repair command. Selling and paying live in Pro.
journeys: 4
site_profile: required
---
# Credits and checkout (Free): what an agent checks here

## Scope
Covers the member-facing Credits tab on the dashboard in Free (`tab-credits.php`), the return banner after Stripe/PayPal/shop checkout, the unit contract (ledger minor units vs the major units a person reads), the REST balance and history figures, and `wp listora repair-credit-ledger`.
Does NOT cover: the Buy Credits page, pack buttons that start a checkout, billing form, coupons, gateway settings, receipts, refunds, webhook, Edit User credits (all Pro: `wb-listora-pro/docs/qa/journeys/credits-checkout/_group.md`, run it next); plan purchase and expiry (`plans-expiry-renewal`); the low-balance and refund emails (Pro journeys here, mail delivery itself is `emails-alerts`); the Add Listing wizard's own credit step (`submission`); the dashboard tabs other than Credits (`dashboard-account`).
Most Credits-tab states only exist with Pro active and Monetization on. Free alone shows no Credits tab.

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| 1 | Dashboard Credits tab, member with packs | `$SITE_URL{dashboard}?tab=credits` | {owner}, {other}, {editor}, {admin} (each their own ledger) | `templates/blocks/user-dashboard/tab-credits.php`, `blocks/user-dashboard/render.php` |
| 2 | Credits tab with return banner (success / cancel / error) | `$SITE_URL{dashboard}?tab=credits&wbcom_credits=success&credits=50&gateway=stripe` (also `=cancel`, `=error`) | member | `tab-credits.php:105-210`, `src/blocks/user-dashboard/view.js` |
| 3 | Return banner, PayPal variant | `...&wbcom_credits=success&credits=100&gateway=paypal&token=EC-TEST&PayerID=ABC` | member | same |
| 4 | Credits tab, second page of history | `$SITE_URL{dashboard}?tab=credits&credits_page=2` (page is clamped to the last page) | member with more than 20 ledger rows | `render.php:406`, `class-render-helpers.php` (`wb_listora_render_pagination`) |
| 5 | Credits tab with "listing is saved" notice | `$SITE_URL{dashboard}?tab=credits&listora_return=<url-encoded draft URL>` | member | `tab-credits.php:54-58` |
| 6 | Credits tab, no packs / no history / not on sale | member with empty ledger, no packs or no payment path (see States) | member | `tab-credits.php:290-310, 405-415` |
| 7 | Credits tab, guest | `$SITE_URL{dashboard}?tab=credits` logged out | guest | dashboard login prompt in `blocks/user-dashboard/render.php` |
| 8 | Sidebar Credits nav item and badge | any dashboard tab | member | `templates/blocks/user-dashboard/nav.php:175-181` |
| 9 | Credit Costs settings (Featured cost etc.) | `$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=credits` (pricing block) | {admin} | `includes/admin/class-credits-settings-tab.php:71` |
| 10 | REST: balance and history (no UI) | `GET $SITE_URL/wp-json/wbcom-credits/v1/wb-listora/balance`, `GET .../listora/v1/credits?per_page=2&page=1` | member (own), guest 401 | `wb-listora.php` filter; Pro `includes/rest/class-credits-controller.php` |
| 11 | CLI: repair command | `wp listora repair-credit-ledger [--execute] [--format=csv]` | {admin} (shell) | `includes/class-cli-commands.php:1203` |

## Components on each surface
1. Credits tab: `#dash-panel-credits.listora-dashboard__panel[role=tabpanel]`; optional return notice `.listora-return-notice[role=status]` with `a.listora-return-notice__link` ("Back to your listing"); balance card `.listora-dashboard__balance-card[role=region]` > `h3#listora-credit-balance-heading.listora-dashboard__balance-label` ("Credit Balance"), `.listora-dashboard__balance-number`, `.listora-dashboard__balance-unit` (credit/credits), optional `.listora-dashboard__balance-warning` (low) or `--owed` ("You owe N credits..."), `.listora-dashboard__balance-actions a.listora-btn--primary` ("Buy Credits"); packs section `section#listora-credit-packs` > `h3#listora-credit-packs-heading` ("Buy Credits") > `.listora-dashboard__credit-packs` > `article.listora-dashboard__credit-pack` each with `header` (`.listora-dashboard__credit-pack-title`: "N credits"), `.listora-dashboard__credit-pack-price`, `footer` holding either `button.listora-dashboard__credit-pack-buy-direct[data-listora-credits-checkout]` ("Buy with Stripe/PayPal"), an `a.listora-btn` ("Buy Now"), or `span.listora-dashboard__credit-pack-unavailable` ("Not available to buy right now"); empty variant `.listora-dashboard__credits-section--empty` with empty-state title/description; history section > `h3#listora-credit-history-heading` ("Transaction History") > `.listora-dashboard__transactions[role=table]` with `.listora-dashboard__transactions-head` (Date, Type, Amount, Note, Receipt) and one `[role=row]` per entry (`.listora-dashboard__transaction-date`, `-type` > `-type-pill--{entry_type}`, `-amount`, `-note`, `-actions`); `nav.listora-pagination[aria-label="Transaction history pagination"]` (Previous, numbers, "Page X of Y", Next); empty history "No transactions yet / Your credit activity will appear here."
2. Return banner: `div.listora-dashboard__credits-banner.listora-dashboard__credits-banner--{success|cancel|error}[role=status][data-listora-credits-banner]` with data attributes `data-start-balance`, `data-balance-decimals`, `data-balance-url`, `data-claim-url`, `data-rest-nonce`, `data-pending-text`, `data-failed-text`, `data-confirmed-text`; bold lead line; `.listora-dashboard__credits-banner-balance[data-listora-credits-balance-status]`; `a.listora-dashboard__credits-banner-dismiss[data-listora-credits-banner-dismiss][aria-label="Dismiss this message"]`; after a failed claim a `span[role=alert]`.
3. Nav item: `a#dash-tab-credits` ("Credits") with a numeric badge.
4. Credit Costs block: `[data-listora-subtab=pricing]` section "Credit Costs" with the Featured cost and renewal fields.
5. Guest: login prompt text "Please log in to view your dashboard." and Log In button.

## States to force
| Surface | State | How |
|---|---|---|
| 1 | one pack / many packs / none | `wb-listora_credit_mappings` and `wb_listora_pro_credit_packs` (record and restore); packs come from `wb_listora_get_purchasable_credit_packs()` |
| 1 | pack kinds | WooCommerce-mapped pack ("Buy Now" link), Direct pack (needs Stripe/PayPal, gateway button), pack with no route (unavailable span) |
| 1 | balance zero / fractional / negative / low | fixtures: `Credit_System::add_credits()`, `Credits::topup_money()`; low = threshold option `wb_listora_low_credit_threshold` above the balance; owed = balance below zero |
| 1 | not on sale / checkout unavailable / store link | clear mappings and packs, set `wb_listora_credit_purchase_url` (external store variant) |
| 1 | currency exponent 2 / 0 / 3 | Settings > General currency USD, JPY, KWD; restore afterwards |
| 2-3 | each banner state | query string above; failed claim = unknown `session_id`; pending = stub the claim route with 202 (`page.route`) |
| 2 | JS off | curl the URL; first paint must already show the lead line and the dismiss link |
| 4 | more than 20 rows | seed 33 `QA-` ledger rows for {owner} |
| 4 | tampered page | `credits_page=999`, `credits_page=-1`, `credits_page=abc` |
| 5 | off-site `listora_return` | must be dropped |
| 7 | logged out | no cookie |
| all | 1440, 1024, 390; light, dark; RTL optional | MICRO_CHECKS matrix |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| Monetization (Settings > Features) | on / off | Credits tab and nav item appear / disappear (surface 8, 1); checkout routes refuse when off |
| `wb_listora_low_credit_threshold` | 0, 5, above balance | low-balance warning on surface 1 shows only when 0 < balance <= threshold; threshold 0 hides it |
| Store currency (Settings > General) | USD / JPY / KWD | `data-balance-decimals` 2 / 0 / 3; pack prices and banner agree |
| `wb_listora_credit_purchase_url` | empty / URL / page id | "Buy Credits" CTA target and the store-link empty state on surface 1 |
| `featured_credit_cost` (Credit Costs) | 0, 5 | Featured charge and the 402 gate (journey `credit-amounts-in-major-units` steps 3-4) |
| `wb-listora_credit_mappings`, `wb_listora_pro_credit_packs` | empty / mapped / direct | which pack cards render (Pro sells them; see Pro group) |

## Fixtures and setup
- `wp eval-file docs/qa/fixtures/2.0-fixtures.php seed` for personas; Combo (Free + Pro), Monetization on, a 2-decimal currency.
- Ledger probes: `wp eval '\WBListoraPro\Features\Credit_System::add_credits( <uid>, 50, "QA-J1- probe", "qa", "qa-j1" );'`. Always prefix notes `QA-`.
- History pager: seed 33 `QA-` rows for {owner} (same call in a loop).
- Force the Credits tab when the site has no payment path: mu-plugin `add_filter( 'wb_listora_show_credits', '__return_true', 99 );`.
- Restore afterwards: delete `note LIKE 'QA-%'` ledger rows, currency, `featured_credit_cost`, mappings option, `wb_listora_low_credit_threshold`, remove mu-plugins, remove throwaway users.

## Run order
1. `credits-checkout-return-banner.md` - browser, critical - banner outcome, major-unit card after poll, dismiss, PayPal, saved-listing notice.
2. `credit-amounts-in-major-units.md` - mixed, critical - award/spend/display units across card, REST, CSV, pager (browser for steps 2, 14; REST/CLI for the rest).
3. `credit-ledger-repair-idempotent.md` - cli, critical - repair twice never pays twice.
Then run: `wb-listora-pro/docs/qa/journeys/credits-checkout/_group.md`.
4. `submission-cost-charged-once.md` - mode mixed, critical. Every listing that goes to review or live pays its cost once, whether by submission cost or by plan

## Group-specific micro checks
| id | Check |
|---|---|
| G-01 | The banner dismiss `href` equals `$SITE_URL{dashboard}?tab=credits` exactly: no `session_id`, `token`, `PayerID`, `credits`, `gateway` or `wbcom_credits` left in it. |
| G-02 | `data-start-balance` divided by 10^`data-balance-decimals` equals the number in `.listora-dashboard__balance-number` (ignore thousands separators) at USD, JPY and KWD. |
| G-03 | `.listora-dashboard__balance-unit` reads "credit" only at exactly 1 and "credits" at 0, 1.5, 2 and -40 (the template passes a float to `_n()`). |
| G-04 | The banner lead "Adding N credit(s) to your account..." and the settled line "N credit(s) added." both use the singular at N=1 and the plural otherwise; `credits=0` or missing falls back to "Adding your credits..." / "Credits added.". |
| G-05 | Pack header "N credits" uses the singular at 1 ("1 credit"), and the pack count on the tab equals `GET /listora/v1/credit-packs` length. |
| G-06 | The type pill text on every history row equals the REST `type_label` for that entry and its modifier class equals the entry type (`--hold`, `--deduction`, `--topup`...); no row has the `--unknown` modifier. |
| G-07 | The Amount column keeps the sign: positive rows read `+N`, negative `-N`; header "Amount" and cell text share the same alignment (right or inline-end); fractional values print as 12.5, never 12.50, matching the balance card. |
| G-08 | "Page X of Y" equals ceil(ledger rows / 20); Previous is absent on page 1 and Next absent on the last page; `credits_page=999` shows the last page, `credits_page=0` and `abc` show page 1; the pager keeps `tab=credits` in every link. |
| G-09 | Low-balance warning appears only when 0 < balance <= threshold, the threshold printed equals the option, and it never shows together with the "You owe" warning. At threshold 0 it never shows. |
| G-10 | The "Buy Credits" button in the balance card points where the pack section says to buy: the Buy Credits page when one is mapped, `#listora-credit-packs` otherwise, the external store when `wb_listora_credit_purchase_url` is set; it is hidden, not dead, when nothing can sell. |
| G-11 | The sidebar Credits badge shows the same figure and format as `.listora-dashboard__balance-number` (both fractional-aware, both negative-aware). |
| G-12 | Receipt column: receiptable rows show "View receipt" with `target="_blank" rel="noopener"`; every other row shows a dash and no anchor; no row shows an empty cell. |
| G-13 | At 390 each history row stacks and every cell carries its `data-label` text (Date, Type, Amount, Note, Receipt) visibly; no cell is clipped and the page does not scroll sideways. |
| G-14 | Return banner dismiss and pack buttons are >= 40 x 40 at 390; the banner text never runs under the dismiss control (compare rects). |
| G-15 | CLI dry run: "Total owed" equals the sum of `should_be - credited` over the CSV rows; the second `--execute` prints the "already exist" note and zero members credited. |
| G-16 | REST `/listora/v1/credits` totals: `X-WP-Total` equals the row count shown by the pager (20 per page there, `per_page` there) and equals `COUNT(*)` of the member's ledger. |

## Known defects (expected-fail)
None marked in this folder. Every step is expected to pass; unrun steps are listed under "Not verified" in each journey.

## Not covered yet
- `wb_listora_dashboard_credit_row_actions` action output (third-party row actions) has no check; only the receipt link is covered.
- The `listora-dashboard__transactions--has-actions` layout (when an extra actions column exists) at 390.
- Credits tab with a non-integer low-balance threshold (`%d` prints it as an integer).
- Zero-price or free pack card rendering (`price` 0) and a pack with fractional credits (cards truncate with `absint`/`(int)`).
- Banner behaviour with two tabs open and the poll running in both.
- Add Listing page credit balance line (`blocks/listing-submission/render.php:496`) is named in a step but has no surface sweep (owner: `submission`).
