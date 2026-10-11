<?php
/**
 * Restore feature snapshot from prep-rft-batch.php.
 * Run: wp eval-file restore-rft-batch.php
 */
$snap_path = __DIR__ . '/rft-batch-snap.json';
if ( ! file_exists( $snap_path ) ) {
	fwrite( STDERR, "no snap\n" );
	return;
}
$snap = json_decode( (string) file_get_contents( $snap_path ), true );
if ( ! is_array( $snap ) ) {
	fwrite( STDERR, "bad snap\n" );
	return;
}
if ( isset( $snap['features'] ) && is_array( $snap['features'] ) ) {
	update_option( 'wb_listora_pro_features', $snap['features'] );
}
echo "restored webhooks=" . ( ! empty( $snap['features']['outgoing_webhooks'] ) ? 'on' : 'off' ) . "\n";
