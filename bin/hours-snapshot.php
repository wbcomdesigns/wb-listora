<?php
/**
 * bin/hours-snapshot.php — capture every observable behaviour of business hours.
 *
 * Written for the multi-slot work (BC 10180685898) and kept because it is the
 * cheapest regression proof this table has: run it before a change and after,
 * and diff. The `deterministic` keys must be byte-identical; anything under
 * `_time_dependent` must not be compared for equality.
 *
 * Usage:
 *   LISTORA_SNAPSHOT=/tmp/before.json wp eval-file bin/hours-snapshot.php
 *   ... make the change ...
 *   LISTORA_SNAPSHOT=/tmp/after.json  wp eval-file bin/hours-snapshot.php
 *   diff <(jq 'del(._time_dependent)' /tmp/before.json) <(jq 'del(._time_dependent)' /tmp/after.json)
 *
 * Dev-only; excluded from dist by .distignore like the rest of bin/.
 *
 * @package WBListora
 */

/**
 * Snapshot every observable behaviour of business hours, for before/after diffing.
 * Written to a file so the two runs are compared byte-for-byte, not by eye.
 */
global $wpdb;
$prefix = $wpdb->prefix . 'listora_';
$out    = array();

// every listing that has hours
$ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT listing_id FROM {$prefix}hours ORDER BY listing_id ASC" ) );
$out['listing_count'] = count( $ids );

// 1. raw rows — the storage itself
$out['rows'] = $wpdb->get_results(
    "SELECT listing_id, day_of_week, open_time, close_time, is_closed, is_24h, timezone
     FROM {$prefix}hours ORDER BY listing_id ASC, day_of_week ASC", ARRAY_A );

// 2. the normalised accessor every consumer reads
\WBListora\Core\Business_Hours::prime( $ids );
foreach ( $ids as $id ) {
    $out['business_hours'][ $id ] = \WBListora\Core\Business_Hours::get( $id );
    $out['timezone'][ $id ]       = \WBListora\Core\Business_Hours::get_timezone( $id );
}

// 3. schema.org output
if ( class_exists( '\WBListora\Schema\Schema_Generator' ) ) {
    $gen = new \WBListora\Schema\Schema_Generator();
    $m   = new ReflectionMethod( $gen, 'format_hours_schema' );
    $m->setAccessible( true );
    foreach ( $ids as $id ) {
        $out['schema'][ $id ] = $m->invoke( $gen, \WBListora\Core\Business_Hours::get( $id ) );
    }
}

/*
 * 4. open_now — kept, but SEPARATED, because it is time-dependent.
 *
 * A strict before/after equality on this is wrong: a listing open at 18:00 and
 * closed at 06:00 legitimately changes membership between two runs, which reads
 * as a regression when it is just the clock. Compare  for
 * equality; treat this as informational.
 */
$r = new WP_REST_Request( 'GET', '/listora/v1/search' );
$r->set_query_params( array( 'open_now' => 'true', 'per_page' => 100 ) );
$res = rest_do_request( $r );
$d   = $res->get_data();
$out['_time_dependent']['open_now_total'] = $d['total'] ?? -1;
$out['_time_dependent']['open_now_ids']   = array_map( static fn( $x ) => (int) ( $x['id'] ?? 0 ), (array) ( $d['listings'] ?? $d['results'] ?? array() ) );
sort( $out['_time_dependent']['open_now_ids'] );

$target = getenv( 'LISTORA_SNAPSHOT' ) ?: '/tmp/hours_snapshot.json';
file_put_contents( $target, wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
echo 'written: ' . $target . "
";
