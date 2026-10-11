<?php
/**
 * One read-only star rating for every surface.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;

/**
 * @group listora
 */
class RenderStarsTest extends WP_UnitTestCase {

	private function stars( $rating ) {
		ob_start();
		wb_listora_render_stars( $rating );
		return ob_get_clean();
	}

	public function test_label_and_filled_count() {
		$html = $this->stars( 4.5 );
		$this->assertStringContainsString( 'aria-label="Rated 4.5 out of 5"', $html );
		$this->assertSame( 5, substr_count( $html, '<svg' ) );
		$this->assertSame( 0, substr_count( $html, '--empty' ), '4.5 rounds to five whole stars.' );

		$html = $this->stars( 3 );
		$this->assertStringContainsString( 'Rated 3 out of 5', $html );
		$this->assertSame( 2, substr_count( $html, '--empty' ) );

		$this->assertSame( 0, substr_count( $this->stars( 9 ), '--empty' ), 'Out-of-range ratings are clamped to 5.' );
		$this->assertSame( 5, substr_count( $this->stars( -1 ), '--empty' ) );
	}
}
