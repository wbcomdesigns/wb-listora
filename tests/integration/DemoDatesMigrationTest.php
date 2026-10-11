<?php
/**
 * Demo listings imported on one day get spread dates, whatever else the demo seeded.
 *
 * @package WBListora\Tests
 */

use WBListora\DB\Migrator;

/**
 * @group listora
 * @group migrations
 */
class DemoDatesMigrationTest extends WP_UnitTestCase {

	private function demo_post( string $type, string $title, string $date, int $parent = 0 ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'     => $type,
				'post_title'    => $title,
				'post_status'   => 'attachment' === $type ? 'inherit' : 'publish',
				'post_date'     => $date,
				'post_date_gmt' => $date,
				'post_parent'   => $parent,
			)
		);
		update_post_meta( $id, '_listora_demo_content', '1' );
		return $id;
	}

	public function test_listings_spread_even_when_another_demo_row_has_its_own_date() {
		$day      = '2026-05-24 10:00:00';
		$listings = array();
		foreach ( array( 'Golden Fork', 'Velvet Lounge', 'Harbor Inn', 'Quiet Gym' ) as $title ) {
			$listings[] = $this->demo_post( 'listora_listing', $title, $day );
		}
		$image   = $this->demo_post( 'attachment', 'fork.jpg', $day, $listings[0] );
		$webhook = $this->demo_post( 'post', 'Demo webhook', '2026-08-16 09:00:00' );

		Migrator::migrate_1_9_1_12();

		global $wpdb;
		$in = implode( ',', $listings );
		$this->assertGreaterThan( 1, (int) $wpdb->get_var( "SELECT COUNT( DISTINCT DATE( post_date_gmt ) ) FROM {$wpdb->posts} WHERE ID IN ({$in})" ) );
		$this->assertSame( get_post( $listings[0] )->post_date_gmt, get_post( $image )->post_date_gmt, 'An image takes its listing\'s date.' );
		$this->assertSame( '2026-08-16 09:00:00', get_post( $webhook )->post_date_gmt, 'Non-listing demo rows keep their date.' );

		$before = get_post( $listings[1] )->post_date_gmt;
		Migrator::migrate_1_9_1_12();
		$this->assertSame( $before, get_post( $listings[1] )->post_date_gmt, 'A second run changes nothing.' );
	}
}
