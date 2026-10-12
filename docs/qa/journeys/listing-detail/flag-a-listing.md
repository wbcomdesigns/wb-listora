---
journey: flag-a-listing
group: listing-detail
actor: customer
mode: mixed
plugin: wb-listora
priority: high
roles: [anonymous, subscriber, owner, listora_moderator, administrator]
covers: [report-listings-feature, listings-report-rest, report-modal, report-admin-column, report-metabox, listing-reports, notifications, listing-columns, claim-report-single-notice, toast-wrap, card-9906156994, card-10317616906, card-10336063667, card-10336378720]
prerequisites:
  - "Site reachable at $SITE_URL; `report_listings` feature ON (default)"
  - "A published {listing} owned by {owner} (NOT the admin, so the owner's address differs from `admin_email`); a reporter {other} who does not own it; a moderator {editor}"
  - "Clean start: `wp option delete _listora_listing_reports_<LISTING_ID>`"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: ["ora:regression/report-listing.md", "ora:regression/listing-reported-notification.md", "ora:regression/claim-report-single-notice.md (report half and toast wrap)"]
---

# A member flags a listing, it confirms once, and staff (never the owner) are told

## Why
Reports are how bad listings reach a human. The reporter must see one clear confirmation, the report must be stored once per reporter, admins must see the flag, and staff must be emailed without tipping off the listing owner.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Reports live in the non-autoloaded option `_listora_listing_reports_<id>` (newest 200 kept, `wb_listora_max_stored_listing_reports`, `class-listings-controller.php:1469`).
- Mail capture: `pre_wp_mail` in `wp eval`, or Mailpit. `wb_listora_listing_reported` is fired at `includes/rest/class-listings-controller.php:1484`, handled by `Notifications::listing_reported()` (`includes/workflow/class-notifications.php:974`). Recipients: `admin_email` plus users with `edit_others_listora_listings` (max 50, `:1044`); no new capability.
- Code map: gate `blocks/listing-detail/render.php:574` (`$listora_can_report`), modal `:987`; REST `class-listings-controller.php:1368`, `:1411`, dedup `:1440`; store `src/interactivity/store.js` (`openReportModal`, `submitReport`); admin `includes/admin/class-listing-columns.php:411`, `class-report-metabox.php:82`, `:191`.

## Steps

### 1. Another member sees the control
- **Action**: as {other} open the {listing} page.
- **Expect**: `button.listora-detail__report-btn` "Report" (`data-wp-on--click="actions.openReportModal"`); `#listora-report-modal[role=dialog]` "Report This Listing" with `select#listora-report-reason[name=reason]` options `inaccurate, spam, closed, duplicate, offensive, other` (Inaccurate information, Spam or misleading, Permanently closed, Duplicate listing, Offensive or inappropriate, Something else), `textarea#listora-report-details`, **Submit Report**.

### 2. Submit: one styled message, no toast
- **Action**: reason `closed`, details `QA-J1 test`, **Submit Report**.
- **Expect**: `.listora-detail__report-body` hidden; `.listora-detail__report-message--success` "Report submitted. Thank you." with a tinted success background; NO `.listora-toast` in `.listora-toast-container`. `POST /listora/v1/listings/<id>/report` `200 {"reported":true}`.
- **Read-back**: option holds one row `{user_id, reason:"closed", details, status:"open", date}`.

### 3. Duplicate and invalid reason
- **Action**: report again as {other}; REST with `reason:"banana"`.
- **Expect**: `.listora-detail__report-message--error` "You have already reported this listing." (`409 listora_already_reported`), no toast, submit re-enabled with its label; `400 rest_invalid_param` "reason is not one of inaccurate, spam, closed, duplicate, offensive, and other".
- **Read-back**: option still one row for {other}.

### 4. Role ladder on the control and API
- **Action / Expect**: per the table below.

### 5. Staff are emailed once per recipient
- **Action**: a report by {other} on {listing}, mail captured.
- **Expect**: one mail per recipient: site `admin_email` AND every user with `edit_others_listora_listings` ({editor}, {admin}); subject `Listing reported: <title>`.
- **Read-back**: recipient list equals `admin_email` + users holding the cap. Positive control for every "not emailed" step.

### 6. The owner is NOT emailed
- **Expect**: {owner}'s address is in no recipient. A listing whose author address equals `admin_email` cannot prove this: use one owned by {owner}.

