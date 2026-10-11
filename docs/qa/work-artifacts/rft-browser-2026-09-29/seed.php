<?php
/**
 * One-shot QA fixtures for the remaining RFT browser walk.
 * Restore with: wp eval-file restore.php
 */
$out = dirname( __FILE__ ) . '/snapshot.json';
$cfg = dirname( __FILE__ ) . '/walk-config.json';

$owner = get_user_by( 'login', 'journey_owner' );
$other = get_user_by( 'login', 'journey_other' );
$admin = get_user_by( 'login', 'journey_admin' );
if ( ! $owner || ! $other || ! $admin ) {
	fwrite( STDERR, "personas missing\n" );
	exit( 1 );
}

$settings = get_option( 'wb_listora_settings', array() );
$snap     = array(
	'theme_mod'     => get_theme_mod( 'reign_color_scheme', '' ),
	'settings'      => $settings,
	'owner_balance' => class_exists( '\Wbcom\Credits\Credits' ) ? \Wbcom\Credits\Credits::balance_money( 'wb-listora', $owner->ID ) : 0,
	'created'       => array(),
	'ledger_id'     => 0,
	'admin_id'      => $admin->ID,
);

set_theme_mod( 'reign_color_scheme', 'reign_dark' );

$settings['captcha_provider']            = 'recaptcha_v3';
$settings['captcha_site_key']            = '6LeIxAcTAAAAAJcZVRA6CyRiN6la67MB6HAEhhoc';
$settings['default_listing_credit_cost'] = 1;
update_option( 'wb_listora_settings', $settings );

$other_listing = wp_insert_post(
	array(
		'post_type'   => 'listora_listing',
		'post_status' => 'publish',
		'post_title'  => 'QA-RFT other listing',
		'post_author' => $other->ID,
	),
	true
);
if ( is_wp_error( $other_listing ) ) {
	fwrite( STDERR, $other_listing->get_error_message() . "\n" );
	exit( 1 );
}
$snap['created'][] = (int) $other_listing;

$need = wp_insert_post(
	array(
		'post_type'   => 'listora_need',
		'post_status' => 'publish',
		'post_title'  => 'QA-RFT need accept',
		'post_author' => $owner->ID,
		'post_content'=> 'Need a quote for browser QA.',
	),
	true
);
if ( is_wp_error( $need ) ) {
	fwrite( STDERR, $need->get_error_message() . "\n" );
	exit( 1 );
}
$snap['created'][] = (int) $need;
update_post_meta( $need, '_listora_need_status', 'open' );

global $wpdb;
$wpdb->insert(
	$wpdb->prefix . 'listora_need_responses',
	array(
		'need_id'    => (int) $need,
		'listing_id' => (int) $other_listing,
		'user_id'    => $other->ID,
		'message'    => 'QA-RFT pending quote so Accept can be clicked in the browser.',
		'status'     => 'pending',
		'created_at' => current_time( 'mysql', true ),
	),
	array( '%d', '%d', '%d', '%s', '%s', '%s' )
);
$response_id = (int) $wpdb->insert_id;
if ( class_exists( '\WBListoraPro\Needs\Need_Response_Manager' ) ) {
	\WBListoraPro\Needs\Need_Response_Manager::sync_response_count( (int) $need );
}

$ledger_id = 0;
if ( class_exists( '\Wbcom\Credits\Credits' ) ) {
	$ledger_id = \Wbcom\Credits\Credits::topup_money(
		'wb-listora',
		$owner->ID,
		12.5,
		'',
		'QA-RFT 12.5 for preview + Remove credits',
		null,
		'topup',
		'qa-rft-browser-20260929'
	);
}
$snap['ledger_id'] = (int) $ledger_id;

$user_audit = $wpdb->get_row(
	"SELECT id, object_id FROM {$wpdb->prefix}listora_audit_log WHERE object_type='user' AND object_id>0 ORDER BY id DESC LIMIT 1",
	ARRAY_A
);
$user_obj = $user_audit ? get_userdata( (int) $user_audit['object_id'] ) : false;

file_put_contents( $out, wp_json_encode( $snap, JSON_PRETTY_PRINT ) );

$walk = array(
	'base'           => home_url( '/' ),
	'owner'          => 'journey_owner',
	'other'          => 'journey_other',
	'admin'          => 'journey_admin',
	'dash'           => wb_listora_get_dashboard_url(),
	'dash_credits'   => wb_listora_get_dashboard_url( 'credits' ),
	'dash_needs'     => wb_listora_get_dashboard_url( 'needs' ),
	'add'            => home_url( '/add-listing/' ),
	'listing'        => get_permalink( 7264 ),
	'review_listing' => get_permalink( 7281 ),
	'need_id'        => (int) $need,
	'response_id'    => $response_id,
	'ledger_id'      => (int) $ledger_id,
	'balance'        => class_exists( '\Wbcom\Credits\Credits' ) ? \Wbcom\Credits\Credits::balance_money( 'wb-listora', $owner->ID ) : 0,
	'audit'          => admin_url( 'admin.php?page=listora-audit-log' ),
	'email'          => admin_url( 'admin.php?page=listora-email-log' ),
	'webhooks'       => admin_url( 'admin.php?page=listora-webhooks' ),
	'tx'             => admin_url( 'admin.php?page=listora-transactions' ),
	'wizard_type'    => admin_url( 'admin.php?page=listora-setup&rerun=1&step=type' ),
	'wizard_loc'     => admin_url( 'admin.php?page=listora-setup&rerun=1&step=location' ),
	'audit_user_id'  => $user_audit ? (int) $user_audit['object_id'] : 0,
	'audit_user_name'=> $user_obj ? $user_obj->display_name : '',
);
file_put_contents( $cfg, wp_json_encode( $walk, JSON_PRETTY_PRINT ) );
echo wp_json_encode( $walk, JSON_PRETTY_PRINT ) . "\n";
