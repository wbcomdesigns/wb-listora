---
journey: owner-opens-own-unpublished-listing
group: submission
actor: customer
mode: browser
plugin: wb-listora
priority: critical
roles: [member-owner, member-other, unrelated-subscriber, guest, admin]
covers: [owner-preview-rejected, owner-preview-paused, owner-preview-awaiting-payment, expired-listing-notice, subscriber-owner, 404-for-everyone-else, 2.0.0-owner-access]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Fixtures seeded: wp eval-file wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php seed (one listing per status owned by {owner}, a Subscriber: published, pending, rejected, paused=deactivated, awaiting payment, expired; the fixtures output lists the titles and slugs)"
  - "Session jars for {owner}, {other}, an unrelated subscriber, {guest}, and {admin}"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: []
fails_on: []
---

# A member can open their own rejected, paused and unpaid listings; everyone else gets a 404; expired shows a notice

## Why
Core WordPress hides a non-public post from anyone without `edit_post`, so a Subscriber owner got a 404 on the "View" link of their own rejected listing. 2.0.0 hands the listing back to its author (read-only preview with the owner bar), keeps the 404 for everyone else, and shows visitors of an EXPIRED listing (a public status) a short notice.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Fixture titles/slugs come from the fixtures output as `$LISTING_PUBLISHED`, `$LISTING_PENDING`, `$LISTING_REJECTED`, `$LISTING_PAUSED`, `$LISTING_AWAITING`, `$LISTING_EXPIRED`.
- Cleanup: `wp eval-file wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php clean`; remove any mu-plugin added in step 4.

## Steps

### 1. Where the owner starts: the dashboard lists them
- **Action**: autologin as {owner}, open `wb_listora_get_dashboard_url()`.
- **Expect**: the My Listings tab lists all six fixtures, each with a View link; for the non-public ones the link is the plain-ID form `/?post_type=listora_listing&p=<id>` (no public permalink); for published/expired it is `/listing/<slug>/`.
- **Read-back**: row count equals the number of fixtures `{owner}` owns (`wp post list --author=<id> --post_type=listora_listing --post_status=any --format=count`).

### 2. Owner opens each non-public listing
| Fixture | Status | Owner bar chip (`.listora-detail__owner-bar-status`) |
|---|---|---|
| $LISTING_PENDING | pending | `is-pending` "Pending review" |
| $LISTING_REJECTED | listora_rejected | `is-rejected` "Rejected" |
| $LISTING_PAUSED | listora_deactivated | `is-deactivated` "Deactivated" |
| $LISTING_AWAITING | listora_payment | `is-paused` "Awaiting credits" |
- **Action**: open each View link from step 1 (typing the URL works too).
- **Expect**: HTTP 200; `<title>` = the listing title; `aside.listora-detail__owner-bar[aria-label="Manage this listing"]` containing "You're viewing this as the owner.", the chip above, an "Edit listing" link to `<dashboard>?tab=listings&action=edit&id=<id>` and a "My dashboard" link; read-only preview: no contact form and no review form for the owner (an admin on the same URL does get them, so this is the owner rule, not a missing template).
- **On fail**: `Status_Manager::remember_owned_unpublished` / `restore_owned_unpublished` (`includes/workflow/class-status-manager.php` ~174-205), `blocks/listing-detail/render.php` (~330).

### 3. Everyone else still gets the 404 (positive control for step 2)
| Rung | Expect (same four URLs, same run) |
|---|---|
| {other} | 404, `<title>` "Page not found" |
| unrelated Subscriber | 404 |
| {guest} | 404 |
| {admin} (control) | 200 with the owner bar |
- **Read-back**: {owner} got 200 in step 2 with the SAME URLs, so the 404s are the access rule, not a broken route.

