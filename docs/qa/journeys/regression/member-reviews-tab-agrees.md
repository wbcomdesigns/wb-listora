---
journey: member-reviews-tab-agrees
plugin: wb-listora
priority: high
roles: [member-owner]
covers: [user-dashboard, reviews, rest-dashboard]
prerequisites:
  - "Reign or BuddyX"
  - "A member with approved, pending, rejected and spam reviews (zz_scale_reviewer_4 has approved/pending/spam; rft_rev_b has a rejected one)"
  - "Object cache flushed: the dashboard caches its stats"
estimated_runtime_minutes: 5
covers_card: 10331641303
---

# The Reviews tab counts what it lists, and says what is not live

## Background

The tab badge counted approved reviews (a card-10167579239 decision, made when the tab listed approved only). The list later came to show every status, spam included, with no label, so the badge read 421 over 35 pages of rows. The REST profile counted every status, a third number. All of these now use `wb_listora_member_review_statuses_sql()`: approved, pending and rejected, never spam.

## Steps

### 1. Website, member with mixed statuses
- **Action**: open the dashboard Reviews tab.
- **Expect**: the tab badge equals the tile, and equals approved + pending + rejected for this member; the pagination page count matches (total / 20); no spam review appears; pending rows say "Awaiting approval", rejected rows "Not published", approved rows carry no label.

### 2. REST (the app)
- **Action**: as the same member, GET `/listora/v1/dashboard/reviews`, `/dashboard/stats`, `/dashboard/profile`.
- **Expect**: `written_total`, `stats.reviews` and the profile review count all equal the web badge; no row has `status: spam`; each row carries `status`.

### 3. Phone width
- **Expect**: at 390px the label sits inside the row and the page does not scroll sideways.

## Fail diagnostics
- Badge and list disagree → a count or list query in `blocks/user-dashboard/render.php` or `includes/rest/class-dashboard-controller.php` no longer uses `wb_listora_member_review_statuses_sql()`.
- The privacy exporter must still count every status: it is deliberately not on the helper.
