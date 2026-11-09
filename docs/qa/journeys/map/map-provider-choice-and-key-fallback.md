---
journey: map-provider-choice-and-key-fallback
group: map
actor: admin
mode: browser
plugin: wb-listora
priority: high
roles: [admin]
covers: [map-provider, settings-maps, setup-wizard, listing-map, listing-detail, add-listing-picker, "10439305996"]
prerequisites:
  - "Free + Pro active; listora.local data noted before and restored after"
  - "For steps 5-7 a real key for the provider under test (Kakao, Naver, AMap or Google) and the site address registered with that provider; these steps are manual-key runs"
estimated_runtime_minutes: 15
last_verified: 2026-11-09
verified_against: free 2.1.1 + pro 2.1.1
viewports: [1440, 390]
themes: [reign]
site_profile: required
---

# Pick a map provider; no key means OpenStreetMap, a key means that provider

## Why
Providers register in one list. A provider chosen without a key must never blank a map: it falls back to OpenStreetMap in one place. Each provider with a key must take over the listing map, the Add Listing picker, the listing detail map and address search.

## Steps
### 1. The list in Settings and the wizard
- **Action**: open Settings > Maps, then the Setup Wizard map step (`&rerun=1`).
- **Expect**: OpenStreetMap, Google Maps, Kakao Maps, Naver Maps and AMap (Gaode) are all offered in both; with Pro off only OpenStreetMap and a locked Google card show. Each keyed provider has its own key box in Settings > Maps with a link to where the key is created.

### 2. No key falls back
- **Action**: choose Kakao, leave its key empty, save, open `{directory}`.
- **Expect**: the map is the OpenStreetMap one (Leaflet), no "map not available" box, no Kakao script in the page source; Site Health "map tiles" is not worse than before.

### 3. A key switches the engine
- **Action**: save any non-empty key for Kakao, open `{directory}` and a listing.
- **Expect**: the page source carries the provider's SDK URL with that key and no Leaflet; `mapConfig.provider` in the map's `data-wp-context` is `kakao`; on a page with no map block the SDK is not loaded. Repeat with Naver and AMap (AMap also prints its security code before the SDK).

### 4. Back to OpenStreetMap
- **Action**: choose OpenStreetMap, save.
- **Expect**: Leaflet returns everywhere; saved keys are kept.

### 5. Directory map with a real key (manual)
- **Action**: with a valid key and registered site address, open `{directory}`.
- **Expect**: tiles draw; one marker per listing with coordinates; a marker opens a popup with the title and link; Near Me recentres; moving the map shows "Search this area" and pressing it narrows the list; hovering a card raises its marker; Kakao clusters at far zoom. For AMap a listing at a known address sits on the right building (the datum conversion).

### 6. Add Listing picker with a real key (manual)
- **Action**: open `{add_listing}`, search an address, drag the marker, click elsewhere.
- **Expect**: the address box fills street, city and state; latitude and longitude change on every move; saving the listing stores coordinates that Leaflet shows at the same spot (AMap: stored coordinates are the standard ones, not the shifted ones).

### 7. Listing detail map (manual)
- **Action**: open a listing with coordinates, view the Location card.
- **Expect**: the provider's map with one marker at the listing, and the Plus code line under the address.

### 8. Phone width
- **Action**: repeat steps 1-3 at 390.
- **Expect**: no horizontal scroll (`scrollWidth` 390); the key boxes fit the screen.
