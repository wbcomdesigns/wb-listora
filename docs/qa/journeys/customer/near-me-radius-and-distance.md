---
journey: near-me-radius-and-distance
plugin: wb-listora
priority: high
roles: [guest, member, admin]
covers: [near-me, near-me-radius, distance-unit, card-distance, settings-general, bc-10393550206]
prerequisites:
  - "Site reachable at $SITE_URL; Directory page with the Search block's Near me enabled (default)"
  - "Geocoded listings: a point $LAT,$LNG with 5+ listings within 50 km"
  - "Browser context with geolocation granted and position stubbed (Playwright: context.grantPermissions(['geolocation']); context.setGeolocation({latitude:$LAT,longitude:$LNG}))"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes: []
fails_on: 1bbbbf5b
site_profile: required
---

# Near me searches within a radius, shows how far, and obeys the site's distance unit

## Why
A visitor who taps Near me expects nearby results, nearest first, with a distance in the unit the owner chose. Without it, cards sorted by distance show no distance and the radius is invisible.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `$LAT,$LNG` is the fixture point, `$OWNER_RADIUS` and `$UNIT` the settings read back below.
- Record settings to restore: `wp --path=$WP_PATH option get wb_listora_settings --format=json | jq '{near_me_radius,distance_unit}'`.
- Reference counts from the API (no browser), for steps 1, 2 and 4:
  ```bash
  wp --path=$WP_PATH eval 'foreach([1,5,10,25,50] as $r){$q=new WP_REST_Request("GET","/listora/v1/search");$q->set_param("lat",$LAT);$q->set_param("lng",$LNG);$q->set_param("radius",$r);echo "r=$r total=",rest_do_request($q)->get_data()["total"],"\n";}'
  ```
  Counts must be non-decreasing; the browser must match them (re-read just before step 1). This journey owns Near me; `search-with-filters` does not repeat it.

## Steps

### 1. Near me adds position, radius and nearest-first (guest, 1440)
- **Action**: open `$SITE_URL/listings/`; click `.listora-search__near-me` (aria-label "Find listings near your location").
- **Expect**: URL becomes `?listora_lat=<3 decimals>&listora_lng=<3 decimals>&listora_radius=$OWNER_RADIUS&listora_sort=distance` (exact position is not in the URL); sort reads "Nearest"; picker `#listora-radius` (label "Near me, within") appears with the five options "1, 5, 10, 25, 50 $UNIT" and the default selected; Filters badge counts 1; total drops below the unfiltered total and equals the reference count for the default radius.
- **Positive control**: before clicking, the picker has `is-hidden` and the total is the full directory total.

### 2. Visitor changes the radius
- **Action**: choose "25", then "1".
- **Expect**: page reloads with `listora_radius=25` then `=1`; totals equal the reference counts; the picker keeps the chosen value after reload; `listora_page` resets (absent).

### 3. Each card says how far
- **Expect**: every `.listora-card` has `.listora-card__distance` reading "<n.n> $UNIT away" (one decimal); with Nearest sort the numbers never decrease down the list; the first distance is <= the chosen radius.
- **Positive control**: on `/listings/` without Near me no card has `.listora-card__distance`.
- **Unit read-back**: iterate `distance_unit` = `km` and `mi`; with `mi` every card reads "<n.n> mi away" and the number is the km value divided by 1.609. **fails_on**: 1bbbbf5b (cards sorted by distance but showed none). Not verified live.

### 4. Owner default and unit (`{admin}`, wp-admin)
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=general`. Set "Distance unit" = Miles, "Near me radius" = 10, Save Changes.
- **Expect**: success notice; open `/listings/?listora_lat=<..>&listora_lng=<..>` WITHOUT `listora_radius`: picker selects "10 mi", options "1 mi, 5 mi, 10 mi, 25 mi, 50 mi", cards read "<n.n> mi away" (~0.62 x the earlier km value).
- **Action**: set the radius to 15 (not a preset), Save.
- **Expect**: the picker offers "15 mi" in sorted position and selects it (`wb_listora_near_me_radius_options()`).
- **Boundary**: 0, -1, 501, `abc`, empty - the field clamps to min 1 / max 500 on save and never stores 0.
- **Restore** the recorded values.

### 5. Bad input never breaks the page
- **Action**: `?listora_lat=abc&listora_lng=1`, `?listora_lat=999&listora_lng=1`, `?listora_lat=40.7&listora_lng=-74&listora_radius=-5`, and `?listora_lat=&listora_lng=`.
- **Expect**: HTTP 200 each time; the first, second and fourth ignore Near me entirely (full directory, picker hidden); the third clamps the radius to >= 1 and shows results; no PHP notice in `debug.log`.

### 6. Denied location
- **Action**: new context with geolocation denied; click the button. (Over plain http the button shows "Location access denied. Use the location search instead.")
- **Expect**: `.listora-search__error` (role=alert) shows the translated message; URL unchanged; the directory still lists everything.

### 7. Map button parity
- **Action**: on a page with the Map block click `.listora-map__near-me-btn`.
- **Expect**: same URL contract as step 1; map and grid agree on the total.

### 8. Sibling surfaces, scale and edge counts
- **Action**: `GET /listora/v1/search?lat=$LAT&lng=$LNG&radius=50` and the Map block's search-this-area for the same point.
- **Expect**: REST `total`, grid count and map marker count agree (map cap notice only when capped). With 0 listings in radius the Directory empty state shows; with exactly 1 the card shows its distance. Seed a QA batch of 2,000 geocoded listings and confirm first paint stays under 1 s with `LIMIT` and index use (EXPLAIN on `${PREFIX}listora_geo`). Not verified live.

### 9. 390px
- **Expect**: the picker wraps under the search bar, its select is >= 40px tall, no horizontal page scroll, the distance text does not overlap the rating.

## Role ladder
| Rung | Expect |
|---|---|
| guest | steps 1-3, 5-9 |
| `{owner}` | identical; the position lives only in the URL (no usermeta row with lat/lng is created) |
| `{admin}` | step 4 (settings) plus identical front end |

## Pass criteria
1. Near me yields lat/lng/radius/sort in the URL and a picker with the five radii; totals equal the API's reference counts.
2. Owner default seeds the picker; non-preset owner values are added; bounds hold.
3. Cards show distance only when a position is present, in the site's unit.
4. Invalid coordinates are ignored without error.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Picker never appears | `state.hasUserLocation` false (lat/lng not in IAPI state) | `includes/class-assets.php`, `src/interactivity/store.js` |
| Total ignores radius | radius dropped before `Search_Engine::search` | `includes/search/search-url-helpers.php`, `includes/rest/class-search-controller.php` |
| "km away" with unit = mi | card template reads wrong setting | `templates/blocks/listing-card/card-content.php` (~153) |
| Radius 0 stored | no bounds on save | `includes/admin/class-settings-page.php` (~136, ~908) |

## Not verified
Step 3 unit iteration, step 8 and the denied-location context were not run live.

## Cleanup
Restore `near_me_radius` and `distance_unit`; delete any QA scale seed.
