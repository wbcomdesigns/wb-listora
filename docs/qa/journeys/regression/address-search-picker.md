---
journey: address-search-picker
plugin: wb-listora
priority: normal
roles: [subscriber, administrator]
covers: [submission-map-picker, geocoding, admin-listing-editor]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A listing type whose Contact step has an Address field"
estimated_runtime_minutes: 8
covers_card: 9867200436
---

# Finding an address is a search, not a guess

Regression sentinel for the location picker's address search.

## Background

The picker geocoded on an 800ms debounce after **every keystroke** and silently applied the **first** result. Two problems:

- A member typing "12 High Street" got a pin on whichever High Street the geocoder happened to rank first, with no sign that a choice had been made and no way to correct it except dragging the pin.
- Per-keystroke lookups are exactly what OpenStreetMap's Nominatim usage policy forbids (roughly 1 request/second, no type-ahead). The penalty is the **site's IP** being blocked, which breaks geocoding for every member, not just the one typing.

Now: type, press Enter or click Find, pick from the matches. One request per search.

Provider split: Free uses Nominatim (no key, no billing, works on every install); a site where Google is the live map provider gets Google's index instead, through Pro registering `window.wbListoraGeocoder`. Same UI either way.

## Steps

### 1. The field is a combobox
- **Action**: Add Listing → pick a type → reach the step with the Address field.
- **Expect**: a Find button beside the input; the input has `role="combobox"`, `aria-expanded="false"`, `aria-controls` pointing at the listbox, and `autocomplete="off"`.
- **Note**: the picker initialises when its step becomes visible, so an address field on a later step is not wired until you get there.

### 2. Typing sends NOTHING
- **Action**: type a dozen characters, wait two seconds.
- **Expect**: **zero** geocoder requests. Watch the network tab, or stub `window.wbListoraGeocoder` and count calls. This is the policy compliance and it is the easiest thing to regress by "improving" the UX back to type-ahead.

### 3. Enter searches and offers the matches
- **Action**: type an address that is genuinely ambiguous ("12 High Street"), press Enter.
- **Expect**: a list of candidates, first one active. The wizard step must **not** submit — Enter is intercepted.

### 4. Picking a match fills everything
- **Action**: arrow down to the SECOND match, press Enter.
- **Expect**: the input takes that match's formatted address, the pin moves, and lat, lng, city, state, country and postcode all fill from **the one chosen** — not the first. That distinction is the whole card.

### 5. Keyboard and mouse both work
- **Expect**: Up/Down move the active option and update `aria-activedescendant`; Escape closes; clicking a match selects it; clicking outside closes.

### 6. No matches says so
- **Action**: search for nonsense.
- **Expect**: "No matching addresses. Try a different spelling, or drop the pin on the map." — and the map is still usable, because dropping the pin still reverse-geocodes.

### 7. 390px
- **Expect**: the Find button goes full-width under the input, no horizontal page scroll, options at least 40px tall.

### 8. Google provider
- **Action**: set Maps provider to Google with a valid key.
- **Expect**: `wb-listora-pro-google-geocoder` is enqueued and the same UI returns Google's matches. With the provider back on OSM, or the key removed, that script must **not** load — a stored key with OSM selected must not bill the owner.

### 9. wp-admin
- **Expect**: the same search on the listing editor's location metabox. It shares this picker; that is why the picker was extracted in 1.6.0.

## Automated coverage

The behaviour is browser-side and is verified here. `window.wbListoraGeocoder` is the seam to stub for a deterministic run — no live Nominatim calls from an automated walk, which the policy also asks of us.
