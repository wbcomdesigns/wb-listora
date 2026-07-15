---
journey: is-favorited-batched-and-on-search
plugin: wb-listora
priority: high
roles: [anonymous, subscriber]
covers: [is_favorited-search-parity, favorites-n1-batching, Favorites_Cache, favorite_count-batching, anonymous-is_favorited-contract, listings-offset-path-priming]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora active"
  - "A user with at least 5 rows in {prefix}listora_favorites (seeded data: user 4 has 30)"
  - "20+ published listora_listing rows so per_page=20 fills a page"
estimated_runtime_minutes: 5
---

# `is_favorited` is on every listing surface and costs ONE query per page

Two defects, one fix:

1. **N+1** — `prepare_item_for_response()` ran one
   `SELECT COUNT(*) FROM {prefix}favorites WHERE user_id=%d AND listing_id=%d` **per item**.
   At `per_page=20` that is 20 queries per page, growing linearly with page size.
   `/listings/{id}/detail` added a second uncached `favorite_count` query.
2. **Missing** — `/search` items carried **no `is_favorited` at all**, and `/search` is the app's
   home screen, so every heart rendered blank or forced a per-card lookup.

1.2.3 adds `\WBListora\Core\Favorites_Cache` (`includes/core/class-favorites-cache.php`), applying
the same prime-once/read-many pattern the adjacent view-count block already used
(`Analytics_Lite::prepare_views()`).

**Contract this journey locks:** `is_favorited` is **always present** on `/search`, `/listings`,
`/listings/{id}/detail` and `/listings/bulk`, and is **`false` for anonymous callers** (no query
issued) — so a client reads `item.is_favorited` with no presence check and no auth branching.

> **Watch the two list paths.** `Listings_Controller::get_items()` has a cursor branch AND an
> OFFSET branch that delegates to `WP_REST_Posts_Controller`. The OFFSET branch (the default, and
> what the app actually calls) owns no render loop, so it primes via a scoped `the_posts` filter.
> A fix that only primes the cursor branch leaves the default path at 20 queries — that was the
> original bug's hiding place. **Measure the offset path.**

## Setup

The harness below counts favorites-table queries via the `query` filter (fires for every `$wpdb`
query — no `SAVEQUERIES` constant needed) around one internally dispatched REST request.

```bash
cat > /tmp/fav-count.php <<'PHP'
<?php
global $wpdb;
$GLOBALS['fq'] = array();
add_filter( 'query', function ( $q ) {
	if ( false !== stripos( $q, 'listora_favorites' ) ) { $GLOBALS['fq'][] = $q; }
	return $q;
} );
$user_id = (int) ( $args[0] ?? 4 );
$truth = array_flip( array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
	"SELECT listing_id FROM {$wpdb->prefix}listora_favorites WHERE user_id = %d", $user_id ) ) ) );
function probe( $label, $method, $route, $params, $uid, $truth ) {
	wp_set_current_user( $uid );
	$req = new WP_REST_Request( $method, $route );
	foreach ( $params as $k => $v ) { $req->set_param( $k, $v ); }
	$GLOBALS['fq'] = array();
	$res  = rest_do_request( $req );
	$data = $res->get_data();
	$items = $data['listings'] ?? ( isset( $data['id'] ) ? array( $data ) : array() );
	$present = 0; $mismatch = 0;
	foreach ( $items as $it ) {
		if ( array_key_exists( 'is_favorited', $it ) ) { $present++; }
		$expected = isset( $truth[ (int) $it['id'] ] ) && $uid > 0;
		if ( $expected !== ! empty( $it['is_favorited'] ) ) { $mismatch++; }
	}
	printf( "%-28s user=%-4s items=%-3s present=%-3s mismatches=%-2s FAV_QUERIES=%s\n",
		$label, $uid ?: 'anon', count( $items ), $present, $mismatch, count( $GLOBALS['fq'] ) );
}
foreach ( array( $user_id, 0 ) as $uid ) {
	probe( '/search per_page=20',   'GET',  '/listora/v1/search',   array( 'per_page' => 20 ), $uid, $truth );
	probe( '/listings per_page=20', 'GET',  '/listora/v1/listings', array( 'per_page' => 20 ), $uid, $truth );
	echo "\n";
}
PHP
wp eval-file /tmp/fav-count.php 4
```

## Steps

### 1. `/search` carries `is_favorited` at all
- **Action**: `curl -s "$SITE/wp-json/listora/v1/search?per_page=5" | python3 -c "import sys,json; print('is_favorited' in json.load(sys.stdin)['listings'][0])"`
- **Expect**: `True`.
- **On fail**: the field was never added to the hydrate path. Suspect:
  `includes/rest/class-search-controller.php::hydrate_listings()`.

### 2. `/search` at `per_page=20` runs exactly ONE favorites query
- **Action**: run the harness; read the `/search per_page=20 user=4` row.
- **Expect**: `items=20 present=20 mismatches=0 FAV_QUERIES=1`.
- **On fail (`FAV_QUERIES=20`)**: `Favorites_Cache::prime()` is not called before the loop, so every
  `is_favorited()` falls back to its bounded single-ID lookup. Suspect: the `prime()` call in
  `hydrate_listings()`.

