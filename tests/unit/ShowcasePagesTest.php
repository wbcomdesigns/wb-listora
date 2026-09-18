<?php
/**
 * The three showcase blocks have a home.
 *
 * listing-calendar, listing-categories and listing-featured shipped as blocks
 * with no page anywhere: registered, theme-defended, and invisible unless an
 * owner went block-hunting (card 10167582244).
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 * @group   pages
 */

namespace WBListora\Tests\Unit;

use WBListora\Core\Page_Registry;
use WP_UnitTestCase;

/**
 * @group listora
 * @group pages
 */
class ShowcasePagesTest extends WP_UnitTestCase {

	private function forget( string $key ): void {
		$id = Page_Registry::get_id( $key );
		if ( $id > 0 ) {
			wp_delete_post( $id, true );
		}

		$created = (array) get_option( Page_Registry::OPTION_CREATED, array() );
		unset( $created[ $key ] );
		update_option( Page_Registry::OPTION_CREATED, $created );
	}

	public function test_the_three_keys_are_registered(): void {
		$keys = Page_Registry::keys();

		foreach ( array( 'categories', 'featured', 'calendar' ) as $key ) {
			$this->assertContains( $key, $keys, "'{$key}' must be registered so Settings > Pages can create and heal it." );
		}
	}

	public function test_ensure_adopts_a_page_that_already_carries_the_block(): void {
		$this->forget( 'categories' );

		// The site this was found on had all three built by hand, in the menu,
		// and unknown to the registry. Registering without adopting would have
		// created a duplicate beside each one.
		$mine = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Our Categories',
				'post_content' => '<!-- wp:listora/listing-categories /-->',
			)
		);

		$this->assertSame( $mine, Page_Registry::ensure( 'categories' ), 'The existing page must be adopted, not duplicated.' );
	}

	public function test_ensure_creates_once_and_does_not_resurrect(): void {
		$this->forget( 'featured' );

		$first = Page_Registry::ensure( 'featured' );
		$this->assertGreaterThan( 0, $first );
		$this->assertSame( $first, Page_Registry::ensure( 'featured' ), 'A second call must not create another page.' );

		// An owner who deletes the page means it - re-running must not bring it back.
		wp_delete_post( $first, true );
		$this->assertSame( 0, Page_Registry::ensure( 'featured' ) );
	}
}
