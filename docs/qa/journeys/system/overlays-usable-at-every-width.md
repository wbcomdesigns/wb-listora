---
journey: overlays-usable-at-every-width
plugin: wb-listora
priority: critical
roles: [anonymous, member, admin]
covers: [modals, drawers, lightbox, quick-view, responsive, a11y, cross-cutting-check-11]
prerequisites:
  - "Combo. A listing with at least 3 photos, one unclaimed listing with an approved review, a member with a published and an expired listing, a credit top-up row, an email in the Email Log"
estimated_runtime_minutes: 25
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 1280, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Every overlay opens and is usable at 1280 and at 390

The inventory behind cross-cutting check 11. Open each overlay at both widths and apply the same test. Add a row here in the PR that adds an overlay.

## The test (every row, both widths)

1. The panel has a non-zero size.
2. `document.elementFromPoint()` at the centre of the close button returns the close button. Same for the primary action.
3. The header is not under the WordPress admin bar (run logged in, where the bar is present).
4. The actions are inside the viewport, or the panel scrolls to them.
5. Labels sit above their fields. No horizontal scroll.
6. Escape or the close button closes it.

## Role ladder

Overlays are screen furniture, not objects, so the ladder is about who can open them: rows 1 and 2 are offered to visitors (the click opens the login modal) and to members who are NOT the listing owner (the owner never sees Claim or Report, `blocks/listing-detail/render.php:561`), row 3 sits on a review written by someone else, row 4 needs a logged-out visitor, rows 6 and 7 need the listing owner, rows 10 to 15 need an admin. Run each as the allowed role (POSITIVE CONTROL: it opens) and confirm the disallowed role does not get the trigger.

## Inventory

| # | Overlay | Where | How to open |
|---|---|---|---|
| 1 | Claim modal `#listora-claim-modal` ("Claim This Business") | Listing page, unclaimed listing, logged in as a non-owner | `[data-wp-on--click="actions.showClaimModal"]` |
| 2 | Report listing modal `#listora-report-modal` | Listing page, logged in | `actions.openReportModal` |
| 3 | Report review modal `#listora-report-review-modal` | Listing page, a review by someone else | `actions.showReportModal` |
| 4 | Login modal `#listora-login-modal` | Listing page, logged out, tap the heart | `actions.toggleFavorite` |
| 5 | Photo lightbox `dialog.listora-detail__lightbox` (aria-label "Photo viewer") | Listing with 3+ photos | `actions.openLightbox`; also check Previous / Next, the "1 / 3" counter and arrow keys |
| 6 | Services panel `.listora-dashboard__services-panel` | `$DASH/?tab=listings`, row button "Services" (Services feature on), then "Add Service" | `actions.toggleDashServices`, `actions.toggleServiceForm` |
| 7 | Renew modal `[data-listora-renew-modal]` | `$DASH/?tab=listings`, an expired listing with renewal on | `[data-listora-renew-listing]` |
| 8 | Confirm dialog `.listora-confirm-overlay` (title/buttons per caller, e.g. "Deactivate listing?" Cancel / Deactivate) | `$DASH/?tab=listings` More, Deactivate; or `window.listoraConfirm({...})` | frontend and wp-admin |
| 9 | Quick view (Pro) `.listora-qv-modal` | Directory grid card | `actions.openQuickView`; test a listing with photos and one without |
| 10 | Detail drawer (`<dialog>`) | wp-admin Claims, Reviews, Email Log (and any shared admin table with a detail) | `[data-listora-drawer]` |
| 11 | Email preview (`<dialog>`) | Settings > Notifications | `[data-listora-email-preview]` |
| 12 | Remove credits modal (Pro) | Monetization > Transactions, a top-up row | `[data-listora-refund-open]` |
| 13 | Pro promotion modal `#listora-promo-modal` | Free with Pro INACTIVE only: Setup Wizard > Map Provider (`admin.php?page=listora-setup&step=maps&rerun=1`), the "Pro" chip beside Google Maps | click the chip |
| 14 | Listing Types detail drawer (`<dialog>`) | wp-admin Listing Types, Delete on a type | `[data-listora-drawer]` |
| 15 | Needs detail / reject drawer (`<dialog>`) | wp-admin Moderation > Needs (Needs feature ON), Details or Reject on a request | `[data-listora-drawer]` |

