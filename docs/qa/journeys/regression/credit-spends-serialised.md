---
journey: credit-spends-serialised
plugin: wb-listora
priority: critical
roles: [owner]
covers: [credits, pricing-plans, featured-upgrade, renewal, need-respond, card-10337028860, card-10337029717]
prerequisites:
  - "Combo (Free + Pro). Monetization ON. Standard plan costs 10 credits; Featured costs 25 (Settings > Credit Costs)"
  - "A member with two listings they own"
estimated_runtime_minutes: 10
---

# Credit spends cannot overdraw, and a failed spend releases only its own hold

Regression sentinel for `WBListora\DB\Credit_Lock` (via `wb_listora_with_credits_lock()`), which now wraps plan activation (`Pricing_Plans::activate_plan_for_listing()`), Featured upgrades and renewals (`Listings_Controller::charge_and_feature()` / `charge_and_renew()`) and need responses; and for the by-id hold release in Free's Featured and renewal failure paths.

## Background

- **Card 10337028860.** Every spend path read the balance and then placed a hold as two steps, and the SDK's `hold()` never re-reads the balance. Two requests from one member at the same moment both passed. Reproduced: balance 10, two 10-credit plan activations -> both listings live, balance **-10**; balance 25, two 25-credit Featured upgrades -> both featured, balance **-25**.
- **Card 10337029717.** Free's Featured and renewal failure paths called the broad `Credits::cancel_hold( $user, $listing_id )`, which also deleted the listing's earlier committed holds. Reproduced: a listing that had paid its 10-credit plan, balance 25, Featured forced to fail -> balance **35**.

Races need two processes. Widen the window by adding `add_filter( 'wbcom_credits_balance', function ( $b ) { usleep( 800000 ); return $b; } );` inside each `wp eval-file` process, then start both with `&` and `wait`.

## Steps

### 1. Plan activation race
Member balance 10, two pending listings. In two parallel processes call `\WBListoraPro\Features\Pricing_Plans::activate_plan_for_listing( $user, <standard plan id>, $listing )`, one per listing.
- **Expect**: exactly one `true` (listing published), the other `listora_insufficient_credits` (listing stays pending). Balance **0**, never negative.

### 2. Featured race
Member balance 25, both listings published and not featured. In two parallel processes `POST /listora/v1/listings/{id}/feature` as the member.
- **Expect**: one HTTP 200, the other **402** `listora_insufficient_credits`. Balance **0**.

### 3. Failed Featured keeps earlier charges
On a listing that already paid its plan (its ledger has `hold`, `refund`, `deduction` for the plan), balance 25. Add `add_filter( 'wb_listora_before_feature_listing', fn() => new WP_Error( 'qa', 'forced' ) )` and request Featured.
- **Expect**: HTTP 500 `listora_feature_failed`, balance still **25**, the plan's `hold` row still present.

### 4. Need responses still serialise (shared lock)
Credits per response 5, vendor balance 5, two open needs. Two parallel `Need_Response_Manager::create()` calls.
- **Expect**: one created, one `listora_insufficient_credits`, balance **0**.

### 5. Busy is refused, not charged
While one process holds the lock longer than 5 s (e.g. `sleep( 6 )` inside the balance filter), a second spend by the same member returns **409** `listora_credits_busy` and changes nothing. A different member is never delayed.

### 6. Restore
Delete test listings, reset balances and Credits per response, clear `_transient_listora_rl_%`.

## Fail diagnostics
- A negative balance in 1, 2 or 4 -> a spend path no longer runs inside `wb_listora_with_credits_lock()`, or the lock is released before the hold row is written.
- Balance above 25 in step 3 -> a failure path is back on `Credits::cancel_hold()` instead of `cancel_hold_by_id()`.
- Nested activations (auto-resume) deadlock or error -> `Credit_Lock::run()` lost its per-name depth counter.
