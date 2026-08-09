<?php
/**
 * Exercise Migration_Base::store_migrated_hours() through a concrete migrator,
 * with the shapes the four competitor migrators actually pass through.
 */
class WBL_Hours_Probe extends \WBListora\ImportExport\Migration_Base {
	protected $source_slug = 'directorist';
	protected $source_name = 'Directorist';
	public function get_source_count() { return 0; }
	public function is_source_available() { return true; }
	public function detect() { return false; }
	public function get_source_ids( $offset = 0, $limit = 50 ) { return array(); }
	public function migrate_listing( $source_id, $dry_run = false ) { return array(); }
	public function probe( $post_id, $value ) { return $this->store_migrated_hours( $post_id, $value ); }
	public function dropped() { return $this->unreadable_hours_count; }
}

$post_id = wp_insert_post( array( 'post_type' => 'listora_listing', 'post_title' => 'hours probe', 'post_status' => 'draft' ) );
$p = new WBL_Hours_Probe();

$cases = array(
	// Unreadable — the shapes the competitor migrators pass through verbatim.
	'directorist string day keys' => array( 'monday' => array( array( 'start' => '09:00', 'close' => '17:00' ) ) ),
	'geodirectory string list'    => array( 'Mo 09:00-17:00', 'Tu 09:00-17:00' ),
	'listingpro blob'             => array( 'hours' => 'Mon-Fri 9-5' ),
	// Readable — must pass straight through, untouched.
	'canonical list'              => array( array( 'day' => 1, 'open' => '09:00', 'close' => '17:00' ) ),
	'day-keyed dict (form)'       => array( 1 => array( 'open' => '09:00', 'close' => '17:00' ) ),
	'ranges (form, split shift)'  => array( 1 => array( 'ranges' => array( array( 'open' => '08:00', 'close' => '12:00' ) ) ) ),
);

foreach ( $cases as $label => $value ) {
	delete_post_meta( $post_id, '_listora_migrated_hours_raw' );
	$stored = $p->probe( $post_id, $value );
	$raw    = get_post_meta( $post_id, '_listora_migrated_hours_raw', true );
	printf(
		"%-28s stored_normally=%-5s raw_preserved=%s\n",
		$label,
		$stored ? 'yes' : 'NO',
		$raw ? 'yes' : 'no'
	);
}
echo 'dropped count: ' . $p->dropped() . " (expect 3)\n";
wp_delete_post( $post_id, true );
