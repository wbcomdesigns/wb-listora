---
journey: plan-auto-renewal
plugin: wb-listora
priority: critical
roles: [member, admin]
covers: [plan-auto-renew, credits, expiry-sweep]
prerequisites:
  - "WB Listora Pro active, Pricing Plans (monetization) ON"
  - "Renewal feature enabled (wb_listora_feature_enabled('renewal'))"
  - "A member with a credit balance you can set"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# A plan renews itself from credits, or pauses

Auto-renew charges **credits**, never a card: enough credits, extend; not enough, pause. It runs inside Free's twice-daily
expiry sweep (`wb_listora_check_expirations`, `includes/workflow/class-expiration-cron.php:31`) through the
`wb_listora_should_expire_listing` filter (`:211`), hooked by Pro at
`wb-listora-pro/includes/features/class-pricing-plans.php:116` (`maybe_auto_renew()`, `:1493`). No second schedule.

Role ladder: system journey. The member's balance and listing are the actors; the cron has no current user, so renewal is
charged to the LISTING AUTHOR (`wb_listora_renew_listing()`, `includes/workflow/renewal-helpers.php:34`). Admin is the control
(triggering the hook as admin must still charge the author, not the admin).

## Setup (changed in 2.0.0)

Expiry is OFF on a free directory and ON while Pro's Pricing Plans is active
(`wb-listora-pro/includes/features/class-pricing-plans.php:45` adds `__return_true` to `wb_listora_expiration_enabled`; Settings >
General shows "Expiration is on while Monetization is active, so paid listings run for their plan's duration and can be renewed.").
So no setting needs flipping with Pro active:
```bash
wp eval 'var_export( wb_listora_expiration_enabled() );'   # expect true on this site (setting itself is false)
```
Reading `wb_listora_get_setting('enable_expiration')` alone now misleads (it is false here while expiry is on).

Fixtures (prefix `QA-J1-`): plan with `_listora_plan_credits`=10, `_listora_plan_duration_days`=30, `_listora_plan_auto_renew`=1,
`_listora_plan_sort_order` set; a listing owned by the test member, `publish`, `_listora_plan_id` set, `_listora_expiration_date`
(UTC `Y-m-d H:i:s`) in the past. Seed credits with `Credit_System::add_credits( $user, 25, 'QA-J1', 'admin' )`.

## Steps

### 1. It renews instead of expiring
- **Action**: `wp eval 'do_action( "wb_listora_check_expirations" );'`
- **Expect** (verified): status `publish`; balance 25 -> 15; `_listora_expiration_date` = now + 30 days; `_listora_renewal_count` = 1;
  `_listora_renewed_at` stamped.
- **On fail**: status `listora_expired` means the filter did not fire (`has_filter( 'wb_listora_should_expire_listing' )`) or expiry is off.

### 2. Running the sweep again does NOT charge twice
- **Action**: set the expiry back into the past, run the sweep again.
- **Expect** (verified): balance unchanged (15), status `publish`. Guard: `_listora_renewed_at` within 24h (`class-pricing-plans.php:1520`).

### 3. Too few credits PAUSES, it does not expire
- **Action**: new listing, balance 5 (< 10), expiry in the past, run the sweep.
- **Expect**: status `listora_payment`; nothing charged; `_listora_pending_plan_failure['code']` = `listora_auto_renew_insufficient_credits`
  (verified); `_listora_pending_plan_id` set (verified).
- **OBSERVED 2026-10-12 (fails)**: the status stayed `publish` because the 2.0.0 transition guard refuses `publish -> listora_payment`
  (`includes/workflow/class-status-manager.php:124`; `Status_Manager::transition_allowed( 'publish', 'listora_payment' )` is false).
  The pause meta is written but the listing is not paused. See PRODUCT BUG in the report.

### 4. Topping up brings it back with no action from anyone
- **Action**: `Credit_System::add_credits( $user, 30, ... )`.
- **Expect**: status returns to `publish` immediately and the plan cost is charged (no cron, no click). Only meaningful once step 3
  actually paused the listing; `auto_resume_pending_listings()` is at `class-pricing-plans.php:1595`.

### 5. NEGATIVE: plans that must not auto-renew
Run the sweep for each; the listing must expire normally (`listora_expired`):
| Plan | Why |
|---|---|
| `_listora_plan_auto_renew` unset/0 | owner never opted in |
| `_listora_plan_duration_days` = 0 and Default expiration 0 | lifetime (verified: `listora_expired`) |
| listing with no `_listora_plan_id` | nothing to renew against |

### 6. The owner's switch survives a save that never showed the checkbox
- **Action**: Quick Edit the plan, or `wp_update_post()` it without the metabox rendering.
- **Expect**: `_listora_plan_auto_renew` unchanged (marker `listora_plan_auto_renew_present`, `class-pricing-plans.php:2470` / `:2621`).

### 7. Restore
Delete fixtures and the test user. Nothing global was changed (no setting flipped).

## Notes
- One renewal implementation: `wb_listora_renew_listing()` wraps the controller's `charge_and_renew()`
  (`includes/rest/class-listings-controller.php:2107`), so Pro's caps (`wb_listora_before_renew_listing`, `:2145`) apply to automatic renewals.

## Not verified
Role-ladder control (admin-triggered sweep charging the author) and step 6 were not run; step 4 could not be exercised because step 3 did not pause.
