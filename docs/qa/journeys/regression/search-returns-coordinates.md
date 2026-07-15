---
journey: search-returns-coordinates
plugin: wb-listora
priority: high
roles: [anonymous]
covers:
  - "P25: /search rows carry geo{lat,lng} so a map client can plot them"
  - "geo is batched — one query per page, never per-row"
  - "geo is null (not 0,0) when a listing has no geocoded row"
prerequisites:
  - "Seeded demo data (wp listora demo seed --pack=all)"
  - "At least one listing with a geocoded row in {prefix}listora_geo"
estimated_runtime_minutes: 2
---

# /search returns coordinates

`/search` returned **no** `lat`/`lng`/`geo` on any row, so a native map client had nothing to
plot — `distance` only appears when the caller passes `lat`/`lng`, and a scalar distance cannot
place a pin. The web `listing-map` block never caught this because it emits its own markers blob
from `render.php` and never calls `/search`; the REST map surface was silently coordinate-less.

This journey is the sentinel. If it fails, the app's map goes back to rendering tiles with zero pins.

## Steps

### 1. A bbox query returns rows carrying coordinates

```bash
curl -s "http://listora.local/wp-json/listora/v1/search?per_page=3&bounds%5Bne_lat%5D=40.92&bounds%5Bne_lng%5D=-73.70&bounds%5Bsw_lat%5D=40.49&bounds%5Bsw_lng%5D=-74.26"
```

**Assert:** `total` > 0, and **every** returned listing has a `geo` key.
**Assert:** for a geocoded listing, `geo.lat` and `geo.lng` are **numbers, not strings**
(MySQL returns DECIMAL as a string — a client must not have to coerce a coordinate).
**Assert:** the coordinates fall inside the requested bbox.

FAIL → `geo` absent means the SELECT-list change in `Search_Controller::hydrate_listings()` was
reverted. `geo` present but string-typed means the `(float)` cast was dropped.

### 2. Coordinates are batched — one query per page, never per row

```bash
wp eval '
$count = 0;
add_filter("query", function($q) use (&$count) {
    if ( false !== strpos($q, "listora_geo") && false !== strpos($q, "SELECT") ) { $count++; }
    return $q;
});
$req = new WP_REST_Request("GET", "/listora/v1/search");
$req->set_param("per_page", 20);
$res = rest_do_request($req);
echo "rows:" . count($res->get_data()["listings"]) . " geo_queries:" . $count . "\n";
'
```

**Assert:** `rows:20` and **`geo_queries:1`**.

FAIL → any count > 1 means the batch prime was bypassed and the geo read drifted into the per-row
loop. At `per_page=20` that is 20 queries per page; on a 2000-listing directory it is the N+1 that
the big-site readiness rule exists to prevent. Fix by restoring the batched `$geo_map` fetch beside
the ratings batch, not by adding a cache around a per-row lookup.

### 3. An un-geocoded listing reports `geo: null`, never `0,0`

```bash
# Pick a listing id, remove its geo row, re-query, then RESTORE it.
wp eval '
global $wpdb;
$p = $wpdb->prefix . "listora_";
$row = $wpdb->get_row( "SELECT * FROM {$p}geo LIMIT 1", ARRAY_A );
echo "stash:" . wp_json_encode( $row ) . "\n";
$wpdb->delete( "{$p}geo", array( "listing_id" => $row["listing_id"] ) );
$req = new WP_REST_Request("GET", "/listora/v1/search");
$req->set_param("per_page", 100);
$res = rest_do_request($req);
foreach ( $res->get_data()["listings"] as $l ) {
    if ( (int) $l["id"] === (int) $row["listing_id"] ) {
        echo "geo_for_ungeocoded:" . wp_json_encode( $l["geo"] ) . "\n";
    }
}
$wpdb->insert( "{$p}geo", $row );
echo "restored:" . (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}geo WHERE listing_id=%d", $row["listing_id"] ) ) . "\n";
'
```

**Assert:** `geo_for_ungeocoded:null` — **not** `{"lat":0,"lng":0}` and not `{}`.
**Assert:** `restored:1`.

FAIL → `0,0` is a real place (Null Island, in the Gulf of Guinea). Defaulting to it scatters every
un-geocoded listing off the coast of Africa, which reads as a map bug on a customer's site. `null`
is the only honest "unknown"; the client skips those rows.

## Fail diagnostics

| Symptom | Likely cause |
|---|---|
| `geo` key absent | SELECT-list change reverted in `includes/rest/class-search-controller.php` |
| `geo.lat` is a string | the `(float)` cast was dropped |
| `geo_queries` > 1 | batch prime bypassed — geo read drifted into the per-row loop |
| `geo: {"lat":0,"lng":0}` for an un-geocoded row | the null-vs-zero contract was broken |
| Map shows tiles but no pins | this journey is failing — start here |
