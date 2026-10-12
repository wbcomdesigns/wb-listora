---
journey: listing-location-terms-follow-address
plugin: wb-listora
priority: high
roles: [administrator, owner-member, other-member, guest]
covers: [location-taxonomy, wp-admin, meta-hook-sync, card-10331867610]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} autologin"
  - "A listing {listing} owned by {owner}; {other}"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
site_profile: required
supersedes:
  - "ora:regression/surfaces-location-and-service-photos.md (steps 1-2; services steps live in customer/listing-services-crud-and-photos.md)"
fails_on: []
---

# Location terms follow the address from any write, and a hand-picked location survives an unchanged save

## Why
If the Locations panel does not follow an address change the listing is filed under the wrong city; if a plain save resets a hand-picked term the owner loses curation.

Location terms follow the address from any write (frontend, wp-admin fields box, block editor, API) through `Meta_Handler::sync_location_terms()` (`includes/core/class-meta-handler.php:55`), only when the address meta changes. Frontend/REST creation is also covered by `system/location-terms-follow-the-address.md` (cross-area duplicate, see the cross-area file).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Capture `$ID` and the listing's current location terms (`wp post term list $ID listora_listing_location --field=name`).

## Steps

### 1. Location from wp-admin follows the address
- **Action**: as {admin} change the address in the Listing Fields box to a new `{loc}` city/state/country; Update.
- **Expect**: the Locations panel follows the new city/state/country.
- **Read-back**: `wp post term list $ID listora_listing_location --field=name` equals the new hierarchy.
- **On fail**: `includes/core/class-meta-handler.php:66-77`.

### 2. Manual location survives an unchanged save
- **Action**: tick a Location term by hand, Update without touching the address.
- **Expect**: the hand-picked term stays.
- **Read-back**: the term list before and after Update is identical (positive control: step 1 proves the sync does fire when the address changes).

### 3. Boundary address input
- **Action**: save an empty address, whitespace only, markup, a very long string, and an address whose country/city already exists as a term.
- **Expect**: empty address leaves the terms untouched; markup stored escaped; a repeat of an existing term reuses it (no duplicate term). Not verified live (added in consolidation).

### 4. Role ladder
| Actor | Expect |
|---|---|
| {owner} | frontend edit follows the address (see `system/location-terms-follow-the-address.md`) |
| {other} | cannot edit {owner}'s listing (403) |
| {guest} | 401/403 on write |
| {admin} | steps 1-3 (control) |

## Pass criteria
1. A changed address updates the Locations terms from every write path.
2. An unchanged save never resets a hand-picked term; no duplicate terms.

## Fail diagnostics
| Symptom | File |
|---|---|
| Location resets on a plain save | `includes/core/class-meta-handler.php:66-77` |
| Terms do not follow a changed address | `Meta_Handler::sync_location_terms()` `:55` |

## Not verified
- Only the meta-hook behaviour of step 1 was run (2026-10-12, wp-cli); step 2, the wp-admin box rendering and the ladder were not run; pointers are from source.

## Cleanup
Restore the listing's address and location terms.
