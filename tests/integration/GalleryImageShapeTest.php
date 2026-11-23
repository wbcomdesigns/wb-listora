<?php
/**
 * A gallery image reads like every other listing image: decoded alt, a string for every size.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class GalleryImageShapeTest extends WP_UnitTestCase {

	public function test_gallery_items_match_the_image_schema() {
		$admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$listing = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_author' => $admin,
				'post_title'  => 'Gallery Place',
			)
		);
		wp_set_object_terms( $listing, 'restaurant', 'listora_listing_type' );
		$attachment = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.jpg', $listing );
		update_post_meta( $attachment, '_wp_attachment_image_alt', 'Tom &amp; Jerry' );
		// The write path keeps only attachments the member may attach: theirs.
		wp_update_post( array( 'ID' => $attachment, 'post_author' => $admin ) );
		wp_set_current_user( $admin );
		\WBListora\Core\Meta_Handler::set_value( $listing, 'gallery', array( $attachment ) );

		$data = rest_do_request( new WP_REST_Request( 'GET', '/listora/v1/listings/' . $listing . '/detail' ) )->get_data();

		$this->assertNotEmpty( $data['gallery'], 'The listing should return its gallery.' );
		$item     = $data['gallery'][0];
		$expected = \WBListora\Core\Image_Schema::for_attachment( $attachment );
		$this->assertSame( 'Tom & Jerry', $item['alt'] );
		foreach ( \WBListora\Core\Image_Schema::SIZES as $size ) {
			$this->assertIsString( $item[ $size ], "{$size} is a string, never false" );
		}
		$this->assertSame( $expected, $item );
	}
}
