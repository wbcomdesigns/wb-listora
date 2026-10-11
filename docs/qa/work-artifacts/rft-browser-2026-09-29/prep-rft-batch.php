<?php
/**
 * Snapshot + enable webhooks for RFT batch. Restore via restore-rft-batch.php.
 * Run: wp eval-file prep-rft-batch.php
 */
$snap_path = __DIR__ . '/rft-batch-snap.json';
$cfg_path  = __DIR__ . '/rft-batch-config.json';

$features = get_option( 'wb_listora_pro_features', array() );
if ( ! is_array( $features ) ) {
	$features = array();
}

$snap = array(
	'features'         => $features,
	'social_platforms' => function_exists( 'wb_listora_get_setting' ) ? wb_listora_get_setting( 'social_platforms', null ) : null,
	'owner_name'       => function_exists( 'wb_listora_feature_enabled' ) ? wb_listora_feature_enabled( 'owner_name' ) : null,
);

file_put_contents( $snap_path, wp_json_encode( $snap, JSON_PRETTY_PRINT ) );

$features['outgoing_webhooks'] = true;
update_option( 'wb_listora_pro_features', $features );

$hours = 0;
foreach ( array( 22, 15, 7264 ) as $id ) {
	$h = get_post_meta( $id, '_listora_business_hours', true );
	if ( ! empty( $h ) && get_post( $id ) ) {
		$hours = $id;
		break;
	}
}

$reviews = get_comments(
	array(
		'post_type' => 'listora_listing',
		'status'    => 'approve',
		'number'    => 8,
	)
);
$review_posts = array();
foreach ( $reviews as $c ) {
	$review_posts[] = array(
		'comment_id' => (int) $c->comment_ID,
		'post_id'    => (int) $c->comment_post_ID,
		'permalink'  => get_permalink( $c->comment_post_ID ),
		'author'     => (int) get_post_field( 'post_author', $c->comment_post_ID ),
	);
}

$out = array(
	'base'            => untrailingslashit( home_url( '/' ) ),
	'golden'          => get_permalink( 15 ),
	'owned'           => get_permalink( 7264 ),
	'hours_id'        => $hours,
	'hours_url'       => $hours ? get_permalink( $hours ) : '',
	'directory'       => get_permalink( 6 ),
	'dashboard'       => get_permalink( 5 ),
	'add_listing'     => get_permalink( 4 ),
	'review_posts'    => $review_posts,
	'webhooks_was_on' => ! empty( $snap['features']['outgoing_webhooks'] ),
	'owner_name_on'   => $snap['owner_name'],
	'social'          => $snap['social_platforms'],
);

file_put_contents( $cfg_path, wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
