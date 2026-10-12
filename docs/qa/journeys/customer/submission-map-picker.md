---
journey: submission-map-picker
plugin: wb-listora
priority: normal
roles: [owner, guest, admin]
covers: [submission-map-picker, geocoding, admin-listing-editor, bc-10355037108, bc-10393550098, card-9867200436, "#9976402618", leaflet-vs-fixed-theme-header, address-place-and-country, card-10355775610]
prerequisites:
  - "A listing type whose Contact/Details step has an Address field"
  - "A theme with a fixed/sticky header whose stacking context is below 1000 (Reign reproduces; BuddyX Pro has a sticky header too)"
  - "Member session {owner} via ?autologin"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/address-search-picker.md
  - ora:regression/submission-map-picker-stacking.md
fails_on: 771baeeb
site_profile: required
---

# Finding an address is a search, and the picker stays inside its box

## Why
The picker (`src/utils/map-picker.js`, shared by the Add Listing wizard and the wp-admin editor) once geocoded after every keystroke and applied the first result silently; per-keystroke lookups violate Nominatim's policy (the site IP gets blocked for every member). Its Leaflet controls (z-index 1000) also painted over fixed theme headers. Now: type, press Enter or click Find, pick a match; the picker has `isolation: isolate` (`blocks/listing-submission/style.css:1756`, `style-rtl.css:1756`).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Provider split: Free uses Nominatim (no key); when Google is the live provider Pro registers `window.wbListoraGeocoder` (script handle `wb-listora-pro-google-geocoder`). Stub `window.wbListoraGeocoder` for a deterministic run; no live Nominatim calls from an automated walk.
- The picker initialises when its step becomes visible, so an address on a later step is not wired until reached.

## Steps

### 1. The field is a combobox
- **Action**: as {owner} open `$SITE_URL/add-listing/`, pick type {type}, reach the step with the Address field.
- **Expect**: inside `.listora-address-search` the input has `role="combobox"`, `aria-expanded="false"`, `aria-autocomplete="list"`, `autocomplete="off"`, `aria-controls` -> the hidden `ul.listora-address-search__matches[role=listbox]`; a secondary `button.listora-address-search__btn[type=button]` "Find" sits beside it.

### 2. Typing sends nothing
- **Action**: type a dozen characters, wait two seconds.
- **Expect**: zero geocoder requests (network tab, or stub call count); the match list stays closed. Typing after a list is open closes it without a request.

### 3. Enter and Find search; fewer than 3 characters do not
- **Action**: type "12 High Street", Enter; then clear, type "ab", click Find.
- **Expect**: status "Searching..." then `li.listora-address-search__match[role=option]` with the first `.is-active`; the wizard step does NOT submit or advance. For "ab" nothing is requested.

### 4. Picking a match fills everything from the chosen one
- **Action**: ArrowDown to the SECOND match, Enter (or click).
- **Expect**: the input takes a short street address ("350 5th Avenue, New York, NY 10118" shape, or place name plus city and country when the match has no street, e.g. "Connaught Place, New Delhi, India"; `formatStreetAddress()` `src/utils/map-picker.js:245`, `wbListora.formatAddress` can reshape it); the pin moves; lat, lng, city, state, country and postcode fill from the chosen match, not the first. The street the owner typed is kept. A match with no city part (a country or region) leaves City EMPTY, never the previous place's city (a box the member edited after picking is the only exception).
- **Read-back**: edit a listing whose City is "Brooklyn", pick a no-city suggestion; `input[name$="[city]"]` is `""` and after Save `_listora_city` meta is empty. **fails_on**: 771baeeb. Not verified live.

### 5. Keyboard and mouse
- **Expect**: Up/Down move `.is-active` and `aria-activedescendant` (wraps); Escape closes and clears `aria-activedescendant`; click selects; click outside closes.

### 6. No matches says so
- **Action**: search "zzzzqqxx 99999".
- **Expect**: `.listora-address-search__status[role=status]` reads "No matching addresses. Try a different spelling, or drop the pin on the map."; dropping the pin reverse-geocodes and a pin without a street address is accepted.

### 7. 390px
- **Expect**: at <= 640px the Find button is full width under the input, options and button >= 40px, no horizontal scroll; the picker is full width and zoom buttons >= 40px (or the theme's tap floor).

### 8. Stacking context
- **Action**: read `getComputedStyle(picker).isolation` on the Details step (repeat on an RTL site).
- **Expect**: `isolate`. **Positive control**: `getComputedStyle(document.querySelector('.listora-submission__fieldset')).isolation` is `auto`.

### 9. Scroll paint order and usability
- **Action**: enter an address so `.leaflet-container` mounts, scroll so the map crosses under the fixed header; click `.leaflet-control-zoom-in`.
- **Expect**: the header paints above the zoom controls and tiles (`document.elementFromPoint()` at a header pixel overlapping the map returns a header element, not `.leaflet-*`); zoom changes and the picker stays about 250px tall (`getBoundingClientRect().height`).

### 10. Google provider (Pro, positive control for the "must not load" half)
- **Action**: Settings > Maps provider = Google with a valid key.
- **Expect**: `script#wb-listora-pro-google-geocoder-js` is on the Add Listing page and the same UI returns Google's matches.
- **Control**: with provider back on OpenStreetMap (key left stored) that script must NOT be in the page (`Google_Maps::enqueue_places()` guards on `is_google_live()`): a stored key with OSM selected must not bill the owner.

### 11. wp-admin
- **Action**: as {admin} open a listing whose type has a map location field (`post.php?post=<id>&action=edit`).
- **Expect**: the same Find/Enter search on the location metabox (`src/admin/listing-map-picker.js` imports the same picker).

## Role ladder
| Actor | Expect |
|---|---|
| {owner} (member who can submit) | steps 1-9 |
| {guest} | login prompt (`.listora-submission__login-prompt`), no picker |
| {admin} (control) | same picker on the frontend, plus step 11 |

## Pass criteria
1. Nothing is geocoded until Enter/Find; a match must be chosen before anything is applied.
2. Fields follow the chosen match; no-city matches leave City empty.
3. The picker is combobox-accessible, usable at 390, and cannot paint over a fixed header.
4. The Google script loads only when Google is the live provider.

## Fail diagnostics
| Symptom | File |
|---|---|
| Request fires while typing | `src/utils/map-picker.js:218-221` (the `input` listener must only close) |
| Enter submits the wizard step | `src/utils/map-picker.js:198-210` |
| Wrong match applied | `src/utils/map-picker.js:170-190` (`choose( result )`) |
| No-match text differs | `includes/class-assets.php:233`, `src/utils/map-picker.js:172` |
| Leaflet over header / map escapes box | `blocks/listing-submission/style.css:1756` (`isolation: isolate`, `position: relative`), `style-rtl.css:1756`; `blocks/listing-map/style.css` for the listing map |
| Google script loads under OSM | `wb-listora-pro/includes/features/class-google-maps.php:240-256` |

## Not verified
Browser interaction for steps 1-7 and 9-11 (markup/strings verified from source only); step 8 was run once (Details step, 1440, light, BuddyX Pro). A fixed-header theme with a low stacking context (Reign), RTL and dark were not run. Location-term search in the wp-admin Locations UIs (card 10355037108) lives in `system/location-terms-follow-the-address.md`.

## Cleanup
Restore provider and key.
