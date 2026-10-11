<?php
/**
 * Throwaway need that reprints the card-10347569000 customer lie:
 * stored count 2, zero response rows. Deleted by restore-count.php.
 */
$owner = get_user_by( 'login', 'journey_owner' );
if ( ! $owner ) {
	fwrite( STDERR, "journey_owner missing\n" );
	exit( 1 );
}

$need = wp_insert_post(
	array(
		'post_type'    => 'listora_need',
		'post_status'  => 'publish',
		'post_title'   => 'QA-RFT-count-lie-20260929',
		'post_author'  => $owner->ID,
		'post_content' => 'Throwaway QA need: count meta is 2, table has 0 rows.',
	),
	true
);
if ( is_wp_error( $need ) ) {
	fwrite( STDERR, $need->get_error_message() . "\n" );
	exit( 1 );
}

update_post_meta( $need, '_listora_need_status', 'open' );
update_post_meta( $need, '_listora_need_urgency', 'normal' );
update_post_meta( $need, '_listora_need_listing_type', '' );
update_post_meta( $need, '_listora_need_response_count', 2 );

global $wpdb;
$rows = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}listora_need_responses WHERE need_id = %d",
		$need
	)
);

$cfg = array(
	'base'      => home_url( '/' ),
	'owner'     => 'journey_owner',
	'other'     => 'journey_other',
	'admin'     => 'journey_admin',
	'dash'      => function_exists( 'wb_listora_get_dashboard_url' ) ? wb_listora_get_dashboard_url() : home_url( '/my-listings/' ),
	'dash_needs'=> function_exists( 'wb_listora_get_dashboard_url' ) ? wb_listora_get_dashboard_url( 'needs' ) : home_url( '/my-listings/#needs' ),
	'need_id'   => (int) $need,
	'need_url'  => get_permalink( $need ),
	'grid_url'  => home_url( '/needs/?need_search=QA-RFT-count-lie-20260929' ),
	'honest_id' => 7148,
	'honest_url'=> get_permalink( 7148 ),
	'rest_url'  => rest_url( 'listora/v1/needs/' . (int) $need ),
	'meta'      => (int) get_post_meta( $need, '_listora_need_response_count', true ),
	'rows'      => $rows,
);

$dir = dirname( __FILE__ );
file_put_contents( $dir . '/walk-count-config.json', wp_json_encode( $cfg, JSON_PRETTY_PRINT ) );
file_put_contents( $dir . '/count-need-id.txt', (string) $need );
echo wp_json_encode( $cfg, JSON_PRETTY_PRINT ) . "\n";
