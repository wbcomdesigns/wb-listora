---
group: map
plugin: wb-listora
scope: Where listings sit on a map - the Listing Map block and its popups, tile source and provider, starting view, marker clustering and cap, Near me radius and distance, the single-listing map and the Add Listing picker data, and the Location (Country > State > City) terms derived from the address
journeys: 7
site_profile: required
---
# Map: what an agent checks here

## Scope
Covers the Listing Map block (`blocks/listing-map/render.php`, `templates/blocks/listing-map/map.php`, `src/blocks/listing-map/view.js`) on the Directory page next to the search and grid blocks, Settings > Maps, the tile/provider contract (`wb_listora_get_map_tiles()`), the Near me button and radius select as they affect the map and the "N km away" line, the single-listing sidebar map `#listora-detail-map`, the Add Listing map picker as a data consumer (tile url, start view), the Location taxonomy kept in step with `_listora_address`, and `wp listora repair-locations`.

Does NOT cover (neighbouring owner in brackets):
- Search bar, filter panel, keyword/category/type filters, paging and the empty state of the grid (directory-search). Here they matter only as "the map agrees with the grid".
- The card itself and its distance chip styling (browse-blocks); only the km/mi text and number are checked here.
- The address search combobox inside the Add Listing picker, its stacking and keyboard rules (submission/submission-map-picker).
- Google Maps rendering, the Google API key and the Google info window (Pro: `wb-listora-pro/docs/qa/journeys/map/_group.md`).
- Listing detail layout around the sidebar map (listing-detail), JSON-LD geo (seo-schema), the REST `/settings/maps` payload contract (integrations-api).
- Setup wizard Maps step (settings-setup).

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| S1 | Directory page with map, no filters | `$SITE_URL{directory}` (page holds Listing Search + Listing Grid + Listing Map blocks) | guest, {owner}, {other}, {editor}, {admin} (identical) | `blocks/listing-map/render.php`, `templates/blocks/listing-map/map.php`, `src/blocks/listing-map/view.js` |
| S2 | Directory with filters and bounds in the URL | `{directory}?listora_keyword=&listora_type=&listora_category=&listora_location=&listora_sort=` plus bounds/near-me parameters written by "Search this area" and Near me | all | `wb_listora_search_args_from_url()`, same files as S1 |
| S3 | Directory, Near me active | S1, click Near me in the search bar (`.listora-search__near-me`) or on the map (`.listora-map__near-me-btn`), allow location | all | `templates/blocks/listing-search/search.php`, `search-bar.php`, `store.js` `actions.nearMe` |
| S4 | Directory, geolocation denied | S1, click Near me with location blocked in the browser | all | `store.js` `actions.nearMe` |
| S5 | Marker popup open | S1, click a marker | all | `view.js` popup builder (`.listora-map__popup`) |
| S6 | Map block with no tile source | clear `map_tile_url` (Settings > Maps), reload S1 | guest, {admin} (admin sees a CTA, guest does not) | `render.php` empty-state branch |
| S7 | Map block capped (more listings than Max markers) | set Max markers below the mappable count, reload S1 | all | `render.php`, `map.php` (`.listora-map__notice`) |
| S8 | Single-listing page sidebar map | `$SITE_URL/listing/{listing}/` > sidebar "Location" card | all | `templates/blocks/listing-detail/sidebar.php` (`#listora-detail-map`) |
| S9 | Add Listing map picker (data only) | `$SITE_URL{add_listing}` > Basic Info/Details map field | {owner}, {admin} | `includes/submission-field-renderer.php` (`#listora-map-picker-{key}`), `src/utils/map-picker.js` |
| S10 | Settings > Maps | `$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=maps` | {admin} | `includes/admin/class-map-settings-tab.php` |
| S11 | Settings > General, "Distance unit" and "Near me radius" | `.../admin.php?page=listora-settings&tab=general` | {admin} | `includes/admin/class-settings-page.php` (`render_general_tab`) |
| S12 | wp-admin listing edit: Locations panel | `$SITE_URL/wp-admin/post.php?post={listing_id}&action=edit` > "Locations" meta box (`listora_location` taxonomy) | {admin}, {editor} | core taxonomy box, terms written by `Meta_Handler::sync_location_terms()` |
| S13 | Block editor: Listing Map inspector | page editor > select Listing Map block | {editor}, {admin} | `src/blocks/listing-map/index.js` |
| S14 | CLI | `wp listora repair-locations [--execute] [--format=csv]` | shell | `includes/class-cli-commands.php` |

