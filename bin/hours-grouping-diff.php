<?php
/**
 * Prove the renderer's new grouping is ADDITIVE: for every listing with stored
 * business_hours, compare the grouping the detail template used to build
 * in-line against the one wb_listora_normalize_hours() now produces. Any
 * listing whose display changes must be one the old code got WRONG.
 */
$ids = get_posts( array(
	'post_type'      => 'listora_listing',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'post_status'    => 'any',
	'meta_key'       => '_listora_business_hours',
) );

$old_group = static function ( $hours ) {
	$by_day = array();
	foreach ( (array) $hours as $key => $h ) {
		if ( ! is_array( $h ) ) { continue; }
		if ( isset( $h['day'] ) ) { $by_day[ (int) $h['day'] ][] = $h; }
		elseif ( is_int( $key ) || ctype_digit( (string) $key ) ) { $by_day[ (int) $key ][] = $h; }
	}
	return $by_day;
};

// The exact cell text the template renders for one day, both before and after.
$cell = static function ( $day_ranges ) {
	$d = $day_ranges[0] ?? null;
	if ( $d && ! empty( $d['closed'] ) ) { return 'Closed'; }
	if ( $d && ! empty( $d['is_24h'] ) ) { return 'Open 24 Hours'; }
	if ( $d && ! empty( $d['open'] ) ) {
		$parts = array();
		foreach ( $day_ranges as $r ) {
			if ( empty( $r['open'] ) ) { continue; }
			$parts[] = $r['open'] . '-' . ( $r['close'] ?? '23:59' );
		}
		return implode( ', ', $parts );
	}
	return '-';
};

$same = 0; $changed = array();
foreach ( $ids as $id ) {
	$hours = get_post_meta( $id, '_listora_business_hours', true );
	if ( empty( $hours ) || ! is_array( $hours ) ) { continue; }

	$before = $old_group( $hours );
	$after  = array();
	foreach ( wb_listora_normalize_hours( $hours ) as $h ) { $after[ (int) $h['day'] ][] = $h; }

	$b = array(); $a = array();
	for ( $d = 0; $d <= 6; $d++ ) {
		$b[ $d ] = $cell( $before[ $d ] ?? array() );
		$a[ $d ] = $cell( $after[ $d ] ?? array() );
	}
	if ( $b === $a ) { $same++; }
	else { $changed[ $id ] = array( 'before' => $b, 'after' => $a ); }
}

echo 'listings with hours: ', count( $ids ), "\n";
echo "identical display: $same\n";
echo 'changed display: ', count( $changed ), "\n";
foreach ( $changed as $id => $c ) {
	echo "--- listing $id\n";
	for ( $d = 0; $d <= 6; $d++ ) {
		if ( $c['before'][ $d ] !== $c['after'][ $d ] ) {
			echo "  day $d: '{$c['before'][$d]}'  ->  '{$c['after'][$d]}'\n";
		}
	}
}
