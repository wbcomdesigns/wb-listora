---
journey: send-notification-to-arg
plugin: wb-listora
priority: high
roles: [administrator]
covers: [send-notification-filter, notification-recipient-arg, digest-consumer-contract]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A listing owner with a known email exists (capture OWNER_EMAIL)"
estimated_runtime_minutes: 4
covers_card: null
covers_commit: 0673644
---

# wb_listora_send_notification passes the recipient as its 4th arg

Regression sentinel for HC-1-digest-recipient (Free side, `0673644`). The
`wb_listora_send_notification` filter only exposed `($send, $event, $vars)` —
the recipient address was not passed. Pro's digest interceptor therefore had no
way to learn who an owner notification was for, so it stored an empty recipient
and the daily digest silently discarded every owner email. The fix passes `$to`
as the 4th filter arg at the fire-site (`includes/workflow/class-notifications.php:1032`),
updates the docblock, and bumps `args_count` 3 → 4 in `audit/manifest.json`.

## Steps

### 1. Fire-site passes $to as the 4th argument
- **Action**:
  ```
  grep -n "apply_filters( 'wb_listora_send_notification'" includes/workflow/class-notifications.php
  ```
- **Expect**: `apply_filters( 'wb_listora_send_notification', true, $event, $vars, $to )` — `$to` is the 4th positional arg.
- **On fail**: `0673644` — the recipient must be exposed on the filter.

### 2. A consumer receives the recipient address
- **Action**: register a probe filter and trigger a notification to the owner (e.g. approve a pending listing owned by OWNER_EMAIL's user, which fires `listing_approved`):
  ```
  wp eval "
  add_filter('wb_listora_send_notification', function(\$send, \$event, \$vars, \$to = '') {
      file_put_contents('/tmp/listora-notif-to.txt', \$event.'|'.\$to);
      return \$send;
  }, 10, 4);
  do_action('wb_listora_listing_status_changed', LISTING_ID, 'publish', 'pending');
  echo file_get_contents('/tmp/listora-notif-to.txt');
  "
  ```
- **Expect**: the probe captures `<event>|<OWNER_EMAIL>` — the 4th arg is the real recipient address, not empty. A 4-arg `add_filter` callback receives `$to` populated.
- **On fail**: the fire-site still passes only 3 args, so `$to` arrives as the default `''`.

### 3. Manifest args_count is 4
- **Action**:
  ```
  jq '.hooks_fired[] | select(.name=="wb_listora_send_notification") | .args_count' audit/manifest.json
  ```
- **Expect**: `4`.
- **On fail**: manifest not bumped in the same PR.

### Cleanup
- Delete `/tmp/listora-notif-to.txt`.

## Notes
- This is the Free contract the Pro digest consumes — see Pro's `regression/digest-owner-event-delivery.md` for the end-to-end delivery path. Widening the signature from 3 → 4 args does NOT add a coupling pair; the existing `wb_listora_send_notification` pair in `cross-plugin-coupling.json` stays counted once.
- 3-arg legacy `add_filter` callbacks remain valid (PHP ignores the extra arg); the 4th arg is additive per the "never change a public signature without back-compat" production rule.
