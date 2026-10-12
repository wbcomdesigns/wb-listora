---
journey: submission-sets-location-terms
plugin: wb-listora
priority: high
roles: [member, other-member, logged-out, administrator]
covers: [listing-submission, location-taxonomy, wp-admin-edit]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Member session ?autologin=QA-J1-member"
estimated_runtime_minutes: 5
covers_card: 10331867610
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A submitted address fills the Location taxonomy

Every importer derives Country > State > City `listora_listing_location` terms from the address; frontend submission did not, so wp-admin's Locations panel was empty for member listings and the location filter missed them. The mechanism is now a meta-write hook: `Meta_Handler::sync_location_terms()` (`includes/core/class-meta-handler.php:55-77`, registered on `added_post_meta` / `updated_post_meta` at `:50-51`) calls `wb_listora_set_location_terms()` (`includes/import-export/import-helpers.php:64`, `Term_Helper::set_location_terms()`) whenever `_listora_address` changes, from any write surface (frontend, wp-admin box, block editor, REST). It fires only when the address meta changes.

## Steps

### 1. Submit with an address
- **Action**: as a member, Add Listing (Restaurant), Details step: enter an address that resolves city, state and country (type in `input[name="meta_address[address]"]` and pick the suggestion, or fill city/state/country), submit.
- **Expect**: `wp post term list <id> listora_listing_location --fields=name` lists all three, hierarchical Country > State > City. (Meta-level run 2026-10-12: `{city:Austin,state:Texas,country:United States}` -> `Austin > Texas > United States`.)

### 2. wp-admin shows it
- **Action**: as admin open the listing's edit screen.
- **Expect**: Locations panel ticked for the three terms; the Address field in the Listing Fields box is filled.

### 3. Edit updates it
- **Action**: member edits the address to another city (Dashboard > Edit).
- **Expect**: the terms follow (2026-10-12: `Dallas | Texas | United States`; the old city term is no longer attached).

### 4. Address without a country
- **Action**: save an address with no `country`.
- **Expect**: no terms change, no error (2026-10-12: still `Dallas | Texas | United States`, no notice in debug.log).

### 5. A hand-picked term survives an unchanged save
- **Action**: as admin tick a Location term by hand in wp-admin, Update without touching the address.
- **Expect**: the term stays (the hook fires only on an address change).

### 6. Public effect (positive control)
- **Action**: Directory (`/listings/`) with the location facet / `GET /listora/v1/search?location=<city slug>`.
- **Expect**: the new listing is found by its city once published.

### 7. Role ladder
Owner: steps 1, 3, 4. Another member cannot edit the listing's address (403). Logged out: no form. Admin: steps 2 and 5 (control only, the owner flow is the contract).

## Cleanup
Delete the QA-J1- listing and the terms it created (`wp term delete listora_listing_location <id>`).

## Fail diagnostics
| Symptom | File |
|---|---|
| Terms empty after frontend submit | `includes/core/class-meta-handler.php:50-51, 66-77` hook registration |
| Terms not following an edit | same hook (only reacts to `_listora_address`); see `WB_LISTORA_META_PREFIX` |
| City/state/country not split | `includes/import-export/class-term-helper.php:198` `set_location_terms()` |
| Facet misses the listing | search index row: `includes/search/class-search-indexer.php` |

## Not verified
Steps 3 and 4 and the hierarchy were verified at the meta layer with `update_post_meta` in wp-cli (draft listing owned by QA-J1-member, deleted after). The frontend submission, wp-admin panel (2, 5), search (6) and the role ladder were not run. A first attempt through `POST /listora/v1/submit` with a `meta_address` array hung the wp-cli process (geocoding or duplicate check); not investigated.

Verified via: wp-cli.
