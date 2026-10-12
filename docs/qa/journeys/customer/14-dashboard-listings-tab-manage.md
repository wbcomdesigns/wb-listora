---
journey: dashboard-listings-tab-manage
plugin: wb-listora
priority: high
roles: [subscriber, contributor]
covers: [user-dashboard, listing-lifecycle, deactivate-reactivate, delete-listing, owner-feature, owner-open-own-listing, bc-10350931568]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member who owns a published listing: create fixtures named QA-J1-<n> through the submission form or `wp post create` (post_type listora_listing, post_author = the member) and delete them afterwards"
  - "A second member (OTHER), an editor (qa_editor), the admin (varundubey)"
estimated_runtime_minutes: 7
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Owner manages their listings from the dashboard Listings tab

What an owner can actually do from the Listings tab: filter, edit, deactivate, reactivate, renew. Two things older versions of this journey promised are NOT in the tab: there is no Feature button there (Feature lives on the listing page) and no Delete button (deleting is the REST route only, used by the app).

Verified via: curl/DOM as the owner, OTHER, anonymous, editor and admin; REST per role; deactivate then confirm then reactivate driven in the shared Playwright browser (observed rows flip Published, Deactivated, Published). 390 viewport: no horizontal scroll and 40px buttons measured there.

## Setup

- Site `$SITE_URL`, dashboard `$DASH` (QA site `/my-listings/`). Tab URL `$DASH/?tab=listings`.
- Fixtures (owner = subscriber2 on the QA site): five listings titled `QA-J1 live`, `QA-J1 deactivated`, `QA-J1 pending`, `QA-J1 rejected`, `QA-J1 draft` in those statuses (`publish`, `listora_deactivated`, `pending`, `listora_rejected`, `draft`), type `business`.

## Steps

### 1. The tab lists the owner's listings with status and row actions
- **Action**: `$DASH/?tab=listings` as the owner.
- **Expect**: one `[data-listora-listing-id="<id>"]` row per listing, `data-listora-state` `active` (published) or `inactive`; status text "Published", "Deactivated", "Pending Review", "Rejected", "Draft". Per-row buttons: published = Services*, Edit, View, More; deactivated = Services*, Edit, More; pending / rejected / draft = Services*, Edit only (no View, no More). (*Services only when the Services feature is on.)
- **Expect (More menu)**: published row: "Deactivate"; deactivated row: "Reactivate"; an expired row with renewal on: "Renew" and a "Renew Now" button.

### 2. Deactivate, with confirmation
- **Action**: on the published row: More, then Deactivate.
- **Expect**: a modal `.listora-confirm-overlay` titled "Deactivate listing?" with text "Deactivate this listing? It will be hidden from the public directory until you reactivate it." and buttons Cancel / Deactivate. Cancel leaves the row untouched (NEGATIVE CONTROL). Confirm sends `POST /listora/v1/listings/<id>/deactivate`, the page reloads, the row reads "Deactivated" with `data-listora-state="inactive"`.
- **REST**: `200 {deactivated:true, already_deactivated:false, message:"Listing deactivated successfully."}`; repeating it returns `200` with `already_deactivated:true`; a pending listing returns `409 listora_invalid_state` ("Only a published listing can be deactivated.").

### 3. Reactivate
- **Action**: More, then Reactivate, confirm "Reactivate listing?" (buttons Cancel / Reactivate).
- **Expect**: row back to "Published"; `POST /listings/<id>/reactivate` `200 {reactivated:true, already_active:false}`. POSITIVE CONTROL for step 2: the public permalink is 404 for a visitor while deactivated and 200 after reactivation.

