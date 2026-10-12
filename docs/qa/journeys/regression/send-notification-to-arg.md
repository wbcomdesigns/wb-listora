---
journey: send-notification-to-arg
plugin: wb-listora
priority: high
roles: [administrator, system]
covers: [send-notification-filter, notification-recipient-arg, digest-consumer-contract]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing whose owner has a known email (verified: listing 845, owner qa_vendor_01, qa_vendor_01@directory.local)"
estimated_runtime_minutes: 4
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---

# wb_listora_send_notification passes the recipient as its 4th arg

System/hook contract (no UI), so no role ladder; the recipient arg is the owner's address for owner events and the admin address for admin events. Verified via: `wp eval-file` with a probe filter that returns `false` (so no real mail is sent) and a manifest read.

The filter `wb_listora_send_notification` is applied in `Notifications::send()` (`includes/workflow/class-notifications.php:1368`): `apply_filters( 'wb_listora_send_notification', true, $event, $vars, $to )`. Pro's digest interceptor (`wb-listora-pro/includes/features/class-notification-digest.php:251`, `add_filter(..., 10, 4)`) needs `$to`.

## Steps

### 1. Fire-site passes `$to` as 4th argument
- **Action**: `grep -n "apply_filters( 'wb_listora_send_notification'" includes/workflow/class-notifications.php`
- **Expect**: `:1368` with `true, $event, $vars, $to`.

### 2. A consumer receives the recipient
- **Action**:
  ```php
  add_filter('wb_listora_send_notification', function($send,$event,$vars,$to=''){ $GLOBALS['got'][]=$event.'|'.$to; return false; }, 10, 4);
  do_action('wb_listora_listing_status_changed', LISTING_ID, 'publish', 'pending');
  echo json_encode($GLOBALS['got']);
  ```
- **Expect**: `["listing_approved|qa_vendor_01@directory.local"]` (observed). Returning `false` suppresses the mail, so nothing is delivered.
- **Positive control for the 3-arg legacy callback**: a callback registered with `accepted_args = 3` still runs and simply lacks `$to`.
- 2.0.0 also adds `wb_listora_should_send_notification` (skipped mail fires `wb_listora_notification_skipped`); this journey covers only the 4-arg contract.

### 3. Manifest says 4
- **Action**: `jq '[.. | objects | select(.name?=="wb_listora_send_notification")] | map({type,args_count})' audit/manifest.json`
- **Expect**: `[{"type":"filter","args_count":4}]` (the entry sits under `hooks_fired`).

## Notes
End-to-end delivery is covered by Pro `regression/digest-owner-event-delivery.md` (exists). The `wb_listora_send_notification` pair in `audit/derived/cross-plugin-coupling.json` stays counted once.
