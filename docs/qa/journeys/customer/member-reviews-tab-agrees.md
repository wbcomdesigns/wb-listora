---
journey: member-reviews-tab-agrees
plugin: wb-listora
priority: high
roles: [member-owner, member-other, anonymous, administrator]
covers: [user-dashboard, reviews, rest-dashboard, card-10331641303]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "{owner} wrote reviews in several statuses AND owns listings that received reviews: seed approved/pending/rejected/spam rows with title 'QA-J1 <status>' on different listings, one review per listing per user"
  - "Object cache flushed: the dashboard caches its stats"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: ["ora:regression/member-reviews-tab-agrees.md"]
---

# The member Reviews tab counts what it lists and says what is not live

## Why
A member sees their written and received reviews in My Dashboard; a badge that disagrees with the list, a spam review that leaks, or a pending review that looks published confuses members and moderation.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Member-visible statuses are approved, pending, rejected, never spam (`wb_listora_member_review_statuses_sql()`, `includes/helpers.php:498-511`; `Dashboard_Data::count_reviews_written()` `:140`, `list_reviews_written()` `:155`, `count_reviews_received()` `:198`, approved only; `stats.reviews_total` `:120`).
- Seed so written = N approved + 1 pending + 1 rejected (+1 spam) and received = M approved. Record `WRITTEN` and `RECEIVED` from the seed, not from the page. Demo data has no pending/spam reviews, so seeding is required.

## Steps

### 1. Website, member with mixed statuses
- **Action**: open `$SITE_URL/my-listings/?tab=reviews` as {owner}.
- **Expect**: `#dash-tab-reviews` reads "Reviews <WRITTEN-visible + RECEIVED>"; headings "Reviews I've Written" and "Reviews on My Listings"; exactly one "Awaiting approval" chip and one "Not published" chip; approved rows carry no chip; the spam review text is nowhere in the panel.
- **Read-back**: badge equals `SELECT COUNT(*)` over member-visible statuses written + approved received. Positive control: before seeding the extra statuses the badge was the approved total only.

### 2. REST (the app)
- **Action**: as {owner} `GET /listora/v1/dashboard/reviews`, `/dashboard/stats`, `/dashboard/profile`.
- **Expect**: `written_total`, `received_total` equal the seed; `stats.reviews` = written + received (= web badge), `stats.reviews_written`, `stats.reviews_received`; profile `review_count` = written; written rows carry `status`, none `spam`.
- **Read-back**: counts equal the SQL in step 1.

### 3. Pagination agrees
- **Expect**: `written_pages` = ceil(`written_total` / per_page) (20 default, filter `wb_listora_dashboard_per_page`); out-of-range pages clamp to the last page; at 2000+ reviews the count is a `COUNT(*)` (no list load). Seed 2000+ rows (QA-prefixed) to run the scale check. Not verified live.

### 4. Phone width
- **Expect**: at 390 the label sits inside the row; `documentElement.scrollWidth <= innerWidth`.

## Role ladder
| Actor | Result |
|---|---|
| {owner} | steps 1-2 |
| {other} | sees only their own written/received reviews |
| {guest} | sign-in prompt / REST `401` |
| {editor} / {admin} | member dashboard shows their own; moderation lives in Listora > Reviews |

## Pass criteria
1. Tab badge, tile and list agree (written + received); spam never shown.
2. Pending and rejected rows say so; approved rows have no chip.
3. REST totals equal the web numbers; pages clamp.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Badge and list disagree | stopped using `Dashboard_Data` | `blocks/user-dashboard/render.php:114-115`, `includes/rest/class-dashboard-controller.php:299-301` |
| Spam visible | helper bypassed | `includes/helpers.php:498` |
| (note) privacy exporter must still count every status | deliberately not on the helper | `Leads`/privacy exporter |

## Not verified
Step 3 scale seed; 390 in dark mode.

## Cleanup
`DELETE FROM ${PREFIX}listora_reviews WHERE title LIKE 'QA-J1 %'`.
