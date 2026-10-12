---
journey: listing-owner-name
plugin: wb-listora
priority: normal
roles: [administrator, subscriber, anonymous]
covers: [listing-detail-sidebar, listing-card, listings-detail-rest, owner-name-feature, bc-10351358128]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Listing A: author has a display name different from the login (demo: /listing/qa-wave2-coupon-test/, author qa_vendor_01, display 'QA Vendor 01')"
  - "Listing B: author whose display name equals the login (demo: /listing/the-golden-fork/, author varundubey)"
estimated_runtime_minutes: 7
covers_card: 10222089571
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A listing says who is behind it, never a login

Resolver: `wb_listora_get_listing_owner_name()` (`includes/helpers.php:422`). Order: the listing's own `_listora_contact_name`, then the author's display name via `wb_listora_user_public_name()` (`helpers.php:388`). A display name that still equals the login (case-insensitive) counts as "never chose one" and yields nothing: no login, no email, and (2.0.0) no private first/last name unless `wb_listora_owner_name_use_real_name` returns true. The feature is the "Show Who Listed It" toggle (Settings > Features, key `owner_name`).

Verified via: curl + DOM grep and `rest_do_request()` on the live site (no Playwright screenshots in this pass).

## Steps

### 1. Name renders publicly (positive control)
- **Action**: logged out, open Listing A.
- **Expect**: a `.listora-detail__owner-card` with `<h3>Listed by</h3>` and `p.listora-detail__owner-name` reading "QA Vendor 01", wrapped in `<a rel="author" href=".../author/qa_vendor_01/">`. It is its own card, not inside Contact (`templates/blocks/listing-detail/sidebar.php:50-62`).

### 2. A display name equal to the login shows nothing (negative control)
- **Action**: logged out, open Listing B.
- **Expect**: zero `.listora-detail__owner-card`; `GET /listora/v1/listings/17/detail` has NO `owner` key (absent, not null) and `author.display_name` is `""` for visitors.

### 3. The listing's contact name wins
- **Action**: set the listing's Contact Name field on Listing A to "Agency X". Reload logged out.
- **Expect**: card reads "Agency X". Clear it, or save only spaces: back to the account's display name.
- **Verified**: by source (`helpers.php:431-445`), not run live.

### 4. Cards carry it too
- **Action**: open `/listings/?listora_keyword=coupon`.
- **Expect**: the Listing A card has `p.listora-card__owner` reading "Listed by QA Vendor 01" (`templates/blocks/listing-card/card-content.php:58-75`). REST card payloads (`/search`, `/listings/{id}/related`) carry no `owner` key; only `/detail` does.

### 5. The link is retargetable
- **Action**: `add_filter( 'wb_listora_listing_owner_url', '__return_empty_string' );`
- **Expect**: name renders as plain text, no anchor; `/detail` returns `owner.url` `""`. A BuddyPress/BuddyNext site returns a profile URL here instead.
- **Note**: the default URL is `/author/<login>/`; a member without a public display name gets no link at all (2.0.0).

### 6. REST carries the same name
- **Action**: `GET /listora/v1/listings/845/detail` logged out.
- **Expect**: `owner: { "name": "QA Vendor 01", "url": "http://wb-listora.local/author/qa_vendor_01/" }`, equal to the DOM in step 1.

### 7. The toggle darkens BOTH surfaces
- **Action**: Settings > Features, turn "Show Who Listed It" off (or set option `wb_listora_features[owner_name]=false`).
- **Expect**: zero `.listora-detail__owner-card` AND no `owner` key in `/detail` AND no `.listora-card__owner` on cards. Turn it back on (set `true`, do not delete the key) and step 1 returns.

### 8. Role ladder (who sees what)
| Actor | Listing A card | `/detail` `author.display_name` |
|---|---|---|
| Logged out | "Listed by QA Vendor 01" | "QA Vendor 01" (public name) |
| Another member | same | same |
| Owner (qa_vendor_01) | same card, plus "You're viewing this as the owner." bar | real display name (can edit) |
| Moderator / admin | same | real display name |

### 9. Owners can set the name
- **Action**: Add Listing, any shipped type with a Contact group.
- **Expect**: an optional "Contact Name" field in the Contact step whose value becomes the Listed by name. Existing sites keep their stored type definitions until re-seeded and fall back to the display name; that is expected.

### 10. Every card surface carries the line, and every one obeys the toggle (card 10351358128)
- **Action**: with `owner_name` ON, as logged out open Listing A on each surface: the grid (`/listings/?listora_keyword=coupon`), the same page switched to list view (`?listora_view=list` or the view toggle), a Featured listings block containing Listing A (feature it first), the member's Favorites tab (log in as any member who saved Listing A), and the `related` strip on another listing's page.
- **Expect**: each surface renders `.listora-card__owner` "Listed by QA Vendor 01" on Listing A's card and none on Listing B's; the count of cards with the line equals the count of cards whose author has a non-login display name.
- **Read-back**: turn the toggle OFF and reload every surface: zero `.listora-card__owner` anywhere (negative control, cached HTML must not keep the line); turn ON again.
- **fails_on**: 926b219a (parent of e04d632a). Not verified live.

## Fail diagnostics
| Symptom | Look at |
|---|---|
| Card missing for a real display name | `blocks/listing-detail/render.php` `$owner_name` passed to the sidebar; `sidebar.php:47` |
| Login shown | `helpers.php:388` `wb_listora_user_public_name()` |
| REST has `owner` when toggle off | `includes/rest/class-listings-controller.php:982` |
| Card shows it when toggle off | `includes/class-template-helpers.php:1349` (`owner_name` in card data) |

## Automated coverage
`tests/integration/ListingOwnerNameTest.php` covers steps 3, 5, 6, 7 and 9 headlessly.
