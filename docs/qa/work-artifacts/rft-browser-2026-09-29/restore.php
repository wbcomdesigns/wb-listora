<?php
$out  = dirname( __FILE__ ) . '/snapshot.json';
$snap = is_readable( $out ) ? json_decode( (string) file_get_contents( $out ), true ) : null;
if ( ! is_array( $snap ) ) {
	fwrite( STDERR, "no snapshot\n" );
	exit( 1 );
}

if ( isset( $snap['theme_mod'] ) ) {
	if ( '' === $snap['theme_mod'] ) {
		remove_theme_mod( 'reign_color_scheme' );
	} else {
		set_theme_mod( 'reign_color_scheme', $snap['theme_mod'] );
	}
}
if ( isset( $snap['settings'] ) && is_array( $snap['settings'] ) ) {
	update_option( 'wb_listora_settings', $snap['settings'] );
}
foreach ( (array) ( $snap['created'] ?? array() ) as $id ) {
	wp_delete_post( (int) $id, true );
}
global $wpdb;
if ( ! empty( $snap['created'] ) ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}listora_need_responses WHERE need_id IN (" . implode( ',', array_map( 'intval', $snap['created'] ) ) . ')'
		)
	);
}

$owner = get_user_by( 'login', 'journey_owner' );
if ( $owner && class_exists( '\Wbcom\Credits\Credits' ) ) {
	$now = \Wbcom\Credits\Credits::balance_money( 'wb-listora', $owner->ID );
	$want = (float) ( $snap['owner_balance'] ?? 0 );
	$delta = $now - $want;
	if ( abs( $delta ) > 0.0001 ) {
		if ( $delta > 0 ) {
			\Wbcom\Credits\Credits::adjust_money( 'wb-listora', $owner->ID, -$delta, '', 'QA-RFT restore' );
		} else {
			\Wbcom\Credits\Credits::topup_money( 'wb-listora', $owner->ID, -$delta, '', 'QA-RFT restore' );
		}
	}
}

if ( ! empty( $snap['admin_id'] ) ) {
	delete_transient( 'wb_listora_wizard_session_' . (int) $snap['admin_id'] );
}

echo "restored theme=" . get_theme_mod( 'reign_color_scheme', '' ) . " balance=" . ( $owner && class_exists( '\Wbcom\Credits\Credits' ) ? \Wbcom\Credits\Credits::balance_money( 'wb-listora', $owner->ID ) : '?' ) . "\n";