## Components on each surface
Check every item on every surface where it appears; this is the MICRO_CHECKS sweep list.

**Map block (S1-S7)** root `.listora-map-wrapper.listora-block` carrying `data-wp-interactive="listora/directory"` and a JSON `data-wp-context` with `mapConfig`.
1. Skip link `a.listora-sr-only.listora-sr-only--focusable` "Skip map, go to listing results" targeting `#listora-after-map`.
2. Map canvas `div.listora-map#listora-map-{n}[role=application]` with `aria-label="Map showing N listing locations"` and inline `height` (default 450px, block attribute `height`).
3. Leaflet chrome (when provider is OSM): zoom control, attribution line, tile layer, marker pins (`.listora-marker`, 40x40 divIcon coloured by listing type), clusters (`.marker-cluster`) when clustering is on.
4. Control cluster `.listora-map__controls` > `button.listora-btn--secondary.listora-map__near-me-btn` ("Near Me", aria-label "Find listings near your location"); only when block attribute `showNearMe` is on.
5. `button.listora-map__search-area-btn` ("Search this area", aria-label "Search listings in this map area"); rendered only when Settings > Maps "Search on drag" is on, `display:none` until the map is moved.
6. Cap notice `p.listora-map__notice[role=status]` ("Showing X of Y on the map, zoom in to see more"), `hidden` unless markers drawn < mappable total.
7. Anchor `span#listora-after-map`.
8. Popup (S5) `.listora-map__popup`: close button `.listora-map__popup-close` (aria-label "Close"), optional image `img.listora-map__popup-image` (alt = title), title `.listora-map__popup-title`, meta `.listora-map__popup-meta` (rating `.listora-map__popup-rating` "★ n.n", Featured badge `.listora-badge--featured`, type badge `.listora-badge--type.listora-type--{slug}`), link `a.listora-map__popup-link` "View Details".
9. Empty state (S6) `wb_listora_render_empty_state`: map icon, title "The map is not available.", description + CTA "Open Map settings" only for users with `manage_listora_settings`.

**Near me radius row (S3)** `.listora-search__radius` (hidden until `state.hasUserLocation`): label "Near me, within", `select#listora-radius.listora-search__radius-select` with options from `wb_listora_near_me_radius_options()` (1, 5, 10, 25, 50 plus the owner default), each labelled `N km|mi`.

**Cards in Near me mode (S3)** `span.listora-card__distance` "X.X km|mi away" (one decimal).

**Single-listing map (S8)** card `.listora-detail__map-card`: h3 "Location", `div#listora-detail-map.listora-detail__map-embed--sidebar` with `data-tile-url`, `data-tile-attribution`, lat/lng, address line `.listora-detail__map-address`, button `a.listora-detail__map-directions` "Get directions".

**Add Listing picker (S9)** `.listora-submission__map-field`: address input (`placeholder "Enter address..."`), `.listora-submission__map-picker` with `data-provider`, `data-tile-url`, `data-tile-attribution`, `data-default-lat|lng|zoom`, hidden coordinate inputs `.listora-submission__map-coords`, address part inputs `.listora-submission__map-address-field` (City, State / Region, ZIP / Postal Code). The "Find" combobox is owned by submission.

