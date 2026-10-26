<?php
/**
 * Live listings with no type are found by Health Check and the Listings filter.
 *
 * @package WBListora\Tests
 */

use WBListora\Admin\Health_Check;

/**
 * @group listora
 */
class TypelessHealthCheckTest extends WP_UnitTestCase {

	private function check(): array {
		$method = new ReflectionMethod( Health_Check::class, 'check_typeless_listings' );
		return $method->invoke( ( new ReflectionClass( Health_Check::class ) )->newInstanceWithoutConstructor() );
	}

	public function test_a_published_listing_without_a_type_is_flagged_and_a_draft_is_not() {
		$this->assertSame( Health_Check::STATE_PASS, $this->check()['state'] );

		self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'draft' ) );
		$this->assertSame( Health_Check::STATE_PASS, $this->check()['state'], 'Drafts are not live, so they are not flagged.' );

		self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );
		$result = $this->check();

		$this->assertSame( Health_Check::STATE_WARN, $result['state'] );
		$this->assertStringContainsString( 'listora_type_filter=__none__', $result['fix_url'] );
	}
}
