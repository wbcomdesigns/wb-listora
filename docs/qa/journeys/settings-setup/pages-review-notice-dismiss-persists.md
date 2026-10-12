---
journey: pages-review-notice-dismiss-persists
group: settings-setup
actor: admin
mode: browser
plugin: wb-listora
priority: high
roles: [admin, editor]
covers: [pages-review-notice, notice-dismiss, admin-notices]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI against $WP_PATH"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
site_profile: required
supersedes:
  - ora:regression/admin-listings-table-layout.md (notice steps 6-7 only)
---

# Dismissing the "pages are mapped" notice with its X keeps it dismissed

## Why
The "WB Listora is set up. N pages are mapped" notice reappeared on every admin screen. It is `is-dismissible`, so core paints an X, but core's X is client-side only: only the small "Dismiss" link wrote the user-meta flag, and every reactivation re-armed the 7-day transient. Where the notice may appear at all is owned by `pages-notice-settings-only`; page creation by `pages-are-created-once`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Admin as {admin} (a user with `manage_options`); `ADMIN_ID` is their id.
- Arm the notice: `wp eval 'delete_user_meta( ADMIN_ID, "wb_listora_pages_review_dismissed" ); set_transient( "wb_listora_pages_review_pending", "1", WEEK_IN_SECONDS );'`

## Steps

### 1. The notice is present (positive control)
- **Action**: load any admin screen EXCEPT Listora > Settings and the Listora landing page (deliberately off there), e.g. `wp-admin/edit.php?post_type=listora_listing`.
- **Expect**: `.notice.listora-notice.listora-pages-review-notice` reads "WB Listora is set up. N pages are mapped - review or remap on Settings > General > Pages." with "Review pages" and "Dismiss" buttons.
- **Read-back**: N equals the mapped-page count in the Pages settings table.

### 2. The X persists the dismissal
- **Action**: click `.notice-dismiss` (the X), not the "Dismiss" link.
- **Expect**: transient `wb_listora_pages_review_pending` deleted AND user meta `wb_listora_pages_review_dismissed` set to `1`.
- **Read-back**: `wp transient get wb_listora_pages_review_pending` fails; `wp user meta get ADMIN_ID wb_listora_pages_review_dismissed` is 1.

### 3. The notice stays gone
- **Action**: reload the screen and open two other admin screens.
- **Expect**: no `.listora-pages-review-notice` in the DOM on any.
- **Read-back**: DOM query count 0. Then re-activate the plugin: the notice must NOT reappear for a user who dismissed it (reactivation must not re-arm it for them).

### 4. The "Dismiss" link persists too
- **Action**: re-arm (Setup) and click the "Dismiss" link.
- **Expect**: same two writes as step 2.
- **Read-back**: same two reads.

### 5. Two tabs
- **Action**: re-arm; open the notice in two tabs, dismiss in tab A, reload tab B.
- **Expect**: tab B shows no notice.
- **Read-back**: user meta 1. Not verified live.

### 6. 390
- **Action**: step 1 at 390x844 (wp-admin).
- **Expect**: the notice wraps, buttons >= 40px tall where they are plugin buttons, no horizontal scroll.
- **Read-back**: `scrollWidth <= innerWidth`. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {admin} | steps 1-6 |
| {editor} (no `manage_options`) | never sees the notice even with the transient set (`wb_listora_should_show_pages_review_notice()` first guard) |
| {guest} | wp-admin redirects to login |

## Pass criteria
1. Present before the click.
2. After the X: transient deleted and user meta set; the notice stays gone across reloads, screens and re-activation.

## Fail diagnostics
| Symptom | File |
|---|---|
| X does not persist | `assets/js/admin/pages-review-notice.js:20` not enqueued: `includes/page-registry-helpers.php:203-216`, guards `:162-190` |
| Notice re-arms | `includes/page-registry-helpers.php:203-285` |

## Not verified
The X click was not driven in a browser; the guard logic was read in source. Steps 3 (re-activation), 5, 6.

## Cleanup
Re-arm or clear the transient as it was; restore the user meta.
