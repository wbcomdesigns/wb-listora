---
journey: monetization-status-is-one-answer
plugin: wb-listora
priority: critical
roles: [administrator, subscriber, anonymous]
covers:
  - BC 10208510192
likely_files:
  - includes/class-template-helpers.php
  - blocks/user-dashboard/render.php
  - templates/blocks/user-dashboard/tab-credits.php
  - ../wb-listora-pro/includes/class-monetization.php
  - ../wb-listora-pro/blocks/credit-purchase/render.php
prerequisites:
  - "Free + Pro active, Monetization feature ON for steps 1-12; a real non-admin member (subscriber2) for the member half"
  - "WooCommerce + woocommerce-gateway-dummy only for steps 9-12"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Owner and member never describe the same site differently

Every surface reads `wb_listora_get_monetization_status()` (`includes/class-template-helpers.php:847`), answered by Pro `Monetization::resolve_monetization_status()` (`wb-listora-pro/includes/class-monetization.php:127`). States, first unmet wins: `disabled`, `no_packs`, `needs_gateway`, `ready`. "Can pay" is the union of: an available direct gateway, a credit MAPPING on an available adapter, a pack URL / external purchase page (`wb_listora_credit_purchase_paths()`).

Verified via: `wp eval` for the state resolver (`ready` and `disabled` observed live on the demo site) and source read for the copy of the other states. Rendering as admin vs member in a browser, and steps 9-12 (WooCommerce dummy purchase) were NOT executed in this pass.

## Copy per state (source, `templates/blocks/user-dashboard/tab-credits.php:282-312`, Pro `class-monetization.php:127-215`)
| State | Member sees (Credits tab) | Owner sees |
|---|---|---|
| disabled | the Credits tab is not offered at all (`wb_listora_dashboard_tab_available( 'credits' )` false, observed) | "Credits and paid plans are turned off. Enable Monetization to sell credits." + "Open Features" |
| no_packs + store URL | "Buy credits from our store" + "Visit Store" button | "Credits are not on sale yet. Connect a payment path (Stripe, PayPal or a payment plugin) and add a credit pack so members have something to buy." + "Set up payments" |
| no_packs, no URL | "Credits are not on sale yet" / "This site has not put any credit packs on sale. Check back soon." no button | same owner copy |
| needs_gateway | "Checkout is unavailable right now" / "Credits cannot be purchased at the moment. Please try again later." no buy CTA | "Credit packs are ready, but there is no way to pay for them. Connect a payment method to open checkout." + "Connect a payment method" |
| ready | packs with Buy buttons | "Members can buy credits through <routes>." + "Manage credit packs" |

## Steps
Run each state and check ALL surfaces (member dashboard Credits tab, Buy Credits block/page `/buy-credits/`, admin Transactions and Credit Mappings notices), as an administrator AND a real non-admin member.

1. **ready via external product URL, no direct gateway.** Give a pack a `url`, configure no gateway.
   - **Expect**: member dashboard shows a working Buy Now, not "checkout unavailable". This was the original regression.
2. **needs_gateway.** Packs, no gateway, no pack URLs.
   - **Expect**: dashboard and Buy Credits give the SAME member sentence; no buy CTA anywhere on the credits tab; admin Transactions and Credit Mappings show a warning with a working deep link.
3. **no_packs with a store URL.** Copy invites the store because a "Visit Store" button renders (copy and button agree in both directions).
4. **no_packs, no store URL.** "not on sale yet", NO button.
5. **disabled.** Members are told nothing; admin sees the Features link.

## Assertions that catch the class
- No member-facing surface contains "administrator", "payment gateway", "payment method", "credit mappings", "contact them" or a PHP snippet (owner words).
- Owner and member messages for the same state never contradict.
- Every visible buy CTA implies a reachable checkout.

## Same viewer, same sentence
7. In `needs_gateway`, view BOTH Buy Credits and the dashboard as an administrator: both give the owner-actionable sentence and the working "Connect a payment method" link.
8. As a non-admin member: both give the member sentence, and neither contains the owner words above.

## Purchase and spend end to end (WooCommerce + dummy gateway)
9. A credit MAPPING is a way to pay: WooCommerce active + a purchasable mapped product, no gateway, no pack URL -> `ready` (fail if `needs_gateway`).
10. Buy the mapped product as a member -> a `topup` ledger row and the dashboard showing the credits (minor units: 5000 shows "50.00").
11. Spend: activate a paid plan on a listing -> `hold` -> `refund` (hold release) -> `deduction`, netting the plan cost once; the listing publishes.
12. Spend with too few credits -> `WP_Error( listora_insufficient_credits )` and the balance unchanged.
- **API note**: `Credits::deduct()` is the COMMIT half of a hold/commit pair; without a preceding `hold()` it writes a matching refund and nets to zero while returning `true`. Test spending through the plan path.

## Role ladder
| Actor | Result |
|---|---|
| Administrator | owner copy + fix link (steps 2, 7) |
| Member | member copy only (steps 1-5, 8) |
| Anonymous | `/buy-credits/` asks for login; no credit data |
| Moderator | no Settings access (cap), sees the member copy on their own dashboard |

## 390px
Credits tab cards stack, CTA >= 40px, no horizontal scroll.

## Fail diagnostics
`includes/class-template-helpers.php:847-880` (allowed states), `templates/blocks/user-dashboard/tab-credits.php:282`, Pro `class-monetization.php:127`, `blocks/credit-purchase/render.php`.
