---
journey: directory-search-and-small-fixes
plugin: wb-listora
priority: high
roles: [anonymous, admin, member-owner]
covers: [search-on-enter, owner-name-unchosen-hidden, review-criteria-unique-keys, address-place-and-country, services-category-checkboxes]
prerequisites:
  - "Site reachable at $SITE_URL with demo content"
estimated_runtime_minutes: 10
---

# Directory search on Enter, Listed by, criteria keys, address, service categories

Cards 10360339355, 10355779437, 10355318639, 10355775610, 10354973676.

## Steps

### 1. Search
- **Action**: /listings/, type "seafood" in the keyword field, wait 2 s.
- **Expect**: suggestions appear; URL, result count and grid unchanged.
- **Action**: press Enter.
- **Expect**: URL `?listora_keyword=seafood`, grid shows only matching listings. Location field + Enter filters by location.

### 2. Listed by
- **Expect**: a listing owned by an account whose display name is still its login shows no "Listed by" line; a listing owned by a member with a chosen name shows it. `add_filter( 'wb_listora_owner_name_use_real_name', '__return_true' )` brings back first + last name.

### 3. Review criteria
- **Action**: save a listing type with two criteria keyed `quality`.
- **Expect**: stored as `quality` and `quality-2`; on the review form each criterion has its own stars. (`ReviewCriteriaKeysTest`.)

### 4. Address picker
- **Action**: Add Listing, search "Connaught Place, New Delhi", pick the result.
- **Expect**: address reads "Connaught Place, New Delhi, Delhi 110001, India". A `wbListora.formatAddress` filter can reshape it.

### 5. Services box
- **Action**: edit a listing in wp-admin, tick a service category, Save, reload.
- **Expect**: checkbox list in a short scrolling box; the choice is kept.
- **On fail**: `src/interactivity/store.js` setSearchQuery/setLocation, `wb_listora_get_listing_owner_name()`, `Listing_Type_Registry::unique_criteria()`, `formatStreetAddress()`, `Services_Metabox::render_category_select()`.

## Pass criteria

ALL of the following hold:
1. The grid always matches the count and URL.
2. No name a member did not choose is published.
3. Each criterion keeps its own rating.
