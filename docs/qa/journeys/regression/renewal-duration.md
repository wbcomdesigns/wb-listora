---
journey: renewal-duration
plugin: wb-listora
priority: high
roles: [member, other-member, anonymous, admin]
covers: [renewal, expiration, pricing-plans, dashboard, card-10340447577]
prerequisites:
  - "An expired listing owned by a member; a plan with duration 0 (Standard) and one with 90 days"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A renewal lasts the period the settings say, and a failed one changes nothing

Sources of truth: `Status_Manager::standard_duration_days()` (`includes/workflow/class-status-manager.php:443`, wrapped by
`wb_listora_listing_duration_days()`), `Listings_Controller::build_renewal_quote()` (`includes/rest/class-listings-controller.php:1954`),
`charge_and_renew()` (`:2107`), Pro `Pricing_Plans::duration_label()`, and the auto-renew lifetime check. Order: the plan's own
duration, then Settings > Renewal duration (`default_renewal_duration_days`), then the type's `expiration_days`, then Default
expiration (`default_expiration`); 0 all the way down = the listing no longer expires. No hardcoded 365.

2.0.0 note: expiry is off on a free directory and on while Pro's Pricing Plans is active (`wb_listora_expiration_enabled()`), but the
renewal quote does not depend on it (an owner can still renew an expired listing).

Routes: `GET /listora/v1/listings/{id}/renewal-quote`, `POST /listora/v1/listings/{id}/renew`.
Role ladder: the owner renews; another member gets 403 (`renew_listing` permission); logged out 401; admin as control may renew
and is charged nothing for a 0-cost listing. Settings save/restore: use `update_option( 'wb_listora_settings', ... )` then
`wb_listora_get_setting( null, null, true )` to flush the static cache, and restore the original afterwards.

## Steps (steps 1, 2, 3, 5 verified live 2026-10-12 via `rest_do_request` as the owner; settings restored)

### 1. Standard period
- **Setup**: Default expiration 30, Renewal duration 0, listing `listora_expired`, no plan.
- **Expect**: quote `duration_days: 30`, `can_renew_now: true`, `is_expired: true`; `new_expiry` = now + 30 days in UTC.

### 2. Plan and Renewal duration win in that order
- A 90-day plan -> +90. Renewal duration 14 with a standard plan -> quote `duration_days: 14` (verified).

### 3. Lifetime
- **Setup**: Default expiration 0 and Renewal duration 0.
- **Expect**: quote `duration_days: 0`; the renew modal duration reads "No expiry" (`jsRenewNoExpiry`); `POST /renew` returns 200 with
  `new_expiry: ""`, `renewed: true`, message "Listing renewed. It no longer expires.", `_listora_expiration_date` deleted and status `publish`.

### 4. Labels agree
- Plan cards (Add Listing plan step and Pricing Plans admin) show "30 days" for a 0-day plan while Default expiration is 30, and
  "Never expires" only when it is 0. The plan meta box help says 0 uses the standard expiration.

### 5. A failed renewal changes nothing
- **Setup**: `add_filter( 'wp_insert_post_empty_content', '__return_true' )` so `wp_update_post()` fails.
- **Expect** (verified): HTTP 500 `listora_renewal_failed` "We couldn't renew your listing. You weren't charged. Please try again.";
  balance unchanged; `_listora_renewal_count` unchanged (1 -> 1); the old expiry date is back.

### 6. Restore
Settings and test listings back as they were.

## Fail diagnostics
- 365 days: a hardcoded fallback in `build_renewal_quote()` (`class-listings-controller.php:1954`) or `charge_and_renew()` (`:2107`).
- Count rises on failure: the `_listora_renewal_count` write (`:2267`) moved before the status update.
- Expiry shifted by the GMT offset: renewal must write `gmdate()` (UTC, `:2224`).

## Not verified
The 403/401 role ladder on the renew routes (read from code), step 4 labels and the renewal modal (browser, 390) were not exercised; the 90-day plan path was read in code, not run.
