---
slug: monetization-is-discoverable
priority: high
covers:
  - BC 10208510255
likely_files:
  - includes/admin/class-admin.php
  - ../wb-listora-pro/includes/class-pro-plugin.php
---

# An owner can find monetization without already knowing where it is

Monetization spans four different kinds of admin object — packs in a Settings
tab, plans as a CPT, coupons on their own screen, transactions on another — so
an owner had to already know the answer to find it.

The fix is NOT a new menu. Rule 1 of the admin UX rulebook reserves submenus
for data with rows (which is why Credit Mappings was moved into Settings), and
Rule 2's menu grouping is already implemented. What was missing was discovery,
so the setup path was added to the dashboard checklist owners already read —
and crucially it carries the ORDER, which no menu can express: packs → a way to
pay → a plan that charges → verify.

## Steps

1. Monetization ON, no packs. Open Listora → Dashboard.
   - **Expect:** three extra items — Credit packs created / Payment method
     connected / Pricing plan published — in that order, after Free's items.
   - **Expect:** "Credit packs created" is NOT done and deep-links to
     Settings → Credits.
2. Create a pack. Reload.
   - **Expect:** that item flips to done; "Payment method connected" is now the
     first incomplete step.
3. **Payment method must agree with the member-facing screens.** Configure a
   pack with an external product URL and no gateway.
   - **Expect:** "Payment method connected" reads DONE, because a member can in
     fact pay. It reads the same resolver the Buy Credits page and member
     dashboard read (BC 10208510192).
   - **Fail if:** the checklist says not-done while the member dashboard offers
     a working Buy Now. A checklist that disagrees with the screens it links to
     is the contradiction bug reintroduced one level up.
4. Turn monetization OFF. Reload.
   - **Expect:** all three items GONE — 7 items, not 10. A directory that never
     sells anything must not be shown four steps it will never take.
5. Confirm every incomplete item's link lands on the screen that completes it.
   - **Fail if:** any item can never become done. An item stuck at not-done
     tells an owner their setup failed forever (BC 10186092511).
