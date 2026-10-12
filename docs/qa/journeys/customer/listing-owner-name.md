---
journey: listing-owner-name
plugin: wb-listora
priority: normal
roles: [admin, subscriber, guest, moderator]
covers: [listing-detail-sidebar, listing-card, listings-detail-rest, owner-name-feature, bc-10351358128, card-10222089571, rest-listing-detail, privacy, bc-10393531287, card-10331945361, owner-name-fallbacks]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Listing A {listing}: author {owner} has a display name different from the login"
  - "Listing B $LISTING_B: author whose display name equals the login"
  - "A Featured block page, a Favorites tab member and a related-strip listing for step 9"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes:
  - ora:customer/listed-by-never-shows-login.md
fails_on: [926b219a, 0d3e778f]
---

# A listing says who is behind it, never a login

## Why
Printing a login (or private first/last name) exposes the account; a card that ignores the "Show Who Listed It" switch leaks the name after the owner turned it off.

Resolver `wb_listora_get_listing_owner_name()` (`includes/helpers.php:422`): the listing's own `_listora_contact_name`, then the author's display name via `wb_listora_user_public_name()` (`helpers.php:388`). A display name equal to the login (case-insensitive) counts as "never chose one" and yields nothing: no login, no email, and (2.0.0) no private first/last name unless `wb_listora_owner_name_use_real_name` returns true. The switch is "Show Who Listed It" (Settings > Features, key `owner_name`).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `$NAME_A` = the display name of {owner}; `$LISTING_A_ID`, `$LISTING_B_ID` from `wp post list`. Derive, never hardcode.

## Steps

### 1. Name renders publicly (positive control)
- **Action**: as {guest} open Listing A.
- **Expect**: `.listora-detail__owner-card` with `<h3>Listed by</h3>` and `p.listora-detail__owner-name` reading `$NAME_A`, wrapped in `<a rel="author" href=".../author/<login>/">`; its own card, not inside Contact (`templates/blocks/listing-detail/sidebar.php:50-62`).

### 2. A display name equal to the login shows nothing (negative control)
- **Action**: as {guest} open Listing B.
- **Expect**: zero `.listora-detail__owner-card` and no `.listora-card__owner` on its card; `GET /listora/v1/listings/$LISTING_B_ID/detail` has NO `owner` key (absent, not null) and `author.display_name` is `""` for visitors; the login string appears nowhere in the HTML.
- **Read-back** (card 10393531287): `curl -s $SITE_URL/wp-json/listora/v1/listings/$LISTING_B_ID/detail | jq -r '.author.display_name'` is empty; `curl -s <permalink> | grep -c "$LOGIN"` is 0; repeat after the toggle is off (step 7): still empty and no `owner`. **fails_on**: 0d3e778f. Not verified live.

### 3. The listing's contact name wins
- **Action**: set Listing A's Contact Name to `Agency X`; reload as {guest}.
- **Expect**: card reads "Agency X". Clear it or save only spaces: back to `$NAME_A`.
- **Read-back**: source `helpers.php:431-445`; automated twin covers it. Not verified live.

### 3b. First/last name is NOT a fallback (changed in 2.0.0)
- **Action**: give the login-named author of Listing B a first and last name.
- **Expect**: still hidden. With `wb_listora_owner_name_use_real_name` returning true it reads "Listed by <First Last>", never the login.
- **Read-back**: HTML and REST `/detail`, with and without the filter.

### 4. Cards carry it too
- **Action**: open the Directory searching a keyword unique to Listing A.
- **Expect**: Listing A's card has `p.listora-card__owner` "Listed by `$NAME_A`" (`templates/blocks/listing-card/card-content.php:58-75`). REST card payloads (`/search`, `/listings/{id}/related`) carry no `owner` key; only `/detail` does.

