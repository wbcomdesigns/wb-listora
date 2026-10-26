<?php
/**
 * The "Listed by" name never links to an empty author archive.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class ListingOwnerLinkTest extends WP_UnitTestCase {

	private function listing_by( int $user_id ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_author' => $user_id,
			)
		);
	}

	public function test_an_owner_with_no_posts_is_plain_text() {
		$owner = self::factory()->user->create();
		$this->assertSame( '', wb_listora_get_listing_owner_url( $this->listing_by( $owner ) ) );
	}

	public function test_an_owner_with_posts_links_to_their_archive() {
		$owner = self::factory()->user->create();
		self::factory()->post->create( array( 'post_author' => $owner ) );
		$this->assertSame( get_author_posts_url( $owner ), wb_listora_get_listing_owner_url( $this->listing_by( $owner ) ) );
	}

	public function test_a_site_can_still_choose_the_link() {
		$owner = self::factory()->user->create();
		add_filter( 'wb_listora_listing_owner_url', static fn() => 'https://example.com/members/x/' );
		$this->assertSame( 'https://example.com/members/x/', wb_listora_get_listing_owner_url( $this->listing_by( $owner ) ) );
	}
}
