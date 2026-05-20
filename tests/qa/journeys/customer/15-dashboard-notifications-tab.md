---
journey: dashboard-notifications-tab
plugin: wb-listora
priority: high
roles: [subscriber]
covers: [user-dashboard, notifications-tab, notifications-read]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A logged-in user with >=1 unread notification (e.g. after a review/claim event on their listing)"
estimated_runtime_minutes: 4
covers_card: null
---

# Dashboard Notifications tab lists + marks notifications read

Covers the user-dashboard Notifications tab and its REST: `GET
/listora/v1/dashboard/notifications` + `PUT
/listora/v1/dashboard/notifications/read`. Previously unguarded.

## Steps

### 1. Notifications tab renders unread items
- **Action**: `/dashboard/?autologin=user#notifications` (or click Notifications tab).
- **Expect**: `GET /dashboard/notifications` returns the user's notifications with an unread count; the tab nav shows the unread badge; each row renders message + timestamp + (where relevant) a link to the related listing/review.

### 2. Mark read
- **Action**: click "Mark all read" (or an individual item) → `PUT /dashboard/notifications/read`.
- **Expect**: 200; unread count drops to 0 (or by one); badge clears; re-fetching `GET /dashboard/notifications` shows the items as read.

### 3. Empty state
- **Action**: as a user with no notifications, open the tab.
- **Expect**: the canonical empty-state card renders (icon + "No notifications") — not a blank panel or a JS error.

### 4. Auth gate
- **Action**: call `GET /dashboard/notifications` unauthenticated.
- **Expect**: 401/403 (dashboard data is per-user).
