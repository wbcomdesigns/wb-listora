---
journey: dashboard-notifications-tab
plugin: wb-listora
priority: normal
roles: [subscriber]
covers: [user-dashboard, notifications-rest, notifications-read, unknown-tab-notice]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member with >=1 notification: QA site qa_vendor_01 (listing approved notice)"
estimated_runtime_minutes: 3
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Dashboard notifications: there is no Notifications tab, only a REST feed

RETIRE-CANDIDATE (owner decides): the member dashboard has NO Notifications tab or unread badge. The tab ids today are overview, listings, reviews, favorites, claims, credits, profile, plus Pro's needs, my-responses, saved-searches and analytics, and Free's leads (shown to members who can receive contact messages). Nothing in `src/`, `templates/` or `blocks/` calls the notifications routes. What remains is the REST feed and the member's email preferences on the Profile tab (`12-dashboard-profile-tab.md`). The old UI steps (tab, badge, "Mark all read", "No notifications" card) cannot pass and are removed below.

Verified via: curl/DOM + REST per role. No browser pass.

## Steps

### 1. The tab does not exist and a link to it says so
- **Action**: as any member, open `$DASH/?tab=notifications`.
- **Expect**: Overview is shown with a notice `p.listora-dashboard__notice[role="status"]`: `There is no "notifications" section in your dashboard. Showing Overview instead.` No element `#dash-tab-notifications` exists.
- **POSITIVE CONTROL**: `$DASH/?tab=profile` opens Profile with no such notice, so the notice is specific to unknown tab ids.

### 2. The REST feed still works (apps and integrations)
- **Action**: `GET /wp-json/listora/v1/dashboard/notifications?per_page=3` as qa_vendor_01.
- **Expect**: `200 {notifications:[{type, message, listing_id, date, read}], unread_count}`. `type` is one of `review_received`, `claim_approved`, `claim_rejected`, `claim_submitted`, `listing_approved`, `listing_rejected`, `listing_expired`; messages such as `Your listing "<title>" was approved`. `per_page` accepts 1 to 50 (default 20).

### 3. Mark read
- **Action**: `PUT /wp-json/listora/v1/dashboard/notifications/read` (no body).
- **Expect**: `200 {success:true, message:"Notifications marked as read."}`; the user meta `_listora_notifications_read_at` is set, and the next GET shows `unread_count: 0` with every item `read: true`. The call marks everything read; there is no per-item route.
- **Restore** (QA): `wp user meta delete <id> _listora_notifications_read_at` if it did not exist before.

### 4. Role ladder
- **Owner (the member whose listings/claims produced the items)**: sees their items.
- **Another member (subscriber3)**: `200` with their own feed only (empty here, `unread_count: 0`); never the first member's items.
- **Logged out**: both routes `401 listora_unauthorized` ("You must be logged in to perform this action.").
- **Editor / admin (control)**: their own feed only; the routes take no user id.

### 5. Empty feed
- **Action**: GET as a member with no reviews, claims or listings.
- **Expect**: `200 {notifications:[], unread_count:0}`, not an error.

## Pass criteria

1. `?tab=notifications` shows the unknown-section notice, never a blank panel or a JS error.
2. The REST feed is per-user, 401 when logged out, and mark-read zeroes the unread count.

## Fail diagnostics

| Symptom | Likely cause | File:line |
|---|---|---|
| Feed shows other users' items | queries not scoped to the caller | `includes/rest/class-dashboard-controller.php:835` (`get_notifications`) |
| Unread count never drops | read marker | `includes/rest/class-dashboard-controller.php:1025` (`mark_notifications_read`) |
| Unknown tab renders blank | notice branch | `blocks/user-dashboard/render.php:646` |
| Routes reachable logged out | permission callback | `includes/rest/class-dashboard-controller.php:1054` |

## Not verified

- Whether any consumer (the Listora app) still reads these routes: unknown from this repo. Check `listora-app` before removing them.
