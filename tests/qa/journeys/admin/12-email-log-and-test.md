---
journey: email-log-and-test
plugin: wb-listora
priority: high
roles: [administrator]
covers: [email-log, notifications-test, notifications-retention]
prerequisites:
  - "Site reachable at $SITE_URL, admin logged in"
  - "Email Log admin page present (admin.php?page=listora-email-log)"
estimated_runtime_minutes: 5
covers_card: null
---

# Email Log page + test-send + retention REST

Covers the notification-log admin surface: `POST /settings/notifications/test`,
`GET`/`DELETE /settings/notifications/log`, `GET /settings/notifications/log/export`,
`POST /settings/notifications/log/retention`, and the `listora-email-log` page.
Email *sending* is covered elsewhere; the log/test/retention surface was not.

## Steps

### 1. Email Log page renders
- **Action**: `admin.php?page=listora-email-log&autologin=1`.
- **Expect**: page renders the logged-emails table (or an empty state); columns recipient / template / status / time.

### 2. Send a test email
- **Action**: trigger "Send test email" → `POST /settings/notifications/test`.
- **Expect**: 200; a new row appears in the log for the test template; (dev) the mail is captured. Capability-gated to `manage_listora_settings`.

### 3. Export the log
- **Action**: `GET /settings/notifications/log/export`.
- **Expect**: 200 CSV download of log rows with correct headers.

### 4. Retention setting
- **Action**: `POST /settings/notifications/log/retention` with a day count.
- **Expect**: 200; retention persisted; rows older than the window are pruned (immediately or on next cleanup).

### 5. Clear the log
- **Action**: `DELETE /settings/notifications/log`.
- **Expect**: 200; the log table empties; the page shows the empty state.

### 6. Cap gate
- **Action**: call any of the above as a non-`manage_listora_settings` user.
- **Expect**: 403.
