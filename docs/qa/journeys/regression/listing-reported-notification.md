---
journey: listing-reported-notification
plugin: wb-listora
priority: high
roles: [administrator, listora_moderator, subscriber, anonymous]
covers: [listing-reports, notifications, listing-columns]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Report Listings feature on (default), a published listing, and a member who does not own it"
estimated_runtime_minutes: 8
covers_card: 10317616906
---

# Reporting a listing reaches a human

Regression sentinel for the `listing_reported` notification.

## Background

`wb_listora_listing_reported` fired for releases with nothing listening. The report was stored and the count incremented, and no email, digest line or notice went anywhere. The Reports column was hidden by default, so even an admin who went looking saw nothing until they opened Screen Options. Reporting is a promise that someone will look.

Two decisions recorded on the card, both deliberate:

- **The listing owner is never told.** Telling them hands a harassment vector to anyone filing reports to needle an owner, and warns a genuine bad actor that staff are looking. Staff decide; the owner hears from staff if there is something to answer for.
- **No new capability.** Recipients are users who can already moderate listings (`edit_others_listora_listings`), which is what gates the Reports metabox and what the Listora Moderator role carries. `view_listora_reports` was retired in card 10317617131 for being granted, documented and never checked - reintroducing it here would have undone that.

## Steps

### 1. A report emails staff
- **Action**: as a member, report a listing they do not own. Capture mail with `pre_wp_mail` rather than sending.
- **Expect**: mail to the site admin address AND to every user with `edit_others_listora_listings` who has an email address. Subject names the listing.
- **On fail**: `includes/workflow/class-notifications.php` → `listing_reported()`, or the hook registration.

### 2. The owner is NOT emailed
- **Expect**: the listing author's address appears in no recipient list. This is the decision, not an oversight - if someone "fixes" it later, this step is why not.

### 3. Repeat reports are throttled
- **Action**: fire reports 1 through 12 on one listing.
- **Expect**: notifications on reports 1, 5 and 10 only. `wb_listora_listing_report_notify_interval` changes the interval; at 3 it is reports 1, 3 and 6.
- **Why**: a brigaded listing must not send one email per report.

### 4. The event can be switched off
- **Action**: Settings → Notifications → uncheck "Listing reported", save.
- **Expect**: no mail on the next report. Note `wb_listora_get_setting()` memoizes in a function static - reload the page rather than testing within one request.

### 5. The notified admin lands somewhere useful
- **Action**: open Listora → All Listings as an admin who has never touched Screen Options.
- **Expect**: the Reports column is visible. It used to be in `default_hidden_columns()`, which made the email a dead end.

### 6. Recipients are retargetable
- **Action**: `add_filter( 'wb_listora_listing_report_recipients', fn() => array( 'abuse-desk@example.com' ) );`
- **Expect**: only that address is mailed.

### 7. The stored reports are capped
- **Action**: check `wb_listora_max_stored_listing_reports` (default 200).
- **Expect**: the per-listing option keeps the newest 200 and the count the email quotes matches what the metabox and column can actually show. A number staff are emailed that no screen can display is worse than a capped one.

### 8. The reporter's own words are not in the email
- **Verify**: the template sends the reason CODE and a link, never the free-text `details` a stranger typed. The full report is on the metabox, where it renders through `esc_html()` (checked - not a defect).

## Automated coverage

`tests/integration/ListingReportedNotificationTest.php` covers steps 1-6 headlessly, including the real REST route end to end.
