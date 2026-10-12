---
journey: directory-search-and-small-fixes
plugin: wb-listora
priority: high
roles: [anonymous, admin, member-owner]
covers: [search-on-enter, owner-name-unchosen-hidden, review-criteria-unique-keys, address-place-and-country, services-category-checkboxes]
prerequisites:
  - "Site reachable at http://wb-listora.local with demo content"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Directory search on Enter, Listed by, criteria keys, address, service categories

Cards 10360339355, 10355779437, 10355318639, 10355775610, 10354973676. Verified via: source + curl; the keyboard steps (1, 4) need a browser and were not run (shared Playwright pane busy).

## Steps

### 1. Search
- **Action**: `/listings/`, type "coffee" into `#listora-keyword` (role combobox), wait 2 s.
- **Expect**: suggestions list (`#listora-suggestions`) fills; URL, count and grid unchanged (typing only runs `setSearchQuery`, a 300 ms suggest request).
- **Action**: press Enter (or click `.listora-search__submit` "Search").
- **Expect**: URL gains `?listora_keyword=coffee`, grid and count agree. Enter in `#listora-location` also searches. Enter with a suggestion highlighted follows that suggestion instead (POSITIVE CONTROL: arrow down, Enter).
- **REST/DOM**: `/listings/?listora_keyword=coffee` server-renders `#listora-keyword` with `value="coffee"`; state totalResults is lower than the unfiltered 104.

### 2. Listed by
- **Action**: logged out, open `/listing/the-golden-fork/` (author varundubey, display name equals login, no Contact Name) and `/listing/qa-wave2-coupon-test/` (author "QA Vendor 01").
- **Expect**: first has no `.listora-detail__owner-card` and no `.listora-card__owner`, and REST `/listora/v1/listings/17/detail` has no `owner` key and `author.display_name` is ""; second shows `Listed by` with "QA Vendor 01" and `owner.name` matches. The login string appears nowhere on the page.
- `add_filter( 'wb_listora_owner_name_use_real_name', '__return_true' )` brings back first + last name for an account with no chosen display name (`includes/helpers.php:448`).

### 3. Review criteria
- **Action**: save a listing type with two criteria keyed `quality`.
- **Expect**: stored as `quality` and `quality-2` (`Listing_Type_Registry::unique_criteria()`); the review form gives each criterion its own star group. Not exercised live; covered by `ReviewCriteriaKeysTest`.

### 4. Address picker
- **Action**: Add Listing, search "Connaught Place, New Delhi", pick the result.
- **Expect**: address reads "Connaught Place, New Delhi, Delhi 110001, India" (place name kept, country added). `wbListora.formatAddress` can reshape it. Needs Maps provider: not run.

### 5. Services box
- **Action**: edit a Service listing in wp-admin, tick a category in the Services box, Save, reload.
- **Expect**: checkbox list in a short scrolling box; the choice is kept (`Services_Metabox::render_category_select()`).

### 6. Role ladder
- Search and Listed by are public (anonymous, member, owner, editor, admin identical). Steps 3 and 5 need admin/editor; step 4 needs a member who can submit. Admin is the control.

## Pass criteria

1. The grid always matches the count and URL.
2. No name a member did not choose is published.
3. Each criterion keeps its own rating.

## Fail diagnostics

| Symptom | File |
|---|---|
| Enter does nothing | `src/interactivity/store.js:1366` Enter branch -> `actions.searchImmediate()`; button `templates/blocks/listing-search/search-bar.php:125` |
| Typing changes the grid | `src/interactivity/store.js:952` `setSearchQuery` (suggest only) |
| Login shown as owner | `includes/helpers.php:388` `wb_listora_user_public_name()`, `:422` `wb_listora_get_listing_owner_name()` |
| Criteria collide | `includes/core/class-listing-type-registry.php:62` `unique_criteria()` |
| Address loses place | `src/utils/map-picker.js:245` `formatStreetAddress()` |
| Services box not checkboxes | `includes/admin/class-services-metabox.php:345` |

## Not verified

Steps 1 (keyboard), 3, 4 and 5 in a browser.
