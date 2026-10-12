---
journey: blocking-enforced-on-live-contact-route
plugin: wb-listora
roles: [member, listing-owner, anonymous, administrator]
priority: critical
covers: [member-blocking, contact-form, wb_listora_can_members_contact, app-config-contact-path, BC-10184284933, BC-10183618407]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora active (Pro optional: with Pro the deprecated /contact alias must refuse too)"
  - "Two member accounts (create QA-J1-ctA, QA-J1-ctB) and a published listing owned by B (QA-J1-contact)"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [REST only]
changed: true
---

# Blocking stops contact on the route members actually reach

Historical shape: there were two contact endpoints and only one rendered on a given site, `POST /listings/{id}/contact-form` (Free) and `POST /listings/{id}/contact` (Pro lead form). Free's carried the block check; Pro's had none, so on the paid configuration the enforced route was the dead one and a blocked member could still message the person who blocked them. App Store Guideline 1.2 severity: the app posts to the live route and a reviewer tests blocking from the app.

2.0.0 collapsed this: every message goes through one send path, `wb_listora_submit_contact_message()` (`includes/helpers.php:777`), fronted by Free's `POST /listora/v1/listings/{id}/contact-form`; Pro's `POST /listora/v1/listings/{id}/contact` is a DEPRECATED alias (`wb-listora-pro/includes/features/class-lead-form.php:93`) that calls the same function, and every accepted message is saved as a lead. The block check sits in Free's path (`includes/class-contact-form.php:307`, code `listora_contact_blocked`, status 403). The guarantee to keep: blocking works on every route, so a guard on only one route re-creates the original divergence the moment a route is added or the alias is removed.

The app-config payload names the route: `contact_path` is `/listings/{id}/contact-form` (there is no `contact_route` key; it was renamed to `contact_path` and holds a full path template).

## Steps (executed 2026-10-12 with rest_do_request as the named user, no HTTP auth needed since an authenticated request needs no nonce)

### 1. Establish a blocked pair
- **Action**: as A, block B (the listing owner): the review card's block action, the dashboard, or `POST /wp-json/listora/v1/me/blocks` with `{"user_id": B}`. In code: `\WBListora\Core\Member_Blocks::block( A, B )`.
- **Expect**: `wb_listora_can_members_contact( A, B )` is `true` before and `false` after; and `wb_listora_can_members_contact( B, A )` is `false` too. Blocking is symmetric: it holds whichever party did the blocking.

### 2. The live route refuses
- **Action**: as A, `POST /listora/v1/listings/{B's listing}/contact-form` with `name`, `email`, `message` (and `phone` optional).
- **Expect**: `403` with code `listora_contact_blocked`. `200 {"sent": true}` is the original regression and must fail the run. Verified.

### 3. The deprecated Pro alias refuses too (Pro active)
- **Action**: same POST to `/listora/v1/listings/{id}/contact`.
- **Expect**: `403` `listora_contact_blocked` (verified), plus a "deprecated" notice in debug.log. Both routes are asserted regardless of which one the site shows.

### 4. Unblocking restores contact (POSITIVE CONTROL: the guard does not block everyone)
- **Action**: `DELETE /me/blocks/{B}` (or `Member_Blocks::unblock`), clear the contact transients (`wp eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%transient%wb_listora_contact%\"");'`), repeat step 2.
- **Expect**: `200` and `{"sent":true,"message":"Your message has been sent to the listing owner."}`; a lead row is saved. Verified.

### 5. The reverse block also holds
- **Action**: B blocks A, A posts to B's listing.
- **Expect**: `403 listora_contact_blocked` (verified). The error text deliberately does not say who blocked whom.

### 6. Guests are unaffected
- **Action**: logged out, POST with the per-listing page nonce (`wp_create_nonce( 'wb_listora_contact_form_' . $listing_id )`, as printed in the form).
- **Expect**: `200`, no `listora_contact_blocked`: an anonymous sender has no member identity, so `is_blocked_pair()` returns false for viewer 0 by design (verified). A guest without the nonce is `403 listora_invalid_nonce`, see `app-nonce-exempt-authenticated-writes.md`.

### 7. The app is told which route to use
- **Action**: `curl -s $SITE_URL/wp-json/listora/v1/settings/app-config | jq '.contact_path'`
- **Expect**: `"/listings/{id}/contact-form"` (verified; `includes/rest/class-settings-controller.php`, key `contact_path`).

### 8. Role ladder
| Sender | Listing owner blocked them (or they blocked owner) | No block |
|---|---|---|
| member A | 403 `listora_contact_blocked` | 200 |
| the listing owner B messaging A's listing | 403 (symmetric) | 200 |
| guest | not applicable (no identity) | 200 with the page nonce |
| administrator (control) | same rule as members: an admin who is in a blocked pair is refused too | 200 |

## Cleanup
Remove the blocks and QA-J1- users/listing, delete the QA leads (`DELETE FROM {prefix}listora_leads WHERE message LIKE 'QA-J1%'`), clear the contact transients, and delete any application password you created. The send emails the owner through `wp_mail()`.

## Fail diagnostics
| Symptom | File |
|---|---|
| Blocked member can message | block check lost: `includes/class-contact-form.php:307`; or a route reaches a sender that does not use `wb_listora_submit_contact_message()` (`includes/helpers.php:777`) |
| Alias not enforcing | `wb-listora-pro/includes/features/class-lead-form.php:90-105` |
| Guest refused as blocked | `Member_Blocks::is_blocked_pair()` must return false for viewer 0 |
| Wrong route for the app | `contact_path` in `class-settings-controller.php` |

Verified via: wp eval REST dispatch as members A and B and as a guest (steps 1-6) and curl for `contact_path`; fixtures removed afterwards. Admin-in-a-blocked-pair row is reasoned from the rule, not executed.
