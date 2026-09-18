---
journey: member-listing-statuses-shared
plugin: wb-listora
priority: high
roles: [subscriber]
covers: [user-dashboard-block, dashboard-listings-rest, listora-payment-status]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member owning at least one listing in `listora_payment` (paused awaiting credits)"
estimated_runtime_minutes: 4
covers_card: 10318160202
---

# The web dashboard and the app agree on which of a member's listings exist

Regression sentinel for the shared member-status list.

## Background

The statuses a member's own listing can hold were written out as a literal in two places, and they drifted: `GET /dashboard/listings` was missing `listora_payment` while the block's server render had it. A listing paused awaiting credits therefore showed on the web dashboard and was invisible in the app - the one surface where the member would buy the credits to resume it - and the app's `total` agreed with the omission, so nothing hinted anything was missing.

Both surfaces now call `wb_listora_member_listing_statuses()` (`includes/helpers.php`), filterable as `wb_listora_member_listing_statuses`.

This is the same failure the block had internally once before: its sidebar badge summed 4 statuses while its rows query used 8. Two literals, one list.

## Steps

### 1. Both surfaces show the paused listing
- **Action**: as a member with a `listora_payment` listing, open the dashboard Listings tab, then call `GET /listora/v1/dashboard/listings`.
- **Expect**: the paused listing appears in BOTH, and the REST `total` matches the number of rows the web tab renders.
- **On fail**: one of the two stopped calling the helper.

### 2. There is only one list
- **Verify**: `grep -rn "listora_deactivated', 'pending_verification'" includes/ blocks/` returns only the helper. A second literal anywhere is the defect re-introduced, whether or not it currently matches.

### 3. The filter moves both surfaces at once
- **Action**: `add_filter( 'wb_listora_member_listing_statuses', fn( $s ) => array_diff( $s, array( 'draft' ) ) );` in an mu-plugin.
- **Expect**: drafts disappear from the web tab AND the REST response together.

### 4. An explicit status request still works
- **Action**: `GET /dashboard/listings?status=listora_payment`.
- **Expect**: only paused listings. `listora_payment` is in `Status_Manager::get_statuses()`, so it passes the route's enum - only the DEFAULT was ever wrong, which is why this went unnoticed.

## Automated coverage

`tests/integration/DashboardListingTypeTest::test_a_paused_listing_is_visible_to_the_block_and_the_app`.
