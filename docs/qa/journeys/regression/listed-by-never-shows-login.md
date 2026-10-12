---
journey: listed-by-never-shows-login
plugin: wb-listora
priority: high
roles: [anonymous, member, admin]
covers: [listing-detail, owner-name, rest-listing-detail, privacy]
prerequisites:
  - "Site reachable at http://wb-listora.local"
  - "Listing 17 /listing/the-golden-fork/ (author varundubey, display name = login, no Contact Name); listing 845 /listing/qa-wave2-coupon-test/ (author QA Vendor 01, chosen name)"
estimated_runtime_minutes: 5
covers_card: 10331945361
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# "Listed by" never shows a login name (or a private real name)

`wb_listora_get_listing_owner_name()` (`includes/helpers.php:422`) returns: the listing's own Contact Name, else the author's PUBLIC name via `wb_listora_user_public_name()` (`:388`: display name unless it equals the login), else `''`. 2.0.0: the first/last-name fallback is OFF by default; a site opts in with `add_filter( 'wb_listora_owner_name_use_real_name', '__return_true' )` (`:448`). Renders as `.listora-detail__owner-card` (heading "Listed by", `.listora-detail__owner-name`, `templates/blocks/listing-detail/sidebar.php:50-62`) and on cards as `p.listora-card__owner` "Listed by %s" (`templates/blocks/listing-card/card-content.php:62-78`). Members with no chosen name also get no profile link.

Verified via: curl + REST detail as anonymous, 2026-10-12.

## Steps

### 1. Login-named owner is hidden
- **Action**: logged out, open `/listing/the-golden-fork/`.
- **Expect**: no `.listora-detail__owner-card`, no `.listora-card__owner` on its card in `/listings/`; `GET /listora/v1/listings/17/detail` has no `owner` key and `author.display_name` is `""`; the login string "varundubey" appears nowhere in the HTML (verified).

### 2. POSITIVE CONTROL: real display name shows
- **Action**: open `/listing/qa-wave2-coupon-test/`.
- **Expect**: `Listed by` / "QA Vendor 01"; REST `owner.name` = "QA Vendor 01", `owner.url` = `/author/qa_vendor_01/`; `author.display_name` matches (verified).

### 3. First/last name is NOT a fallback (changed in 2.0.0)
- **Action**: give the login-named author a first and last name (or use a member whose display name = login but first/last set).
- **Expect**: still hidden. With the `wb_listora_owner_name_use_real_name` filter returning true it reads "Listed by <First Last>".

### 4. Contact Name wins
- **Action**: set the listing's Contact Name.
- **Expect**: "Listed by <Contact Name>" even when the account is login-named.

### 5. Feature off
- **Action**: Settings > Features > Owner name off (`wb_listora_features.owner_name`).
- **Expect**: row and REST `owner` gone on every surface.

### 6. Role ladder
- Anonymous: steps 1-2 (the privacy-critical viewer). Logged-in member and the owner see the same public name, not the login. Editor/admin see the same on the frontend (admin wp-admin shows the account login, which is not a public surface). Admin is the control.

## Fail diagnostics

| Symptom | File |
|---|---|
| Login or real name shown | `includes/helpers.php:388-451` |
| Row shows when feature off | `includes/helpers.php:425` |
| Card line missing | `templates/blocks/listing-card/card-content.php:62-78` |
