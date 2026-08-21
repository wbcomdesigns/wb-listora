---
journey: plan-category-scope
plugin: wb-listora-pro
priority: high
roles: [admin, member]
covers: [plan-category-restriction]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WB Listora Pro active, Monetization ON, at least 2 published pricing plans"
  - "At least 2 listing categories, and one published listing in a known category"
estimated_runtime_minutes: 6
---

# A plan scoped to a category is offered there and refused elsewhere

Pricing plans could be scoped by listing TYPE since 1.6.0 and by CATEGORY since 1.7.0. The category level is what lets one directory price Lawyers differently from Dog Walkers when both are the same type.

Two behaviours in this journey are deliberate judgement calls rather than obvious defaults, and both are easy to "fix" into a bug later: **any overlap passes**, and **a listing with no categories is never refused**. Assert them explicitly.

## Setup

- Note two category term IDs: one to scope to (`CAT_IN`), one to exclude (`CAT_OUT`)
- Note a scoped plan (`PLAN_SCOPED`) and an unrestricted one (`PLAN_OPEN`)
- Record the starting value so the site can be restored:
  ```bash
  wp eval 'echo wp_json_encode( get_post_meta( PLAN_SCOPED, "_listora_plan_categories", true ) );'
  ```

## Steps

### 1. Scope a plan from the admin UI
- **Action**: edit `PLAN_SCOPED`, select only `CAT_IN` in the **Categories** control, Update.
- **Expect**: the control renders as a multi-select listing every category, and the value persists:
  ```bash
  wp eval 'echo wp_json_encode( get_post_meta( PLAN_SCOPED, "_listora_plan_categories", true ) );'
  ```
- **On fail**: the hidden `listora_plan_categories_present` marker is what distinguishes "cleared" from "never rendered". Without it a Quick Edit wipes the scoping.

### 2. The plan is offered in its category, hidden outside it
- **Action**:
  ```bash
  wp eval 'use WBListoraPro\Features\Pricing_Plans;
    echo count( Pricing_Plans::get_all( "", array( CAT_IN ) ) ), " in-scope\n";
    echo count( Pricing_Plans::get_all( "", array( CAT_OUT ) ) ), " out-of-scope\n";
    echo count( Pricing_Plans::get_all() ), " unfiltered\n";'
  ```
- **Expect**: in-scope includes `PLAN_SCOPED`; out-of-scope excludes it but still includes `PLAN_OPEN`; unfiltered returns every plan.
- **On fail**: `Pricing_Plans::get_all()` second argument, or `plan_allows_category()`.

### 3. Overlap passes — a listing in both categories
- **Action**: `wp eval '... plan_allows_category( PLAN_SCOPED, array( CAT_OUT, CAT_IN ) );'`
- **Expect**: **true**. Any overlap is enough.
- **On fail**: someone changed intersect to a subset test. A plan that needs EVERY category becomes unusable the moment a member ticks a second box.

### 4. An uncategorised listing is not refused
- **Action**: `wp eval '... plan_allows_category( PLAN_SCOPED, array() );'`
- **Expect**: **true**.
- **On fail**: the plan step can render before categories are chosen. Refusing here empties the picker with nothing on screen explaining why.

### 5. Activation refuses a listing outside the scope
- **Action**: attempt to activate `PLAN_SCOPED` on a published listing whose categories are only `CAT_OUT`.
- **Expect**: `listora_plan_category_mismatch`, HTTP **400**, no credits held or deducted.
- **On fail**: the picker is a convenience, not a gate — a plan id arrives over REST and any id can be posted. This check is the real boundary.

### 6. An unrestricted plan is unaffected
- **Action**: same listing, `PLAN_OPEN`.
- **Expect**: allowed. Existing plans must behave exactly as they did before this feature existed.

### 7. Restore
- **Action**: restore the recorded meta value (or delete it to return the plan to unrestricted).

## Notes

- **Term IDs, not slugs.** Categories are an owner-renamable taxonomy; a slug restriction detaches silently on rename and the plan quietly becomes available everywhere. If this ever stores slugs, that is the regression.
- Type and category restrictions are **independent and both apply**. A plan scoped to a type AND a category must satisfy both.