**Settings > Maps (S10)** panes `.listora-settings-block`:
1. "Map Provider": radios OpenStreetMap / Google Maps (Google disabled on Free with hint "requires the Pro add-on."), tile preset picker, `#wb_listora_map_tile_url` ("Map tile URL"), `#wb_listora_map_tile_attribution` ("Tile attribution", shown for custom presets).
2. "Where maps start": "Find a place" search `#listora-map-center-search`, draggable picker `.listora-settings-map__picker`, collapsed `details.listora-settings-map__advanced` ("Enter coordinates"): `#map_default_lat`, `#map_default_lng`, `#map_default_zoom` (1-20).
3. "Options": Marker clustering checkbox (`map_clustering`), Search on drag checkbox (`map_search_on_drag`), `#map_max_markers`.

**Settings > General (S11)** radios `distance_unit` km / mi, `#near_me_radius` (1-500).

**Block inspector (S13)** controls for height, default zoom, center, Show Near Me, Show Fullscreen, Marker clustering.

## States to force
| Surface | State | How |
|---|---|---|
| S1 | N = 0 mappable listings | filter to a keyword with no match (`?listora_keyword=$NOMATCH`); markers 0, aria-label "Map showing 0 listing locations", no notice |
| S1 | N = 1 / N > 1 | filter to one listing / unfiltered |
| S1 | Clustering on / off | Settings > Maps "Marker clustering"; per-block override in S13 |
| S1 | Dense (cluster visible) | seed 30 QA listings within a few hundred metres (fixture below) |
| S1 | Two map blocks on one page | page with two `wp:listora/listing-map` blocks (checks duplicate `#listora-after-map` and notice handling) |
| S6 | No tile source | `wp option patch update wb_listora_settings map_tile_url ''` with provider osm; restore afterwards |
| S7 | Marker cap | `wp option patch update wb_listora_settings map_max_markers 3` with more than 3 mappable listings |
| S3 | Near me with a position | Playwright geolocation permission granted with a fixed lat/lng near the fixture cluster |
| S4 | Location denied | Playwright geolocation permission denied |
| S1-S3 | km vs mi | Settings > General "Distance unit" |
| S1 | Default start view | clear block centre; set Settings > Maps start view; a block with its own centre wins |
| S5 | Popup with and without image | listing with featured image / listing without; untitled listing resolves to "Listing #{id}" |
| S1 | RTL | not supported by journeys today; listed in Not covered yet |
| any | Dark mode / unbridged theme | toggle theme dark mode; `theme.unbridged` from the site profile |
| S1 | Loading / error | throttle the tile host; block `/wp-json/listora/v1/search` for the "Search this area" path |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| `map_provider` (Maps > Map Provider) | osm, google | osm: Leaflet CSS/JS enqueued, tile layer drawn. google (Pro with key): Leaflet not enqueued, Google basemap on S1, S8, S9. google without Pro or key: falls back to osm (S1), Settings radio disabled or "enter your API key" hint |
| `map_tile_url`, `map_tile_attribution` (Maps) | empty, preset, custom URL | empty with osm: S6 empty state on every map block; set: `data-tile-url` on S8/S9 and tiles on S1 carry the same URL; attribution text appears bottom-right of the map |
| `map_default_lat`, `map_default_lng`, `map_default_zoom` (Maps > Where maps start) | decimals, 1-20 | S1 opens on this view when the block has no centre; S8 and S9 `data-default-*` carry the same numbers |
| `map_clustering` (Maps > Options) | on / off | S1 groups nearby pins into numbered clusters when on and shows single pins when off; a block with its own Marker clustering choice ignores the setting; REST `/settings/maps` reports the same value |
| `map_search_on_drag` (Maps > Options) | on / off | "Search this area" button exists in the DOM only when on |
| `map_max_markers` (Maps > Options) | integer >= 1 | S1 draws at most this many pins; S7 notice appears when mappable total is larger |
| `distance_unit` (General) | km, mi | S3 radius options and cards read "km"/"mi" consistently; distance numbers are the same value in the other unit conversion |
| `near_me_radius` (General) | 1-500 | S3 radius select preselects this value when the URL carries none, and it is always an option |
| Block attributes `showNearMe`, `showClustering`, `height`, `defaultZoom`, `centerLat|Lng` (S13) | per block | S1 button presence, clustering, canvas height, opening view |
| Filters `wb_listora_map_config`, `wb_listora_map_block_clustering`, `wb_listora_map_query_args`, `wb_listora_near_me_radius_options` | code | map provider swap, clustering default, marker query, radius list |

