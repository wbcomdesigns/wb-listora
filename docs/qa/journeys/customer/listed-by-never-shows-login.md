---
journey: listed-by-never-shows-login
plugin: wb-listora
priority: high
roles: [guest, owner, unnamed, editor, admin]
covers: [listing-detail, owner-name, rest-listing-detail, privacy, bc-10393531287, covers_card 10331945361]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "LOGIN_LISTING: a published listing whose author is {unnamed} (display name equals login, no Contact Name); NAMED_LISTING: a published listing whose author is {owner} with a chosen display name"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged theme]
site_profile: required
supersedes:
  - ora:regression/listed-by-never-shows-login.md
fails_on:
  - 0d3e778f
---

# "Listed by" never shows a login name (or a private real name)

## Why
A guest reading a listing must never see an account login or a member's real name they did not choose to publish. `wb_listora_get_listing_owner_name()` (`includes/helpers.php:422`) returns the listing's own Contact Name, else the author's PUBLIC name via `wb_listora_user_public_name()` (`:388`: display name unless it equals the login), else `''`. 2.0.0: the first/last-name fallback is OFF by default; a site opts in with `add_filter( 'wb_listora_owner_name_use_real_name', '__return_true' )` (`:448`). Renders as `.listora-detail__owner-card` (heading "Listed by", `.listora-detail__owner-name`, `templates/blocks/listing-detail/sidebar.php:50-62`) and on cards as `p.listora-card__owner` "Listed by %s" (`templates/blocks/listing-card/card-content.php:62-78`). Members with no chosen name also get no profile link. Broader no-login-leak surfaces (oEmbed, feeds, reviewer names) are owned by `2.0-reviewer-name-member-no-login-leak`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Capture `LOGIN_LISTING` id/slug and `NAMED_LISTING` id/slug from the fixtures output; `LOGIN` = {unnamed}'s login string.

## Steps

### 1. Login-named owner is hidden
- **Action**: logged out, open `$SITE_URL/listing/<LOGIN_LISTING slug>/` and its card in the Directory.
- **Expect**: no `.listora-detail__owner-card`, no `.listora-card__owner` on its card; `GET /listora/v1/listings/<id>/detail` has no `owner` key and `author.display_name` is `""`; the string `LOGIN` appears nowhere in the HTML.
- **Read-back** (card 10393531287): `curl -s $SITE_URL/wp-json/listora/v1/listings/<id>/detail | jq -r '.author.display_name'` is empty; `curl -s <permalink> | grep -c "$LOGIN"` is 0. Repeat after Owner name is switched off (step 5): still empty and no `owner`.
- **fails_on**: 0d3e778f. Not verified live.

### 2. POSITIVE CONTROL: real display name shows
- **Action**: open `<NAMED_LISTING>`.
- **Expect**: "Listed by" / the chosen name; REST `owner.name` equals it, `owner.url` is the author archive; `author.display_name` matches.
- **Read-back**: REST `owner` object present.

### 3. First/last name is NOT a fallback (changed in 2.0.0)
- **Action**: give the login-named author a first and last name.
- **Expect**: still hidden. With `wb_listora_owner_name_use_real_name` returning true it reads "Listed by <First Last>".
- **Read-back**: HTML and REST both, with and without the filter.

### 4. Contact Name wins
- **Action**: set the listing's Contact Name.
- **Expect**: "Listed by <Contact Name>" even when the account is login-named.
- **Read-back**: REST `owner.name`.

### 5. Feature off
- **Action**: Settings > Features > Owner name off (`wb_listora_features.owner_name`).
- **Expect**: the row and REST `owner` are gone on every surface (detail, card, REST).
- **Read-back**: step 1 queries.

### 6. Boundary names
- **Action**: set the display name to whitespace-only, markup (`<b>x</b>`), a very long string and emoji.
- **Expect**: whitespace-only is treated as empty (hidden); markup is escaped; long names wrap without breaking the card.
- **Read-back**: rendered HTML escaped. Not verified live.

### 7. 390 and dark
- **Action**: repeat steps 1-2 at 390 and in dark.
- **Expect**: same visibility; owner card readable.
- **Read-back**: DOM query for `.listora-detail__owner-card`. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {guest} | steps 1-2 (the privacy-critical viewer) |
| {owner}, other members | see the same public name, never the login |
| {editor}, {admin} | same on the front end (wp-admin shows the account login, not a public surface); admin is the control |

## Pass criteria
1. A login-named owner is hidden on detail, card and REST.
2. A chosen display name or Contact Name shows; real first/last name does not unless the filter is on.
3. Feature off removes the row everywhere.

## Fail diagnostics
| Symptom | File |
|---|---|
| Login or real name shown | `includes/helpers.php:388-451` |
| Row shows when feature off | `includes/helpers.php:425` |
| Card line missing | `templates/blocks/listing-card/card-content.php:62-78` |

## Not verified
Step 1 read-back after Owner name off; steps 6-7. Steps 1-2 were verified with curl and REST detail as a guest.

## Cleanup
Restore display/first/last name, Contact Name and the Owner name feature.
