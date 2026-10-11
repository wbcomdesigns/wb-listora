<?php
/**
 * Review criteria keys are unique per listing type.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Core\Listing_Type_Registry;

/**
 * Two criteria with one key shared a radio group on the review form.
 *
 * @group listora
 */
class ReviewCriteriaKeysTest extends WP_UnitTestCase {

	public function test_repeated_keys_are_suffixed_and_stored_keys_kept() {
		$rows = Listing_Type_Registry::unique_criteria(
			array(
				array(
					'key'   => 'Quality',
					'label' => 'Quality',
				),
				array(
					'key'   => 'Quality',
					'label' => 'Food quality',
				),
				array( 'label' => 'Value for money' ),
				'not-a-row',
				array(
					'key'   => '',
					'label' => '',
				),
			)
		);

		$this->assertSame( array( 'Quality', 'Quality-2', 'value-for-money' ), wp_list_pluck( $rows, 'key' ) );
		$this->assertSame( 'Food quality', $rows[1]['label'] );
	}
}
