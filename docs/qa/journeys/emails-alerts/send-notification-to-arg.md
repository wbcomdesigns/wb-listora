---
journey: send-notification-to-arg
group: emails-alerts
actor: system
mode: cli
plugin: wb-listora
priority: high
roles: [admin, system]
covers: [send-notification-filter, notification-recipient-arg, digest-consumer-contract]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI against $WP_PATH"
  - "A published listing whose author ({owner}) has a known email"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
site_profile: required
supersedes:
  - ora:regression/send-notification-to-arg.md
---

# wb_listora_send_notification passes the recipient as its 4th argument

## Why
Pro's digest interceptor (`wb-listora-pro/includes/features/class-notification-digest.php:251`, `add_filter(..., 10, 4)`) needs `$to` to group queued mail per recipient. System/hook contract, no UI: the recipient is the owner's address for owner events and the admin address for admin events. End-to-end digest delivery is owned by Pro `pro:emails-alerts/notification-digest.md` steps 4b-4d; this journey covers only the 4-arg contract. 2.0.0 also adds `wb_listora_should_send_notification` (skipped mail fires `wb_listora_notification_skipped`); out of scope here.

The filter is applied in `Notifications::send()` (`includes/workflow/class-notifications.php:1368`): `apply_filters( 'wb_listora_send_notification', true, $event, $vars, $to )`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `LISTING_ID` = a published listing owned by {owner}; `OWNER_EMAIL` = `wp user get {owner} --field=user_email`.

## Steps

### 1. The fire-site passes `$to` as 4th argument
- **Action**: `grep -n "apply_filters( 'wb_listora_send_notification'" includes/workflow/class-notifications.php`
- **Expect**: a line with `true, $event, $vars, $to`.
- **Read-back**: n/a (static).

### 2. A consumer receives the recipient
- **Action**:
  ```php
  add_filter('wb_listora_send_notification', function($send,$event,$vars,$to=''){ $GLOBALS['got'][]=$event.'|'.$to; return false; }, 10, 4);
  do_action('wb_listora_listing_status_changed', LISTING_ID, 'publish', 'pending');
  echo json_encode($GLOBALS['got']);
  ```
- **Expect**: `["listing_approved|OWNER_EMAIL"]`. Returning `false` suppresses the mail, so nothing is delivered.
- **Read-back**: Listora email log has no new row.

### 3. Legacy 3-arg callbacks still run
- **Action**: register a callback with `accepted_args = 3` and repeat step 2.
- **Expect**: it runs and simply lacks `$to`.
- **Read-back**: the callback captured `$to === ''`.

### 4. Admin events pass the admin address
- **Action**: fire an admin-facing event (`listing_submitted`) the same way.
- **Expect**: `$to` equals the Listora admin recipient (WordPress admin email by default).
- **Read-back**: captured string equals `get_option( 'admin_email' )`. Not verified live.

### 5. Manifest says 4
- **Action**: `jq '[.. | objects | select(.name?=="wb_listora_send_notification")] | map({type,args_count})' audit/manifest.json`
- **Expect**: `[{"type":"filter","args_count":4}]` (under `hooks_fired`).
- **Read-back**: the `wb_listora_send_notification` pair in `audit/derived/cross-plugin-coupling.json` stays counted once.

## Role ladder
Not applicable (hook contract). {admin} is the control for step 4.

## Pass criteria
1. The fire-site passes `true, $event, $vars, $to`.
2. A 4-arg consumer sees the owner address for an owner event and the admin address for an admin event.
3. The manifest records `args_count: 4`.

## Fail diagnostics
| Symptom | File |
|---|---|
| `$to` empty in consumer | `includes/workflow/class-notifications.php:1368` |
| Digest groups under the wrong recipient | `wb-listora-pro/includes/features/class-notification-digest.php:251` |

## Not verified
Step 4; delivery of the real mail (probe returns false).

## Cleanup
None (the probe filter lives only in the eval process).
