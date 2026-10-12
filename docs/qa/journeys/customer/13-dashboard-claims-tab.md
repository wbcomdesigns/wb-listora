---
journey: dashboard-claims-tab
plugin: wb-listora
priority: normal
roles: [member, other-member, anonymous, editor, administrator]
covers: [user-dashboard, claims-tab, claim-status-display, claims-pagination, claims-rest-scope, claims-no-member-writes, claims-feature-switch]
prerequisites:
  - "Site reachable at $SITE_URL; Claims feature ON (Listora > Settings > Features)"
  - "CLAIMER = {owner}: a member with claims in all states (approved + pending + rejected) and more than one page (> per_page). Read-only: do not seed; take counts from the DB"
  - "NOCLAIMS = {other}: a member with no claims"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: []
---

# A member sees only their own claim history on the "My Claims" tab

## Why
Claimants track a claim here; showing another member's claims, wrong status labels, an empty page past the last page, or a withdraw control that does nothing breaks trust. The tab is read-only: no member Withdraw and no member DELETE route.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `$DASH` = the dashboard page (Listora > Settings > General > Dashboard page); tab `$DASH/?tab=claims`, pages `&claims_page=N`; sidebar tab `#dash-tab-claims` "My Claims", panel `#dash-panel-claims`.
- `TOTAL` = `SELECT COUNT(*) FROM ${PREFIX}listora_claims WHERE user_id=<CLAIMER id>`; `PER_PAGE` = 20 (filter `wb_listora_dashboard_per_page`); `PAGES` = ceil(TOTAL / PER_PAGE). Record counts from SQL, never from this file.

## Steps

### 1. Open the tab as a member with claims
- **Action**: `$DASH/?tab=claims` as CLAIMER.
- **Expect**: `#dash-tab-claims[aria-selected=true]`; `#dash-panel-claims` not `hidden`; `ul.listora-dashboard__claims-list` with up to PER_PAGE `li.listora-dashboard__claim-row`; the tab badge is TOTAL.
- **Read-back**: row count on page 1 = `min(TOTAL, PER_PAGE)`.

### 2. Row content and status labels
- **Expect per row**: title link `.listora-dashboard__claim-title a` to the listing; chip `.listora-dashboard__status`; "Submitted <date>"; note and action by status:
  - pending: chip "Under review", note "We will email you as soon as the review is complete.", action "View Listing"
  - approved: chip "Approved", note "You now own this listing. Open My Listings to edit it.", action "Edit Listing" (to `<submit page>?edit=<id>`)
  - rejected: chip "Not approved", action "View Listing", and "Reviewer notes:" when the admin left notes
- **Not present**: "Pending" label, "Withdraw", "Manage Listing".
- **Read-back**: a claim just submitted in `customer/05-claim-a-business.md` step 5 is the top row with "Under review".

### 3. Pagination (scale)
- **Action**: none restated. `customer/dashboard-tabs-paginate-every-row.md` owns the Claims pager (Previous / Page X of Y / Next, `claims_page=999` clamping to the LAST page, the paginated REST envelope and the `LIMIT/OFFSET` cost, steps 4 and 7-9).
- **Expect**: run those steps for CLAIMER (> PER_PAGE claims); this journey only requires the tab badge to equal TOTAL (step 1).

### 4. Empty state with a positive control
- **Action**: `$DASH/?tab=claims` as NOCLAIMS.
- **Expect**: `.listora-empty` "No claim requests yet", text `Claim an existing business listing to manage it. Click "Claim this listing" on any unowned entry.` and primary button "Browse the directory" to the Directory page.
- **Read-back**: step 1 (CLAIMER) shows rows and no empty card, so the card is conditional (positive control).

### 5. REST scope (`GET /listora/v1/dashboard/claims`)
- **Action**: call as each rung of the ladder below.
- **Expect**: CLAIMER `200 {claims:[...], total:TOTAL, pages:PAGES, has_more, page:1, per_page:20}`, every item has `listing_id, listing_title, listing_url, status, proof_text, admin_notes, created_at, updated_at`; NOCLAIMS `200 {claims:[], total:0}` and never CLAIMER's rows (no user id parameter); `?user_id=<other>` and `?mine=1` as a member `403 listora_forbidden`.
- **Read-back**: `total` equals SQL TOTAL.

### 6. No member write routes
- **Action**: as CLAIMER `DELETE /listora/v1/claims/<id>` and `PUT /claims/<id>` (status).
- **Expect**: `403 listora_forbidden` for both (admin only). `POST /claims` is the only member write and is rate limited (`claim_submit`, daily window).
- **Read-back**: the row is unchanged.

### 7. Feature switch
- **Action**: Claims feature OFF.
- **Expect**: tab and sidebar item not rendered; `GET /dashboard/claims` `403 listora_claims_disabled` "Claiming is not enabled."; admin Claims management stays reachable so a backlog can be processed. Not verified live.

### 8. 390 viewport and dark
- **Expect**: no horizontal scroll; View/Edit Listing buttons >= 40px; rows stack. Not verified live in dark mode.

### 9. Developer filter
- **Expect**: `wb_listora_rest_prepare_claim` is applied to claim payloads (submit, update, list builders in `includes/rest/class-claims-controller.php`); a filter adding `custom_badge` appears. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| CLAIMER (member) | own claims only (steps 1-3, 5) |
| NOCLAIMS ({other}) | empty state, `{claims:[],total:0}` |
| {guest} | `401 listora_unauthorized` |
| {editor} / {admin} (control) | the same route returns only THEIR own claims; the site-wide list is `GET /claims`, admin-only (admin `200`, member `403`) |

## Pass criteria
1. A member sees only their own claims; others and logged-out callers cannot read them.
2. Chips read "Under review" / "Approved" / "Not approved" with the matching action.
3. Pagination works and an out-of-range page shows the last page.
4. The empty state is conditional and links to the Directory; no member write routes exist.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Other users' claims visible | list not scoped | `includes/rest/class-dashboard-controller.php:517`, `includes/core/class-dashboard-data.php:290` |
| Wrong chip text | label map | `templates/blocks/user-dashboard/tab-claims.php:26` |
| Page 999 empty | clamp removed | `blocks/user-dashboard/render.php:354` |
| Empty card missing | template condition | `tab-claims.php:47` |
| Tab shown with feature off | availability gate | `includes/class-template-helpers.php:400` |

## Not verified
Browser rendering at 390 and dark; Claims feature OFF (source only); the `wb_listora_rest_prepare_claim` filter.

## Cleanup
None (read-only).
