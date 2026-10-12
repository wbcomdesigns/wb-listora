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
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---

# Credit spends cannot overdraw, and a failed spend releases only its own hold

Regression sentinel for `WBListora\DB\Credit_Lock` (via `wb_listora_with_credits_lock()`, `wb-listora.php:1190`), which wraps plan activation (`Pricing_Plans::activate_plan_for_listing()`), Featured upgrades and renewals (`Listings_Controller::charge_and_feature()` / `charge_and_renew()`), the submission credit charge and need responses; and for the by-id hold release (`Credits::cancel_hold_by_id()`) in Featured and renewal failure paths.

## Background

- **Overdraw.** Every spend path once read the balance and then placed a hold as two steps, and the SDK's `hold()` never re-reads the balance. Two requests from one member at the same moment both passed: balance 10, two 10-credit plan activations made both listings live at balance **-10**; balance 25, two 25-credit Featured upgrades made both featured at **-25**.
- **Over-refund.** Free's failure paths called the broad `Credits::cancel_hold( $user, $listing_id )`, which also deleted the listing's earlier committed holds: a listing that had paid its 10-credit plan, balance 25, with Featured forced to fail ended at **35**.

The lock is a per-member MySQL `GET_LOCK` with a 5 s wait (`Credit_Lock::WAIT_SECONDS`), re-entrant per request (a per-name depth counter), released in `finally`.

Roles: the owner / member spending credits; a second member is the "a different member is never delayed" control; logged out cannot spend (401); admin is the control (admin top-ups are not spends).

## Setup

- Combo (Free + Pro), Monetization ON. Standard plan costs 10 credits; Featured costs 25 (Settings > Credit Costs; the QA site default for featured is 0, set it and restore). A member with two listings they own.
- Races need two processes. Widen the window with `add_filter( 'wbcom_credits_balance', function ( $b ) { usleep( 800000 ); return $b; } );` inside each `wp eval-file` process, start both with `&`, then `wait`.

## Steps

### 1. Plan activation race
- **Action**: member balance 10, two pending listings. In two parallel processes call `\WBListoraPro\Features\Pricing_Plans::activate_plan_for_listing( $user, <standard plan slug>, $listing )`, one per listing (the argument is the plan SLUG, signature `( $user_id, $plan_slug, $listing_id, $discount = 0 )`).
- **Expect**: exactly one `true` (listing published), the other `listora_insufficient_credits` (listing stays pending). Balance **0**, never negative.

### 2. Featured race
- **Action**: balance 25, both listings published and not featured. In two parallel processes `POST /listora/v1/listings/{id}/feature` as the member.
- **Expect**: one HTTP 200, the other **402** `listora_insufficient_credits`. Balance **0**.

### 3. Failed Featured keeps earlier charges
- **Action**: on a listing that already paid its plan (ledger has `hold`, `refund`, `deduction` for the plan), balance 25. Add `add_filter( 'wb_listora_before_feature_listing', fn() => new WP_Error( 'qa', 'forced' ) )` and request Featured.
- **Expect**: HTTP 500 `listora_feature_failed`, balance still **25**, the plan's `hold` row still present.

### 4. Need responses still serialise (shared lock)
- **Action**: Credits per response 5, vendor balance 5, two open needs. Two parallel `Need_Response_Manager::create()` calls (`wb-listora-pro/includes/needs/class-need-response-manager.php:73`, the charge runs inside `wb_listora_with_credits_lock()` at `:216`).
- **Expect**: one created, one `listora_insufficient_credits`, balance **0**.

### 5. Busy is refused, not charged
- **Action**: while one process holds the lock longer than 5 s (`wb_listora_with_credits_lock( $uid, fn() => sleep( 8 ) )`), a second spend by the same member runs `wb_listora_with_credits_lock( $uid, fn() => 'ran' )`.
- **Expect**: the second returns WP_Error `listora_credits_busy` (`data.status` 409) after ~5 s and its callback never runs; a different member's lock is acquired immediately (0 s); a nested call by the same member inside the first returns normally.

### 6. Positive control, role ladder
- **Control**: step 5's "different member is never delayed" and nested call prove the lock is per member and re-entrant; a global lock would delay both.
- **Another member** (second member's own spend) proceeds while the first member's spend is blocked.
- **Logged out**: `POST /listora/v1/listings/{id}/feature` returns 401 `listora_unauthorized`.
- **Non-owner**: spending on a listing the member does not own returns 403 (and no hold row).

### 7. Restore
- Delete test listings, reset balances and Credits per response, clear `_transient_listora_rl_%`.

## Fail diagnostics

- A negative balance in steps 1, 2 or 4: a spend path no longer runs inside `wb_listora_with_credits_lock()` (`includes/rest/class-listings-controller.php:1618` and `:2093`, `includes/rest/class-submission-controller.php:981`, `wb-listora-pro/includes/features/class-pricing-plans.php:3592` / `:3616`, `class-need-response-manager.php:216`), or the lock is released before the hold row is written.
- Balance above 25 in step 3: a failure path is back on `Credits::cancel_hold()` instead of `cancel_hold_by_id()` (`class-listings-controller.php:1722`, `:2279`; `class-pricing-plans.php:4035`; `class-need-response-manager.php:290`).
- Nested activations (auto-resume) deadlock or error: `Credit_Lock::run()` lost its per-name depth counter (`includes/db/class-credit-lock.php:66-102`).

## Not verified

Step 5 (busy 409 after 5 s, different member 0 s, nested ok) was run live with two wp-cli processes on 2026-10-12 (no ledger rows touched), and the logged-out 401 / non-owner 403 on `POST /listings/{id}/feature` were called live. Steps 1 to 4 were NOT run: they need seeded balances, a configured Featured cost and plan, and parallel HTTP clients on the shared QA site.
