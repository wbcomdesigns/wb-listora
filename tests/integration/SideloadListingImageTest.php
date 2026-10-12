<?php
/**
 * Imported images belong to the listing's author.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group import
 */
class SideloadListingImageTest extends WP_UnitTestCase {

	public function test_imported_image_is_owned_by_the_listing_author() {
		$owner   = self::factory()->user->create();
		$listing = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_author' => $owner,
			)
		);
		$serve   = static function ( $pre, $args ) {
			// An 8x8 PNG, written where download_url() streams to.
			file_put_contents( $args['filename'], base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAAEklEQVR4nGP4z8CAFWEXHbQSACj/P8Fu7N9hAAAAAElFTkSuQmCC' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return array(
				'headers'  => array(),
				'body'     => '',
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => $args['filename'],
			);
		};
		add_filter( 'pre_http_request', $serve, 10, 2 );

		$id = wb_listora_sideload_listing_image( 'https://example.com/photos/shop.png', $listing );

		remove_filter( 'pre_http_request', $serve, 10 );
		$this->assertIsInt( $id );
		$this->assertSame( $owner, (int) get_post_field( 'post_author', $id ) );
		$this->assertSame( $listing, (int) get_post_field( 'post_parent', $id ) );
	}
}
