<?php
defined( 'ABSPATH' ) || exit;

$path = __DIR__ . '/rft5-snap.json';
if ( ! file_exists( $path ) ) {
	echo "no snap\n";
	return;
}
$snap = json_decode( file_get_contents( $path ), true );
if ( ! is_array( $snap ) ) {
	echo "bad snap\n";
	return;
}

if ( ! empty( $snap['classified_term'] ) && isset( $snap['classified_allowed'] ) ) {
	update_term_meta( (int) $snap['classified_term'], '_listora_allowed_categories', $snap['classified_allowed'] );
	wp_cache_delete( 'wb_listora_type_registry', 'listora' );
}

if ( ! empty( $snap['plan_id'] ) ) {
	wp_delete_post( (int) $snap['plan_id'], true );
}

if ( ! empty( $snap['need_id'] ) ) {
	wp_delete_post( (int) $snap['need_id'], true );
}

foreach ( array( 'cruiser_id', 'sport_id', 'parent_id' ) as $k ) {
	if ( ! empty( $snap[ $k ] ) ) {
		wp_delete_term( (int) $snap[ $k ], 'listora_listing_cat' );
	}
}

$drafts = get_posts(
	array(
		'post_type'      => 'listora_listing',
		'post_status'    => 'draft',
		'posts_per_page' => 20,
		's'              => 'QA-RFT5-AUTOSAVE',
		'fields'         => 'ids',
	)
);
foreach ( $drafts as $id ) {
	wp_delete_post( (int) $id, true );
}

echo wp_json_encode( array( 'restored' => true, 'deleted_drafts' => $drafts ) ) . "\n";
