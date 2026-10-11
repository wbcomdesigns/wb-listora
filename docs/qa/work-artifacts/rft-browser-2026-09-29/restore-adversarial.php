<?php
$snap = json_decode( (string) file_get_contents( __DIR__ . '/adversarial-snap.json' ), true );
if ( ! is_array( $snap ) ) {
	fwrite( STDERR, "no snap\n" );
	return;
}
if ( ! empty( $snap['criteria_term'] ) ) {
	if ( empty( $snap['criteria'] ) ) {
		delete_term_meta( (int) $snap['criteria_term'], '_listora_review_criteria' );
	} else {
		update_term_meta( (int) $snap['criteria_term'], '_listora_review_criteria', $snap['criteria'] );
	}
}
$s = get_option( 'wb_listora_settings', array() );
if ( array_key_exists( 'social_platforms', $snap ) ) {
	if ( null === $snap['social_platforms'] ) {
		unset( $s['social_platforms'] );
	} else {
		$s['social_platforms'] = $snap['social_platforms'];
	}
	update_option( 'wb_listora_settings', $s );
}
if ( ! empty( $snap['owned_status'] ) && get_post( 7264 ) ) {
	wp_update_post(
		array(
			'ID'          => 7264,
			'post_status' => $snap['owned_status'],
		)
	);
}
if ( ! empty( $snap['svc_cat'] ) ) {
	wp_delete_term( (int) $snap['svc_cat'], 'listora_service_cat' );
}
if ( ! empty( $snap['need_id'] ) ) {
	wp_delete_post( (int) $snap['need_id'], true );
}
echo "restored\n";