Not overlays, but tested in the same pass because they behave like one on a phone: the share control on a listing (native share sheet, or copy link), and the listing page's fixed Call / Visit / Save bar against the theme's back-to-top button.

## Before you start

Turn every Pro feature ON (cross-cutting check 13). With Needs, webhooks or analytics off their screens do not exist and the pass silently skips them.

## Known traps (each shipped once)

- Markup printed on `in_admin_footer` lands inside `#wpfooter`, which WordPress hides at 782px and below. Print admin overlays on `admin_footer`.
- A fixed overlay with `z-index` below 99999 sits under the admin bar. Offset by `var(--wp-admin--admin-bar--height, 0px)`.
- A close button repositioned for mobile can land on another control. Check with `elementFromPoint`, not by reading the CSS.
- A gallery bound to "has image" must derive that from the list of usable image URLs, not from a row count.
- A rule in a Listora stylesheet cannot beat a theme rule: Listora CSS is layered. Put theme-facing overrides in `assets/css/listora-isolation.css`, and verify with the cache cleared, never by injecting the stylesheet.
- A modal state with no markup (`openModal( 'share' )`) still sets `body { overflow: hidden }`. Grep for the modal's markup before trusting a state name.

## Verification status (2026-10-12, QA site, Pro active)

Verified via curl/DOM only for presence and ids; the width tests (the six-point test above) were NOT run in a browser in this refresh because the shared Playwright browser was being driven by parallel sessions.

| Rows | Status |
|---|---|
| 1-5 | DOM present on `/listing/brooklyn-bridge-park/` for a logged-in member and for a visitor: `#listora-report-modal`, `#listora-report-review-modal`, `#listora-login-modal`, the lightbox `<dialog>` and the `showClaimModal` / `openReportModal` / `openLightbox` triggers. The Claim trigger renders only on an unclaimed listing (`$is_claimed` false) for a visitor or non-owner. |
| 6, 7 | Triggers and panels present in `templates/blocks/user-dashboard/tab-listings.php`; the Renew modal markup is rendered on every Listings tab render (hidden). |
| 8 | Observed open: the Deactivate confirm renders `.listora-confirm-overlay.is-visible` with Cancel (43px) and Deactivate buttons. |
| 9 | Pro; `.listora-qv-modal` markup present on the dashboard page; opening it from a grid card not re-run. |
| 10-12, 14, 15 | `data-listora-drawer` / `[data-listora-email-preview]` / `[data-listora-refund-open]` found in source (`includes/admin/class-admin-table.php`, `class-settings-page.php`, Pro `class-transactions-page.php`); not opened. |
| 13 | Cannot be exercised on this site: Pro is active, so the Pro chip is not rendered. Run on a Free-only install. |

## Not verified

- Every width-by-width test result (rows 1 to 15 at 1280 and 390).
- Dark mode and unknown-theme appearance.

## Fail diagnostics

| Symptom | Likely cause | File:line |
|---|---|---|
| Claim / report modal not opening | state getter | `blocks/listing-detail/render.php:951` (claim modal), `src/interactivity/store.js` (`showClaimModal`) |
| Lightbox missing | gallery template | `templates/blocks/listing-detail/gallery.php:202` |
| Confirm dialog under the admin bar | z-index / offset | `src/components/modal.css` |
| Drawer markup inside `#wpfooter` | printed on `in_admin_footer` | `includes/admin/class-admin-table.php` |
| Renew modal not populated | script hook | `src/blocks/user-dashboard/view.js` |
| Pro modal markup | promo | `includes/admin/class-pro-promotion.php:919` |
