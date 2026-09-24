---
journey: submission-sets-location-terms
plugin: wb-listora
priority: high
roles: [subscriber, administrator]
covers: [listing-submission, location-taxonomy, wp-admin-edit]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
covers_card: 10331867610
---

# A submitted address fills the Location taxonomy

Regression sentinel for the 1.8.0 QA bounce.

## Background

Every importer derives Country > State > City `listora_listing_location` terms from the address; frontend submission never did, so the wp-admin Locations panel was empty for member listings and the location filter missed them. `save_meta_fields()` now calls `wb_listora_set_location_terms()` for a `map_location` field, on create and on edit.

## Steps

### 1. Submit with an address
- **Action**: as a member, submit a listing with an address that resolves city, state and country.
- **Expect**: `wp post term list <id> listora_listing_location` lists all three.

### 2. wp-admin shows it
- **Action**: as admin, open the listing's edit screen.
- **Expect**: Locations panel ticked for the three terms; the Address field is filled.

### 3. Edit updates it
- **Action**: member edits the address to another city.
- **Expect**: terms follow the new address.

### 4. Address without a country
- **Expect**: no terms change, no error.
