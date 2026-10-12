---
journey: dashboard-listings-tab-manage
plugin: wb-listora
priority: high
roles: [owner, other, editor, admin, guest]
covers: [user-dashboard, listing-lifecycle, deactivate-reactivate, delete-listing, owner-feature, owner-open-own-listing, row-actions-by-status, no-empty-more-menu, bc-10350931568, covers_card 10332121171]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "{owner} owns listings in every status (fixtures below); {other}, {editor}, {admin} exist"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, Reign, one unbridged theme]
site_profile: required
supersedes:
  - ora:regression/dashboard-no-empty-more-menu.md
fails_on:
  - 4b0fd91f
---

# Owner manages their listings from the dashboard Listings tab

## Why
This is what an owner can actually do from the Listings tab: filter, edit, deactivate, reactivate, renew. A row showing a More menu with nothing in it reads as a button that does nothing. Feature lives on the listing page (no Feature button in the tab) and Delete is REST only (used by the app).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `$DASH` = `wp eval 'echo wb_listora_get_dashboard_url();'`; tab URL `$DASH/?tab=listings`.
- Fixtures owned by {owner}, type `{type}`: `QA-J1 live` (`publish`), `QA-J1 deactivated` (`listora_deactivated`), `QA-J1 pending` (`pending`), `QA-J1 rejected` (`listora_rejected`), `QA-J1 draft` (`draft`), `QA-J1 expired` (`listora_expired`, renewal feature ON), `QA-J1 awaiting` (awaiting credits, if Pro credits is on). Create with `wp post create`; delete afterwards.

## Steps

### 1. The tab lists the owner's listings with status and row actions
- **Action**: open `$DASH/?tab=listings` as {owner}.
- **Expect**: one `[data-listora-listing-id="<id>"]` row per listing; `data-listora-state` `active` (published) or `inactive`; status text "Published", "Deactivated", "Pending Review", "Rejected", "Draft". Buttons: published = Services*, Edit, View, More; deactivated = Services*, Edit, More; pending / rejected / draft / awaiting credits = Services*, Edit only (no View, no More, no `.listora-dashboard__menu-wrap`). *Services only when the type has services on (see `dashboard-services-modal`).
- **Read-back**: the More-menu renders only when it holds an item: `$listora_can_renew || post_status in ( publish, listora_deactivated )` (`templates/blocks/user-dashboard/tab-listings.php:490-494`).

### 2. More menu contents (positive control for step 1)
- **Action**: open More on a published, a deactivated and an expired row; also an expiring published row.
- **Expect**: published: one item **Deactivate** (`.listora-dashboard__menu-item--danger`, `actions.deactivateListing`), View shown; deactivated: **Reactivate**, no View (not public); expired: primary **Renew Now** AND More holding **Renew**; expiring published: Renew Now plus More with Renew and Deactivate. A page with no More anywhere would also satisfy step 1, so this step is the control. Clicking More removes `hidden` from `.listora-dashboard__menu-dropdown`; Esc or an outside click closes it.
- **Read-back**: count `.listora-dashboard__menu-wrap` per row equals rows with at least one menu item.

### 3. Deactivate, with confirmation
- **Action**: published row > More > Deactivate.
- **Expect**: modal `.listora-confirm-overlay` "Deactivate listing?" / "Deactivate this listing? It will be hidden from the public directory until you reactivate it." with Cancel / Deactivate. Cancel leaves the row untouched (negative control). Confirm sends `POST /listora/v1/listings/<id>/deactivate`, the page reloads, the row reads "Deactivated" with `data-listora-state="inactive"`.
- **Read-back**: REST `200 {deactivated:true, already_deactivated:false, message:"Listing deactivated successfully."}`; repeating returns `already_deactivated:true`; a pending listing returns `409 listora_invalid_state` ("Only a published listing can be deactivated."); `wp post get <id> --field=post_status` = `listora_deactivated`.

### 4. Reactivate
- **Action**: More > Reactivate, confirm "Reactivate listing?" (Cancel / Reactivate).
- **Expect**: row back to "Published"; `POST /listings/<id>/reactivate` `200 {reactivated:true, already_active:false}`.
- **Read-back**: positive control for step 3: the public permalink is 404 for a visitor while deactivated and 200 after.

### 5. Feature (from the listing page, not the dashboard)
- **Action**: {owner} opens the published listing; click `.listora-detail__feature-btn` ("Feature for N credits · 30 days" or "Feature This Listing (N credits)").
- **Expect**: toast with the server message (for example "Your listing is now featured until <date>."), reload, button replaced by "Featured until <date>".
- **Read-back**: `POST /listings/<id>/feature` `200 {featured:true, days:30, featured_until}`; a second call `409 listora_already_featured`; with `featured_credit_cost` > 0 and too few credits `402 listora_insufficient_credits` (source-verified). Role: {other} and {guest} never see the button; REST as {other} `403 listora_forbidden`, {guest} `401`; {editor} and {admin} allowed.

