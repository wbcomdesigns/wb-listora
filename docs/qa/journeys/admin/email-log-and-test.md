---
journey: email-log-and-test
plugin: wb-listora
priority: high
roles: [administrator, editor, member-other, anonymous, cli]
covers: [email-log, notifications-test, notifications-retention, email-resend, email-log-export, 2.0.0-email-log, wp-cli, listora-test-email, "C.cli"]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} session; WP-CLI at $WP_PATH"
  - "Local mail catcher (Mailpit) or any working wp_mail; at least one logged email (send a test in step 2 if the log is empty)"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
supersedes:
  - ora:admin/12-email-log-and-test.md
  - ora:regression/cli-test-email-cleanup.md
fails_on: unknown (no regression steps carry a pre-fix sha)
site_profile: required
---

# Email Log: read, filter, resend, retain, clear, export, and test-send from the screen, REST and WP-CLI

## Why
The log (`{prefix}listora_email_log`: `event_key, recipient, subject, body, headers, success, error, resent_from, sent_at`) is how an owner proves what a member received. Tools hub > Email Log (`admin.php?page=listora-email-log`, `manage_listora_settings`). REST: `POST /listora/v1/settings/notifications/test`, `GET|DELETE /settings/notifications/log`, `GET /settings/notifications/log/export`, `POST /settings/notifications/log/retention`, `GET /settings/notifications/preview`. The nightly retention runs through `wp listora cleanup` (the full cleanup contract: `system/cron-schedule-and-maintenance.md`).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- A recipient address you control for test sends, e.g. `qa-test@example.test`.

## Steps

### 1. The screen
- **Action**: open the Email Log as {admin} at 1440.
- **Expect**: header "Email Log" ("Every email Listora sent: who got it, whether it went, and exactly what it said."); Tools hub tabs (Audit Log | Email Log | Webhooks with Pro; Email Log alone with Free); retention form `#listora-email-log-retention` ("Keep entries for" 7 days / 15 days / 30 days / 90 days (default) / 1 year / Forever + Save, "Entries older than 90 days are removed each day."); views All / Sent / Failed with counts; "Search subjects"; filters "Any email" and "Recipient email"; columns Sent, Email, To, Result; empty state "No emails logged yet - Emails appear here as Listora sends them.".

### 2. Test send from Settings and the row it creates
- **Action**: Settings > Notifications > "Send a test email": Email "New message from a visitor", Send to `qa-test@example.test`. API: `POST /listora/v1/settings/notifications/test {event_key:"lead_received", recipient_email:"qa-test@example.test"}`.
- **Expect**: "Sent" / 200 `{"sent":true,"error":"","subject":"[TEST] New message from Sample Visitor about [Test] Sample Listing","recipient":"qa-test@example.test"}`; the mail arrives; a new log row on top with Result "Sent".
- **Negative**: an invalid recipient returns a 4xx with a message, nothing is logged, no fatal.

### 3. Test send from WP-CLI
- **Action**: `wp listora test-email listing_approved --to=qa-test@example.test`.
- **Expect**: exit 0 with `Success: Sent "listing_approved" to ... (subject: [TEST] Your listing has been approved: [Test] Sample Listing).` or a non-fatal `Warning: Template "listing_approved" rendered, but delivery to ... failed...` (delivery failure warns, never errors); Mailpit shows one `[TEST]` message with branded header and footer; a log row exists.
- **List path**: `wp listora test-email` prints `Available templates:` then exactly the keys of the notification events (`listing_submitted`, `listing_approved`, `listing_reported`, `lead_received`, `listing_rejected`, `listing_expired`, `listing_expiring_soon`, `listing_renewed`, `listing_pending_admin`, `review_received`, `review_reply`, `review_helpful`, `claim_submitted`, `claim_approved`, `claim_rejected`, `draft_reminder`, `review_reminder`, `listing_verify_email`) then `Error: Specify a template: wp listora test-email <template> --to=<email>` with exit 1 by design. The key count equals the list in `Workflow\Notifications::send_test()`.
- **Unknown key**: `wp listora test-email not_a_real_template --to=...` gives `Error: Unknown template "not_a_real_template". Run \`wp listora test-email\` to list valid templates.`, nothing sent (positive control: step 3 with a valid key sends exactly one; run the invalid one first with the mail catcher open).
- **Once per release**: loop all keys: `for k in $(wp listora test-email 2>&1 | awk '/^  - /{print $2}'); do wp listora test-email $k --to=qa-test@example.test; done` with no fatal. Both subcommands appear under `wp help listora`.

