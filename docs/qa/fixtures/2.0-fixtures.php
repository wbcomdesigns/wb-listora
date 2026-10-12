<?php
/**
 * Fixtures for the 2.0-* journeys.
 *
 *   wp eval-file wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php seed
 *   wp eval-file wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php clean
 *
 * `seed` is idempotent (it cleans first) and prints one JSON object of IDs.
 * Every row it makes is tagged `_j20_fixture` (posts) or sits on a tagged
 * listing, so `clean` removes exactly that and nothing else. Local/dev sites
 * only: it writes posts, reviews and leads directly.
 *
 * Owner of the fixtures: qa_vendor_01 (a Subscriber). Second member: dana_whitfield.
 * "Unnamed" member (display name equals login, so the public name is empty): tester.
 *
 * @package WBListora
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) ) {
	exit;
}

$mode = isset( $args[0] ) ? (string) $args[0] : 'seed';
global $wpdb;
$p = $wpdb->prefix . 'listora_';

$clean = static function () use ( $wpdb, $p ): void {
	$ids = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_j20_fixture'" );
	foreach ( $ids as $id ) {
		$id = (int) $id;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}review_votes WHERE review_id IN ( SELECT id FROM {$p}reviews WHERE listing_id = %d )", $id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}reviews WHERE listing_id = %d", $id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}leads WHERE listing_id = %d", $id ) );
		wp_delete_post( $id, true );
	}
	WP_CLI::log( 'cleaned ' . count( $ids ) . ' fixture listing(s)' );
};

$clean();
if ( 'clean' === $mode ) {
	return;
}

$uid = static function ( string $login ): int {
	$u = get_user_by( 'login', $login );
	return $u ? (int) $u->ID : 0;
};

$make = static function ( string $title, string $status, int $author, string $type = 'restaurant', array $meta = array() ): int {
	$id = wp_insert_post(
		array(
			'post_type'    => 'listora_listing',
			'post_status'  => $status,
			'post_title'   => $title,
			'post_content' => 'J20 fixture. Safe to delete.',
			'post_author'  => $author,
		)
	);
	wp_set_object_terms( $id, $type, 'listora_listing_type' );
	update_post_meta( $id, '_j20_fixture', 1 );
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	return (int) $id;
};

$owner = $uid( 'qa_vendor_01' );
$ids   = array(
	'owner_id'    => $owner,
	'published'   => $make( 'J20 Owner Published', 'publish', $owner ),
	'rejected'    => $make( 'J20 Owner Rejected', 'listora_rejected', $owner ),
	'paused'      => $make( 'J20 Owner Paused', 'listora_deactivated', $owner ),
	'awaiting'    => $make( 'J20 Owner Awaiting Payment', 'listora_payment', $owner ),
	'expired'     => $make( 'J20 Owner Expired', 'listora_expired', $owner ),
	'pending'     => $make( 'J20 Owner Pending', 'pending', $owner ),
	'unnamed_own' => $make( 'J20 Unnamed Member Listing', 'publish', $uid( 'tester' ) ),
);

// Reviews: one published review by the unnamed member (tester) and one pending review by dana.
$rev = static function ( int $listing, int $user, string $status, string $text ) use ( $wpdb, $p ): int {
	$wpdb->insert(
		$p . 'reviews',
		array(
			'listing_id'     => $listing,
			'user_id'        => $user,
			'overall_rating' => 4,
			'title'          => 'J20 review',
			'content'        => $text,
			'status'         => $status,
			'created_at'     => current_time( 'mysql', true ),
			'updated_at'     => current_time( 'mysql', true ),
		)
	);
	return (int) $wpdb->insert_id;
};
$ids['review_published'] = $rev( $ids['published'], $uid( 'tester' ), 'approved', 'J20 published review from the unnamed member, long enough to pass.' );
$ids['review_pending']   = $rev( $ids['published'], $uid( 'dana_whitfield' ), 'pending', 'J20 pending review from the second member, long enough to pass.' );

foreach ( array( 'published', 'unnamed_own' ) as $k ) {
	do_action( 'save_post_listora_listing', $ids[ $k ], get_post( $ids[ $k ] ), true ); // Index the published ones.
}

WP_CLI::print_value( $ids, array( 'format' => 'json' ) );
