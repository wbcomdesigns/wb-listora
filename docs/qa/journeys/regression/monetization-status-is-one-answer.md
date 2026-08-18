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
