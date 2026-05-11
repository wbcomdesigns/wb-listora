---
journey: email-approval-send
plugin: wb-listora
priority: high
roles: [admin, anonymous]
covers: [notifications, listing-approved-email, listing-status-changed-hook]
prerequisites:
  - "SMTP / Mailhog reachable OR wp_mail captured via filter"
  - "Test member with a 'pending' listing"
estimated_runtime_minutes: 3
---

# Listing approval sends an email to the owner

Regression sentinel for commit `0aa62ca` (2026-04-30) — Notifications previously hooked typo'd action names (`wb_listora_listing_publish`) that nothing fired. Replaced with single listener on `wb_listora_listing_status_changed`. This journey verifies the email reaches the owner on every approval.

## Setup

- Site: `$SITE_URL`
- Login: admin (`?autologin=1`)
- Fixture: a pending listing owned by `member1`. Capture `LISTING_ID`, `OWNER_EMAIL`.
- Clear log:
  ```sql
  DELETE FROM wp_listora_email_log WHERE listing_id=$LISTING_ID;
  ```

## Steps

### 1. Hook the status-changed event for visibility
- **Action**: `wp eval 'add_action("wb_listora_listing_status_changed", function($id, $old, $new, $ctx){ error_log("status_changed: id=$id old=$old new=$new"); }, 1, 4);'`
- **Expect**: handler registered; no output yet

### 2. Approve the pending listing
- **Action**: in admin, change listing status from `pending` to `publish` (via edit screen save OR `/wp-json/listora/v1/listings/<id>` PATCH)
- **Expect**: 200; DB row updated

### 3. Verify status-changed hook fired
- **Action**: `tail -5 wp-content/debug.log`
- **Expect**: line `status_changed: id=$LISTING_ID old=pending new=publish`. Args contract: `(int $listing_id, string $old, string $new, array $context)`.

### 4. Verify approval email logged
- **Action**:
  ```sql
  SELECT template, recipient, created_at FROM wp_listora_email_log
  WHERE listing_id=$LISTING_ID ORDER BY id DESC LIMIT 1;
  ```
- **Expect**: 1 row, `template = 'listing-approved'`, `recipient = $OWNER_EMAIL`, `created_at` ≈ NOW

### 5. Email body content
- **Action**: inspect email body (via Mailhog or email_log content)
- **Expect**: contains listing title, listing URL, owner first name (if set)

### 6. Reject + re-approve → two distinct emails
- **Action**: change status `publish` → `pending` → `publish` again
- **Expect**: 2 new email rows: one for rejection (template `listing-rejected`), one for re-approval (`listing-approved`)

### 7. Hook ordering — Pro listeners get called too
- **Action**: with both Free and Pro active, count `add_action('wb_listora_listing_status_changed')` registrations
- **Action**: ```bash
  wp eval 'global $wp_filter; echo isset($wp_filter["wb_listora_listing_status_changed"]) ? count($wp_filter["wb_listora_listing_status_changed"]->callbacks) : 0;'
  ```
- **Expect**: ≥ 2 priority levels — Free (Notifications) + Pro (Outgoing_Webhooks dispatcher at the canonical fire site, per commit 97810e8)

### 8. Email send failure handling
- **Action**: temporarily break SMTP (e.g., bad host); approve another listing
- **Expect**: status changes succeed; email_log row gets `status='failed'` OR error logged; NO fatal/500 on admin save

### 9. Filter the email content (developer flow)
- **Action**: `wp eval 'add_filter("wb_listora_email_listing_approved_body", function($body, $listing){ return "CUSTOM: " . $body; }, 10, 2);'` approve another listing
- **Expect**: email body starts with "CUSTOM: "

### 10. No email for status changes that aren't approval (e.g., draft → pending)
- **Action**: change a draft listing to `pending`
- **Expect**: NO `listing-approved` email row created. Notifications dispatcher must only act on `publish` transitions from `pending`.

## Pass criteria

1. `wb_listora_listing_status_changed` fires with correct args
2. Approval triggers `listing-approved` email to owner
3. Rejection triggers `listing-rejected` email
4. Pro listener also runs (webhook dispatch)
5. SMTP failure doesn't break admin save
6. Email body filter is honored
7. No spurious emails on intermediate transitions

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| No status_changed line in debug.log | hook not fired on save | `class-status-manager.php::on_status_change` |
| Status changes but no email | Notifications listener detached (regression of 0aa62ca) | `includes/workflow/class-notifications.php:39` |
| Email goes to wrong recipient | `post_author` lookup buggy | `class-notifications.php::on_listing_status_changed` |
| Admin save 500 on SMTP fail | exception not caught | `class-notifications.php::send` — must wrap in try/catch |
| Email body filter ignored | hook not fired before mail() | `class-notifications.php::build_body` |
