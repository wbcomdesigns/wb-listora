---
journey: map-clustering-site-default
plugin: wb-listora
priority: high
roles: [administrator, anonymous]
covers: [maps, settings, listing-map-block, card-10230658539]
prerequisites:
  - "The Directory page with <!-- wp:listora/listing-map --> and no showClustering attribute, and enough geocoded listings to cluster"
estimated_runtime_minutes: 6
---

# Marker clustering setting reaches the website, per-map choices still win

Settings > Maps > Marker clustering was read only by `/settings/app-config`: the app obeyed it
and every web map kept clustering, because `showClustering` defaulted to `true` in block.json
(card 10230658539). The attribute now has no default: unset = site setting, set = the block's
choice. Inspector: "Marker Clustering" select - Use site setting / On for this map / Off for this
map. Escape hatch `wb_listora_map_block_clustering`.

## Steps

### 1. Site off, block unset
- **Action**: uncheck Marker clustering, save; open `/listings/` anonymously
- **Expect**: no `.marker-cluster` badges, one pin per listing (pre-fix: a cluster badge)

### 2. Site off, block explicitly On
- **Action**: a page with `{"showClustering":true}`
- **Expect**: cluster badges

### 3. Site on, block unset / block explicitly Off
- **Expect**: `/listings/` clusters; the `{"showClustering":false}` page shows individual pins

### 4. Editor
- **Action**: open the explicit page, Block sidebar > Map Controls
- **Expect**: select reads "Off for this map"; choose "Use site setting" and save - the saved block comment has no `showClustering`

## Pass criteria

1. A map with no choice follows the site setting, on and off.
2. A map with a choice keeps it against both site values.
