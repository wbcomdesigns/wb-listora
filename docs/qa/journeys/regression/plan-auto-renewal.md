---
journey: plan-auto-renewal
plugin: wb-listora
priority: critical
roles: [member, admin]
covers: []
prerequisites:
  - "WB Listora Pro active, Monetization feature ON"
  - "Renewal feature enabled (wb_listora_feature_enabled('renewal'))"
  - "A member with a credit balance you can set"
estimated_runtime_minutes: 12
---

# A plan renews itself from credits, or pauses

Auto-renew charges **credits**, never a card. The money question was answered when
the member topped up, so a renewal is arithmetic on a balance: enough credits,
extend; not enough, pause. There is no gateway in this path, no card on file and
nothing to dun — which is why this is a small feature rather than a billing
subsystem, and why the assertions below are about balances and post statuses
rather than payment states.

It runs inside Free's existing twice-daily expiry sweep
(`wb_listora_check_expirations`, on Action Scheduler where available), via the
`wb_listora_should_expire_listing` filter. There is no second schedule.

## Setup

```bash
# The sweep early-returns entirely when expiration is off site-wide — a run that
# "does nothing" is usually this, not a broken renewal.
wp eval 'var_export( wb_listora_get_setting( "enable_expiration", true ) );'
```

If it is `false`, enable it for the test and **restore it afterwards**. The
setting is statically cached inside `wb_listora_get_setting()`, so after writing
the option call `wb_listora_get_setting( null, null, true )` to force a reload —
otherwise the run silently uses the old value.

Fixtures:

- A plan with `_listora_plan_credits` = 10, `_listora_plan_duration_days` = 30,
  `_listora_plan_auto_renew` = 1.
- A listing owned by the test member, `post_status` = `publish`, with
  `_listora_plan_id` set and `_listora_expiration_date` **in the past**.

To lower a balance, hold THEN deduct — a bare `deduct_money()` routes through
`deduct_with_hold_release()` and nets to zero. That is API misuse, not a bug.

## Steps

### 1. It renews instead of expiring

- **Action**: `wp eval 'do_action( "wb_listora_check_expirations" );'`
- **Expect**: status still `publish`; balance down by exactly the plan cost;
  `_listora_expiration_date` moved forward by the plan duration;
  `_listora_renewal_count` incremented.
- **On fail**: if the status is `listora_expired`, the filter did not fire or
  returned true — check `has_filter( 'wb_listora_should_expire_listing' )`.

### 2. Running the sweep again does NOT charge twice

The sweep can run twice over one listing: a retried Action Scheduler job, an
overlapping WP-Cron, an admin firing the hook by hand. Each pass charging again
is the worst failure this feature can have, because it is silent and it is money.

- **Action**: set the expiry back into the past, run the sweep again.
- **Expect**: balance **unchanged**, status still `publish`.
- **On fail**: the `_listora_renewed_at` guard is not holding. It is written by
  Free's renewal itself, so a renewal within the last 24h counts as this cycle's.

### 3. Too few credits PAUSES, it does not expire

This is the difference the member feels. Expired reads as "your listing is over".
Paused reads as "top up and it comes back" — and that is literally true.

- **Action**: clear `_listora_renewed_at`, drop the balance below the plan cost,
  put the expiry in the past, run the sweep.
- **Expect**: status `listora_payment`; **nothing charged**;
  `_listora_pending_plan_failure['code']` is
  `listora_auto_renew_insufficient_credits`; `_listora_pending_plan_id` set.
- **On fail**: a listing that expires here is the regression. The member has to
  rebuild rather than top up, and the pending meta the resume path reads is gone.

### 4. Topping up brings it back with no action from anyone

- **Action**: `Credits::topup_money()` enough to cover the plan.
- **Expect**: status returns to `publish` **immediately**, and the plan cost is
  charged. No cron run, no click.
- **On fail**: `auto_resume_pending_listings()` reads `_listora_pending_plan_id`.
  If step 3 wrote a different shape, resume cannot see it — which is exactly why
  all three pause paths go through `pause_listing_for_credits()`.

### 5. NEGATIVE — plans that must not auto-renew

Run the sweep with each of these and expect the listing to **expire normally**:

| Plan | Why it must not renew |
|---|---|
| `_listora_plan_auto_renew` unset/0 | Owner never opted in |
| `_listora_plan_duration_days` = 0 | Lifetime plan — renewing charges again and stamps an expiry on a listing that should not have one |
| Listing with no `_listora_plan_id` | Nothing to renew against |

- **On fail**: a lifetime plan that renews is the expensive one — it charges the
  member on a schedule for something they bought outright.

### 6. The owner's switch survives a save that never showed the checkbox

- **Action**: Quick Edit the plan, or `wp_update_post()` on it, without the
  metabox rendering.
- **Expect**: `_listora_plan_auto_renew` **unchanged**.
- **On fail**: an unticked checkbox posts no key, which is indistinguishable
  from a save where the metabox never rendered. The
  `listora_plan_auto_renew_present` marker is what tells them apart. Without it,
  every Quick Edit silently switches auto-renew off and the owner's listings
  start expiring.

### 7. Restore

Delete the fixtures, restore `enable_expiration`, restore the balance.

## Notes

- **Renewal is charged to the LISTING AUTHOR, not the current user.**
  `wb_listora_renew_listing()` sets the author as current user for the duration
  of the call because the controller reads `get_current_user_id()` to decide
  whose balance to charge — and cron has no current user at all. If that ever
  regresses, a cron renewal charges user 0 and an admin-triggered one charges the
  admin, quietly.
- **There is one renewal implementation.** `wb_listora_renew_listing()` wraps the
  same controller method an owner's own renewal uses, so Pro's renewal caps
  (`wb_listora_before_renew_listing`) still apply to automatic renewals. A second
  "charge and extend" path is the thing to reject in review.
