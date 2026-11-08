# Map provider registry (2.2.0)

**Status: OPEN plan, drafted 2026-11-08, awaiting owner approval. No code written.**
Card: 10439305996 (Kakao, Naver, AMap, Mapbox, MapTiler, HERE/TomTom, Plus Codes). Raised by a Korean customer building a BuddyNext + Listora site.

## What exists today (read from code, 2.1.1)

| Concern | Where | State |
|---|---|---|
| Which provider is live | `wb-listora.php` ~626-640 | `map_provider` setting, default `osm`, passed through the `wb_listora_map_provider` filter |
| Pro Google | `wb-listora-pro/includes/features/class-google-maps.php` | Answers the filter (`google` only with a key, else `osm`), loads its own JS, `wb_listora_map_config` filter |
| Add-listing picker | `src/utils/map-picker.js` | Already has a JS registry: `window.wbListoraMapPickers[provider]( el, ctx )`; Free falls back to Leaflet/OSM when it returns falsy |
| Listing map block | `src/blocks/listing-map/view.js` | Leaflet only; returns early when `config.provider !== 'osm'` (Google swaps in its own script) |
| Geocoding | `map-picker.js` `geocodeAddress()` / `reverseGeocode()` | Hard-coded Nominatim; Pro Google Places replaces it |
| Tile source | `Map_Settings_Tab::default_tile()`, `map_tile_url` setting | OSM preset prefilled (2.1.1) |

So the picker already has the right seam; the display map and the geocoder do not.

## Decision: two kinds of provider, one registry

1. **Tile providers** (Leaflet stays the engine): Mapbox, MapTiler, HERE, TomTom, Stadia, Geoapify. They need a tile URL template, a key, attribution and a geocoder adapter (REST). No new map JS. Cheap to add and covers the card's western and global names.
2. **SDK providers** (own JS map): Google (exists, moves onto the registry), Kakao, Naver, later AMap / Baidu / Tencent. They need an SDK loader, a key, their own map, picker, marker/cluster code and geocoder.

Both register through one PHP base class plus a filter-filled registry (`wb_listora_map_providers`), per the integration-seam standard: adding a provider needs no core edit.

### PHP contract (Free owns it)
`Map_Provider` abstract class: `slug()`, `label()`, `kind()` (`tiles` | `sdk`), `settings_fields()` (key, optional secret), `is_ready()` (key present), `tile_config()` (tiles kind), `sdk_config()` (script URL, handle, privacy note), `to_display_coords( $lat, $lng )` (identity by default; AMap overrides to GCJ-02), `geocoder()` slug.
`Map_Provider_Registry`: `all()`, `get( $slug )`, `active()`. `active()` replaces the raw read in `wb_listora_get_setting( 'map_provider' )`: a provider that is not ready (no key) falls back to OSM, the same guarantee Google has today, in one place.
Existing `wb_listora_map_provider` and `wb_listora_map_config` filters keep working (rule 3: no default change without a filter, 2 minors).

### JS contract (Free owns it)
Extend the existing `window.wbListoraMapPickers` idea to a `window.wbListoraMapProviders[slug]` object: `{ initMap( el, cfg ), initPicker( el, ctx ), geocode( q ), reverse( lat, lng ) }`. Free ships `osm` and the generic Leaflet tile provider; the listing-map view and the picker both look the provider up instead of branching on `'osm'`. The old `wbListoraMapPickers[provider]` function stays as an alias.

### Geocoding follows the provider
One `geocode()`/`reverse()` per provider, replacing the Nominatim hard-code. Results normalise to the shape `map-picker.js` already consumes (display name, street, city, state, postcode, country, lat, lng) so the address-fill code does not change.

## Where it ships (Free vs Pro)

- **Free**: registry, base class, JS contract, OSM, generic tile provider with preset entries (MapTiler, Mapbox, Stadia, Geoapify as tile URL presets), Settings > Maps provider picker, setup wizard map step, Map_Health wording.
- **Pro**: Google (moves onto the registry, behaviour unchanged), and the SDK providers (Kakao, Naver, then AMap). Pro consumes the Free registry only through the documented surface (INV-3).
- Open question for the owner below: whether Kakao/Naver should be Free instead.

## Plug-and-play rules

- Default stays OSM with the prefilled tile; a fresh site never needs a key.
- A provider with no key is hidden from "ready" and silently falls back to OSM; Settings shows "Add a key to use this provider", never a broken map.
- One Settings > Maps screen: provider dropdown, then only that provider's key field, a link to where the owner gets the key, and one line on what the provider sees (privacy: the visitor's browser calls the provider).

## Coordinates (China)

AMap/Tencent use GCJ-02, Baidu BD-09. Stored coordinates stay WGS-84 (one rule, one storage). Conversion happens at render and at picker save through `to_display_coords()` / its inverse, in one helper, never in views. Needs a round-trip unit test (WGS to GCJ to WGS within a metre) and a fixture of 5 known points. Mainland China also needs the site owner to hold an ICP licence for AMap; documented, not enforced.

## Phases

| Phase | Scope | Release |
|---|---|---|
| 1 | Registry + base class + JS contract; OSM and Google ported onto it with no behaviour change; tests prove Google output is byte-identical | 2.2.0 |
| 2 | Generic tile providers (MapTiler, Mapbox, Stadia, Geoapify) with geocoder adapters | 2.2.0 |
| 3 | Kakao (map, marker, picker, Local API geocode) | 2.2.0 if phase 1 is clean, else 2.2.1 |
| 4 | Naver, then AMap with GCJ-02 | 2.3.0 |
| 5 | Plus Codes (Open Location Code): show on listing detail, accept in search; pure JS/PHP, no provider | 2.3.0 |

## Big-site and entry-point checks (CLAUDE.md rules)

- Three entry points: owner/admin Settings > Maps (new), member picker on Add Listing, REST (`GET /listora/v1/settings` exposes provider and public key only, never a secret). Each phase wires all three or documents the exception.
- No per-row queries; the provider choice is one cached setting read. Map block marker count unchanged (clustering already caps it); each provider's cluster support is a phase acceptance row.
- Mobile 390 and RTL for every provider picker; keys are never printed in markup except the public browser key; keys saved through the existing settings sanitiser.

## Tests and QA

- PHPUnit: registry resolution, not-ready fallback to OSM, filter precedence, coord conversion round trip.
- Journeys: one per provider on Add Listing (pick, drag, address search), listing-map, listing-detail; Google journey must pass unchanged after phase 1.
- Provider SDK calls need real keys, so those journeys are manual-key runs recorded in the card, not CI.

## Open questions for the owner

1. Kakao and Naver in Pro (paid regional value) or Free (they are the default maps in Korea, so Free there would be OSM-only)?
2. Is 2.2.0 scoped to phases 1-3, or phases 1-2 only with Kakao in 2.2.1?
3. Do we have, or can we get, Kakao and Naver developer keys for QA?
4. OK to skip Baidu and Tencent entirely (AMap only for China)?
