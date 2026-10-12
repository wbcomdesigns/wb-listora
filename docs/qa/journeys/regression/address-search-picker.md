---
journey: address-search-picker
plugin: wb-listora
priority: normal
roles: [subscriber, administrator]
covers: [submission-map-picker, geocoding, admin-listing-editor, bc-10355037108, bc-10393550098]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A listing type whose Contact step has an Address field"
estimated_runtime_minutes: 8
covers_card: 9867200436
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Finding an address is a search, not a guess

Sentinel for the location picker's address search (`src/utils/map-picker.js`, shared by the Add Listing wizard and the wp-admin listing editor).

## Background

The picker once geocoded on a debounce after every keystroke and silently applied the first result. Per-keystroke lookups are what OpenStreetMap's Nominatim usage policy forbids (about 1 request per second, no type-ahead); the penalty is the site's IP being blocked for every member. Now: type, press Enter or click Find, pick from the matches. One request per search, and nothing is applied until a match is chosen.

Provider split: Free uses Nominatim (no key); when Google is the live map provider Pro registers `window.wbListoraGeocoder` (script handle `wb-listora-pro-google-geocoder`). Same UI either way.

## Steps

### 1. The field is a combobox
- **Action**: as a member (e.g. subscriber2 via `?autologin=`), open `/add-listing/`, pick a type, reach the step with the Address field.
- **Expect**: inside `.listora-address-search` the input has `role="combobox"`, `aria-expanded="false"`, `aria-autocomplete="list"`, `autocomplete="off"` and `aria-controls` pointing at the `ul.listora-address-search__matches[role=listbox]` (hidden); a secondary button labelled "Find" (`.listora-address-search__btn`, `type="button"`) sits beside the input.
- **Note**: the picker initialises when its step becomes visible, so an address on a later step is not wired until you reach it.

### 2. Typing sends NOTHING
- **Action**: type a dozen characters, wait two seconds.
- **Expect**: zero geocoder requests (network tab, or stub `window.wbListoraGeocoder` and count calls); the match list stays closed. Typing after a list is open closes it without a request.

### 3. Enter and Find search; fewer than 3 characters do not
- **Action**: type "12 High Street", press Enter. Then clear, type "ab", click Find.
- **Expect**: a status "Searching…" then a list of `li.listora-address-search__match[role=option]`, first one `.is-active`; the wizard step does NOT submit or advance. For "ab" nothing is requested.

### 4. Picking a match fills everything from the chosen one
- **Action**: ArrowDown to the SECOND match, press Enter (or click it).
- **Expect**: the input takes a short street address ("350 5th Avenue, New York, NY 10118" shape, or the place name plus city and country when the match has no street, e.g. "Connaught Place, New Delhi, India"), the pin moves, and lat, lng, city, state, country and postcode fill from the chosen match, not the first. The street/address line the owner typed is kept. City, State and Postcode always follow the chosen match; a match with no city part (for example a country or region result) leaves City EMPTY, never the previous place's city (a box the member edited after picking is the only exception).
- **Read-back**: edit a listing whose City is "Brooklyn", pick "India" or another suggestion without a city; `input[name$="[city]"]` value is `""`, and after Save `_listora_city` meta is empty. **fails_on**: 771baeeb. Not verified live.

### 5. Keyboard and mouse
- **Expect**: Up/Down move `.is-active` and update `aria-activedescendant` (wraps at the ends); Escape closes and clears `aria-activedescendant`; clicking a match selects it; clicking outside closes.

### 6. No matches says so
- **Action**: search "zzzzqqxx 99999".
- **Expect**: `.listora-address-search__status[role=status]` reads "No matching addresses. Try a different spelling, or drop the pin on the map." and the map still works: dropping the pin reverse-geocodes, and a pin without a street address is accepted.

### 7. 390px
- **Expect**: at <= 640px the Find button is full width under the input, no horizontal page scroll, options and button >= 40px tall.

### 8. Google provider (Pro, POSITIVE CONTROL for the "must not load" half)
- **Action**: set Settings > Maps provider to Google with a valid key.
- **Expect**: `script#wb-listora-pro-google-geocoder-js` is on the Add Listing page and the same UI returns Google's matches.
- **Control**: with provider back on OpenStreetMap (key left stored), that script must NOT be in the page (`Google_Maps::enqueue_places()` guards on `is_google_live()`): a stored key with OSM selected must not bill the owner.

### 9. wp-admin
- **Action**: as administrator open a listing whose type has a map location field (`post.php?post=<id>&action=edit`).
- **Expect**: the same Find/Enter search on the location metabox (`src/admin/listing-map-picker.js` imports the same picker).

### 10. Existing Location terms are searchable in both wp-admin location UIs (card 10355037108)
- **Action**: as administrator open `post-new.php?post_type=listora_listing`; fixture Location terms `QA-J1-Springfield` (and 30+ other terms so the list is paged). In the Locations meta box (core checklist) type `Springfield` in its "Search Locations" box; in the editor sidebar Locations panel (block-editor taxonomy panel) type `Springfield`.
- **Expect**: both UIs list `QA-J1-Springfield` as a selectable match; selecting it and Update saves it. The sidebar panel's `GET /wp/v2/listora_listing_location?search=Springfield` returns 200 with the term.
- **Read-back**: `wp_get_object_terms( ID, 'listora_listing_location', ['fields'=>'names'] )` = `["QA-J1-Springfield"]`; negative control: search `zzzzqq` shows no match in either.
- **fails_on**: unknown (card 10355037108, no fix commit found). Not verified live.

## Role ladder
Single-role by nature for the search itself (any member who can reach Add Listing; admin for step 9). Control: administrator gets the same picker on the frontend; logged-out sees the login prompt, no picker.

## Fail diagnostics

| Symptom | File |
|---|---|
| Request fires while typing | `src/utils/map-picker.js:218-221` (the `input` listener must only close) |
| Enter submits the wizard step | `src/utils/map-picker.js:198-210` |
| Wrong match applied | `src/utils/map-picker.js:170-190` (`choose( result )`) |
| No-match text differs | `includes/class-assets.php:233`, `src/utils/map-picker.js:172` |
| Google script loads under OSM | `wb-listora-pro/includes/features/class-google-maps.php:240-256` |

## Automated coverage
Browser-side only. `window.wbListoraGeocoder` is the seam to stub for a deterministic run; no live Nominatim calls from an automated walk.

## Not verified
Browser interaction (Playwright not used in this pass). Strings, roles, classes, keyboard handling and the Pro enqueue guard were verified in current source only; the live page has the picker markup built at runtime by JS.
