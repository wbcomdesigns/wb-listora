---
journey: near-me-radius
plugin: wb-listora
priority: high
roles: [anonymous, administrator]
covers: [near-me-search, near-me-radius-setting, card-distance, locations-panel-help]
prerequisites:
  - "Demo content with geocoded listings and the Directory page (/listings/) carrying the search block with Near me on"
  - "Settings > General > Near me radius = 5 (default), Distance unit = km"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Near me searches around the visitor, within a radius they can change

Cards 10351203714 (owner default + visitor choice) and 10355019529. URL args are `listora_lat`, `listora_lng` (3 decimals), `listora_radius`, `listora_sort=distance` (`src/interactivity/store.js:837-857`, parsed by `wb_listora_search_geo_from_url()` `includes/search/search-url-helpers.php:265`, radius clamped 1-500). The picker `select#listora-radius.listora-search__radius-select` lists `wb_listora_near_me_radius_options()` (`wb-listora.php:681`): 1, 5, 10, 25, 50 plus the owner's default, labelled in the site unit ("5 km" / "mi"); it is hidden (`.listora-search__radius.is-hidden`) until a position exists. The trigger is `button.listora-search__near-me` (aria-label "Find listings near your location") in the search bar; the map also has its own `button.listora-map__near-me-btn` "Near Me".

Verified via: Playwright at 1440 with `navigator.geolocation.getCurrentPosition` stubbed in an init script (browser permission cannot be granted by the driver) and a 390 measurement of the picker.

## Steps

### 1. Near me filters by distance
- **Action**: open `/listings/`, click `.listora-search__near-me` with geolocation stubbed to 40.7128, -74.0060.
- **Expect**: URL becomes `/listings/?listora_sort=distance&listora_lat=40.713&listora_lng=-74.006&listora_radius=5`; count drops from "Showing 1-20 of 83 listings" to "of 51 listings"; `select#listora-sort` value `distance`; `#listora-radius` visible with options "1 km, 5 km, 10 km, 25 km, 50 km" and `5` selected; the Filters button reads "Filters 1"; cards show "0.2 km away", "0.6 km away" in the site's unit.
- **Control**: before the click the radius picker is `is-hidden` and the count is 83.

### 2. Visitor widens the radius
- **Action**: select `50` in `#listora-radius`.
- **Expect**: page reloads with `listora_radius=50`, more results than 51, picker still reads 50.

### 3. Clear all
- **Action**: expand Filters, click `button.listora-search__clear-all` ("Clear All Filters").
- **Expect**: URL back to `/listings/`, lat/lng/radius and the Nearest sort gone, picker hidden again (observed).

### 4. Owner default
- **Action**: Settings > General > "Near me radius" (`#near_me_radius`) = 25, save.
- **Expect**: a Near me URL without `listora_radius` selects 25; the option list always contains the owner's value. Restore 5.
- **Fail**: `includes/search/search-url-helpers.php:265`, `src/interactivity/store.js` (`searchImmediate`, `setSearchRadius`), `templates/blocks/listing-search/search.php:117-135`.

### 5. Unit
- **Action**: Distance unit = mi (Settings > General).
- **Expect**: picker labels read "5 mi", card distance in miles. Restore km.

### 6. Locations panel help (admin)
- **Action**: edit any listing in the block editor, open the Locations panel (taxonomy `listora_listing_location`).
- **Expect**: a help line "Controls where this listing appears in Browse by Location and location filters. The address shown on the listing comes from its Address field." above the term list (`assets/js/admin/location-panel-help.js`).

### 7. 390px
- **Expect**: picker 345 x 43.8 px at x=15 (observed), no horizontal page scroll (375 <= 390).

### 8. Role ladder
| Actor | Result |
|---|---|
| Anonymous | steps 1-3, 5 |
| Logged-in member | identical |
| Owner of a listing in range | listing appears with a distance on its card like any other |
| Moderator | same public behaviour |
| Admin (control) | steps 4, 5, 6 (settings) |

### 9. Large site
- **Expect**: result counts come from `search_index` + geo (51 of 83 published); with 2809 listing rows (most not published) the count and pages stay on the published set; page links keep `listora_lat/lng/radius`.

## Pass criteria
Near me narrows results to the radius; the radius is changeable by the visitor and defaulted by the owner; distance shows in the site unit.
