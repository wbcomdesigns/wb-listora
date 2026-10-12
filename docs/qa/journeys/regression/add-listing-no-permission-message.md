---
journey: add-listing-no-permission-message
plugin: wb-listora
priority: high
roles: [member-without-cap, subscriber, anon, administrator]
covers: [10380188010, wb_listora_can_offer_submission, submission-no-permission]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A user whose role lacks submit_listora_listing: a WooCommerce `customer` (create QA-J1-cust with that role)"
  - "A subscriber (e.g. subscriber2) and a logged-out browser"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A member who cannot submit gets a reason, not a blank page

A signed-in member whose role lacks `submit_listora_listing` (a WooCommerce `customer`, an LMS student) used to open Add Listing and get an empty page, and My Dashboard still offered them Add Listing. 2.0.0 shows a reason with a link to My Dashboard and stops offering Add Listing.

Pages on the reference site: Add Listing `/add-listing/`, member dashboard `/my-listings/` (there is NO `/my-dashboard/`; it 404s).

## Steps

### 1. Customer opens Add Listing (the reported page)
- **Action**: log in as the `customer`-role user (`?autologin=<login>`), open `$SITE_URL/add-listing/` at 1440 and 390.
- **Expect**: `.listora-submission--no-permission` (inside `.listora-submission__login-prompt[role=status]`) with heading "You cannot add listings", the text "Your account does not have permission to add listings on this site. If you think it should, please contact the site owner.", and a secondary button "Go to My Dashboard" whose href is `/my-listings/`. No wizard (`.listora-submission__next` absent). At 390 no horizontal page scroll; the button is >= 40px tall.

### 2. Customer's dashboard offers nothing to add
- **Action**: open `/my-listings/` as the same user.
- **Expect**: no `.listora-dashboard__header-actions a[href*="action=add"]` and no "Add Your First Listing"; the empty state (`.listora-empty__desc`) reads "Your account cannot add listings on this site. Listings you are given will appear here."
- **Note**: the theme menu's own "Add Listing" item (two `li.menu-item` in header and mobile menu) is not Listora's and stays; scope the assertion to `.listora-dashboard`.

### 3. POSITIVE CONTROL - role ladder
- **Subscriber (subscriber2)**: `/add-listing/` renders the wizard (`.listora-submission__next`, no `--no-permission`); `/my-listings/` shows `.listora-dashboard__header-actions a[href*="action=add"]` "Add Listing", and, when the member has no listings, "Add Your First Listing" (`.listora-empty__actions a`).
- **Logged out**: `/add-listing/` shows `.listora-submission__login-prompt` with Log in, not the no-permission message.
- **Administrator (control)**: wizard renders, header Add Listing present.
- Expect all three to differ from step 1, proving the message in step 1 is permission-driven and not a broken page.

## Pass criteria

1. The member without the capability sees the reason and the My Dashboard link on Add Listing.
2. No Add Listing CTA for them inside the dashboard block or its empty state (`wb_listora_can_offer_submission()`).
3. Subscriber, logged-out and admin views are unchanged (steps above).
4. Message is filterable through `wb_listora_submission_no_permission_message`.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Blank Add Listing for the customer | no-permission branch lost | `blocks/listing-submission/render.php:217-228` |
| Dashboard still shows Add Listing | `wb_listora_can_offer_submission()` not consulted | `includes/class-template-helpers.php:964`, `blocks/user-dashboard/render.php:614`, `templates/blocks/user-dashboard/tab-listings.php:101-107` |

Verified via: curl/DOM on http://wb-listora.local as QA-J1-cust (customer), subscriber2 and anonymous. 390px layout and tap target not verified in a browser (no Playwright in this run).

## Not verified
- 390px horizontal scroll and 40px tap target (curl only).
- Dark-mode and unknown-theme rendering.
