---
journey: paused-listing-always-has-a-route-forward
plugin: wb-listora
priority: critical
roles: [member]
covers: [BC-10194590910, credits, pricing-plans, money-journey, dead-end]
prerequisites:
  - "Combo, monetization ON, at least one published plan with a credit cost"
  - "A member whose balance ALREADY covers that plan"
estimated_runtime_minutes: 8
covers_card: 10194590910
---

# A vendor paused on credits is never stranded

A member sat on 115 credits looking at a listing that said *"Paused - credits
needed to activate"*, with no plan named, no cost, and a **Buy credits** button
that opened an empty store. There was no way to finish, and nothing said why.

Three separate defects produced that one screen:

1. **The UI read only `_listora_pending_plan_id`.** This listing carried
   `_listora_plan_id`, so no plan and no cost rendered - just a balance and a
   promise that it would "activate automatically".
2. **The resume sweep also matched only `_listora_pending_plan_id`**, so the
   listing could never be resumed by ANY top-up. The promise was false.
3. **Resuming was only ever triggered by a top-up**, so a member who already
   held enough credits had no trigger at all - they were told to buy credits
   they did not need.

## Steps

### 1. Enough credits: activate directly
Member balance >= plan cost, listing `listora_payment`.
- The card names the plan and its cost.
- Copy reads "You already have enough credits"; the CTA is **Activate now**, not
  Buy credits.
- Clicking it: listing becomes `publish`, balance drops by EXACTLY the plan
  cost, `_listora_pending_plan_id` / `_listora_pending_plan_failure` cleared.
- **Zero console errors.** The first version of this button activated the
  listing server-side and then threw on a non-existent toast helper, so it
  looked like nothing happened. Assert the console, not just the database.

### 2. Short of credits: the old path still works
Balance < cost -> "Short by N", CTA "Buy N credits to resume", and a top-up
auto-resumes it.

### 3. Plan deleted: say so, offer no false hope
Point a paused listing at a plan id that no longer exists.
- Copy states the plan is no longer available and to contact the site owner.
- **No Buy credits CTA** - buying cannot fix it, and sending them to the store
  is the dead end this card reported.
- **Fails if** the "activates automatically" copy renders. It is untrue here.

### 4. Both meta keys resume
`Pricing_Plans::resolve_paused_plan_id()` returns the pending id when set and
falls back to `_listora_plan_id`. A listing carrying only the latter must be
picked up by the top-up sweep.

### 5. The endpoint defends itself
`POST /listora/v1/listings/{id}/activate-plan`:
- another member's listing -> 403
- logged out -> 401
- a listing that is not paused -> 409
- a paused listing with no plan recorded -> 409
- insufficient balance -> the error from `activate_plan_for_listing()`, and NO
  status change and NO deduction.

## Test-data trap

A listing whose plan still exists AND whose owner is short of credits exercises
none of this - it is the one combination that always worked. The states that
broke are "plan gone" and "already has enough", so cover both explicitly.