### 3. `/listings` (default OFFSET path) also runs ONE favorites query
- **Action**: read the `/listings per_page=20 user=4` row.
- **Expect**: `items=20 present=20 mismatches=0 FAV_QUERIES=1`.
- **On fail (`FAV_QUERIES=20`)**: the OFFSET branch is not priming — the cursor branch may be fine
  while the default path (what the app calls) is still N+1. Suspect: the scoped `the_posts` primer
  and `prime_batch_caches()` in `includes/rest/class-listings-controller.php::get_items()`.

### 4. Values are CORRECT, not merely cheap
- **Action**: read `mismatches` on every harness row (it diffs REST `is_favorited` against
  `{prefix}listora_favorites` truth for the user, per listing).
- **Expect**: `mismatches=0` everywhere, and `present` equals `items` on every row.
- **On fail**: the batch resolved but mapped wrong — likely the primed/favorited bookkeeping in
  `Favorites_Cache` (an ID absent from the result set is a real `false`, not a cache miss).

### 5. Anonymous → `is_favorited: false`, and ZERO favorites queries
- **Action**: read the `user=anon` rows.
- **Expect**: `present=20` (field always present), `mismatches=0` (all false), **`FAV_QUERIES=0`**.
- **Why**: a favourite is a per-user fact; an anonymous request must not touch the table at all.
- **On fail**: the `$user_id <= 0` early return in `Favorites_Cache::prime()`/`is_favorited()`.

### 6. `/listings/bulk` primes flags AND counts for the whole batch
- **Action**:
  ```bash
  wp eval '$GLOBALS["fq"]=array(); add_filter("query", function($q){ if(false!==stripos($q,"listora_favorites")){$GLOBALS["fq"][]=$q;} return $q; });
  wp_set_current_user(4);
  $r = new WP_REST_Request("POST","/listora/v1/listings/bulk"); $r->set_param("ids", array(55,63,71,81,87,335,329,322,300,15));
  $d = rest_do_request($r)->get_data();
  echo "items=".count($d["listings"])." fav_queries=".count($GLOBALS["fq"])."\n";'
  ```
- **Expect**: `items=10` and `fav_queries` ≤ `2` (one for flags, one for counts) — **not 10 or 20**.
- **On fail**: `get_bulk()` fans out to `get_listing()` per ID without priming first.

### 7. `favorite_count` on `/detail` still matches the DB
- **Action**:
  ```bash
  wp eval 'global $wpdb; $id=55; $d = rest_do_request(new WP_REST_Request("GET","/listora/v1/listings/$id/detail"))->get_data();
  $db = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}listora_favorites WHERE listing_id=%d",$id));
  echo "REST=".(int)$d["favorite_count"]." DB=$db\n";'
  ```
- **Expect**: the two numbers are equal.
- **On fail**: `Favorites_Cache::get_count()` grouping is wrong, or a zero-favourite listing is not
  caching its `0`.

### 8. Write-then-read in one request is not stale
- **Action**: POST `/favorites` for a listing the user has not favourited, then GET that listing's
  `/detail` **in the same PHP process** (`wp eval` with both `rest_do_request` calls).
- **Expect**: `is_favorited` is `true` on the read-back.
- **On fail**: `Favorites_Cache::forget()` is not called from `bump_favorites_generation()`.
- **Cleanup**: `DELETE /favorites/{listing_id}` to restore the original state.

## Pass criteria

ALL must hold:
- `/search`, `/listings`, `/listings/{id}/detail`, `/listings/bulk` **all** carry `is_favorited`.
- `per_page=20` authenticated → **exactly 1** favorites query on BOTH `/search` and `/listings`.
- `mismatches=0` on every row (correct values, not just few queries).
- Anonymous → field present, `false`, **0** favorites queries.
- `/listings/bulk` (10 ids) → ≤2 favorites queries.
- `/detail` `favorite_count` equals the DB count.
- Same-request write-then-read reflects the write.
- No new notices in `wp-content/debug.log`.

## Fail diagnostics

| Symptom | Suspect |
|---|---|
| `/search` has no `is_favorited` | `class-search-controller.php::hydrate_listings()` |
| `/search` FAV_QUERIES=20 | `Favorites_Cache::prime()` missing before the hydrate loop |
| `/listings` FAV_QUERIES=20 (cursor OK) | OFFSET branch not primed — `the_posts` primer / `prime_batch_caches()` |
| mismatches > 0 | `Favorites_Cache` primed/favorited bookkeeping |
| anon FAV_QUERIES > 0 | missing `$user_id <= 0` early return |
| bulk FAV_QUERIES = 10+ | `get_bulk()` not priming before the per-ID fan-out |
| stale write-then-read | `forget()` not wired into `bump_favorites_generation()` |

## State restored

- Step 8 adds then removes one favorites row — verify the row count returns to its starting value:
  `wp eval 'global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}listora_favorites");'`
- `rm -f /tmp/fav-count.php`