## Fixtures and setup
- Personas: `bash bin/qa-fixtures.sh` (creates {owner}, {other}, {editor}, {admin}). Resolve placeholders from `docs/qa/sites/<site-host>.json` (`QA_SITE=<site-host>`).
- Pages: `wp eval 'echo \WBListora\Core\Page_Registry::get_id("directory");'` (also `add_listing`, `dashboard`). The Directory must hold search + grid + map blocks; if not, create a QA page `QA-map` with all three and delete it afterwards.
- Seed: QA-prefixed listings with coordinates, for example `wp post create --post_type=listora_listing --post_status=publish --post_title="QA-map 01"` then `wp post meta update {id} _listora_address '{...lat,lng...}'` (or use the demo importer). Seed 3 listings at distinct places, 30 within 500 m of one point (cluster), 1 with no coordinates (in the grid, not on the map), 1 with a featured image and 1 without.
- Record original values first: `wp option get wb_listora_settings --format=json > $SCRATCH/settings-before.json` (scratch dir under `~/work-artifacts/scratch/`).
- Settings used by this group: `map_provider`, `map_tile_url`, `map_tile_attribution`, `map_default_lat|lng|zoom`, `map_clustering`, `map_search_on_drag`, `map_max_markers`, `distance_unit`, `near_me_radius`.
- Restore afterwards: write the saved settings JSON back with `wp option update wb_listora_settings --format=json < file`, delete `QA-map*` posts and the QA page, reset the Playwright geolocation permission, set `wb_listora_settings.distance_unit` back.

## Run order
Browser journeys first, in the order a visitor meets the map, then cli.
1. `map-renders-and-agrees-with-grid.md` - mode browser, critical: map renders, popup, markers equal the grid's search, cap notice, bounds.
2. `map-provider-single-engine-and-defaults.md` - mode browser, high: one provider and the same start view on picker, detail map, directory map and Settings preview.
3. `near-me-radius-and-distance.md` - mode browser, high: Near me adds position, radius select, "X km|mi away", unit setting, denied location.
4. `map-clustering-site-default.md` - mode mixed, high: Marker clustering setting reaches the site; a block's own choice wins.
5. `location-terms-follow-the-address.md` - mode mixed, high: Country > State > City terms follow the address on every write surface.
6. `map-tiles-survive-upgrade.md` - mode cli, high: removing the OSM default tile does not blank existing maps.
7. `repair-locations-command.md` - mode cli, high: dry run first, execute restores addresses.
Then run: `wb-listora-pro/docs/qa/journeys/map/_group.md` (Google Maps).

