<?php
/**
 * Test factory for `wp_listora_reviews` rows.
 *
 * @package WBListora\Tests\Factories
 */

namespace WBListora\Tests\Factories;

defined( 'ABSPATH' ) || exit;

/**
 * Review factory — direct INSERT into the reviews table for deterministic test setup.
 * Bypasses REST validation on purpose — tests can still exercise REST flows
 * separately.
 */
class Review_Factory {

	/**
	 * Insert a review row.
	 *
	 * @param array<string, mixed> $args Override:
	 *     - listing_id     (int,    required)
	 *     - user_id        (int,    default 1)
	 *     - overall_rating (int,    default 5)
	 *     - title          (string, default generated)
	 *     - content        (string, default generated)
	 *     - status         (string, default 'approved')
	 *     - criteria       (array,  optional — nested per-criterion ratings)
	 *
	 * @return int Review ID.
	 */
	public static function create( array $args = array() ): int {
		global $wpdb;

		if ( empty( $args['listing_id'] ) ) {
			throw new \InvalidArgumentException( 'Review_Factory::create() requires a listing_id.' );
		}

		$defaults = array(
			'user_id'        => 1,
			'overall_rating' => 5,
			'title'          => 'Factory Review ' . wp_generate_password( 4, false ),
			'content'        => 'Factory-generated review text.',
			'status'         => 'approved',
			'criteria'       => array(),
		);

		$args = array_merge( $defaults, $args );

		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'reviews';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$prefix,
			array(
				'listing_id'       => (int) $args['listing_id'],
				'user_id'          => (int) $args['user_id'],
				'overall_rating'   => (int) $args['overall_rating'],
				'title'            => (string) $args['title'],
				'content'          => (string) $args['content'],
				'status'           => (string) $args['status'],
				'criteria_ratings' => wp_json_encode( $args['criteria'] ),
				'created_at'       => current_time( 'mysql', true ),
			)
		);

		return (int) $wpdb->insert_id;
	}
}
