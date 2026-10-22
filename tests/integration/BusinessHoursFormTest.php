<?php
/**
 * The business-hours form shows each stored entry on its own day, whatever
 * shape the hours were stored in.
 *
 * @package WBListora\Tests
 */

use WBListora\Core\Field;

/**
 * @group listora
 */
class BusinessHoursFormTest extends WP_UnitTestCase {

	/**
	 * Render the field and return "day => open-close" for each day's first range.
	 *
	 * @param mixed $stored Stored hours value.
	 * @return array<int, string>
	 */
	private function rendered_ranges( $stored ): array {
		$field = new Field( array( 'key' => 'business_hours', 'type' => 'business_hours', 'label' => 'Hours' ) );

		ob_start();
		wb_listora_render_submission_field( $field, $stored );
		$html = ob_get_clean();

		$out = array();
		preg_match_all( '/name="meta_business_hours\[(\d)\]\[ranges\]\[0\]\[(open|close)\]"[^>]*value="([^"]*)"/', $html, $m, PREG_SET_ORDER );
		foreach ( $m as $hit ) {
			$out[ (int) $hit[1] ][ $hit[2] ] = $hit[3];
		}

		return array_map( static fn ( $r ) => ( $r['open'] ?? '' ) . '-' . ( $r['close'] ?? '' ), $out );
	}

	public function test_list_shape_lands_on_the_right_days() {
		$stored = array(
			array( 'day' => 1, 'open' => '11:00', 'close' => '22:00' ),
			array( 'day' => 6, 'open' => '12:00', 'close' => '23:00' ),
			array( 'day' => 0, 'open' => '12:00', 'close' => '21:00' ),
		);

		$days = $this->rendered_ranges( $stored );

		$this->assertSame( '11:00-22:00', $days[1] );
		$this->assertSame( '12:00-23:00', $days[6] );
		$this->assertSame( '12:00-21:00', $days[0], 'Sunday is day 0, not the last list position.' );
		$this->assertSame( '-', $days[2], 'A day with no entry stays blank.' );
	}

	public function test_split_shift_keeps_both_ranges_on_one_day() {
		$stored = array(
			array( 'day' => 1, 'open' => '11:30', 'close' => '14:30' ),
			array( 'day' => 1, 'open' => '17:30', 'close' => '22:00' ),
		);

		$field = new Field( array( 'key' => 'business_hours', 'type' => 'business_hours', 'label' => 'Hours' ) );
		ob_start();
		wb_listora_render_submission_field( $field, $stored );
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression( '/meta_business_hours\[1\]\[ranges\]\[0\]\[open\]"[^>]*value="11:30"/', $html );
		$this->assertMatchesRegularExpression( '/meta_business_hours\[1\]\[ranges\]\[1\]\[open\]"[^>]*value="17:30"/', $html );
	}
}
