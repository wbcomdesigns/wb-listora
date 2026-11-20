<?php
/**
 * A card tells the viewer which listings are theirs; guests and other members see no badge.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class CardOwnBadgeTest extends WP_UnitTestCase {

	public function test_own_flag_is_true_only_for_the_author() {
		$owner = self::factory()->user->create();
		$other = self::factory()->user->create();
		$id    = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_author' => $owner,
			)
		);

		wp_set_current_user( 0 );
		$this->assertFalse( wb_listora_prepare_card_data( $id )['badges']['own'] );

		wp_set_current_user( $other );
		$this->assertFalse( wb_listora_prepare_card_data( $id )['badges']['own'] );

		wp_set_current_user( $owner );
		$this->assertTrue( wb_listora_prepare_card_data( $id )['badges']['own'] );
	}
}
