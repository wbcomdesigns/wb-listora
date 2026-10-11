<?php
$owner = get_user_by( 'login', 'journey_owner' );
if ( ! $owner ) {
	fwrite( STDERR, "journey_owner missing\n" );
	exit( 1 );
}

$make = static function ( $title, $meta ) use ( $owner ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'listora_need',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_author'  => $owner->ID,
			'post_content' => 'Throwaway QA for card 10350113286.',
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		fwrite( STDERR, $id->get_error_message() . "\n" );
		exit( 1 );
	}
	update_post_meta( $id, '_listora_need_status', 'open' );
	update_post_meta( $id, '_listora_need_urgency', 'normal' );
	update_post_meta( $id, '_listora_need_response_count', (int) $meta );
	return (int) $id;
};

$edit   = $make( 'QA-RFT-stale-edit-20260929', 2 );
$del    = $make( 'QA-RFT-stale-delete-20260929', 2 );
$honest = $make( 'QA-RFT-honest-delete-20260929', 0 );

$post_need = function_exists( 'wb_listora_get_page_url' ) ? wb_listora_get_page_url( 'post_need' ) : home_url( '/post-need/' );
$cfg       = array(
	'base'       => home_url( '/' ),
	'owner'      => 'journey_owner',
	'admin'      => 'journey_admin',
	'edit_id'    => $edit,
	'del_id'     => $del,
	'honest_id'  => $honest,
	'edit_url'   => add_query_arg( 'edit_need', $edit, $post_need ),
	'dash_needs' => function_exists( 'wb_listora_get_dashboard_url' ) ? wb_listora_get_dashboard_url( 'needs' ) : home_url( '/my-listings/#needs' ),
	'admin_url'  => admin_url( 'admin.php?page=listora-needs' ),
	'rest_edit'  => rest_url( 'listora/v1/needs/' . $edit ),
	'rest_del'   => rest_url( 'listora/v1/needs/' . $del ),
);
$dir = dirname( __FILE__ );
file_put_contents( $dir . '/walk-stale-config.json', wp_json_encode( $cfg, JSON_PRETTY_PRINT ) );
file_put_contents( $dir . '/stale-need-ids.txt', implode( ',', array( $edit, $del, $honest ) ) );
echo wp_json_encode( $cfg, JSON_PRETTY_PRINT ) . "\n";
