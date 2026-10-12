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
 * Personas come from docs/qa/sites/<site-host>.json (QA_SITE env, default the
 * host of home_url()): owner and other (two Subscribers), unnamed (display name
 * equals login, so the public name is empty). A persona whose user does not
 * exist on this site is created, so the script runs on any local site.
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

$site    = getenv( 'QA_SITE' ) ? getenv( 'QA_SITE' ) : (string) wp_parse_url( home_url(), PHP_URL_HOST );
$profile = json_decode( (string) @file_get_contents( dirname( __DIR__ ) . '/sites/' . $site . '.json' ), true );
if ( ! is_array( $profile ) ) {
	WP_CLI::error( "No site profile docs/qa/sites/{$site}.json. Copy sites/example.json and fill it in." );
}

// Persona name -> user ID, creating the user on first use.
$persona_ids = array();
$uid         = static function ( string $persona ) use ( $profile, &$persona_ids ): int {
	if ( isset( $persona_ids[ $persona ] ) ) {
		return $persona_ids[ $persona ];
	}
	$def   = $profile['personas'][ $persona ] ?? array();
	$login = (string) ( $def['login'] ?? '' );
	if ( '' === $login ) {
		return 0;
	}
	$u = get_user_by( 'login', $login );
	if ( ! $u ) {
		$id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 24 ),
				'user_email'   => $login . '@example.invalid',
				'role'         => $def['role'] ?? 'subscriber',
				'display_name' => 'unnamed' === $persona ? $login : ucwords( str_replace( array( '_', '-' ), ' ', $login ) ),
			)
		);
		return $persona_ids[ $persona ] = is_wp_error( $id ) ? 0 : (int) $id;
	}
	return $persona_ids[ $persona ] = (int) $u->ID;
};

$make = static function ( string $title, string $status, int $author, string $type = '', array $meta = array() ): int {
	static $default_type = null;
	if ( null === $default_type ) {
		$types        = get_terms( array( 'taxonomy' => 'listora_listing_type', 'hide_empty' => false, 'number' => 1, 'fields' => 'slugs' ) );
		$default_type = is_array( $types ) && $types ? (string) $types[0] : '';
	}
	$type = '' !== $type ? $type : $default_type;
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

$owner = $uid( 'owner' );
$ids   = array(
	'owner_id'    => $owner,
	'published'   => $make( 'J20 Owner Published', 'publish', $owner ),
	'rejected'    => $make( 'J20 Owner Rejected', 'listora_rejected', $owner ),
	'paused'      => $make( 'J20 Owner Paused', 'listora_deactivated', $owner ),
	'awaiting'    => $make( 'J20 Owner Awaiting Payment', 'listora_payment', $owner ),
	'expired'     => $make( 'J20 Owner Expired', 'listora_expired', $owner ),
	'pending'     => $make( 'J20 Owner Pending', 'pending', $owner ),
	'unnamed_own' => $make( 'J20 Unnamed Member Listing', 'publish', $uid( 'unnamed' ) ),
);

// Reviews: one published review by the unnamed member and one pending review by the second member.
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
$ids['review_published'] = $rev( $ids['published'], $uid( 'unnamed' ), 'approved', 'J20 published review from the unnamed member, long enough to pass.' );
$ids['review_pending']   = $rev( $ids['published'], $uid( 'other' ), 'pending', 'J20 pending review from the second member, long enough to pass.' );

foreach ( array( 'published', 'unnamed_own' ) as $k ) {
	do_action( 'save_post_listora_listing', $ids[ $k ], get_post( $ids[ $k ] ), true ); // Index the published ones.
}

WP_CLI::print_value( $ids, array( 'format' => 'json' ) );
