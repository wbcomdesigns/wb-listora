<?php
/**
 * One review form template serves the Reviews block and the listing page.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;

/**
 * @group listora
 */
class ReviewFormTemplateTest extends WP_UnitTestCase {

	public function test_defaults_and_listing_page_variant() {
		wp_set_current_user( self::factory()->user->create() );
		$args = array( 'post_id' => 1, 'listing_type_slug' => '', 'review_criteria' => array() );

		$block = wb_listora_get_template_html( 'blocks/listing-reviews/review-form.php', $args );
		$this->assertStringContainsString( 'id="listora-review-form"', $block );
		$this->assertStringContainsString( 'id="listora-review-title"', $block );
		$this->assertStringContainsString( 'actions.submitReviewForm', $block );
		$this->assertStringContainsString( 'actions.toggleReviewForm', $block );

		$detail = wb_listora_get_template_html(
			'blocks/listing-reviews/review-form.php',
			$args + array(
				'form_id'         => 'listora-detail-review',
				'submit_action'   => 'actions.submitDetailReviewForm',
				'cancel_action'   => 'actions.toggleDetailReviewForm',
				'captcha_context' => 'review-detail',
			)
		);
		$this->assertStringContainsString( 'id="listora-detail-review-form"', $detail );
		$this->assertStringContainsString( 'for="listora-detail-review-content"', $detail );
		$this->assertStringContainsString( 'actions.submitDetailReviewForm', $detail );
		$this->assertStringNotContainsString( 'actions.submitReviewForm', $detail );
	}
}
