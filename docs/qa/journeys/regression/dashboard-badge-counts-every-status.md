---
journey: dashboard-badge-counts-every-status
plugin: wb-listora
priority: high
roles: [member]
covers: [dashboard, counter-vs-list, cross-cutting-check-8, credits]
prerequisites:
  - "A member whose ONLY listing is in a non-headline status (listora_payment is ideal)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The My Listings badge counts every status the panel shows

The sidebar badge once summed four statuses (`publish`, `pending`, `listora_expired`, `draft`) while the rows query beside it used eight. A member whose only listing sat in `listora_payment` ("Awaiting Credits", the paused-for-credits state) saw **"My Listings 0"** above a panel listing that very listing. The status list is now defined once: `wb_listora_member_listing_statuses()` (`includes/helpers.php:347`, filter `wb_listora_member_listing_statuses`): `publish, pending, draft, listora_expired, listora_rejected, listora_deactivated, pending_verification, listora_payment`. Both consumers read it: the badge/tiles through `Dashboard_Data::stats()` (`includes/core/class-dashboard-data.php:43`, key `total`) and the rows through `blocks/user-dashboard/render.php:96`.

Roles: the member with a listing in a non-headline status (actor); another member sees their own count only; logged out has no badge; admin as control (their own total, 2,632 on the QA site).

## Setup

- A member whose ONLY listing is in a status outside the headline four (`listora_payment` is ideal). QA site: none exists with `listora_payment` only (the three `listora_payment` listings belong to a deleted user id 107), so create one: `wp post create --post_type=listora_listing --post_status=listora_payment --post_author=<member id> --post_title='QA-J1- payment only'` for a member with no other listings (delete it afterwards), or use `qa_vendor_01`, whose seven listings span six statuses, as the multi-status case.
- Dashboard: `$SITE_URL/my-listings/?tab=listings`.

## Test-data trap

A member whose listings are all `publish` passes every assertion while the bug is fully present (`publish` was one of the four counted). The fixture MUST use a status outside the headline four.

## Steps

### 1. Badge equals rows
- **Action**: sign in as the member whose only listing is `listora_payment`; read the sidebar item `#dash-tab-listings` and count `.listora-dashboard__listing-row` in `#dash-panel-listings`.
- **Expect**: badge `1`, panel shows 1 row (pill "Awaiting Credits").
- **Fails if**: the badge reads 0 while a row renders. That pairing is never legitimate.

### 2. Every status counts
- **Action**: as `qa_vendor_01` (published x2, deactivated, expired, rejected, awaiting credits, pending review).
- **Expect**: `#dash-tab-listings` reads "My Listings 7" and the panel renders 7 rows (verified). With one listing per each of the 8 statuses the badge must be 8 and 8 rows render.
- **Fails if**: any status is visible in the panel but missing from the badge.

### 3. One list, not two
- **Action**: `grep -n "wb_listora_member_listing_statuses\|listings_statuses = array" blocks/user-dashboard/render.php includes/core/class-dashboard-data.php`
- **Expect**: both read the helper (`render.php:96`, `class-dashboard-data.php:98`); there is no second literal array. Two lists drift; that is what caused this.

### 4. The stat cache cannot serve a stale shape
- **Expect**: the stats are cached per user and type in the `wb_listora_dashboard` object-cache group keyed with the group's `last_changed` stamp (`Dashboard_Data::stats()` + `Core\Cache::key()`); there is no `listora_dashboard_stats_*` transient any more. The group is bumped on every listing, review, favourite and claim write (`includes/core/class-cache.php:66-80`), so the new total is visible on the next render. The old "transient with no `total` key" case no longer exists.

### 5. Positive control, role ladder
- **Control**: step 2's mixed member (7) vs a member with only `publish` rows (badge = rows trivially) shows the badge follows the helper, not a fixed four.
- **Another member**: badge equals their own rows; never another member's.
- **Logged out**: no badge (login prompt).
- **Admin, 2000+ rows**: badge "My Listings 2,632"; the panel shows "Page 1 of 132 (2,632 items)" (verified), proving the count is a `COUNT(*)` and not `count( list )`.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Badge 0 with a row visible | stats sum a literal subset | `includes/core/class-dashboard-data.php:97-100` |
| Panel misses a status | rows query list diverged | `blocks/user-dashboard/render.php:96`, `:272` |
| Two lists | literal re-added | `includes/helpers.php:347-358` |

## Not verified

Steps 2 and 5 (admin 2,632, `qa_vendor_01` 7 with badge "My Listings 7") were read live with curl. Step 1's payment-only member and step 4's cache bump were NOT run (no such member on the QA site; no persistent object cache is installed, so cross-request staleness is unobservable there).
