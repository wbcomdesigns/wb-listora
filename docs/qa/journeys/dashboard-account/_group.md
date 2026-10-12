---
group: dashboard-account
plugin: wb-listora
scope: The logged-in member dashboard (User Dashboard block) with all its tabs, row actions, counters and deep links, plus the member's account lifecycle (profile, deactivate, delete) and the dashboard REST feeds.
journeys: 14
site_profile: required
---
# Dashboard and account: what an agent checks here

## Scope
Covers the page that holds the User Dashboard block (`{dashboard}`): sidebar rail, header, Overview, My Listings (rows, filters, pager, More menu, Renew modal, Services dialog, inline add/edit), Reviews, Favorites, Claims, Leads, Profile (incl. Blocked Members), the unknown-tab fallback, the `?tab=` deep links, document title and single heading, and the account-level REST routes (`/account/deactivate`, `/account/reactivate`, `DELETE /account`, `/dashboard/*`).

Does NOT cover: the Credits tab body, pack buttons, ledger and checkout (credits-checkout owns it; this group only checks the Credits nav badge, the Overview low-balance step and the tab's presence); the submission form that the inline add/edit view embeds (submission); renewal windows, expiry cron and the plan rules behind "Expiring soon" (plans-expiry-renewal); review writing, voting and the public review list (reviews); claim submission and approval (claims-verification); lead capture on the listing page (contact-leads); who may do what by role and rate limits (access-security); Pro tab bodies Analytics, Saved Searches, My Needs, My Responses (their own groups in the Pro repo; this group only checks they appear in the rail, title and fallback).

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| 1 | Logged-out prompt ("My Dashboard", Log In button) | `$SITE_URL{dashboard}` as {guest} | guest | `blocks/user-dashboard/render.php` (top, `--logged-out`) |
| 2 | Overview | `$SITE_URL{dashboard}` or `?tab=overview` | owner, other, editor, admin | `blocks/user-dashboard/render.php` (`#dash-panel-overview`) |
| 3 | My Listings list | `?tab=listings` (+ `listings_page`, `listings_search`, `listings_status`, `listings_filter`) | owner, other, editor, admin | `templates/blocks/user-dashboard/tab-listings.php` |
| 4 | My Listings inline add / edit | `?tab=listings&action=add` and `?tab=listings&action=edit&id={listing id}` | owner (own listing), admin | `tab-listings.php` (inline form) embedding the submission block |
| 5 | Reviews (written + received) | `?tab=reviews` (+ `reviews_page`, `received_page`) | owner, other, editor, admin | `tab-reviews.php` |
| 6 | Favorites | `?tab=favorites` (+ `favorites_page`) | owner, other, editor, admin | `tab-favorites.php` |
| 7 | Claims | `?tab=claims` (+ `claims_page`) | claimant, owner, admin | `tab-claims.php` |
| 8 | Leads | `?tab=leads` (+ `lead_listing`) | owner with a listing or lead | `tab-leads.php`, `includes/core/class-leads-dashboard-tab.php` |
| 9 | Profile + Blocked Members | `?tab=profile` | owner, other, editor, admin | `tab-profile.php` |
| 10 | Credits (rail badge and low-balance step only) | `?tab=credits` | member with a credit record | `tab-credits.php` (body owned by credits-checkout) |
| 11 | Unknown or switched-off tab fallback | `?tab=nope`, `?tab={tab of a feature that is off}` | any logged-in member | `render.php` (`.listora-dashboard__notice`) |
| 12 | Pinned-type dashboard | a page whose block has `listingType={type}` | owner | `render.php`, `block.json` |
| 13 | Row overlays: More menu, Renew modal, Services dialog, deactivate / reactivate confirm, toast stack | click from surface 3 | owner | `tab-listings.php`, `src/blocks/user-dashboard/view.js` |
| 14 | Anonymous login modal (lives on the listing page) | click Save or Claim on `$SITE_URL/listing/{listing}/` as {guest} | guest | `blocks/listing-detail/render.php` (misfiled here, see Not covered) |
| 15 | REST feeds and account routes | `/wp-json/listora/v1/dashboard/{stats,listings,reviews,claims,profile,notifications,notifications/read}` and `/account/{deactivate,reactivate,blocks}`, `DELETE /account` | owner (own data), admin | `includes/rest/class-dashboard-controller.php`, `class-account-controller.php` |

## Components on each surface
Shell (surfaces 2-11): `div.listora-dashboard` (grid: rail + main) > `nav.listora-dashboard__sidebar` (`role=tablist`): `.listora-dashboard__sidebar-header` (`__user-name`, `__user-email`); three groups `.listora-dashboard__nav-group` titled Listings / Marketplace / Account (a group with no item is not rendered); each item `a.listora-dashboard__nav-item#dash-tab-{id}` with icon, label and optional `.listora-dashboard__nav-count` (accent variant for pending claims). Items in order: Overview, My Listings (total), Reviews (count), My Claims (pending, accent), then Favorites (count) under Marketplace, then Credits (balance) and Profile under Account; Pro and Leads add items through `wb_listora_dashboard_nav_group`. Main: `.listora-dashboard__header` > `.listora-dashboard__heading-group` (`h1.listora-dashboard__title[data-listora-dash-heading]`, `p.listora-dashboard__greeting` "Hello, {name}!") and `.listora-dashboard__header-actions` (Add Listing button, Pro "Post a Need"; the wrapper is absent when both are off); then `p.listora-dashboard__notice[role=status]` only for an unknown tab; then the panels, one visible, the rest `hidden`, each `div.listora-dashboard__panel[role=tabpanel]#dash-panel-{id}`; `div.listora-dashboard__toast-stack[aria-live=polite]` at the end of the listings panel.

2 Overview: "Next steps" `section.listora-dashboard__todos` (list of `a.listora-dashboard__todo-link`, only when something needs action: paused, awaiting verification, pending review, expired, pending claims, low credits); `div.listora-dashboard__stats[role=group]` with up to five `a.listora-dashboard__stat` tiles (icon `__stat-icon--active|pending|reviews|saved|claims`, `__stat-value`, `__stat-label`): Active, Pending, Reviews, Saved, Claims ("Claims (N pending)"); listing-limit card `.listora-dashboard__limit[role=region]` with modifiers `--unlimited|--exhausted|--low`, metrics Used / Limit / Remaining, message, and Buy credits / Add listing CTAs.

3 My Listings: empty state "No listings yet" (CTA only if submissions can be offered); filter form `form.listora-dashboard__filters` (search input `#listora-listings-search`, Status select `#listora-listings-status`, Expiry select `#listora-renewal-filter` only with renewal on, Apply, Clear when active); `.listora-dashboard__listing-list` of `.listora-dashboard__listing-row[data-listora-listing-id][data-listora-state]`: thumb or type placeholder, `h3.__listing-title` link, meta (`__status` pill, `__type-tag`, `__listing-expires`, expiring pill, `__featured-tag`, `__services-count`, `__view-count`), conditional notes (`__verify-note` with Resend verification email, `__paused-note` with balance, Short by, Activate now / Buy credits / "Why was this paused?" details), actions (`__renew-btn` Renew Now, Services, Edit, View, More menu `__menu-wrap` > `__menu-dropdown` with Renew / Reactivate / Deactivate); filtered-empty state `[data-listora-filter-empty]`; `nav.listora-pagination.listora-dashboard__pagination` "Listings pagination"; Renew modal `[data-listora-renew-modal]` (listing name, Plan, Cost, Duration, Your balance, error live region, Cancel, Buy more credits, Confirm renewal); Services dialog `.listora-dashboard__services-panel[role=dialog]` per listing (Add Service form with photo, name, description, price, price type, duration, categories; service rows with drag handle, edit, delete).

4 Inline add/edit: `.listora-dashboard__inline-form` with heading "Add New Listing" or "Edit Listing", `Back to listings` button, then the submission form.

5 Reviews: `h3` "Reviews I've Written" + `.listora-dashboard__review-list` rows (listing link, date, status pill, 30-word excerpt); `h3` "Reviews on My Listings" rows (reviewer meta, listing link, date, excerpt, Reply / Edit reply trigger, `blockquote.__owner-reply`, `form.__reply-form` with textarea, error `role=alert`, Submit reply / Submitting…, Cancel); two independent pagers (`reviews_page`, `received_page`); empty state when both are empty.

6 Favorites: empty state "No saved listings" (CTA Browse the directory); `.listora-dashboard__favorites-grid.listora-grid` of listing cards (2 columns); pager `favorites_page`.

7 Claims: empty state "No claim requests yet"; `ul.listora-dashboard__claims-list` of `li.__claim-row` (title link, status pill, "Submitted {date}", reviewer notes or pending/approved note, Edit Listing and View Listing actions); pager `claims_page`.

8 Leads: filter form (Listing select `#listora-lead-listing`, Apply, Clear); `ul.__leads-list` rows (name, meta, date, message, mailto Reply and tel Call actions); empty state; pager.

9 Profile: `form.listora-dashboard__profile-form`: avatar block with "Change photo" link and source hint; grid of Display Name `#listora-display-name`, Email `#listora-email`, First name, Last name, Phone `#listora-phone` (private hint), Website, Bio `#listora-bio`; Social Links section (only when platforms are configured); Email Notifications fieldsets of `.listora-toggle` checkboxes `notification_prefs[{event}]` (listing paused / resumed shown only with credits); Save Changes `button[name=listora_update_profile]`. Outside the form: `.listora-dashboard__blocked` "Blocked Members" with empty state or `ul.__blocked-list` (avatar, name, Unblock button).

11 Fallback: notice text `There is no "{tab}" section in your dashboard. Showing Overview instead.`; Overview panel, rail and heading all agree on Overview.

## States to force
| Surface | State | How |
|---|---|---|
| All | logged out | fresh session on `{dashboard}`; and `?tab=needs` while logged out |
| All | no-permission / other member | open `?tab=listings&action=edit&id={another member's listing}` as {other}; REST with {other} nonce on {owner}'s ids |
| Overview | N=0 (no listings, no favourites) | sign in as {other} on a clean profile or the member with nothing |
| Overview | next steps present | member with one pending, one expired, one paused (`listora_payment`), one `pending_verification` listing (fixtures script) |
| Overview | limit card unlimited / low / exhausted | change the plan limit so remaining is unlimited, 2, 0 |
| Overview | low credit balance | set balance below the low-balance threshold (Settings > Credits) |
| My Listings | one / many / 2000+ rows | fixtures script `seed`; large member from the scale seed |
| My Listings | each status pill | one listing per status: publish, pending, draft, `listora_expired`, `listora_rejected`, `listora_deactivated`, `pending_verification`, `listora_payment` |
| My Listings | filtered-empty | `?tab=listings&listings_filter=expiring` for a member with none |
| My Listings | renewal filter present / absent | feature `renewal` on / off (Settings > Features) |
| My Listings | out-of-range page | `listings_page=99999` |
| My Listings | long title | 120-character title and an unbroken 60-character word |
| Reviews / Favorites / Claims / Leads | empty / one / many / pager | seed rows; pager appears above 20 rows (`wb_listora_dashboard_per_page` filter lowers it for testing) |
| Reviews | reply open, reply error, reply saved | click Reply, submit empty, submit text |
| Profile | saved, validation error, email change staged, blocked list empty / long name | edit fields, `PUT /dashboard/profile` with bad input, add blocks via `POST /account/blocks` |
| Tabs | feature off | turn off favorites, reviews, claims, contact_form, or a Pro feature; open the deep link |
| Tabs | unknown id | `?tab=nope`, `?tab=notifications` |
| Pinned dashboard | other type, unknown slug | `listingType` on the block; `?listing_type=not-a-type` |
| Shell | RTL, 390, 820, dark | RTL locale; resize; theme dark toggle |
| REST | account deactivated | `POST /account/deactivate` as the throwaway member |
| Shell | slow or failed request | throttle then block `/dashboard/*`, `/listings/{id}/deactivate` |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| Features > Favorites (`wb_listora_features`) | on / off | Favorites rail item, panel, Saved tile, and `?tab=favorites` (falls back to Overview, no notice for a known-off tab) |
| Features > Reviews | on / off | Reviews rail item, panel, Reviews tile |
| Features > Claims | on / off | My Claims rail item, Claims tile, panel, pending-claim next step |
| Features > Contact form (leads) | on / off | Leads rail item and panel |
| Features > Renewal | on / off | Expiry filter select, Renew Now button, More > Renew, expiring pill |
| Features > Submission | on / off | Header Add Listing button, empty-state CTA text, inline add view |
| Features > Reverse listings (Pro) | on / off | My Needs, My Responses rail items and header "Post a Need" |
| Settings > Credits / Monetization | on / off, balance, threshold | Credits rail item and badge, Overview low-balance step, "Awaiting Credits" rows, Renew modal cost |
| Plan listing limit | unlimited / N | Overview limit card variant and message |
| `renewal_window_days` (Settings > Expiry) | integer | which rows carry the "Expires in N days" pill and the Expiring soon filter |
| Block attributes `showListings`, `showReviews`, `showFavorites`, `showProfile`, `showClaims`, `defaultTab`, `listingType` | per page | rail items, panels, first visible tab, pinned type scope |
| Filter `wb_listora_dashboard_per_page` | integer | page size of every pager |
| Filter `wb_listora_member_listing_statuses` | status list | which statuses count in the badge and list |
| Profile notification toggles | per event | `wb_listora_notification_prefs` user meta and the emails sent |

## Fixtures and setup
- `wp eval-file wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php seed` (idempotent; prints one JSON of ids; creates the {owner}, {other} personas and tagged listings, reviews, leads). Restore: `... 2.0-fixtures.php clean`.
- Auto-login: `$SITE_URL{dashboard}?autologin={owner}` (also `{other}`, `{editor}`, `{admin}`).
- Feature switches: `wp option get wb_listora_features` first, save the JSON to the scratch dir, change, and write it back at the end.
- Scale: seed 2000+ listings, favourites and claims for a large member through the fixtures script's scale option or `wp post generate`; delete them after.
- Status pills: `wp post update {id} --post_status=listora_expired` etc. on QA-prefixed listings only.
- Restore afterwards: feature JSON, `users_can_register`, site timezone, any mu-plugin filter, QA-prefixed rows, the throwaway member.

## Run order
1. dashboard-layout-and-navigation (browser, high): grid, rail, in-page tab switching, params dropped on switch.
2. dashboard-title-follows-tab (mixed, normal): document title and single heading per tab.
3. dashboard-overview-tab (browser, high): tiles, next steps, limit card, counters refresh.
4. dashboard-my-listings-statuses-counts-filters (mixed, high): statuses, badge vs rows, filters, paging, REST agreement.
5. dashboard-listings-tab-manage (browser, high): row actions, More menu, deactivate / reactivate / feature / delete.
6. dashboard-listing-type (mixed, normal): pinned-type dashboard.
7. dashboard-tabs-paginate-every-row (mixed, high): every pager reaches every row; Claims REST.
8. dashboard-tabs-follow-features (mixed, high): tabs exist only while their feature is on.
9. dashboard-profile-tab (browser, normal): save, sanitise, email staging, Blocked Members, Change photo.
10. dashboard-tabs-emit-no-db-errors (mixed, critical): every tab clean in the debug log, counters equal lists.
12. listing-deactivate-reactivate-flags (api, normal): `already_*` flags and invalid state.
13. dashboard-notifications-feed (api, normal): REST feed read and mark-read.
14. deactivate-and-reactivate-account (api, critical): account round trip.
15. delete-account (api, critical): permanent delete and erasure map (last: it destroys the throwaway member).

No Pro group folder exists for this group.

## Group-specific micro checks
- G-01 The Listings rail badge (`#dash-tab-listings .listora-dashboard__nav-count`) equals the My Listings pager total with no filter, and is NOT the Active tile value when a pending or expired listing exists.
- G-02 The Reviews rail badge equals the Reviews tile value, and equals written + received counts shown across the two lists' pagers.
- G-03 The Claims rail badge shows pending claims only (accent colour) and is absent at zero; the Claims tile label reads "Claims (N pending)" with the same N, and the tile's big number is the total.
- G-04 The Credits rail badge equals `.listora-dashboard__balance-number` on the Credits tab and uses the same decimals; a negative balance keeps its minus sign in both.
- G-05 `nav.listora-dashboard__sidebar` is `role=tablist` but also holds group wrappers and `<p>` titles: the accessibility tree lists only the `a[role=tab]` items as tabs, each `aria-controls` points at an existing `#dash-panel-{id}`, and exactly one has `aria-selected=true`.
- G-06 After a click on a rail item the URL gets `?tab={id}` and loses `action`, `id` and the hash; the visible `h1[data-listora-dash-heading]` text equals the item's `data-listora-tab-label` and `document.title` starts with the same label.
- G-07 Ctrl/Cmd-click and middle-click on a rail item open the `?tab=` URL in a new tab and leave the current panel unchanged (the click handler must step aside).
- G-08 Exactly one `.listora-dashboard__panel` lacks `hidden` at all times, and it is the panel named by `?tab=`; with `?tab=nope` it is Overview AND the notice is present once, without echoing markup from the id.
- G-09 The greeting "Hello, {name}!" is a `p`, not a heading; the page has one `h1` from the plugin (the theme's own entry-title is the only other, and both must not repeat the same words).
- G-10 `.listora-dashboard__header-actions` is absent from the DOM when Add Listing and Post a Need are both off (no empty flex box with a gap).
- G-11 Each listing row's pill text and `data-listora-state` agree: Published / Pending Review / Draft / Expired / Rejected / Deactivated / Pending Verification / Awaiting Credits; an expiring published row shows the expiring pill with "Expires today", "Expires tomorrow" or "Expires in N days" (never "in 1 days").
- G-12 More menu contents follow status: published has Deactivate (danger style) and, with renewal, Renew; deactivated has Reactivate; every other status has no More button at all (no empty menu).
- G-13 The "Awaiting Credits" row balance and "Short by" amounts equal the Credits tab balance and the plan cost, in credits (never a currency symbol), and "Activate now" appears only when balance >= cost.
- G-14 The view-count label is hidden for rows that are not live, muted (`--empty`) at zero, and uses a singular form at one view.
- G-15 Every pager is a `nav.listora-pagination` with a distinct accessible name per list (Listings, Reviews, Favorites, Claims pagination), absent at 20 rows or fewer, with Previous disabled on page 1 and Next disabled on the last page as non-link elements; page number preserves the other query args (`listings_status`, `listings_search`, `listings_filter`).
- G-16 Apply on the filter form keeps typed search text, status and expiry selected after reload; Clear appears only when a filter is active and resets to `?tab=listings`.
- G-17 The reply form: the textarea has a label (`.listora-sr-only`), empty submit shows `role=alert` text in place, Cancel returns focus to the Reply trigger, and a saved reply shows "Your reply:" with the trigger relabelled "Edit reply".
- G-18 Review excerpts end at 30 words with an ellipsis; the date uses the site date format; the status pill matches the review's real status (published / pending / rejected).
- G-19 The Profile "Change photo" link opens in a new tab with `rel=noopener`; the hint text names the real avatar source (Gravatar or BuddyPress) and the link points there.
- G-20 Notification toggles: the number of checkboxes checked after save equals the saved `notification_prefs`; paused / resumed toggles appear only when credits are on.
- G-21 Blocked Members: Unblock removes the row without reload, the empty state appears when the last row goes, and the avatar of a deleted user does not break (no broken image).
- G-22 Toast stack: a success toast fades after about 4 seconds, an error toast says what failed, stacked toasts do not overlap the sticky header at 390, and `aria-live=polite` is on the container (not on each toast).
- G-23 Favorites grid shows two columns at 1440 and one at 390; a favourite whose listing was trashed is skipped, and the pager total matches the visible count.
- G-24 Leads rows: the mailto Reply link has the listing title in the subject; Call link strips spaces and punctuation but keeps "+"; a lead without a phone shows no Call button.
- G-25 Dark mode: status pills, accent nav badges, the limit card variants and toast variants keep 4.5:1 contrast; no pill background is a fixed light hex.

## Known defects (expected-fail)
- dashboard-profile-tab, step 5: the web form has no current-password field, so an email change returns `403 listora_password_required` with nothing in the form to answer it.
- dashboard-tabs-follow-features, step 6: REST routes of a switched-off feature (`/dashboard/needs`, `/dashboard/responses`) still answer; only the pages are gated (owner decision pending).
- delete-account, step 10a: the leads eraser id is not run by account deletion; the member's sent leads survive with name, email, phone and message.

## Not covered yet
- Pro tab bodies (Analytics, Saved Searches, My Needs, My Responses) have no dashboard-account journey; only their rail items, titles and fallback are checked.
- The Leads tab (`tab-leads.php`) has no journey: filter by listing, pager, mailto and tel links, empty state, and who sees the tab.
- The Services dialog on a listing row (add, edit, delete, drag reorder, photo upload, price type, duration format) has no journey in this group.
- The Reviews tab reply flow (open, empty error, submit, edit reply, location reload to `?tab=reviews`) and both Reviews pagers have no browser journey.
- Resend verification email on a `pending_verification` row, and Activate now on a paused row, have no journey.
- The inline add / edit view (`?tab=listings&action=add|edit&id=`) is only touched for param dropping, not for ownership, Back to listings, or a bad id.
- `listing-detail/anon-login-modal-register-cta` (moved to listing-detail) is a listing-detail surface filed here; target group listing-detail.
- Account deactivation and deletion have no web or admin entry point (REST only), which is a three-entry-point gap for the site owner.
