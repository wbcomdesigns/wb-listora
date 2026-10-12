---
journey: listing-status-emails
group: emails-alerts
actor: admin
mode: mixed
plugin: wb-listora
priority: high
roles: [administrator, member-owner, member-other, anonymous]
covers: [notifications, listing-approved-email, listing-rejected-email, listing-status-changed-hook, status-transitions, email-log, notifications-hooked-names, "sentinel 0aa62ca"]
prerequisites:
  - "Site reachable at $SITE_URL; wp_mail captured (Mailpit) or read the email log"
  - "{owner} owns a pending listing created for the run, titled QA approval mail"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
supersedes:
  - ora:regression/email-approval-send.md
fails_on: step 3 fails on the commit before 0aa62ca (Notifications once hooked typo'd hook names, so approve/reject/expire emails never sent); step 4 saved-template fails on 74f6610f
site_profile: required
---

# Listing approval and rejection email the owner once, through one hook, and are logged

## Why
`Search_Indexer::on_status_change()` fires `wb_listora_listing_status_changed( $post_id, $new, $old )` (3 args). `Notifications::on_listing_status_changed()` (priority 10) maps `publish` -> `listing_approved`, `listora_rejected` -> `listing_rejected`, `listora_expired` -> `listing_expired`, logging each send in `${PREFIX}listora_email_log`. If a listener is on the wrong hook the owner is never told; if the hook fires per `save_post` the owner is told twice.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Create `QA approval mail` (`post_status=pending`, author {owner}, any type). `MAIL_BASE` = `SELECT MAX(id) FROM ${PREFIX}listora_email_log`. Event keys use underscores (`listing_approved`).
- Approval itself (row action, Bulk Edit, transition rules): `admin-listings/listing-approve-reject-and-bulk-actions.md`.

## Steps

### 1. Hook args and listeners
- **Action**: `add_action( 'wb_listora_listing_status_changed', fn($id,$n,$o)=>error_log("$id $n $o"), 1, 3 )`; list `$wp_filter['wb_listora_listing_status_changed']->callbacks` priorities.
- **Expect**: 3 args; priorities 1 (yours), 10 (Notifications), 20 (Suite_Notifications), 50 (Pro Outgoing_Webhooks/BuddyPress when Pro is active).

### 2. Approve the pending listing
- **Action**: `POST /listora/v1/listings/bulk-moderate {ids:[ID], action:"approve"}` as {admin} (or the Approve row action).
- **Expect**: 200 `{"succeeded":[ID],"failed":[]}`; hook line `new=publish old=pending`; status `publish`.

### 3. Exactly one approval email, logged
- **Action**: `SELECT event_key, recipient, subject, success FROM ${PREFIX}listora_email_log WHERE id > MAIL_BASE ORDER BY id`, or Tools > Email Log (`admin.php?page=listora-email-log`), or `GET /listora/v1/settings/notifications/log`.
- **Expect**: exactly 1 row: `event_key=listing_approved`, `recipient={owner} email`, `subject="Your listing has been approved: QA approval mail"`, `success=1`; Email Log screen top row Email "Listing approved", To = owner, Result "Sent", row action "Send again", body in a sandboxed iframe.
- **Re-save**: open the listing and Update with no change: still ONE `listing_approved` row (the hook fires on a real transition only).

### 4. A saved custom body keeps the shell (card 10393531330)
- **Action**: Settings > Notifications > "Listing approved" > Edit template; Message = `Good news, your listing is live.`; Save (option `wb_listora_email_templates`). Approve a fresh pending listing as in step 2.
- **Expect**: the email contains your sentence AND every landmark the unedited email of step 3 had (header block with site name, footer "This email was sent by <site name>.", email-preferences/unsubscribe link); the landmark sets are identical (positive control).
- **Read-back**: `wp option get wb_listora_email_templates --format=json` holds the sentence under `listing_approved`; Reset to default afterwards. Editor behaviour itself: `emails-alerts/email-templates-editor.md`. fails_on 74f6610f. Not verified live.

### 5. Developer filters
- **Action**: `add_filter( 'wb_listora_email_content_listing_approved', fn($b,$v)=>'CUSTOM: '.$b, 10, 2 )` (also `wb_listora_email_content`, `wb_listora_email_subject_{event}`), approve another pending listing; also skip a send with `wb_listora_should_send_notification` (fires `wb_listora_notification_skipped`).
- **Expect**: body starts "CUSTOM: " and contains title and URL; skipped sends log nothing. Not verified live.

### 6. Reject only from Pending
- **Action**: on a fresh pending listing `POST bulk-moderate action:"reject"` (+ `reason`).
- **Expect**: 200, status `listora_rejected`, one `listing_rejected` row ("Your listing needs changes: <title>").
- **Action**: approve the rejected listing directly or `wp_update_post( status=publish )`.
- **Expect**: refused (`listora_invalid_transition`), status stays; then set Pending Review and approve: `publish` plus a new `listing_approved` row.

### 7. No spurious emails
- **Action**: draft -> pending.
- **Expect**: 0 new rows (only `publish`, `listora_rejected`, `listora_expired` notify; draft/payment -> publish also sends approval).

### 8. SMTP failure does not break the save
- **Action**: point SMTP at a bad host, approve another listing.
- **Expect**: status still changes; the log row has `success=0` and error text; no 500. Not verified live.

## Role ladder

| Rung | Action | Expect |
|---|---|---|
| {admin} | steps 1-8 | pass |
| {editor} | `bulk-moderate` | checked per id with `edit_post`; allowed for editable listings, email sent. Not verified live |
| {owner}, {other} | `bulk-moderate` | 403 `rest_forbidden`; the owner is only the recipient |
| {guest} | same | 401 |

## Pass criteria
1. Approve and reject each send exactly one logged email to the owner with the right subject.
2. No email on a no-op save or draft-to-pending.
3. Illegal transitions send nothing and are refused with `listora_invalid_transition`.
4. A customised body still carries header, footer and preferences link.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| No `status_changed` | indexer hook | `includes/search/class-search-indexer.php` `on_status_change` |
| Status changes, no email | listener on wrong hook name (0aa62ca) | `includes/workflow/class-notifications.php` hook registrations, `listing_approved()` |
| Two rows | hook on every `save_post` | `Status_Manager::on_transition` |
| Wrong recipient | author lookup | `class-notifications.php` |
| Row missing | log write | `Notifications::log_send()` -> `Email_Log::insert()`, filter `wb_listora_notification_log_enabled` |
| Rejected -> publish allowed | transition map | `includes/workflow/class-status-manager.php` `transition_allowed()` |

## Not verified
Steps 4, 5, 8; the Mailpit inbox was not opened in the 2026-10-12 run.

## Cleanup
Delete the QA listings and the email rows with `id > MAIL_BASE` AND the QA subject only; reset the template.
