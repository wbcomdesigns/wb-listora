# Coverage: emails-alerts

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Email log, retention, resend, test send (UI, REST, CLI) | ora:emails-alerts/email-log-and-test.md | A, R, C, E | admin, editor, other, guest | Y | light | scale, resend failure branch |
| Email templates editor and branded shell | ora:emails-alerts/email-templates-editor.md | A, E | admin, editor, owner | Y | light | Pro marketing mail (step 7 expected-fail: alert footer has no prefs link), boundary input |
| Status emails (approve/reject) logged once | ora:emails-alerts/listing-status-emails.md | E, R, A | admin, editor, owner, guest | 1440 | light | saved-template shell, SMTP failure, filters |
| `wb_listora_send_notification` passes recipient | ora:emails-alerts/send-notification-to-arg.md | CLI (hook) | admin, system | n/a | n/a | admin-recipient step unrun |
| Pro email templates render warning-free | pro:emails-alerts/email-template-palette-keys.md | CLI render | admin, system, owner | - | gap | the other 15 templates |
| Notification digest queue and flush; also: Digest delivers owner events (Pro) | pro:emails-alerts/notification-digest.md | A, E, CLI | admin, owner, editor, guest | both | n/a | timezone of 9 AM schedule; scale |
| Pro email send gate and digest queue | pro:emails-alerts/pro-email-send-gate.md | email, CLI | admin, system | n/a | n/a | member opt-out out of scope |
| Save a search from the Directory + dashboard + alerts | pro:emails-alerts/saved-search-alert.md | frontend, email, cron | owner, other, guest, editor, admin | both | both | geometry at both widths, boundary names, new-listing alert not live |
| Saved searches REST contract + alert job | pro:emails-alerts/saved-search-crud.md | REST, cron, hooks | owner, other, guest, editor, admin | n | n | boundary body, parallel create not live |
