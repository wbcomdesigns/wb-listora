---
journey: add-listing-no-permission-message
group: submission
actor: customer
mode: browser
plugin: wb-listora
priority: high
roles: [no-cap-member, owner, guest, admin]
covers: [10380188010, wb_listora_can_offer_submission, submission-no-permission]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A user whose role lacks `submit_listora_listing` ({nocap}: for example a WooCommerce `customer`; create QA-J1-cust with that role)"
  - "{owner} (a subscriber with the capability) and a logged-out browser"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, unbridged]
site_profile: required
supersedes: []
fails_on: "blank Add Listing page for a role without submit_listora_listing"
---

# A member who cannot submit gets a reason, not a blank page

## Why
A signed-in member whose role lacks `submit_listora_listing` (a WooCommerce customer, an LMS student) used to open Add Listing and get an empty page, and their dashboard still offered Add Listing. 2.0.0 shows a reason with a link to My Dashboard and stops offering Add Listing.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Page paths: Add Listing and member dashboard are resolved from the page registry (`wp eval 'echo wb_listora_get_page_url("add_listing");'`); the dashboard is not at `/my-dashboard/`.

## Steps

### 1. {nocap} opens Add Listing (the reported page)
- **Action**: log in as {nocap}, open Add Listing at 1440 and 390.
- **Expect**: `.listora-submission--no-permission` (inside `.listora-submission__login-prompt[role=status]`) with heading "You cannot add listings", text "Your account does not have permission to add listings on this site. If you think it should, please contact the site owner.", and a secondary button "Go to My Dashboard" linking to the dashboard. No wizard (`.listora-submission__next` absent). At 390 no horizontal page scroll; the button is >= 40px tall.

### 2. Their dashboard offers nothing to add
- **Action**: open the dashboard as {nocap}.
- **Expect**: no `.listora-dashboard__header-actions a[href*="action=add"]` and no "Add Your First Listing"; the empty state (`.listora-empty__desc`) reads "Your account cannot add listings on this site. Listings you are given will appear here." The theme menu's own "Add Listing" item is not Listora's and stays; scope the assertion to `.listora-dashboard`.

### 3. Positive control: role ladder differs
- **Action**: repeat as {owner}, {guest}, {admin}.
- **Expect**: see the Role ladder table; each differs from step 1, proving step 1 is permission-driven, not a broken page.

### 4. Extension point
- **Action**: `add_filter( 'wb_listora_submission_no_permission_message', fn() => 'Custom text' )`.
- **Expect**: step 1 shows "Custom text". Remove the filter.

### 5. Direct REST
- **Action**: `POST /submit` as {nocap}.
- **Expect**: 403 (capability), not a created listing. Not verified live.

## Role ladder
| Rung | Add Listing | Dashboard |
|---|---|---|
| {nocap} | no-permission message + dashboard link | no Add Listing CTA; explanatory empty state |
| {owner} | wizard (`.listora-submission__next`) | header "Add Listing"; "Add Your First Listing" when empty |
| {guest} | login prompt with Log in, not the no-permission message | login required |
| {admin} (control) | wizard | header Add Listing present |

## Pass criteria
1. The member without the capability sees the reason and the My Dashboard link on Add Listing.
2. No Add Listing CTA for them inside the dashboard block or empty state (`wb_listora_can_offer_submission()`).
3. {owner}, {guest} and {admin} views are unchanged; message is filterable.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Blank Add Listing | no-permission branch lost | `blocks/listing-submission/render.php` (~:217-228) |
| Dashboard still shows Add Listing | `wb_listora_can_offer_submission()` not consulted | `includes/class-template-helpers.php` (~:964), `blocks/user-dashboard/render.php` (~:614), `templates/blocks/user-dashboard/tab-listings.php` (~:101-107) |

## Not verified
Checked via curl/DOM as the three roles on 2026-10-12; 390px layout, tap target, dark mode and unbridged theme not verified in a browser; step 5 not run.

## Cleanup
Delete the QA-J1-cust user.
