<?php
$dir = dirname( __FILE__ );
$id  = 0;
if ( file_exists( $dir . '/count-need-id.txt' ) ) {
	$id = (int) file_get_contents( $dir . '/count-need-id.txt' );
}
if ( $id > 0 ) {
	wp_delete_post( $id, true );
	echo "deleted need {$id}\n";
} else {
	echo "no count-need-id.txt\n";
}
