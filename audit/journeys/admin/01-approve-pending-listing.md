---
journey: admin-approve-pending-listing
plugin: wb-listora
priority: critical
roles: [administrator]
covers: [listing-status-machine, status-changed-hook, notification-email, email-log]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin user (?autologin=1) with manage_listora_types cap"
  - "At least 1 listora_listing exists with post_status='pending'"
estimated_runtime_minutes: 4
---

# Admin approves a pending listing

An admin opens the Listings list table, finds a pending listing, clicks Approve. Verifies the status transition fires `wb_listora_listing_status_changed` exactly once, the listing-approved email reaches the author, and a row lands in the Email Log admin page. Sentinel for commit `0aa62ca` (Notifications class previously hooked typo'd hook names so approve/reject/expire emails never sent).

## Setup

- Site: `$SITE_URL`
- Admin: `?autologin=1`
- Fixture: 1 pending listing. Capture as `LISTING_ID` and its author's email as `AUTHOR_EMAIL`.
  ```bash
  wp eval 'echo get_post(LISTING_ID)->post_author . "\n";'
  ```
- Baseline email-log count:
  ```sql
  SELECT COUNT(*) FROM wp_listora_email_log;
  ```
  Capture as `EMAIL_BASELINE`.

## Steps

### 1. Auto-login as admin + open Listings list
- **Action**: `playwright_navigate $SITE_URL/wp-admin/edit.php?post_type=listora_listing&post_status=pending&autologin=1`
- **Expect**: list table renders with at least 1 pending row containing `data-id="$LISTING_ID"`
- **On fail**: `class-listing-columns.php`, capabilities

### 2. Approve via row action
- **Action**: hover the listing row → click "Approve" inline action (or the Approve button on the edit screen)
- **Expect**: success notice, list refreshes, listing now in `publish` filter
- **On fail**: `Listings_Controller::approve_listing` REST or admin-post handler

### 3. Verify status transition
- **Action**:
  ```bash
  wp post get $LISTING_ID --field=post_status
  ```
- **Expect**: `publish`

### 4. Verify the status-changed hook fired exactly once
- **Action**: tail debug.log (with a temporary listener registered) OR query the action log:
  ```sql
  SELECT COUNT(*) FROM wp_listora_email_log
  WHERE listing_id=$LISTING_ID AND template='listing-approved';
  ```
- **Expect**: exactly 1 row. Pre-fix bug = 0 rows because Notifications class hooked typo'd hook names.
- **On fail**: `includes/workflow/class-notifications.php` — check `add_action` lines reference `wb_listora_listing_status_changed`, NOT typo'd `wb_listora_listing_listora_*`. Sentinel for commit `0aa62ca`.

### 5. Verify email log admin page
- **Action**: `playwright_navigate $SITE_URL/wp-admin/admin.php?page=listora-email-log`
- **Expect**: top row in the log table shows: recipient = `$AUTHOR_EMAIL`, template = `listing-approved`, timestamp = recent (≤30s ago), status = `sent`
- **On fail**: `class-email-log.php` admin page

### 6. Verify no double-fire on idempotent re-approve
- **Action**: open the listing edit screen → click Update with no changes
- **Expect**:
  ```sql
  SELECT COUNT(*) FROM wp_listora_email_log WHERE listing_id=$LISTING_ID AND template='listing-approved';
  ```
  STILL = 1 (NOT 2). Status didn't transition; hook should NOT re-fire.
- **On fail**: hook fires on every save_post — should fire only on transition

## Pass criteria

ALL of the following hold:
1. Pending listing transitions to `publish` after admin approve action
2. `listing-approved` email row lands in `wp_listora_email_log` (count = baseline+1)
3. `wb_listora_listing_status_changed` fires once per actual transition (not on no-op saves)
4. Email Log admin page displays the new row
5. Resaving without status change does NOT re-fire the email

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Approve action does nothing | capability check failing | `Listings_Controller::permissions_check` |
| Status flips but no email | typo'd hook regression #0aa62ca | `class-notifications.php:39-41` |
| Email logged but never sent | `wp_mail` failure | check SMTP plugin / `wp listora test-email` |
| Email logged twice | hook firing on every save_post | `Status_Manager::on_status_changed` transition guard |
