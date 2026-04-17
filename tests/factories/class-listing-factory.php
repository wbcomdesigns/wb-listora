<?php
/**
 * Test factory for `listora_listing` posts.
 *
 * Creates a published listing with sensible defaults and automatically
 * assigns a listing_type taxonomy term so the search indexer produces
 * a sane row.
 *
 * @package WBListora\Tests\Factories
 */

namespace WBListora\Tests\Factories;

defined( 'ABSPATH' ) || exit;

/**
 * Listing factory — wraps WP_UnitTest_Factory_For_Post for the custom post type.
 */
class Listing_Factory {

	/**
	 * Create a listora_listing post and return its ID.
	 *
	 * @param array<string, mixed> $args Override any of:
	 *     - title       (string, default generated)
	 *     - content     (string, default short excerpt)
	 *     - status      (string, default 'publish')
	 *     - author_id   (int,    default 1)
	 *     - type_slug   (string, default 'restaurant' — existing registered type)
	 *     - category_id (int,    optional — a listora_listing_cat term ID)
	 *     - meta        (array,  optional — key => value pairs stored via Meta_Handler)
	 *     - address     (array,  optional — meta_address payload: address/lat/lng/city/country)
	 *
	 * @return int Post ID.
	 */
	public static function create( array $args = array() ): int {
		$defaults = array(
			'title'     => 'Test Listing ' . wp_generate_password( 6, false ),
			'content'   => 'Factory-generated listing content.',
			'status'    => 'publish',
			'author_id' => 1,
			'type_slug' => 'restaurant',
		);

		$args = array_merge( $defaults, $args );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'listora_listing',
				'post_title'   => $args['title'],
				'post_content' => $args['content'],
				'post_status'  => $args['status'],
				'post_author'  => $args['author_id'],
			)
		);

		if ( is_wp_error( $post_id ) || 0 === $post_id ) {
			throw new \RuntimeException( 'Listing_Factory failed to insert post: ' . ( is_wp_error( $post_id ) ? $post_id->get_error_message() : 'unknown error' ) );
		}

		if ( ! empty( $args['type_slug'] ) ) {
			wp_set_object_terms( $post_id, $args['type_slug'], 'listora_listing_type' );

			// Registry is a singleton that caches types on first init. In test
			// environments, init may have run before this term existed, so
			// Registry::get('restaurant') returns null and the indexer stamps
			// listing_type=''. Flush + re-init so the just-added term is seen.
			$registry = \WBListora\Core\Listing_Type_Registry::instance();
			$registry->flush();
			$registry->init();
		}

		if ( ! empty( $args['category_id'] ) ) {
			wp_set_object_terms( $post_id, array( (int) $args['category_id'] ), 'listora_listing_cat' );
		}

		if ( ! empty( $args['address'] ) && is_array( $args['address'] ) ) {
			\WBListora\Core\Meta_Handler::set_value( $post_id, 'address', $args['address'] );
		}

		if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
			foreach ( $args['meta'] as $key => $value ) {
				\WBListora\Core\Meta_Handler::set_value( $post_id, $key, $value );
			}
		}

		return (int) $post_id;
	}

	/**
	 * Create N listings in bulk.
	 *
	 * @param int                  $count How many.
	 * @param array<string, mixed> $args  Shared args passed to each create() call.
	 * @return array<int, int>            Post IDs in creation order.
	 */
	public static function create_many( int $count, array $args = array() ): array {
		$ids = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$ids[] = self::create( $args );
		}
		return $ids;
	}
}
