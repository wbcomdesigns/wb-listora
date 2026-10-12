---
journey: dashboard-notifications-feed
group: dashboard-account
actor: system
mode: api
plugin: wb-listora
priority: normal
roles: [owner, other, editor, admin, guest]
covers: [notifications-rest, notifications-read, no-notifications-tab]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "{owner} has at least 1 notification (a listing-approved notice, a received review or a claim update)"
estimated_runtime_minutes: 3
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
site_profile: required
supersedes:
  - ora:customer/15-dashboard-notifications-tab.md
---

# The member notification feed is REST only: per-user, mark-read zeroes it, and there is no dashboard tab

## Why
RETIRE-CANDIDATE (owner decides): the member dashboard has NO Notifications tab or unread badge, and nothing in `src/`, `templates/` or `blocks/` calls these routes. What remains is the REST feed (apps and integrations) and the member's email preferences on the Profile tab (`12-dashboard-profile-tab`). The old UI steps cannot pass and were removed; the `?tab=notifications` link-to-nowhere case is owned by `dashboard-tabs-follow-features`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Cookie + `X-WP-Nonce` for {owner}, {other}. Baseline `wp user meta get <id> _listora_notifications_read_at` (restore afterwards).

## Steps

### 1. The feed works
- **Action**: `GET /wp-json/listora/v1/dashboard/notifications?per_page=3` as {owner}.
- **Expect**: `200 {notifications:[{type, message, listing_id, date, read}], unread_count}`. `type` is one of `review_received`, `claim_approved`, `claim_rejected`, `claim_submitted`, `listing_approved`, `listing_rejected`, `listing_expired`; messages such as `Your listing "<title>" was approved`. `per_page` accepts 1-50 (default 20).
- **Read-back**: item count <= 3; boundary: `per_page=0`, `51`, `abc` are clamped or rejected with a 400, never a 500 (Not verified live).

### 2. Mark read
- **Action**: `PUT /wp-json/listora/v1/dashboard/notifications/read` (no body); repeat once.
- **Expect**: `200 {success:true, message:"Notifications marked as read."}`; the next GET shows `unread_count: 0` with every item `read: true`. It marks everything read; there is no per-item route. The second call is a safe no-op.
- **Read-back**: user meta `_listora_notifications_read_at` is set. Restore: `wp user meta delete <id> _listora_notifications_read_at` if it did not exist before.

### 3. Empty feed (N = 0)
- **Action**: GET as a member with no reviews, claims or listings.
- **Expect**: `200 {notifications:[], unread_count:0}`, not an error.
- **Read-back**: body matches.

### 4. A new event appears and is unread
- **Action**: after step 2 approve a pending listing of {owner} (or submit a claim), then GET.
- **Expect**: the new item is `read: false`, `unread_count: 1`.
- **Read-back**: `date` is later than `_listora_notifications_read_at`. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | sees their own items |
| {other} | `200` with their own feed only; never {owner}'s items |
| {guest} | both routes `401 listora_unauthorized` ("You must be logged in to perform this action.") |
| {editor}, {admin} | their own feed only; the routes take no user id |

## Pass criteria
1. The feed is per-user, 401 when logged out, and mark-read zeroes the unread count.
2. An empty feed is `200` with empty arrays.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Feed shows other users' items | not scoped to the caller | `includes/rest/class-dashboard-controller.php:835` (`get_notifications`) |
| Unread count never drops | read marker | `class-dashboard-controller.php:1025` (`mark_notifications_read`) |
| Routes reachable logged out | permission callback | `class-dashboard-controller.php:1054` |

## Not verified
Whether any consumer (the Listora app) still reads these routes: unknown from this repo. Check `listora-app` before removing them. Verified via curl/DOM + REST per role; no browser pass; step 4 and boundary `per_page` values not run.

## Cleanup
Restore `_listora_notifications_read_at`.
