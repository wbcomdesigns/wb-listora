---
journey: email-approval-send
plugin: wb-listora
priority: high
roles: [admin, listing-owner, anonymous]
covers: [notifications, listing-approved-email, listing-status-changed-hook, status-transitions]
prerequisites:
  - "wp_mail captured (Local Mailpit) or read the email log"
  - "A member (tester, user 15) as owner of a pending listing created for the run, titled QA-J1 ..."
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# Listing approval and rejection email the owner, once, through one hook

`Search_Indexer::on_status_change()` fires `wb_listora_listing_status_changed( $post_id, $new_status, $old )` (`includes/search/class-search-indexer.php:706`, 3 args). `Notifications::on_listing_status_changed()` (`includes/workflow/class-notifications.php:290`, priority 10) maps `publish` -> `listing_approved`, `listora_rejected` -> `listing_rejected`, `listora_expired` -> `listing_expired`. Sends are logged in the `{prefix}listora_email_log` TABLE (`includes/workflow/class-email-log.php`), not an option.

Verified via: `wp eval-file` with `rest_do_request()` as admin (POST `/listora/v1/listings/bulk-moderate`) and a throwaway listing, 2026-10-12. Mailpit inbox not opened.

## Setup

- Create `QA-J1 approval mail` (`post_status=pending`, author 15, type business). Note `$base = SELECT MAX(id) FROM wp_listora_email_log`.

## Steps

### 1. Hook args and listeners
- **Action**: `add_action( 'wb_listora_listing_status_changed', fn($id,$n,$o)=>error_log("$id $n $o"), 1, 3 )`; count `$wp_filter['wb_listora_listing_status_changed']->callbacks` priorities.
- **Expect**: 3 args; on this site priority levels 1 (yours), 10 (Notifications), 20 (Suite_Notifications), 50 (Pro Outgoing_Webhooks/BuddyPress) = at least Free + Pro.

### 2. Approve the pending listing
- **Action**: POST `/listora/v1/listings/bulk-moderate` `{ids:[ID], action:"approve"}` as admin (or the Approve row action, see listing-approve-reject-row-actions).
- **Expect**: 200 `{"succeeded":[ID],"failed":[]}`; hook line `new=publish old=pending`; status `publish`.

### 3. Approval email logged
- **Action**: `SELECT event_key, recipient, subject, success FROM wp_listora_email_log WHERE id > $base`, or Listora > Tools > Email Log (`admin.php?page=listora-email-log`), or `GET /listora/v1/settings/notifications/log` as admin.
- **Expect**: exactly 1 row: `event_key=listing_approved`, `recipient=tester@test.local`, `subject="Your listing has been approved: QA-J1 approval mail"`, `success=1`. (Event keys use underscores: `listing_approved`, not `listing-approved`.)

### 4. Body and filters (developer flow)
- **Action**: `add_filter( 'wb_listora_email_content_listing_approved', fn($b,$v)=>'CUSTOM: '.$b, 10, 2 )` (also `wb_listora_email_content` and `wb_listora_email_subject_{event}`), approve another pending listing.
- **Expect**: body starts with "CUSTOM: " and contains the listing title and URL. Skip a send with `wb_listora_should_send_notification` (fires `wb_listora_notification_skipped`). Not run: filters read from `class-notifications.php:1504-1530`.

### 5. Reject only from Pending
- **Action**: on a fresh pending listing POST bulk-moderate `action:"reject"` (+ `reason`).
- **Expect**: 200, status `listora_rejected`, one `listing_rejected` row ("Your listing needs changes: <title>").
- **Action**: approve the rejected listing directly, or `wp_update_post( status=publish )`.
- **Expect**: refused: bulk-moderate `failed:[{"id":ID,"error":"listora_invalid_transition"}]`, status stays `listora_rejected`; the admin message reads "This listing is Rejected, so it was not published. Set it to Pending Review, then approve it." Then set Pending Review and approve: status `publish`, a new `listing_approved` row.

### 6. A published listing cannot be rejected
- **POSITIVE CONTROL**: the same reject call on a `publish` listing returns `listora_invalid_transition` (publish -> rejected is not in the map; reject is Pending-only). Confirms rows in step 5 come from the pending path.

### 7. No spurious emails
- **Action**: draft -> pending.
- **Expect**: 0 new email rows (only `publish`, `listora_rejected`, `listora_expired` transitions notify; draft/payment -> publish also sends approval).

### 8. SMTP failure does not break the save
- **Action**: point SMTP at a bad host, approve another listing.
- **Expect**: status still changes; the log row has `success=0` and `error` text; no 500. Not run.

### 9. Role ladder
- Admin approves (control). bulk-moderate needs `edit_others_listora_listings` (`class-listings-controller.php:539`): the owner, another member and anonymous get 403 `rest_forbidden` / 401; qa_editor is checked per id with `edit_post` (not run for the editor).

### 10. Clean up
- Delete the QA-J1 listings and email rows with `id > $base` AND the QA-J1 subject only.

## Fail diagnostics

| Symptom | File |
|---|---|
| No `status_changed` | `includes/search/class-search-indexer.php:706` |
| Status changes, no email | `includes/workflow/class-notifications.php:154` hook, `:307` `listing_approved()` (old status must be pending/rejected/expired/draft/payment) |
| Wrong recipient | `class-notifications.php:316` author lookup |
| Row missing | `class-notifications.php:1590` `log_send()` -> `Email_Log::insert()`; `wb_listora_notification_log_enabled` |
| Reject-from-publish allowed / rejected->publish allowed | `includes/workflow/class-status-manager.php:221` `transition_allowed()` |
