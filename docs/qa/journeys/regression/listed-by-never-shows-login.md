---
journey: listed-by-never-shows-login
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [listing-detail, owner-name, rest-listing-detail]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
covers_card: 10331945361
---

# "Listed by" never shows a login name

Regression sentinel for the 1.8.0 QA bounce.

## Background

WordPress sets `display_name` to the login until the user changes it, so an admin-owned listing with no Contact Name showed "Listed by admin" on the page and in `GET /listora/v1/listings/{id}/detail`. `wb_listora_get_listing_owner_name()` now falls back to first + last name when the display name equals the login, and to nothing (row hidden, `owner` absent) when there is none.

## Steps

### 1. Login-named owner is hidden
- **Action**: logged out, open a listing whose author's display name equals their login and that has no Contact Name.
- **Expect**: no "Listed by" row; REST detail has no `owner` key; the login appears nowhere on the page.

### 2. Real display name still shows
- **Action**: same for a member whose display name differs from the login.
- **Expect**: "Listed by <display name>" and REST `owner.name` match.

### 3. First/last name fallback
- **Action**: give the login-named author a first and last name.
- **Expect**: "Listed by <First Last>".
