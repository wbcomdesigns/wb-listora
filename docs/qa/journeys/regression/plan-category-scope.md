---
journey: plan-category-scope
plugin: wb-listora-pro
priority: high
roles: [admin, member]
covers: [plan-category-restriction, bc-10354810125]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WB Listora Pro active, Pricing Plans ON, at least 2 published plans"
  - "At least 2 listing categories"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A plan scoped to a category is offered there and refused elsewhere

Plans can be scoped by listing TYPE and by CATEGORY (term IDs in `_listora_plan_categories`). Judgement calls to assert
explicitly: **any overlap passes**, **a listing with no categories is never refused**, and **a plan scoped to a parent
category also covers its children** (the child's ancestors are walked, `class-pricing-plans.php:3135`).

Role ladder: admin scopes the plan (plan edit screen); the member meets it in the Add Listing plan step and at activation;
a member whose categories are outside the scope is refused server-side whatever the picker shows; logged out sees neither.
Admin is also the control: an admin (`edit_others_listora_listings`) bypasses the plan-required gate, but plan eligibility
(`listora_plan_category_mismatch`) is checked on the activation path for the listing's author.

Fixtures (prefix `QA-J1-`): categories `CAT_IN`, `CAT_OUT`, `CAT_IN_CHILD` (child of CAT_IN); `PLAN_SCOPED` (credits 5, sort order
set, `_listora_plan_categories`=[CAT_IN]) and `PLAN_OPEN`. Plans need `_listora_plan_sort_order` meta to appear in `get_all()`
(it orders by that key); plans created through the metabox always have it.

## Steps

### 1. Scope a plan from the admin UI
- **Action**: edit PLAN_SCOPED, choose only CAT_IN in the **Categories** control, Update.
- **Expect**: multi-select listing every category; `_listora_plan_categories` = `[CAT_IN]` (term IDs, not slugs).
- **On fail**: marker `listora_plan_categories_present` (`class-pricing-plans.php:2387`, saved `:2648`) distinguishes "cleared" from "never rendered".

### 2. Offered in its category, hidden outside it (verified)
- **Action**: `Pricing_Plans::get_all( '', array( CAT_IN ) )`, `( '', array( CAT_OUT ) )`, `get_all()` (`:3253`).
- **Expect**: in-scope has PLAN_SCOPED and PLAN_OPEN; out-of-scope has PLAN_OPEN only; unfiltered has both.
- **Large site**: `get_all()` returns at most 20 plans (`posts_per_page` 20); more than 20 published plans are not all offered.

### 3. Overlap passes (verified)
- `plan_allows_category( PLAN_SCOPED, [ CAT_OUT, CAT_IN ] )` is true.

### 4. An uncategorised listing is not refused (verified)
- `plan_allows_category( PLAN_SCOPED, [] )` is true. (The picker can render before categories are chosen.)

### 5. Parent covers child (verified)
- `plan_allows_category( PLAN_SCOPED, [ CAT_IN_CHILD ] )` is true; `[ CAT_OUT ]` alone is false (negative control).

### 5b. Parent scope covers a child at BOTH gates (card 10354810125)
- **Action**: PLAN_SCOPED scoped to parent `Motorcycle`; a listing categorised only under its child `Cruiser`. Run `plan_allows_category( PLAN_SCOPED, [ Cruiser ] )`, then `activate_plan_for_listing( $author, PLAN_SCOPED, $listing_in_Cruiser )`, then in the Add Listing wizard pick `Cruiser` and open the Plan step.
- **Expect**: helper true; activation succeeds with credits deducted by the plan cost (no `listora_plan_category_mismatch`); the plan card is visible and its radio enabled in the wizard. Grandchild of the parent also true.
- **Read-back**: balance before minus after equals the plan cost, `_listora_plan_id` = PLAN_SCOPED; negative control: a category under an unrelated parent still returns `listora_plan_category_mismatch` and the balance is unchanged.
- **fails_on**: 1cfb2e3 (Pro parent of 4a0ea5c). Not verified live.

### 6. Activation refuses a listing outside the scope (verified)
- **Action**: `Pricing_Plans::activate_plan_for_listing( $author, PLAN_SCOPED, $listing_in_CAT_OUT )`.
- **Expect**: WP_Error `listora_plan_category_mismatch`, status 400 (`class-pricing-plans.php:792`), no credits held or deducted (balance unchanged).
  Permanent failures never park the listing in "Awaiting credits" (`permanent_plan_failure_codes()`, `:1417`).

### 7. An unrestricted plan is unaffected (verified)
- Same listing with PLAN_OPEN activates; balance down by its cost.

### 8. Restore
Delete fixtures (plans, categories, listing, user).

## Notes
- **Term IDs, not slugs**: renaming a category must not detach the plan.
- Type and category restrictions are independent and both apply.

## Not verified
Steps 1 and the Add Listing plan-picker rendering (browser, 390) were not exercised; checked at the PHP API level only.
