---
slug: monetization-status-is-one-answer
priority: critical
covers:
  - BC 10208510192
likely_files:
  - includes/class-template-helpers.php
  - blocks/user-dashboard/render.php
  - templates/blocks/user-dashboard/tab-credits.php
  - ../wb-listora-pro/includes/class-pro-plugin.php
  - ../wb-listora-pro/blocks/credit-purchase/render.php
---

# Owner and member never describe the same site differently

Every surface used to answer "can a member buy credits?" for itself, from
different inputs. Free's dashboard tested for a **direct SDK gateway**; Pro's
Buy Credits block tested whether **packs resolve to a checkout URL**. A pack
sold as an external WooCommerce product satisfies the second and not the first
— so the dashboard told members to *"contact the administrator"* on a site that
was ready to take their money, while Buy Credits listed the same pack as
available.

That is lost revenue, not a copy nit. All surfaces now read
`wb_listora_get_monetization_status()`, which Pro answers.

## The four states

`disabled` · `no_packs` · `needs_gateway` · `ready`

## Steps

Run each state and check **all four surfaces**, as an **admin AND a real
non-admin member**. Testing as admin only will pass while members see something
different — the Buy Credits block deliberately shows admins a developer-facing
message that members must never see.

1. **ready via external product URL, no direct gateway.** Give a pack a `url`
   and configure no gateway.
   - **Expect:** member dashboard shows a working **Buy Now**, not "contact the
     administrator". **This is the regression that caused the card** — it is
     the one case where the old code disagreed with itself.
2. **needs_gateway.** Packs, no gateway, no pack URLs.
   - **Expect:** dashboard and Buy Credits show the *same* member sentence.
   - **Expect:** NO buy CTA anywhere on the credits tab — a "Buy Credits"
     button above "credits cannot be purchased" is the same contradiction
     inside one screen.
   - **Expect:** admin Transactions + Credit Mappings show a warning notice
     with a working deep link.
3. **no_packs, with a store URL configured.**
   - **Expect:** copy INVITES the store ("Buy credits from our store") because
     a "Visit Store" button renders. Copy and button must agree in both
     directions.
4. **no_packs, no store URL.**
   - **Expect:** "not on sale yet", and NO button.
5. **disabled.**
   - **Expect:** members are told nothing at all — the feature does not exist
     for this site. Admin sees the Features link.

## Assertions that catch the class

- No member-facing surface ever contains: "administrator", "payment gateway",
  "credit mappings", "contact them", or a PHP snippet. Those are owner words;
  to a member they read as a broken site.
- Owner message and member message for the same state never contradict.
- Every buy CTA visible implies a reachable checkout.

## Same viewer, same sentence (added after QA bounce)

Members agreeing with each other is not enough. QA bounced the first fix
because an ADMIN saw two explanations for one state: Buy Credits named the
missing gateway while the dashboard said "try again later".

7. In `needs_gateway`, view BOTH surfaces as an **administrator**.
   - **Expect:** both give the owner-actionable sentence and a working fix
     link ("Connect a payment method").
8. View both as a **non-admin member**.
   - **Expect:** both give the member sentence, and NEITHER contains
     "gateway", "payment method", "administrator" or "credit mappings".

## Purchase and spend, end to end

Install `woocommerce-gateway-dummy` and map a WooCommerce product to credits.

9. **A credit MAPPING is a way to pay.** WooCommerce active + a purchasable
   mapped product + NO direct gateway and NO pack URL.
   - **Expect:** state `ready`.
   - **Fail if:** `needs_gateway`. The first version of the resolver tested
     only a direct gateway and pack URLs, so it declared checkout unavailable
     on the most common WooCommerce setup there is.
10. Buy the mapped product with Dummy Payment as a member.
    - **Expect:** a `topup` ledger row and the dashboard showing the credits.
      Balances are stored in MINOR units: 5000 displays as "50.00".
11. Spend: activate a paid plan on a listing.
    - **Expect:** `hold` → `refund` (hold release) → `deduction`, netting the
      plan cost once. The listing publishes.
12. Spend with too few credits.
    - **Expect:** `WP_Error( listora_insufficient_credits )` and the balance
      **unchanged** — the hold must never leave an orphan debit.

**API note for future tests:** `Credits::deduct()` is the COMMIT half of a
hold/commit pair (`Ledger::deduct_with_hold_release()`), not a standalone
"remove credits" call. Invoked without a preceding `hold()` it writes a
matching refund and nets to zero, while still returning `true`. Test spending
through the plan path, not by calling `deduct()` directly.
