---
journey: member-listing-statuses-shared
plugin: wb-listora
priority: high
roles: [subscriber]
covers: [user-dashboard-block, dashboard-listings-rest, listora-payment-status]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member owning a listing in status listora_payment ('Awaiting Credits') (demo: qa_vendor_01 owns 7 listings incl. one Awaiting Credits)"
estimated_runtime_minutes: 5
covers_card: 10318160202
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The web dashboard and the app agree on a member's listings

One list: `wb_listora_member_listing_statuses()` (`includes/helpers.php:356`): publish, pending, draft, listora_expired, listora_rejected, listora_deactivated, pending_verification, listora_payment (filter `wb_listora_member_listing_statuses`). Both the dashboard block and `GET /listora/v1/dashboard/listings` use it. 2.0.0 note: owners can open their own rejected / deactivated / awaiting-payment listings from the dashboard (Subscriber too; everyone else gets 404).

Verified via: Playwright as qa_vendor_01 at 1440 (tab badge, status chips) and `rest_do_request()` for REST.

## Steps

### 1. Both surfaces show the paused listing (positive control)
- **Action**: autologin qa_vendor_01, open `/my-listings/` (Listings tab, `#dash-panel-listings`); then `GET /listora/v1/dashboard/listings` as the same user.
- **Expect**: tab badge `#dash-tab-listings` reads "My Listings 7"; the panel has 7 `.listora-dashboard__listing-row` including one with the chip "Awaiting Credits"; REST `total` is 7 and its rows include status `listora_payment`. Statuses seen: Published x2, Pending Review, Expired, Rejected, Deactivated, Awaiting Credits.
- **Fail**: one surface stopped calling the helper.

### 2. There is only one list
- **Verify**: `grep -rn "'listora_deactivated', 'pending_verification'" includes/ blocks/` returns only `includes/helpers.php:356` (a second literal anywhere is the defect re-introduced; `includes/admin/class-listing-columns.php:601` is the admin list and intentionally separate).

### 3. The filter moves both surfaces at once
- **Action**: mu-plugin `add_filter( 'wb_listora_member_listing_statuses', fn( $s ) => array_diff( $s, array( 'draft' ) ) );` with a draft listing present.
- **Expect**: the draft disappears from the web tab AND the REST response; `total` drops by one in both.

### 4. An explicit status request
- **Action**: `GET /dashboard/listings?status=listora_payment`.
- **Expect**: `total` 1, only the paused listing (`listora_payment` is in `Status_Manager::get_statuses()`, so it passes the enum).

### 5. Role ladder
| Actor | Dashboard / REST |
|---|---|
| Owner (member) | sees all own listings in all member statuses |
| Another member | sees only their own; opening someone else's non-public listing URL is 404 |
| Logged out | `/my-listings/` shows the sign-in prompt; REST 401 |
| Moderator / admin | own listings only on the member dashboard (admin lists everything in wp-admin) |

### 6. Large member (2000+ rows)
- **Expect**: the tab paginates (`has_more`, `cursor`/`next_cursor` and `pages` in REST), the badge uses a COUNT, filters by status exist (the Status select: All statuses, Published, Pending Review, Draft, Expired, Rejected, Deactivated, Pending Verification, Awaiting Credits). The demo has no member with 2000 listings; seed one with a `QA-J1-` prefix via WP-CLI before claiming this step.

### 7. 390px
- **Expect**: rows stack, no horizontal page scroll (375 <= 390 observed on the Reviews tab; repeat for Listings).

## Fail diagnostics
`includes/helpers.php:356`; `includes/rest/class-dashboard-controller.php` (default status); `blocks/user-dashboard/render.php` / `includes/core/class-dashboard-data.php:115` (paused count).

## Automated coverage
`tests/integration/DashboardListingTypeTest::test_a_paused_listing_is_visible_to_the_block_and_the_app`.
