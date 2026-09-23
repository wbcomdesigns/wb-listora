---
journey: listing-owner-name
plugin: wb-listora
priority: normal
roles: [administrator, subscriber, anonymous]
covers: [listing-detail-sidebar, listings-detail-rest, owner-name-feature]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing whose author's display name differs from their login"
estimated_runtime_minutes: 6
covers_card: 10222089571
---

# A listing says who is behind it

Regression sentinel for the public "Listed by" name.

## Background

A visitor had no way to see who listed a business - only the owner themselves saw an owner bar. Every major directory (Google Maps, Yelp, TripAdvisor) shows a name, and an anonymous listing reads as untrustworthy.

The name resolves in this order, in `wb_listora_get_listing_owner_name()`:

1. the listing's own `contact_name` field (`_listora_contact_name`)
2. the author account's display name

The listing's own name wins because the business is what the listing is about; the WordPress account behind it may be an agency, a member of staff, or `admin`. A login or email address is never shown.

## Steps

### 1. The name renders publicly
- **Action**: open a published listing logged OUT.
- **Expect**: a "Listed by" card in the sidebar with the name. It is its own card, not a line inside Contact - a listing with no phone, email or website still has an owner, and the contact card does not render without one of those.
- **On fail**: `templates/blocks/listing-detail/sidebar.php`, or `owner_name` missing from `$sidebar_view_data` in `blocks/listing-detail/render.php`.

### 2. A listing with no contact details still shows it
- **Action**: a listing with no phone, email or website.
- **Expect**: no Contact card, but the Listed by card is still there. This is the case that breaks if someone "tidies up" by folding the name into the contact card.

### 3. The listing's contact name wins over the account
- **Action**: set the Contact Name field on a listing whose author display name is different.
- **Expect**: the page shows the contact name. Clear it → the display name is back. A whitespace-only value counts as empty.

### 4. Never a login or an email
- **Verify**: on a site where display name was never set, the page shows the account's display name (WordPress defaults that to the login) - but nothing anywhere renders `user_email` or a raw `user_login` field.

### 5. The link is retargetable
- **Action**: `add_filter( 'wb_listora_listing_owner_url', fn() => '' );`
- **Expect**: the name renders as plain text, no anchor. Returning a member-profile URL instead points the name there - this is the seam a BuddyPress / BuddyNext site uses rather than `/author/<slug>/`.

### 6. REST carries the same name
- **Action**: `GET /listora/v1/listings/<id>/detail`.
- **Expect**: `owner: { name, url }` matching what the page renders. List and card payloads deliberately omit it (a user lookup per row).

### 7. The toggle darkens BOTH surfaces
- **Action**: Settings → Features → turn "Show Who Listed It" off.
- **Expect**: no Listed by card on the page AND no `owner` key in the detail response - absent, not null. A toggle that hides the card and leaves the REST field is the half-applied toggle this plugin has already been bitten by (reviews, card 9895809632).

### 8. Owners can set the name
- **Action**: submit a new listing of a shipped type with a contact group.
- **Expect**: a "Contact Name" field in the Contact step, optional, whose value becomes the Listed by name.
- **Note**: field-group defaults apply on (re)seed, so a site upgrading keeps its existing type definitions and falls back to display name until its types are re-seeded. That is the behaviour, not a defect.

## Automated coverage

`tests/integration/ListingOwnerNameTest.php` covers steps 3, 5, 6, 7 and 8 headlessly.
