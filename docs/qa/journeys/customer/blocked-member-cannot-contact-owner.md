---
journey: blocked-member-cannot-contact-owner
plugin: wb-listora
priority: critical
roles: [member, listing-owner, anonymous, administrator]
covers: [member-blocking, contact-form, wb_listora_can_members_contact, app-config-contact-path, BC-10184284933, BC-10183618407]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Free active (Pro optional: with Pro the deprecated /contact alias must refuse too)"
  - "Two throwaway members A = {other} and B = {owner}, and a published {listing} owned by B (create QA-J1 data and remove it afterwards)"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
site_profile: required
supersedes: ["ora:regression/blocking-enforced-on-live-contact-route.md"]
fails_on: "pre-2.0.0 split routes (Pro /contact had no block check); no sha recorded"
---

# Blocking stops contact on the route members actually reach

## Why
There were two contact endpoints and only one rendered per site; only Free's carried the block check, so on the paid configuration a blocked member could still message the person who blocked them (App Store Guideline 1.2 severity). One guard on one route re-creates that divergence the moment a route is added or the alias removed.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Every message goes through `wb_listora_submit_contact_message()` (`includes/helpers.php:777`), fronted by Free's `POST /listora/v1/listings/{id}/contact-form`; Pro's `POST /listora/v1/listings/{id}/contact` is a DEPRECATED alias (`wb-listora-pro/includes/features/class-lead-form.php:93`). The block check is `includes/class-contact-form.php:307` (code `listora_contact_blocked`, 403). App-config key is `contact_path` (not `contact_route`).
- Run via `rest_do_request()` as the named user in `wp eval` (an authenticated request needs no nonce), or curl with cookie + nonce.

## Steps

### 1. Establish a blocked pair
- **Action**: as A block B: the review-card block action, the dashboard, or `POST /wp-json/listora/v1/me/blocks {"user_id": B}`; in code `\WBListora\Core\Member_Blocks::block( A, B )`.
- **Expect**: `wb_listora_can_members_contact( A, B )` true before, false after; `( B, A )` false too (symmetric).
- **Read-back**: `Member_Blocks::is_blocked_pair( A, B )` true.

### 2. The live route refuses
- **Action**: as A `POST /listora/v1/listings/{B's listing}/contact-form` with `name`, `email`, `message` (`phone` optional).
- **Expect**: `403 listora_contact_blocked`. `200 {"sent":true}` is the original regression and fails the run.
- **Read-back**: no new `listora_leads` row, no new email-log row.

### 3. The deprecated Pro alias refuses too (Pro active)
- **Action**: same POST to `/listora/v1/listings/{id}/contact`.
- **Expect**: `403 listora_contact_blocked`, plus a "deprecated" notice in `debug.log`. Assert both routes whichever one the site shows. Alias delivery, nonce and hook bridges: `pro:customer/lead-form-deprecated-route.md`.

### 4. Unblocking restores contact (positive control)
- **Action**: `DELETE /me/blocks/B` (or `Member_Blocks::unblock`), clear contact transients (`DELETE FROM ${PREFIX}options WHERE option_name LIKE '%transient%wb_listora_contact%'`), repeat step 2.
- **Expect**: `200 {"sent":true,"message":"Your message has been sent to the listing owner."}`.
- **Read-back**: a lead row is saved.

### 5. The reverse block also holds
- **Action**: B blocks A, A posts to B's listing.
- **Expect**: `403 listora_contact_blocked`; the error text does not say who blocked whom.

### 6. Guests are unaffected
- **Action**: logged out, POST with the page nonce (`wp_create_nonce( 'wb_listora_contact_form_' . $listing_id )`, as printed in the form).
- **Expect**: `200`, no `listora_contact_blocked` (`is_blocked_pair()` is false for viewer 0 by design); without the nonce `403 listora_invalid_nonce`.

### 7. The app is told which route to use
- **Action**: `curl -s $SITE_URL/wp-json/listora/v1/settings/app-config | jq '.contact_path'`.
- **Expect**: `"/listings/{id}/contact-form"` (`includes/rest/class-settings-controller.php`).

### 8. Contact form hides for a blocked pair (UI)
- **Action**: A opens B's listing while blocked.
- **Expect**: no form is rendered to A. Source-verified; Not verified live.

## Role ladder
| Sender | Blocked pair | No block |
|---|---|---|
| member A | `403 listora_contact_blocked` | `200` |
| listing owner B messaging A's listing | `403` (symmetric) | `200` |
| {guest} | not applicable (no identity) | `200` with the page nonce |
| {admin} (control) | same rule as members; an admin in a blocked pair is refused too (reasoned from the rule, not executed) | `200` |

## Pass criteria
1. Both contact routes return `403 listora_contact_blocked` for a blocked pair in either direction.
2. Unblocking restores `200` and a lead row; guests are unaffected.
3. `app-config.contact_path` names the live route.

## Fail diagnostics
| Symptom | File |
|---|---|
| Blocked member can message | block check lost `includes/class-contact-form.php:307`, or a route not using `wb_listora_submit_contact_message()` (`includes/helpers.php:777`) |
| Alias not enforcing | `wb-listora-pro/includes/features/class-lead-form.php:90-105` |
| Guest refused as blocked | `Member_Blocks::is_blocked_pair()` must be false for viewer 0 |
| Wrong route for the app | `contact_path` in `class-settings-controller.php` |

## Not verified
Steps 1-7 were executed on 2026-10-12 via `rest_do_request` as A, B and a guest and curl for `contact_path`; step 8 and the admin row were not.

## Cleanup
Remove the blocks and QA-J1 users/listing, delete QA leads (`DELETE FROM ${PREFIX}listora_leads WHERE message LIKE 'QA-J1%'`), clear the contact transients, delete any application password you created. The send emails the owner through `wp_mail()`.
