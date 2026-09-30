---
journey: overlays-usable-at-every-width
plugin: wb-listora
priority: critical
roles: [anonymous, member, admin]
covers: [modals, drawers, lightbox, quick-view, responsive, a11y, cross-cutting-check-11]
prerequisites:
  - "Combo. A listing with at least 3 photos, one unclaimed listing with an approved review, a member with a published and an expired listing, a credit top-up row, an email in the Email Log"
estimated_runtime_minutes: 25
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

## Inventory

| # | Overlay | Where | How to open |
|---|---|---|---|
| 1 | Claim modal | Listing page, unclaimed listing, logged in | `[data-wp-on--click="actions.showClaimModal"]` |
| 2 | Report listing modal | Listing page, logged in | `actions.openReportModal` |
| 3 | Report review modal | Listing page, a review by someone else | `actions.showReportModal` |
| 4 | Login modal | Listing page, logged out, tap the heart | `actions.toggleFavorite` |
| 5 | Photo lightbox (`<dialog>`) | Listing with 3+ photos | `actions.openLightbox`; also check Previous / Next, the "1 / 3" counter and arrow keys |
| 6 | Services panel | Dashboard > My Listings > Services, then Add Service | `actions.toggleDashServices`, `actions.toggleServiceForm` |
| 7 | Renew modal | Dashboard > My Listings, an expired listing | `[data-listora-renew-listing]` |
| 8 | Confirm dialog | Any delete; or call `window.listoraConfirm({...})` | frontend and wp-admin |
| 9 | Quick view (Pro) | Directory grid card | `actions.openQuickView`; test a listing with photos and one without |
| 10 | Detail drawer (`<dialog>`) | wp-admin Claims, Reviews, Email Log (and any shared admin table with a detail) | `[data-listora-drawer]` |
| 11 | Email preview (`<dialog>`) | Settings > Notifications | `[data-listora-email-preview]` |
| 12 | Remove credits modal (Pro) | Monetization > Transactions, a top-up row | `[data-listora-refund-open]` |
| 13 | Pro promotion modal | Free with Pro inactive: Setup Wizard > Map Provider (`admin.php?page=listora-setup&step=maps&rerun=1`), the "Pro" chip beside Google Maps | click the chip |
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