### 4. Feature (from the listing page, not the dashboard)
- **Action**: owner opens the published listing; the header shows a button `.listora-detail__feature-btn` "Feature for N credits · 30 days" (or "Feature This Listing (N credits)" for permanent). Click it.
- **Expect**: toast with the server message (for example "Your listing is now featured until <date>."), page reloads, the button is replaced by the status "Featured until <date>". REST: `POST /listings/<id>/feature` `200 {featured:true, days:30, featured_until}`; a second call `409 listora_already_featured`. With `featured_credit_cost` > 0 and too few credits: `402 listora_insufficient_credits` (source-verified; the QA site's cost is 0).
- **Role ladder**: OTHER and logged out never see the button; `POST /listings/<id>/feature` as OTHER returns `403 listora_forbidden`, logged out `401`; editor and admin (control) are allowed (observed 200).

### 5. Delete (REST only)
- **Action**: `DELETE /listora/v1/listings/<id>` as the owner.
- **Expect**: `200 {deleted:true, message:"Listing deleted successfully."}`; the post is in the trash (`wp post get <id> --field=post_status` = `trash`), so the row leaves the tab and the public page is 404. Logged out `401 listora_unauthorized`; OTHER `403 listora_forbidden` ("You do not have permission to delete this listing."); admin allowed.
- **Note**: the dashboard has no delete control; the "listoraConfirm" delete modal named in older docs is not in the tab.

### 6. Owner can open own non-public listings (2.0.0)
- **Action**: request the permalink of the pending, rejected and deactivated fixtures as owner, OTHER, anonymous, editor, admin.
- **Expect**: owner 200 (Subscriber role too), OTHER 404, anonymous 404, editor 200, admin 200 (observed 2026-10-12 for deactivated, pending and rejected).

### 7. Ownership gate on the lifecycle routes
- **Action**: as OTHER, `POST /listings/<id>/deactivate` and `/reactivate`.
- **Expect**: `403 listora_forbidden`; logged out `401 listora_unauthorized`; editor and admin are allowed (`edit_others_posts`), shown as the moderator/admin control.

### 8. Big member (2000+ rows)
- **Action**: as the admin (owns 2,632 listings on the QA site, 2,533 of them drafts): open `$DASH/?tab=listings`, then `&listings_page=2`, `&listings_page=99999`, `&listings_status=publish`, `&listings_search=park`.
- **Expect**: 20 rows per page, "Page 1 of 132" in `nav[aria-label="Listings pagination"]`; page 99999 shows the last page ("Page 132 of 132", 12 rows); the Status filter (`#listora-listings-status`) narrows to "Page 1 of 4"; search (`#listora-listings-search`) returns matching rows; each request answers in well under a second (measured about 0.18s).

### 9. 390 viewport
- **Expect**: no horizontal page scroll; Edit and More buttons >= 40px tall (measured 40px); the More menu opens within the viewport.

### 10. Reactivate (and Deactivate) controls take the theme's colours and stay readable (card 10350931568)
- **Action**: as owner with the deactivated fixture, activate each of BuddyX Pro (light, dark), Reign and Twenty Twenty-Five in turn; at 1440 and 390 open More on the row and read the "Reactivate" item, then open the "Reactivate listing?" modal and read its confirm button. For each element read `getComputedStyle` `backgroundColor`, `color`, `borderColor`.
- **Expect**: the confirm button's background equals the resolved value of the plugin's primary token (`getComputedStyle(document.documentElement).getPropertyValue('--listora-color-primary')` or the theme bridge value) for that theme, not a hard-coded colour; text/background contrast >= 4.5:1 (compute from the two colours) on every theme in light and dark; hover and focus-visible states keep >= 4.5:1.
- **Read-back**: assert the computed pair, not a screenshot; negative control: the secondary Cancel button resolves to a different background than the primary Reactivate button.
- **fails_on**: 4b0fd91f (parent of 939a5e6c). Not verified live.

## Pass criteria

1. Row actions match the status (table in step 1); Deactivate/Reactivate use the design-system confirm modal and round-trip.
2. Feature is offered only to the owner on the listing page and cannot double-charge (409).
3. Delete is owner/cap-holder only and soft (trash).
4. Non-owners get 403 (401 logged out) on every lifecycle route and a 404 on non-public permalinks.
5. The tab paginates and filters at 2,600+ rows.

## Fail diagnostics

| Symptom | Likely cause | File:line |
|---|---|---|
| Row shows wrong actions | status gate in the template | `templates/blocks/user-dashboard/tab-listings.php:495` |
| Deactivate does nothing | action or confirm | `src/interactivity/store.js:1577` (`deactivateListing`), `:1655` (`reactivateListing`) |
| Deactivate allowed for non-owner | owner gate | `includes/rest/class-listings-controller.php:1140` (`require_listing_owner`) |
| Wrong state error | state machine | `includes/rest/class-listings-controller.php:1297`, `:1517` |
| Feature charges twice | credit lock | `includes/rest/class-listings-controller.php:1603`, `:1632` |
| Feature button for non-owner | template condition | `blocks/listing-detail/render.php:600` |
| Delete hard-deletes | override lost | `includes/rest/class-listings-controller.php:1193`, `:1212` |
| Page 99999 empty | clamp | `blocks/user-dashboard/render.php:261` |

## Not verified

- Feature with credits > 0 (QA site charge is 0) and the Renew modal (needs an expired listing with renewal on).
- Dark mode and the unknown-theme screenshots.
