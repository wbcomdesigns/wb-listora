<?php
$file = dirname( __FILE__ ) . '/stale-need-ids.txt';
if ( ! file_exists( $file ) ) {
	echo "no ids\n";
	return;
}
foreach ( array_filter( array_map( 'intval', explode( ',', (string) file_get_contents( $file ) ) ) ) as $id ) {
	wp_delete_post( $id, true );
	echo "deleted {$id}\n";
}