### 7. Repeat reports are throttled
- **Action**: `do_action( 'wb_listora_listing_reported', $id, array( 'reason' => 'spam' ), $n )` for n=1..12.
- **Expect**: mail on n=1, 5, 10 only; the 10th subject `Listing reported 10 times: <title>`. With filter `wb_listora_listing_report_notify_interval` => 3: n=1, 3, 6, 9, 12.
- **Read-back**: distinct reporters are needed over REST because one row is kept per reporter.

### 8. The event can be switched off and retargeted
- **Action**: Settings > Notifications untick `Send "Listing reported"` (`wb_listora_settings[notifications][listing_reported]=0`); reload (setting memoized per request); then re-tick and add `wb_listora_listing_report_recipients` returning `abuse-desk@example.com`.
- **Expect**: no mail when off; only that address when retargeted.

### 9. The reporter's words are not in the email
- **Expect**: template placeholders `listing_title, listing_url, admin_review_url, report_reason, report_count` (`includes/admin/class-email-templates-page.php:296-302`): reason CODE and a link, never the free-text `details`.

### 10. Admin sees the flag
- **Action**: as {admin} open `edit.php?post_type=listora_listing` and the listing edit screen.
- **Expect**: column **Reports** (`column-listora_reports`); `a.listora-listing-col__reports` with flag icon and count, title "1 report - click to review"; metabox `#wb_listora_reports` "Report (1)" listing reason label, details, reporter, date and checkbox `wb_listora_clear_reports` "Clear all reports on save". The column is hidden by default ONLY while no listing has a stored report (`class-listing-columns.php:200-215`, `any_reported()` `:246`): with zero reports it is hidden (control).
- **Read-back**: count equals option row count and the count in the email (cap 200, step 7).

### 11. Admin clears the reports
- **Action**: tick the checkbox, **Update**.
- **Expect**: option deleted, `wb_listora_listing_reports_cleared` fires, column and metabox gone. (Metabox save click: Not verified live.)

### 12. Feature toggle gates everything
- **Action**: `wb_listora_features['report_listings']=false`.
- **Expect**: button absent for everyone; `POST` `403 listora_reports_disabled` "Listing reports are not enabled." Restore.

### 13. Long toast wraps (toast component positive control)
- **Action**: at 1440 and 390 in the console `listoraToast( 'Claim submitted - we will email you when it is reviewed, plus some extra words to wrap.', 'success' )`.
- **Expect**: text wraps inside the box (max-width 360px desktop; full width, `white-space: normal` under 480px); nothing runs past the background. Proves "no toast" in step 2 is a real absence. The claim half of the single-confirmation contract: `claims-verification/claim-a-business.md` step 5.
- **Read-back**: `getComputedStyle('.listora-toast').whiteSpace` is `normal`.

### 14. Mobile (390)
- **Expect**: modal fits, close and Submit >= 40px, no horizontal scroll.

## Role ladder
| Viewer | Button | `POST /listings/{id}/report` | Mail |
|---|---|---|---|
| {guest} | present, click opens the login modal "Log in to report this listing" | `401 listora_unauthorized` | none |
| {other} | present | `200` | none to them |
| {owner} | absent (and `#listora-report-modal` absent) | `403 listora_forbidden` "You cannot report your own listing." | none |
| {editor} (moderator, non-owner) | present | `200` | yes |
| {admin} on a listing they do not own (control) | present | `200` | yes (`admin_email`) |

## Pass criteria
1. Report stores one row per reporter and confirms once, inside the dialog, with no toast.
2. Staff mailed (admin + `edit_others_listora_listings`), the owner never; throttled at 1, 5, 10.
3. Admin sees Reports column and metabox; clearing removes both.
4. Feature off removes the control and the route.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Report confirms twice | toast fired alongside message | `src/interactivity/store.js:1968` (`submitReport`) |
| Report message unstyled | selector lost | `blocks/listing-detail/style.css:1644-1670` |
| Toast runs past box | `white-space: nowrap` back | `assets/css/listora-base.css:739`, `@media` `:916` |
| No mail / owner mailed | hook / recipients | `class-notifications.php:164`, `:1018`, `:1044` |
| Reports column missing | column logic | `class-listing-columns.php:200` |
| Message elements missing | template override | `blocks/listing-detail/render.php:979`, `:1020`; strings `includes/class-assets.php:294-301` |

## Not verified
Modal click-through, toast, focus and 390 (browser); metabox clear click; steps 5-9 verified by `wp eval` + `pre_wp_mail` only (automated: `tests/integration/ListingReportedNotificationTest.php` covers 5-8).

## Cleanup
`wp option delete _listora_listing_reports_<id>`; restore the toggle and notification setting.
