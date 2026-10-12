---
journey: dashboard-no-empty-more-menu
plugin: wb-listora
priority: normal
roles: [subscriber]
covers: [user-dashboard, listing-actions]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
covers_card: 10332121171
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Dashboard rows show More only when it has actions

Regression sentinel: pending, rejected and awaiting-credits rows once rendered the More (three-dot) button with an empty menu, which read as an action that did nothing. The menu (`.listora-dashboard__menu-wrap` > button "More" `actions.toggleListingMenu` > `.listora-dashboard__menu-dropdown`) renders only when it holds Renew, Deactivate or Reactivate: `templates/blocks/user-dashboard/tab-listings.php:493` (`$listora_can_renew || post_status in ( publish, listora_deactivated )`).

Roles: the listing owner (a member) is the actor; another member sees only their own rows; logged out gets the login prompt; admin as control.

## Setup

- A member with listings in several statuses. QA site: `qa_vendor_01` (user 6): published 7379 and 845, deactivated 7381, expired 7383, rejected 7380, awaiting credits 7382, pending review 7384. Renewal feature ON so expired / expiring rows can renew.
- Dashboard `$SITE_URL/my-listings/?tab=listings`.

## Steps

### 1. Pending, rejected and awaiting-credits rows
- **Action**: read the actions cluster (`.listora-dashboard__listing-actions`) of 7384 (Pending Review), 7380 (Rejected), 7382 (Awaiting Credits).
- **Expect**: NO More button and no `.listora-dashboard__menu-wrap` (verified). Row actions are Services, Edit (and View only when published).

### 2. Published row
- **Action**: 7379 / 845.
- **Expect**: **More** present; its dropdown holds one item, **Deactivate** (`.listora-dashboard__menu-item--danger`, `actions.deactivateListing`); View is shown.

### 3. Deactivated / renewable rows
- **Action**: 7381 (Deactivated), 7383 (Expired).
- **Expect**: 7381: More holds **Reactivate**; no View (not public). 7383: a primary **Renew Now** button AND More holding **Renew** (verified). An expiring published row gets Renew Now plus More with Renew and Deactivate.

### 4. Positive control, role ladder
- **Control**: steps 2 and 3 are the positive control for step 1: a page with no More anywhere would also satisfy step 1.
- **Clicking More** opens the dropdown (`hidden` removed) with the same items; Esc / outside click closes it.
- **Another member**: only their own rows and menus. **Logged out**: login prompt. **Admin** (2,632 rows; only 99 non-draft): a draft row has Edit but no More.

### 5. Mobile
- **Expect** at 390: actions wrap under the row title, each button >= 40px tall, no horizontal scroll.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Empty More on pending / awaiting rows | gate dropped | `templates/blocks/user-dashboard/tab-listings.php:490-494` |
| Missing Renew on expired | `$listora_can_renew` false | `tab-listings.php:158-176` |

## Not verified

Steps 1 to 3 were read live with curl as `qa_vendor_01` (action labels and presence of the menu wrapper per row). The dropdown click, Esc handling and the 390 layout were NOT run in a browser; the admin draft-row case was read in source only.
