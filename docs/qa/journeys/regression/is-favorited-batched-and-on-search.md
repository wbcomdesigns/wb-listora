---
journey: is-favorited-batched-and-on-search
plugin: wb-listora
priority: high
roles: [anonymous, subscriber, admin]
covers: [is_favorited-search-parity, favorites-n1-batching, Favorites_Cache, favorite_count-batching, anonymous-is_favorited-contract, listings-offset-path-priming]
prerequisites:
  - "Site reachable at http://wb-listora.local"
  - "A user with at least 5 rows in {prefix}listora_favorites: subscriber3 (user 5) has 25"
  - "20+ published listora_listing rows so per_page=20 fills a page (104 published)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# `is_favorited` is on every listing surface and costs ONE query per page

Contract: `is_favorited` is always present on `/search`, `/listings`, `/listings/{id}/detail` and `/listings/bulk`, and is `false` for anonymous callers with no favourites query. `\WBListora\Core\Favorites_Cache` (`includes/core/class-favorites-cache.php:23`) primes once per request. `Listings_Controller::get_items()` has a cursor branch AND an OFFSET branch (the default, what the app calls): measure the OFFSET path.

Verified via: harness below run with `wp eval-file`, 2026-10-12 (rest_do_request, `query` filter counting `listora_favorites` statements). Results: `/search` and `/listings` as user 5: items=20 present=20 mismatches=0 FAV_QUERIES=1; anonymous: present=20 FAV_QUERIES=0; bulk(10 ids): items=10 fav_queries=2; detail id 17: REST favorite_count 3 = DB 3, is_favorited true.

## Setup

Save as `$SCRATCH/fav-count.php`; run `wp eval-file $SCRATCH/fav-count.php 5` (arg = user id with favourites).

```php
<?php
global $wpdb;
$GLOBALS['fq'] = array();
add_filter( 'query', function ( $q ) { if ( false !== stripos( $q, 'listora_favorites' ) ) { $GLOBALS['fq'][] = $q; } return $q; } );
$user_id = (int) ( $args[0] ?? 5 );
$truth = array_flip( array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
	"SELECT listing_id FROM {$wpdb->prefix}listora_favorites WHERE user_id = %d", $user_id ) ) ) );
function probe( $label, $route, $params, $uid, $truth ) {
	wp_set_current_user( $uid );
	$req = new WP_REST_Request( 'GET', $route );
	foreach ( $params as $k => $v ) { $req->set_param( $k, $v ); }
	$GLOBALS['fq'] = array();
	$res   = rest_do_request( $req );
	$data  = $res->get_data();
	$items = $data['listings'] ?? array();
	$present = 0; $mismatch = 0;
	foreach ( $items as $it ) {
		if ( array_key_exists( 'is_favorited', $it ) ) { $present++; }
		if ( ( isset( $truth[ (int) $it['id'] ] ) && $uid > 0 ) !== ! empty( $it['is_favorited'] ) ) { $mismatch++; }
	}
	printf( "%-22s user=%-4s status=%s items=%-3s present=%-3s mismatches=%-2s FAV_QUERIES=%s\n",
		$label, $uid ?: 'anon', $res->get_status(), count( $items ), $present, $mismatch, count( $GLOBALS['fq'] ) );
}
foreach ( array( $user_id, 0 ) as $uid ) {
	probe( '/search per_page=20', '/listora/v1/search', array( 'per_page' => 20 ), $uid, $truth );
	probe( '/listings per_page=20', '/listora/v1/listings', array( 'per_page' => 20 ), $uid, $truth );
}
```

## Steps

### 1. `/search` carries `is_favorited` at all
- **Action**: `curl -s "http://wb-listora.local/wp-json/listora/v1/search?per_page=5"`; first listing has the key.
- **Expect**: present (value false for anonymous).

### 2. `/search` at per_page=20 runs exactly ONE favourites query (logged in)
- **Expect**: `items=20 present=20 mismatches=0 FAV_QUERIES=1`.

### 3. `/listings` (OFFSET path) also ONE
- **Expect**: same row for `/listings per_page=20`. `FAV_QUERIES=20` means the OFFSET branch is not primed.

### 4. Values are correct, not merely cheap (POSITIVE CONTROL)
- **Action**: also call `POST /listora/v1/listings/bulk {ids:[<10 of $truth keys>]}` as user 5.
- **Expect**: all 10 `is_favorited: true`, `fav_queries <= 2` (flags + counts). On the 20-item search page a user's favourites may not intersect, so mismatches=0 alone is weak: this step proves true values.

### 5. Anonymous: false and ZERO queries
- **Expect**: `present=20 mismatches=0 FAV_QUERIES=0`.

### 6. `favorite_count` on `/detail` matches the DB
- **Action**: `GET /listora/v1/listings/17/detail` vs `SELECT COUNT(*) FROM wp_listora_favorites WHERE listing_id=17`.
- **Expect**: equal (3 = 3).

### 7. Write then read in one request is not stale
- **Action**: as user 5 `POST /listora/v1/favorites {listing_id: <not yet favourited>}` then GET its `/detail` in the SAME PHP process.
- **Expect**: `is_favorited: true`. **Cleanup**: `DELETE /listora/v1/favorites/{listing_id}`; row count returns to 25.

### 8. Role ladder
- Owner of the favourites (subscriber3): steps 2-7. Another member (subscriber2, 0 favourites): all `is_favorited` false with mismatches=0 against their own truth. Anonymous: step 5. Editor: like a member. Admin: control only.

### 9. 2000+ rows
- With 2,811 indexed rows the page is still one favourites query: confirm at `per_page=50` (max) and at `page=6`.

## Fail diagnostics

| Symptom | File |
|---|---|
| `/search` has no `is_favorited` | `includes/rest/class-search-controller.php` `hydrate_listings()` |
| FAV_QUERIES=20 | `Favorites_Cache::prime()` missing before the loop (`includes/core/class-favorites-cache.php`) |
| `/listings` 20 but cursor OK | OFFSET branch priming in `includes/rest/class-listings-controller.php` `get_items()` |
| anon queries > 0 | `$user_id <= 0` early return in `Favorites_Cache` |
| bulk queries = 10+ | `get_bulk()` (route at `class-listings-controller.php:460`) not priming |
| stale write-then-read | `Favorites_Cache::forget()` not called from `bump_favorites_generation()` |

## State restored

`SELECT COUNT(*) FROM wp_listora_favorites` back to its start value (28 on this site); delete the scratch script.
