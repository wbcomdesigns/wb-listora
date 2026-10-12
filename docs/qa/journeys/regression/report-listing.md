---
journey: report-listing
plugin: wb-listora
priority: high
roles: [anonymous, subscriber, owner, administrator]
covers: [report-listings-feature, listings-report-rest, report-modal, report-admin-column, report-metabox]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "report_listings feature enabled (default)"
  - "At least 1 published listing NOT owned by the test reporter (capture LISTING_ID + permalink)"
  - "Test user 'subscriber2' (subscriber) exists"
estimated_runtime_minutes: 5
covers_card: 9906156994
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Report a listing (flag for admin review)

A member flags a listing they do not own; the report is stored in option `_listora_listing_reports_<id>` (non-autoloaded, capped at
200 rows), shown to admins by a Reports column and an edit-screen metabox, and an admin clears it.

Setup: reporter `subscriber2` (`?autologin=subscriber2`); listing `LISTING_ID` with `post_author` != subscriber2. Start clean:
`wp option delete _listora_listing_reports_<LISTING_ID>`. Listing URL: the permalink (here `/listing/<slug>/`).

## Steps

### 1. Another member sees the control (verified)
- **Expect** on the listing page: `button.listora-detail__report-btn` ("Report", `data-wp-on--click="actions.openReportModal"`) in the actions
  row; `#listora-report-modal` (`role="dialog"`, title "Report This Listing") with `select#listora-report-reason[name=reason]` of 6 options:
  `inaccurate` Inaccurate information, `spam` Spam or misleading, `closed` Permanently closed, `duplicate` Duplicate listing,
  `offensive` Offensive or inappropriate, `other` Something else; `textarea#listora-report-details[name=details]`; **Submit Report**.

### 2. Role ladder (verified by DOM and REST)
| Viewer | Button | `POST /listings/{id}/report` |
|---|---|---|
| logged out | present, click opens the login modal | 401 `listora_unauthorized` |
| another member | present | 200 |
| the owner (here admin owns 59) | absent (and `#listora-report-modal` absent) | 403 `listora_forbidden` "You cannot report your own listing." |
| admin on a listing they do not own (control) | present | 200 |

### 3. Submit a report (verified over REST)
- **Action**: open the modal, reason `closed`, details "QA-J1 test", **Submit Report**.
- **Expect**: toast "Report submitted. Thank you." (`listoraI18n.reportSubmitted`); `POST /listora/v1/listings/<id>/report` 200
  `{"reported":true}`; option holds one row `{user_id, reason:"closed", details, status:"open", date}`.
- An invalid reason (`banana`) is refused 400 `rest_invalid_param` ("reason is not one of inaccurate, spam, closed, duplicate, offensive, and other").

### 4. Duplicate report blocked (verified)
- **Expect**: second report by the same user -> 409 `listora_already_reported`.

### 5. Admin sees the flag (verified)
- **Action**: as admin open `edit.php?post_type=listora_listing` and `post.php?post=<id>&action=edit`.
- **Expect**: column header **Reports** (`column-listora_reports`); the row has `a.listora-listing-col__reports` with a flag icon and the count,
  title "1 report - click to review"; the edit screen has metabox `#wb_listora_reports` titled "Report (1)" listing the reason label, details, reporter, date,
  and a checkbox `wb_listora_clear_reports` "Clear all reports on save".

### 6. Admin clears the reports
- **Action**: tick the checkbox, **Update**.
- **Expect**: option deleted, `wb_listora_listing_reports_cleared` fires, the column and metabox are gone. (Cleanup in this run was
  `wp option delete`; the metabox save itself was not clicked.)

### 7. Feature toggle gates everything (verified)
- **Action**: set `wb_listora_features['report_listings']=false`.
- **Expect**: the button is absent for everyone; `POST` returns 403 `listora_reports_disabled` "Listing reports are not enabled.".
  Restore the toggle afterwards.

### 8. Mobile (390)
- **Expect**: the modal fits, close button and Submit >= 40px, no horizontal page scroll.

## Fail diagnostics
- Button/modal gate: `blocks/listing-detail/render.php:574` (`$listora_can_report`), modal `:987`.
- REST: `includes/rest/class-listings-controller.php:1368` (`report_listing_permissions`), `:1411` (`report_listing`, dedup `:1440`).
- Admin: `includes/admin/class-listing-columns.php:411`, `includes/admin/class-report-metabox.php:82` (metabox), `:191` (clear).
- Store action: `src/interactivity/store.js:1956` (`openReportModal`), `:1968` (`submitReport`).

## Not verified
Modal click-through, toast, focus handling and 390px layout (browser); the metabox clear-on-save click.
