<?php
/**
 * Unit tests for wb_listora_get_page_publish_status().
 *
 * Settings read core's empty privacy URL for a draft page as "Not set", so an
 * owner who had just created the page was told to create one (card
 * 10313405198). The helper separates "no page" from "page not public".
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 */

namespace WBListora\Tests\Unit;

use WP_UnitTestCase;

/**
 * @group listora
 */
class PagePublishStatusTest extends WP_UnitTestCase {

	public function test_status_per_page_state(): void {
		$published = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$draft     = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'draft' ) );
		$private   = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'private' ) );
		$trashed   = self::factory()->post->create( array( 'post_type' => 'page' ) );
		wp_trash_post( $trashed );

		$this->assertSame( 'published', wb_listora_get_page_publish_status( $published ) );
		$this->assertSame( 'unpublished', wb_listora_get_page_publish_status( $draft ) );
		$this->assertSame( 'unpublished', wb_listora_get_page_publish_status( $private ) );
		$this->assertSame( 'none', wb_listora_get_page_publish_status( $trashed ) );
		$this->assertSame( 'none', wb_listora_get_page_publish_status( 0 ) );
		$this->assertSame( 'none', wb_listora_get_page_publish_status( 999999 ) );
	}
}
