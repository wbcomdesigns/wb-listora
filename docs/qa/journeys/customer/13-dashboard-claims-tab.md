---
journey: dashboard-claims-tab
plugin: wb-listora
priority: normal
roles: [member]
covers: [user-dashboard, claims-tab, claim-status-display, claims-pagination, claims-rest-scope]
prerequisites:
  - "A member with claims in all states: QA site author1 (about 35 claims, approved + pending + rejected). Do not seed; read-only journey."
  - "A member with no claims: subscriber2"
  - "Claims feature ON (Listora > Settings > Features)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Member sees only their own claim history on the "My Claims" tab

The tab is read-only. A member cannot withdraw a claim from the dashboard (there is no Withdraw button and no member DELETE route); only an administrator resolves or deletes a claim.

Verified via: curl/DOM as author1 and subscriber2 plus REST (rest_do_request per role). Not re-driven in a browser; see "Not verified".

## Setup

- Site: `$SITE_URL`; dashboard page `/my-listings/` on the QA site (Listora > Settings > General > Dashboard page).
- `?autologin=author1` (claims) and `?autologin=subscriber2` (none).
- Tab URL: `$DASH/?tab=claims`; extra pages: `$DASH/?tab=claims&claims_page=N`. The sidebar tab is `#dash-tab-claims`, label "My Claims"; the panel is `#dash-panel-claims`.

## Steps

### 1. Open the tab as a member with claims
- **Action**: `$DASH/?tab=claims` as author1.
- **Expect**: `#dash-tab-claims` has `aria-selected="true"`; `#dash-panel-claims` has no `hidden` attribute; a `ul.listora-dashboard__claims-list` with up to 20 `li.listora-dashboard__claim-row` (20 per page).

### 2. Row content and status labels
- **Expect per row**: a title link (`.listora-dashboard__claim-title a`) to the listing, a status chip `.listora-dashboard__status`, "Submitted <date>", and a note:
  - pending: chip "Under review", note "We will email you as soon as the review is complete.", action "View Listing"
  - approved: chip "Approved", note "You now own this listing. Open My Listings to edit it.", action "Edit Listing" (to `<submit page>?edit=<id>`)
  - rejected: chip "Not approved", action "View Listing", and "Reviewer notes:" when the admin left notes
- **Not present** (older journeys asserted them): "Pending" label, "Withdraw", "Manage Listing".

### 3. Pagination at 2000+ listings scale
- **Action**: author1 has 35 claims. Open `?tab=claims`, then `?tab=claims&claims_page=2`, then `?tab=claims&claims_page=999`.
- **Expect**: `nav.listora-pagination[aria-label="Claims pagination"]` showing "Page 1 of 2" with Previous disabled (`aria-disabled="true"`) and a Next link (`rel="next"`); page 2 shows the remaining 15; page 999 shows the LAST page (not an empty list). The site has 2,809 listings; the query is a LIMIT/OFFSET on `listora_claims.user_id`, so the listing count does not change the cost.

### 4. Empty state, with a positive control
- **Action**: `$DASH/?tab=claims` as subscriber2.
- **Expect**: card `.listora-empty` with title "No claim requests yet", text `Claim an existing business listing to manage it. Click "Claim this listing" on any unowned entry.` and a primary button "Browse the directory" linking to the Directory page.
- **POSITIVE CONTROL**: step 1 (author1) shows rows with no empty card, so the empty card is conditional on zero claims, not always rendered.

### 5. Role ladder (REST, `GET /listora/v1/dashboard/claims`)
- **Owner (author1)**: `200 {claims:[...], total:35, pages:2, has_more:true, page:1, per_page:20}`; every item has `listing_id, listing_title, listing_url, status, proof_text, admin_notes, created_at, updated_at`.
- **Another member (subscriber2)**: `200 {claims:[], total:0}`; never author1's rows (the route has no user id parameter).
- **Logged out**: `401 listora_unauthorized`.
- **Moderator/editor (qa_editor) and admin (varundubey), control**: the same route returns only THEIR own claims (qa_editor 0; admin 2 pending); the site-wide list is `GET /listora/v1/claims`, admin-only (admin 200, author1 403).
- **Cross-user probes**: `GET /listora/v1/claims?user_id=999` and `?mine=1` as a member return `403 listora_forbidden`.

### 6. No member write routes
- **Action**: as author1, `DELETE /listora/v1/claims/<id>` and `PUT /listora/v1/claims/<id>` (status).
- **Expect**: `403 listora_forbidden` for both (admin only; observed as author1). `POST /listora/v1/claims` is the only member write and is rate limited (`claim_submit`, daily window).

### 7. Feature switch (source-verified)
- With the Claims feature OFF the tab and sidebar item are not rendered and `GET /dashboard/claims` returns `403 listora_claims_disabled` ("Claiming is not enabled."). Admin claims management stays reachable so a backlog can still be processed. Not flipped in this refresh.

### 8. 390 viewport
- **Expect**: no horizontal page scroll; "View Listing" / "Edit Listing" buttons >= 40px tall; rows stack.

### 9. Developer filter
- `wb_listora_rest_prepare_claim` is applied to claim payloads in `includes/rest/class-claims-controller.php` (submit, update, and list item builders). A filter adding `custom_badge` appears in the claim payload that controller returns.

## Pass criteria

1. A member sees only their own claims; other members and logged-out callers cannot read them.
2. Status chips read "Under review" / "Approved" / "Not approved" with the matching action button.
3. Pagination works and an out-of-range page shows the last page.
4. The empty state is conditional and links to the Directory.

## Fail diagnostics

| Symptom | Likely cause | File:line |
|---|---|---|
| Other users' claims visible | list not scoped to caller | `includes/rest/class-dashboard-controller.php:517` (`get_my_claims`), `includes/core/class-dashboard-data.php:290` (`list_claims`) |
| Wrong chip text | label map | `templates/blocks/user-dashboard/tab-claims.php:26` |
| Page 999 empty | clamp removed | `blocks/user-dashboard/render.php:354` |
| Empty card missing | template condition | `templates/blocks/user-dashboard/tab-claims.php:47` |
| Tab shown with feature off | availability gate | `includes/class-template-helpers.php:400` |

## Not verified

- Browser rendering at 390 and in dark mode (checked markup only).
- Claims feature OFF behaviour (source only).
- The `wb_listora_rest_prepare_claim` filter was not exercised live.