### 5. The link is retargetable
- **Action**: `add_filter( 'wb_listora_listing_owner_url', '__return_empty_string' );`
- **Expect**: name renders as plain text, no anchor; `/detail` returns `owner.url` `""`. A BuddyPress/BuddyNext site returns a profile URL instead. A member without a public display name gets no link at all (2.0.0).

### 6. REST carries the same name
- **Action**: `GET /listora/v1/listings/$LISTING_A_ID/detail` as {guest}.
- **Expect**: `owner: { "name": "<$NAME_A>", "url": "$SITE_URL/author/<login>/" }`, equal to the DOM in step 1.

### 7. The toggle darkens BOTH surfaces
- **Action**: Settings > Features, "Show Who Listed It" off (or `wb_listora_features[owner_name]=false`).
- **Expect**: zero `.listora-detail__owner-card`, no `owner` key in `/detail`, no `.listora-card__owner` on cards. Turn it back on (set `true`, do not delete the key) and step 1 returns.

### 8. Owners can set the name
- **Action**: Add Listing, any shipped type with a Contact group.
- **Expect**: an optional "Contact Name" field in the Contact step whose value becomes the Listed by name. Existing sites keep stored type definitions until re-seeded and fall back to the display name (expected).

### 9. Every card surface carries the line and every one obeys the toggle (card 10351358128)
- **Action**: with `owner_name` ON, as {guest} open Listing A on each surface: grid, list view (`?listora_view=list`), a Featured block containing it, the Favorites tab of a member who saved it, and the `related` strip on another listing's page.
- **Expect**: each renders `.listora-card__owner` "Listed by `$NAME_A`" on Listing A's card and none on Listing B's; the count of cards with the line equals the count of cards whose author has a non-login display name.
- **Read-back**: toggle OFF and reload every surface: zero `.listora-card__owner` (cached HTML must not keep the line); toggle ON again.
- **fails_on**: 926b219a (parent of e04d632a). Not verified live.

### 10. Boundary and 390px
- **Action**: a display name with markup (`<b>x</b>`), 200 characters, and emoji; view at 390 and in dark mode.
- **Expect**: whitespace-only display name is treated as empty (hidden); markup shown escaped, long names wrap without horizontal scroll, contrast holds. Not verified live.

## Role ladder
| Rung | Listing A card | `/detail` `author.display_name` |
|---|---|---|
| {guest} | "Listed by `$NAME_A`" | `$NAME_A` (public name) |
| another member | same | same |
| {owner} | same card, plus "You're viewing this as the owner." bar | real display name (can edit) |
| moderator / {admin} | same | real display name |

## Pass criteria
1. A real display name (or contact name) renders on the page and the cards; a login never does.
2. REST `/detail` and the DOM agree; absent `owner` key when nothing qualifies.
3. The toggle removes the name from the page, `/detail` and every card surface.

## Fail diagnostics
| Symptom | Look at |
|---|---|
| Card missing for a real display name | `blocks/listing-detail/render.php` `$owner_name` passed to the sidebar; `sidebar.php:47` |
| Login shown | `helpers.php:388` `wb_listora_user_public_name()` |
| REST has `owner` when toggle off | `includes/rest/class-listings-controller.php:982` |
| Card shows it when toggle off | `includes/class-template-helpers.php:1349` (`owner_name` in card data) |

## Not verified
- Verified via curl + DOM grep and `rest_do_request()` (no Playwright pass); steps 3, 9, 10 not run live. Automated coverage: `tests/integration/ListingOwnerNameTest.php` covers steps 3, 5, 6, 7 and 8 headlessly.

## Cleanup
- Set Listing A's Contact Name back to its original value (blank) and remove any `Agency X` text saved in step 3.
- Turn the toggle back on: `wb_listora_features[owner_name]` = `true` (Settings > Features, "Show Who Listed It"); never delete the key.
- Remove the mu-plugin or snippet that adds the `wb_listora_listing_owner_url` filter (step 5).
- Restore the display name edited in step 10 (markup, 200 characters, emoji) to `$NAME_A`, and any Contact Name field added to a type in step 8.
