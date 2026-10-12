---
journey: listing-reported-notification
plugin: wb-listora
priority: high
roles: [administrator, listora_moderator, subscriber, anonymous]
covers: [listing-reports, notifications, listing-columns]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Report Listings feature on (default), a published listing owned by a member other than admin (demo: 845, qa_vendor_01), and a second member who does not own it"
estimated_runtime_minutes: 8
covers_card: 10317616906
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Reporting a listing reaches a human

`wb_listora_listing_reported` is fired by `POST /listings/{id}/report` (`includes/rest/class-listings-controller.php:1484`) and handled by `Notifications::listing_reported()` (`includes/workflow/class-notifications.php:974`).

Two deliberate decisions: the listing OWNER is never emailed (harassment vector, tips off bad actors), and there is no new capability: recipients are the site admin address plus users with `edit_others_listora_listings` (at most 50, `class-notifications.php:1044`).

Verified via: `wp eval` firing the hook with `pre_wp_mail` capture (throttle, recipients, subject), source read for the rest. The report modal was not driven in a browser in this pass.

## Steps

### 1. A report emails staff (positive control for every "not emailed" step)
- **Action**: as a member who does not own the listing, open the listing, click "Report", pick a reason, submit (or `POST /listora/v1/listings/845/report {"reason":"spam"}`). Capture mail via `pre_wp_mail`.
- **Expect**: one mail per recipient: site `admin_email` AND every user holding `edit_others_listora_listings` (demo: qa_editor, varundubey). Subject `Listing reported: <title>`.

### 2. The owner is NOT emailed
- **Expect**: the listing author's address (`qa_vendor_01@directory.local`) appears in no recipient. The demo listing 17 is useless for this check (author address equals `admin_email`); use 845.

### 3. Repeat reports are throttled
- **Action**: fire `do_action( 'wb_listora_listing_reported', $id, array( 'reason' => 'spam' ), $n )` for n = 1..12 (distinct reporters are needed over REST because one row is kept per reporter).
- **Expect**: mail on n = 1, 5 and 10 only; the 10th subject reads `Listing reported 10 times: <title>`. With `add_filter( 'wb_listora_listing_report_notify_interval', fn() => 3 )`: n = 1, 3, 6, 9, 12.

### 4. The event can be switched off
- **Action**: Settings > Notifications, untick `Send "Listing reported"`, save (option `wb_listora_settings[notifications][listing_reported]=0`).
- **Expect**: no mail on the next report. `wb_listora_get_setting()` memoizes per request: reload instead of re-testing inside one process. Re-tick afterwards.

### 5. Staff land somewhere useful
- **Action**: as admin open Listora > All Listings (`edit.php?post_type=listora_listing`) with Screen Options untouched, after at least one report exists.
- **Expect**: the Reports column is visible. It is hidden by default ONLY while no listing has a stored report (`includes/admin/class-listing-columns.php:200-215`, `any_reported()` at `:246`). Control: with zero reports the column is hidden.

### 6. Recipients are retargetable
- **Action**: `add_filter( 'wb_listora_listing_report_recipients', fn() => array( 'abuse-desk@example.com' ) );`
- **Expect**: only that address is mailed.

### 7. Stored reports are capped
- **Expect**: the per-listing option `_listora_listing_reports_<id>` keeps the newest 200 (`wb_listora_max_stored_listing_reports`, `class-listings-controller.php:1469`), and the count in the email equals what the metabox/column can show.

### 8. The reporter's own words are not in the email
- **Verify**: the template placeholders are `listing_title, listing_url, admin_review_url, report_reason, report_count` (`includes/admin/class-email-templates-page.php:296-302`): the reason CODE and a link, never the free-text `details`.

### 9. Role ladder
| Actor | Report button / API | Mail received |
|---|---|---|
| Anonymous | login modal first; `POST` 401 | none |
| Member (not owner) | reports (step 1) | none |
| Owner | can report (no owner check in `report_listing()`); a second report by the same user is 409 `listora_already_reported` | none (step 2) |
| Moderator (qa_editor) | reports | yes |
| Admin (control) | reports | yes (`admin_email`) |

## Fail diagnostics
| Symptom | Look at |
|---|---|
| No mail | `class-notifications.php:164` hook registration; `:1018` `should_notify_for_report_count()` |
| Owner mailed | `class-notifications.php:1044` `get_listing_moderator_emails()` |
| Reports column missing | `class-listing-columns.php:200` |

## Automated coverage
`tests/integration/ListingReportedNotificationTest.php` covers steps 1-6 headlessly.