### 6. Delete (REST only)
- **Action**: `DELETE /listora/v1/listings/<id>` as {owner}.
- **Expect**: `200 {deleted:true, message:"Listing deleted successfully."}`; the row leaves the tab, public page 404.
- **Read-back**: `wp post get <id> --field=post_status` = `trash`. {guest} `401 listora_unauthorized`; {other} `403 listora_forbidden` ("You do not have permission to delete this listing."); {admin} allowed. The dashboard has no delete control.

### 7. Owner opens own non-public listings (2.0.0)
- **Action**: request the permalink of the pending, rejected and deactivated fixtures as {owner}, {other}, {guest}, {editor}, {admin}.
- **Expect**: {owner} 200 (Subscriber role too), {other} 404, {guest} 404, {editor} 200, {admin} 200.
- **Read-back**: HTTP status per rung.

### 8. Ownership gate on lifecycle routes
- **Action**: as {other}, `POST /listings/<id>/deactivate` and `/reactivate`.
- **Expect**: `403 listora_forbidden`; {guest} `401 listora_unauthorized`; {editor} and {admin} allowed (`edit_others_posts`, shown as the control).
- **Read-back**: post status unchanged after the 403s.

### 9. Big member (2000+ rows)
- **Action**: as the member owning the large seed (seed N via the fixtures script if < 2,000): open `$DASH/?tab=listings`, then `&listings_page=2`, `&listings_page=99999`, `&listings_status=publish`, `&listings_search=<term>`.
- **Expect**: 20 rows per page, "Page 1 of N" in `nav[aria-label="Listings pagination"]`; page 99999 clamps to the last page; the Status filter (`#listora-listings-status`) narrows the count; search (`#listora-listings-search`) returns matching rows; each request answers well under a second.
- **Read-back**: N equals `ceil( wp post list --author=<id> --format=count / 20 )`.

### 10. 390 viewport
- **Action**: repeat steps 1-2 at 390x844.
- **Expect**: no horizontal scroll; actions wrap under the row title; Edit and More >= 40px tall; the More menu opens within the viewport.
- **Read-back**: `scrollWidth <= innerWidth`.

### 11. Reactivate and Deactivate controls take the theme's colours (card 10350931568)
- **Action**: as {owner} with the deactivated fixture activate each of BuddyX Pro (light, dark), Reign and Twenty Twenty-Five; at 1440 and 390 open More, read "Reactivate", open the "Reactivate listing?" modal and read its confirm button (`backgroundColor`, `color`, `borderColor`).
- **Expect**: confirm background equals the resolved `--listora-color-primary` (or theme bridge value) for that theme, not a hard-coded colour; contrast >= 4.5:1 in light and dark; hover and focus-visible keep >= 4.5:1.
- **Read-back**: assert the computed pair, not a screenshot; negative control: Cancel resolves to a different background than the primary button.
- **fails_on**: 4b0fd91f (parent of 939a5e6c). Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | steps 1-11 |
| {other} | only their own rows and menus; 403 on lifecycle routes |
| {guest} | login prompt; 401 on routes |
| {editor}, {admin} | allowed on lifecycle routes (moderator control); {admin} draft row has Edit but no More |

## Pass criteria
1. Row actions match status (step 1 table); a More menu appears only with at least one item.
2. Deactivate/Reactivate use the design-system confirm modal and round-trip.
3. Feature is owner-only and cannot double-charge (409); Delete is cap-holder only and soft (trash).
4. Non-owners get 403 (401 logged out) on every lifecycle route and 404 on non-public permalinks.
5. The tab paginates and filters at 2,600+ rows.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Wrong row actions / empty More | status gate | `templates/blocks/user-dashboard/tab-listings.php:490-495` |
| Missing Renew on expired | `$listora_can_renew` false | `tab-listings.php:158-176` |
| Deactivate does nothing | action or confirm | `src/interactivity/store.js:1577` (`deactivateListing`), `:1655` (`reactivateListing`) |
| Allowed for non-owner | owner gate | `includes/rest/class-listings-controller.php:1140` (`require_listing_owner`) |
| Wrong state error | state machine | `class-listings-controller.php:1297`, `:1517` |
| Feature charges twice | credit lock | `class-listings-controller.php:1603`, `:1632` |
| Feature button for non-owner | template condition | `blocks/listing-detail/render.php:600` |
| Delete hard-deletes | override lost | `class-listings-controller.php:1193`, `:1212` |
| Page 99999 empty | clamp | `blocks/user-dashboard/render.php:261` |

## Not verified
Feature with credits > 0 and the Renew modal (need credit cost and an expired listing with renewal on); More dropdown click, Esc and 390 layout were read from served HTML only; dark mode and unbridged-theme colours.

## Cleanup
Delete all `QA-J1` fixtures; restore any theme switched in step 11.