### 4. Read and filter
- **Action**: open the new row's drawer; filter "Any email" = `lead_received`; search `Sample Visitor`; open "Failed".
- **Expect**: drawer shows To, Subject and the email in a sandboxed iframe (`iframe.listora-email-preview[sandbox]`, title "Email content"); filters narrow the list and count text; Failed shows only `success=0` rows (empty state when none).

### 5. Resend
- **Action**: row action "Send again" (a failed row says "Resend"), confirm "The same email goes to <recipient> again."
- **Expect**: notice "Email sent again. The new attempt is at the top of the log."; new row with badge "Resent" and `resent_from` = original id; mail arrives again. When `wp_mail` fails: "The email could not be sent again. The new attempt and its error are at the top of the log." and the row shows the error.

### 6. Retention
- **Action**: choose "7 days", Save (back-date one row with SQL first to prove deletion).
- **Expect**: notice "Retention saved. Older entries were removed."; older rows gone; text "Entries older than 7 days are removed each day."; "Forever" shows "Entries are kept until you clear the log." and the nightly cleanup keeps old rows.
- **API**: `POST /settings/notifications/log/retention {days: 30}` returns 200 and the screen reflects it; an unlisted value (5) is rejected or coerced to a valid choice.

### 7. Export and clear
- **Action**: `GET /listora/v1/settings/notifications/log/export` as {admin}; then "Clear log" (confirmation) or `DELETE /settings/notifications/log`.
- **Expect**: 200 CSV with header and the logged rows; after clearing "Email log cleared." and the empty state, counts 0.

### 8. Scale (added)
- **Action**: seed 5000+ log rows.
- **Expect**: the screen paginates with a total count, filters stay responsive, export streams without timing out. Not verified live.

### 9. 390px
- **Expect**: table rows stack; retention form wraps; drawer full width with the iframe scrolling inside; actions >= 40 px.

## Role ladder

| Rung | Action | Expect |
|---|---|---|
| {admin} | steps 1-9 | pass |
| {editor} | the screen URL | not allowed ("You do not have permission to manage the email log." on the action handler, 403) |
| any non-admin | `POST /settings/notifications/test`, `GET .../log`, `DELETE .../log` | 403 `listora_forbidden` |
| {guest} | same | 401 `listora_unauthorized` |
| {other} | an old log row's drawer URL | not allowed |
| cli (server user) | step 3 | full authority; no role ladder |

## Pass criteria
1. Every email Listora sends appears with recipient, subject, result and full body.
2. Test send (screen, REST, CLI), Resend, Retention, Clear and Export work and say what they did.
3. An unknown CLI key is a clean `Error:`, not a fatal.
4. Only `manage_listora_settings` can read, send, resend or clear.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Test sends but no row | log write skipped for test sends | `Notifications::send_test`, `Email_Log::record` |
| Resend duplicates silently | no `resent_from` | `Email_Log_Page::handle_action` (`resend`) |
| Old rows survive retention | cleanup not scheduled | `wb_listora_daily_cleanup` / `Expiration_Cron` |
| Non-admin reads log via REST | permission widened | `includes/rest/class-settings-controller.php` `manage_settings_permissions` |
| `test-email` not a subcommand | `@subcommand test-email` dropped | `includes/class-cli-commands.php` |
| CLI exits non-zero when mail cannot deliver | failure escalated to `WP_CLI::error` | `class-cli-commands.php` (must be `WP_CLI::warning`) |
| Key count drifts | `$templates` vs events | `class-cli-commands.php` `test_email()` and `Workflow\Notifications::send_test()` |

## Not verified
Steps 5 (failure branch), 8, 9. CLI steps 3 (send, list, unknown key) were run live on 2026-10-12; Mailpit receipt was not checked.

## Cleanup
`DELETE FROM {prefix}listora_email_log WHERE recipient = 'qa-test@example.test'`.