### 4. Expired listings: visitors see a notice, the owner sees the bar
- **Action**: open `/listing/$LISTING_EXPIRED_SLUG/` as {guest} and as {other}.
- **Expect**: HTTP 200 (expired is a public status); exactly one `p.listora-detail__expired-notice[role="note"]` reading "This listing has expired, so its details may be out of date."; header `X-Robots-Tag: noindex`; no owner bar.
- **Action**: same URL as {owner} and as {admin}.
- **Expect**: owner bar with chip `is-expired` "Expired" and NO expired notice.
- **Action**: mu-plugin `add_filter( 'wb_listora_expired_listing_notice', '__return_empty_string' );`.
- **Expect**: visitors see no notice (the documented off switch). Remove the mu-plugin.
- **On fail**: `blocks/listing-detail/render.php` (~408), `Plugin::handle_expired_listing` (`includes/class-plugin.php` ~1105).

### 5. Read-only means read-only
- **Action**: as {owner} on the rejected listing, `wp.apiFetch({path:'/wp/v2/listings/<id>',method:'POST',data:{title:'hacked'}})`.
- **Expect**: `403 rest_cannot_edit` (edits go through the dashboard form using `wb_listora_user_can_edit_listing`).
- **Read-back**: `post_title` unchanged.

### 6. Search, archives and REST never leak them
- **Action**: as {guest} and {other} search the Directory for the fixture title prefix and open `/listing-category/{cat}/`.
- **Expect**: only `$LISTING_PUBLISHED` appears (an expired listing keeps its page but is not in search); rejected, paused, awaiting payment and pending never do.
- **Read-back**: `GET /listora/v1/listings/<rejected id>`: {other} `403 rest_forbidden`, {guest} `401 rest_forbidden`, {owner} `200`.

### 7. Lifecycle edge: trashed and deleted-author
- **Action**: trash `$LISTING_REJECTED`; separately delete the author of a copy.
- **Expect**: the owner gets 404 for the trashed one; a deleted-author listing is 404 for everyone except {admin}. Not verified live (added in consolidation).

### 8. 390px and dark
- **Expect**: owner bar wraps (status chip above, buttons below), no horizontal scroll, "Edit listing" and "My dashboard" >= 40px tall; the expired notice wraps and keeps contrast in dark mode.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | Opens the pending, rejected, paused and awaiting-payment listings with HTTP 200, the owner bar and a read-only preview (steps 1, 2, 5); expired shows the bar without the notice (step 4). |
| {other} | 404 on the four non-public URLs; sees the expired notice on the expired listing (steps 3, 4). |
| unrelated Subscriber | 404 on the four non-public URLs; no leak through search, archives or REST (steps 3, 6). |
| {guest} | 404 on the four non-public URLs, `401 rest_forbidden` on REST, expired notice plus `X-Robots-Tag: noindex` (steps 3, 4, 6). |
| {admin} (control) | 200 with the owner bar on every status, plus contact and review forms (steps 2, 3). |

## Pass criteria
1. The Subscriber owner opens pending, rejected, paused and awaiting-payment listings (200 + owner bar + correct chip).
2. Another member, an unrelated Subscriber and a guest get 404 for the same URLs in the same run.
3. Expired listings show visitors the notice and noindex, and show the owner their bar instead.
4. The preview cannot be used to edit or to find the listing through search or REST.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Owner still gets 404 | `posts_results`/`the_posts` pair not registered, or custom status not `protected` | `class-status-manager.php`, `class-post-types.php` |
| Everyone gets 200 | restore ignores author check | `remember_owned_unpublished` |
| No expired note | filter returned empty or status mismatch | `listing-detail/render.php` |
| Owner sees the visitor note too | `$listora_owner_view` false for owner | `listing-detail/render.php` ~330 |

## Not verified
- Steps 7 and the dark-mode half of step 8 were not run; the earlier steps were run live against the fixtures.

## Cleanup
- `wp eval-file wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php clean` removes the six fixture listings, the Subscriber and the session state.
- Delete the mu-plugin that returns an empty string from `wb_listora_expired_listing_notice` (step 4).
- Untrash `$LISTING_REJECTED` and restore the deleted-author copy if step 7 ran before the fixtures clean.