## Group-specific micro checks
| id | Check |
|---|---|
| G-01 | The canvas `aria-label` on S1 reads "Map showing N listing locations" where N equals the number of pins in `mapConfig.markers` (drawn), not the mappable total in the notice. |
| G-02 | When the notice is visible, its first number equals the pin count and its second equals the grid's "N results" count minus listings that have no coordinates; both use thousands separators (`number_format_i18n` on first paint, `toLocaleString` after a client search - compare the two for a 4-digit total). |
| G-03 | After a client-side filter that shrinks results below Max markers, `.listora-map__notice` becomes `hidden` and its text is emptied (no stale "Showing 3 of 9"). |
| G-04 | `.listora-map__search-area-btn` is not in the DOM when Search on drag is off, and is `display:none` on first paint when on; it appears only after a pan or zoom, centred at the top of the wrapper and never overlapping `.listora-map__near-me-btn` at 390. |
| G-05 | `.listora-map__near-me-btn` is absent when the block's Show Near Me is off, and its tap target is at least 40 x 40 at 390 (icon 16 px inside a `listora-btn`). |
| G-06 | The skip link is the first Tab stop inside the wrapper, becomes visible on focus, and `#listora-after-map` exists exactly once. With two map blocks on one page the id is duplicated (expect a finding) and `view.js` reads `document.querySelector('.listora-map__notice')` so only the first map's notice is updated. |
| G-07 | Popup image `alt` equals the popup title; for an untitled listing both read "Listing #{id}", never empty; the image is `medium` size and not stretched (natural ratio kept, cover area about 241 x 80). |
| G-08 | Popup rating shows one decimal ("★ 4.0", not "★ 4"), is absent for a listing with no rating, and the Featured badge appears only for featured listings. |
| G-09 | The pin colour of a listing equals its listing type colour, and a type with no colour falls back to `#0073aa`; two listings of different types never share a pin colour unless the types do. |
| G-10 | With clustering on, a cluster's number equals the sum of listings inside it and expands on click; with clustering off no `.marker-cluster` element exists even at maximum density. |
| G-11 | Popup close: the `x` button, Escape and a click on empty map each close it, and focus returns to the pin that opened it. |
| G-12 | S6: a guest sees the title "The map is not available." with no description and no button; an {admin} sees the description and a working "Open Map settings" link to the Maps tab. The wrapper height does not collapse to zero. |
| G-13 | On a Free site with provider `google` stored, the Settings radio for Google is `disabled`, the hint reads "requires the Pro add-on.", and S1 still draws the OSM tiles (never a grey canvas). |
| G-14 | `data-tile-url` on `#listora-detail-map`, on `.listora-submission__map-picker` and in the Directory `mapConfig.tileUrl` are byte-identical to the saved `map_tile_url`; the attribution line shown on each map is identical too. |
| G-15 | The start view numbers on S8/S9 (`data-default-lat|lng|zoom`) equal the Settings > Maps fields; the Settings preview pin sits on the same coordinates after Save and reload. |
| G-16 | Radius select: the option equal to the owner default (`near_me_radius`) is present even if it is not in 1/5/10/25/50, options are sorted ascending and unique, and every label ends in the current unit. |
| G-17 | Cards in Near me mode show "X.X km away" / "X.X mi away" with exactly one decimal, sorted nearest first (non-decreasing); a listing 0.04 km away reads "0.0", not blank. |
| G-18 | Block inspector offers "Show Fullscreen", but neither first paint nor the map exposes a fullscreen control in any state (`$show_fullscreen` is read in `render.php` and never output): record as a dead setting unless a control exists. |
| G-19 | The Locations taxonomy panel on S12 lists the same Country, State and City as the address saved on the listing, in hierarchy order, and the Directory location filter lists the same names. |
| G-20 | The Settings > Maps number fields reject out-of-range input: zoom 0 and 21 are refused, Max markers 0 is stored as at least 1 (the block uses `max(1, ...)`). |

## Known defects (expected-fail)
- `wb-listora-pro/docs/qa/journeys/map/google-maps-render.md` step 5: Google info window has no image although marker data carries `image` (owner decision pending on parity). Free OSM popup shows the image.
- No Free-folder step is marked `expected-fail`. G-18 (Show Fullscreen has no frontend control) and G-06 (duplicate `#listora-after-map` with two maps) are read from source and are not yet tracked in a journey.

## Not covered yet
- Show Fullscreen attribute (dead control) and two map blocks on one page (duplicate id, notice/search-area handled by first match only).
- RTL: no step mirrors the control cluster, popup close button or notice.
- Marker keyboard access: pins are `divIcon`s; no journey tabs to a pin, opens it with Enter or checks the popup focus order.
- Block-level `defaultZoom`, `centerLat|Lng`, `height` attributes and the `wb_listora_map_query_args` filter have no step.
- Fresh-install "no tile source" state S6 (empty state copy and the admin CTA) is only mentioned inside map-tiles-survive-upgrade step 4, never opened in a browser.
- Marker drag in the Settings "Where maps start" picker and its write to the three fields.
