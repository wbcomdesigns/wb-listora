---
journey: renewal-duration
plugin: wb-listora
priority: high
roles: [member, admin]
covers: [renewal, expiration, pricing-plans, dashboard, card-10340447577]
prerequisites:
  - "An expired listing owned by a member; a plan with duration 0 (Standard) and one with 90 days"
estimated_runtime_minutes: 6
---

# A renewal lasts the period the settings say, and a failed one changes nothing

Regression sentinel for `Status_Manager::standard_duration_days()` (`wb_listora_listing_duration_days()`), `Listings_Controller::get_renewal_quote()` / `charge_and_renew()`, Pro's `Pricing_Plans::duration_label()`, and the auto-renew lifetime check.

## Background

Card 10340447577. With Renewal duration 0 ("use the standard expiration") and Default expiration 30, a renewal gave **365** days: the stored 0 never fell back to Default expiration, and 0 then became a hardcoded 365. Publishing ignored Default expiration altogether. A plan of 0 days read "Never expires" on the plan cards while the listing actually expired. A failed renewal still counted towards `_listora_renewal_count` and showed members "wp_update_post failed: …". Renewal wrote the expiry in site time while the cron compares UTC.

## Steps

### 1. Standard period
Default expiration 30, Renewal duration 0, listing on the Standard plan (0 days). Renew from Dashboard > Listings (or `POST /listora/v1/listings/{id}/renew`).
- **Expect**: renewal quote and the renew window say **30 days**; `new_expiry` = now + 30 days in **UTC**.

### 2. Plan and Renewal duration win in that order
- A 90-day plan -> **+90**.
- Standard plan with Renewal duration 14 -> **+14**.

### 3. Lifetime
Default expiration 0 and Renewal duration 0, Standard plan.
- **Expect**: quote `duration_days` 0, the renew window says "No expiry", the renewal clears `_listora_expiration_date`, and the message is "Listing renewed. It no longer expires."

### 4. Labels agree
Plan cards (Add Listing plan step and Pricing Plans admin) show "30 days" for a 0-day plan while Default expiration is 30, and "Never expires" only when it is 0. The plan meta box help says 0 uses the standard expiration.

### 5. A failed renewal changes nothing
Force `wp_update_post()` to fail (e.g. `add_filter( 'wp_insert_post_empty_content', '__return_true' )`).
- **Expect**: HTTP 500 "We couldn't renew your listing. You weren't charged. Please try again."; balance unchanged; `_listora_renewal_count` unchanged; the old expiry date is back.

### 6. Restore
Settings and test listings back as they were.

## Fail diagnostics
- 365 days -> a hardcoded fallback came back in `get_renewal_quote()` / `charge_and_renew()`.
- Count rises on failure -> the `_listora_renewal_count` write moved back before the status update.
