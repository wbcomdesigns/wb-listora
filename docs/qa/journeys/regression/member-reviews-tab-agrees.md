---
journey: member-reviews-tab-agrees
plugin: wb-listora
priority: high
roles: [member-owner]
covers: [user-dashboard, reviews, rest-dashboard]
prerequisites:
  - "A member who wrote reviews in several statuses AND owns listings that received reviews (create as QA-J1: seed approved/pending/rejected/spam rows for qa_vendor_01 with title 'QA-J1 <status>' on different listings, one review per listing per user)"
  - "Object cache flushed: the dashboard caches its stats"
estimated_runtime_minutes: 6
covers_card: 10331641303
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The Reviews tab counts what it lists, and says what is not live

The member-visible review statuses are approved, pending, rejected - never spam (`wb_listora_member_review_statuses_sql()`, `includes/helpers.php:498-511`, used by `Dashboard_Data::count_reviews_written()` `includes/core/class-dashboard-data.php:140` and `list_reviews_written()` `:155`). The tab lists TWO groups, "Reviews I've Written" and "Reviews on My Listings" (received: approved only, `count_reviews_received()` `:198`), so the tab badge and tile are written + received (`stats.reviews_total`, `:120`).

Verified via: Playwright as qa_vendor_01 (1440 and a 390 overflow check) with seeded QA-J1 rows, plus `rest_do_request()`; seed rows removed afterwards. Demo data has no pending/spam reviews of its own, so seeding is required.

## Steps

### 1. Website, member with mixed statuses
- **Action**: seed pending, rejected and spam reviews for qa_vendor_01 (listings 31, 38, 45) so written = 2 approved + 1 pending + 1 rejected (+1 spam) and received = 2. Open `/my-listings/?tab=reviews`.
- **Expect**: `#dash-tab-reviews` reads "Reviews 6" (written 4 + received 2); the tab has headings "Reviews I've Written" and "Reviews on My Listings"; exactly one "Awaiting approval" chip (pending) and one "Not published" chip (rejected); approved rows carry no chip; the spam review's text is nowhere in the panel.
- **Positive control**: before seeding the extra statuses the badge was 4 (2 + 2).

### 2. REST (the app)
- **Action**: as the same member `GET /listora/v1/dashboard/reviews`, `/dashboard/stats`, `/dashboard/profile`.
- **Expect**: `written_total` = 4, `received_total` = 2; `stats.reviews` = 6 (= web badge), `stats.reviews_written` = 4, `stats.reviews_received` = 2; profile `review_count` = 4 (written). Written rows carry `status` and none is `spam`.

### 3. Pagination agrees
- **Expect**: `written_pages` = ceil( `written_total` / per_page ) (20 by default, filter `wb_listora_dashboard_per_page`); out-of-range pages clamp to the last page. At 2000+ reviews the count is a `COUNT(*)` (no list load).

### 4. Phone width
- **Expect**: at 390px the label sits inside the row; `documentElement.scrollWidth` (375) <= `innerWidth` (390).

### 5. Role ladder
| Actor | Result |
|---|---|
| Member-owner | steps 1-2 |
| Another member | sees only their own written/received reviews |
| Logged out | sign-in prompt / REST 401 |
| Moderator / admin | member dashboard shows their own; moderation lives in Listora > Reviews |

## Fail diagnostics
- Badge and list disagree: `blocks/user-dashboard/render.php:114-115` / `includes/rest/class-dashboard-controller.php:299-301` stopped using `Dashboard_Data`.
- Spam visible: `includes/helpers.php:498` helper bypassed.
- The privacy exporter must still count every status: deliberately not on the helper.

## Cleanup
`DELETE FROM wp_listora_reviews WHERE title LIKE 'QA-J1 %'`.
