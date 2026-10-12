---
journey: email-log-and-test
plugin: wb-listora
priority: high
roles: [administrator, qa_editor, member-other, anonymous]
covers: [email-log, notifications-test, notifications-retention, email-resend, email-log-export, 2.0.0-email-log]
prerequisites:
  - "Site reachable at $SITE_URL; admin ?autologin=1"
  - "Local mail catcher (Mailpit) or any working `wp_mail`; at least one logged email (send a test in step 2 if the log is empty)"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
---

# Email Log: read, filter, resend, retain, clear, export, and test-send

Tools hub > Email Log (`admin.php?page=listora-email-log`, `manage_listora_settings`). Table `{prefix}listora_email_log` keeps the full email (`event_key, recipient, subject, body, headers, success, error, resent_from, sent_at`) so the owner can read and resend exactly what a member received. Test and log REST: `POST /listora/v1/settings/notifications/test`, `GET|DELETE /settings/notifications/log`, `GET /settings/notifications/log/export`, `POST /settings/notifications/log/retention`, `GET /settings/notifications/preview`.

## Steps

### 1. The screen (administrator, 1440)
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-email-log&autologin=1`.
- **Expect**: shared header "Email Log" with "Every email Listora sent: who got it, whether it went, and exactly what it said."; the Tools hub tab row (Audit Log | Email Log | Webhooks when Pro is active; Email Log alone with Free); the retention form `#listora-email-log-retention` ("Keep entries for" 7 days / 15 days / 30 days / 90 days (default) / 1 year / Forever + Save, with the sentence "Entries older than 90 days are removed each day."); views All / Sent / Failed with counts; search "Search subjects"; filters "Any email" (event) and "Recipient email"; columns Sent, Email, To, Result; empty state "No emails logged yet - Emails appear here as Listora sends them.".
- **On fail**: `Email_Log_Page::render` (`includes/admin/class-email-log-page.php`).

### 2. Test send from Settings (and the log row it creates)
- **Action**: Settings > Notifications > "Send a test email": Email "New message from a visitor", Send to `j20-test@example.com`, "Send test email". (API: `POST /listora/v1/settings/notifications/test {event_key:"lead_received", recipient_email:"j20-test@example.com"}`.)
- **Expect**: status "Sent" / HTTP 200 `{"sent":true,"error":"","subject":"[TEST] New message from Sample Visitor about [Test] Sample Listing","recipient":"j20-test@example.com"}`; the mail arrives in the catcher; a new log row appears at the top of Email Log with Result "Sent".
- **Negative**: an invalid recipient returns a 4xx with a message, nothing is logged, no fatal.

### 3. Read and filter
- **Action**: open the new row's detail drawer; use the "Any email" filter = `lead_received`; search `Sample Visitor`; open the "Failed" view.
- **Expect**: the drawer shows To and Subject and the rendered email in a sandboxed iframe (`iframe.listora-email-preview[sandbox]`, title "Email content"); filters narrow the list and the count text; Failed shows only `success=0` rows (empty state when none).

### 4. Resend
- **Action**: row action "Send again" (a failed row says "Resend"), confirm "The same email goes to <recipient> again."
- **Expect**: notice "Email sent again. The new attempt is at the top of the log."; a new row with the badge "Resent" and `resent_from` = original ID; the mail arrives again. When `wp_mail` fails: "The email could not be sent again. The new attempt and its error are at the top of the log." and the row shows the error text.

### 5. Retention
- **Action**: choose "7 days", Save.
- **Expect**: notice "Retention saved. Older entries were removed."; rows older than 7 days are gone (back-date one row with SQL first to prove it); text "Entries older than 7 days are removed each day."; "Forever" shows "Entries are kept until you clear the log." and the nightly cleanup (`wp listora cleanup`) keeps old rows.
- **API**: `POST /settings/notifications/log/retention {days: 30}` returns 200 and is reflected on the screen; an unlisted value (e.g. 5) is rejected or coerced to a valid choice.

### 6. Export and clear
- **Action**: `GET /listora/v1/settings/notifications/log/export` as admin.
- **Expect**: 200 CSV with a header and the logged rows (no body column dump of HTML beyond what is documented).
- **Action**: "Clear log" (confirmation), or `DELETE /settings/notifications/log`.
- **Expect**: notice "Email log cleared."; the empty state shows; count tabs 0.

### 7. Role ladder
| Rung | Action | Expect |
|---|---|---|
| administrator | steps 1-6 | pass |
| qa_editor | the screen URL | not allowed ("You do not have permission to manage the email log." on the action handler, 403) |
| any non-admin | `POST /settings/notifications/test`, `GET .../log`, `DELETE .../log` | 403 `listora_forbidden`; anonymous 401 `listora_unauthorized` |
| member-other | open an old log row's drawer URL | not allowed (the drawer is part of the admin screen) |

### 8. 390px
- **Expect**: table rows stack; the retention form wraps; the detail drawer is full width with the iframe scrolling inside it; actions >= 40px.

## Pass criteria

1. Every email Listora sends appears with recipient, subject, result and the full body.
2. Test send, Resend, Retention, Clear and Export work and say what they did.
3. Only `manage_listora_settings` can read, send, resend or clear.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Test sends but no log row | log write skipped for test sends | `Notifications::send_test`, `Email_Log::record` |
| Resend duplicates silently | no `resent_from` set | `Email_Log_Page::handle_action` (`resend`) |
| Old rows survive retention | cleanup hook not scheduled | `wb_listora_daily_cleanup` / `Expiration_Cron` |
| Non-admin reads the log via REST | permission callback widened | `class-settings-controller.php` `manage_settings_permissions` |
