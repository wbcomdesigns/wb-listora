<?php
/**
 * Test factory for `wp_listora_favorites` rows.
 *
 * @package WBListora\Tests\Factories
 */

namespace WBListora\Tests\Factories;

defined( 'ABSPATH' ) || exit;

/**
 * Favorite factory — direct INSERT into the composite-PK favorites table.
 */
class Favorite_Factory {

	/**
	 * @param array<string, mixed> $args Override:
	 *     - user_id    (int, default 1)
	 *     - listing_id (int, required)
	 *     - collection (string, default 'default')
	 *
	 * @return array{user_id:int,listing_id:int,collection:string}
	 */
	public static function create( array $args = array() ): array {
		global $wpdb;

		if ( empty( $args['listing_id'] ) ) {
			throw new \InvalidArgumentException( 'Favorite_Factory::create() requires a listing_id.' );
		}

		$defaults = array(
			'user_id'    => 1,
			'collection' => 'default',
		);

		$args = array_merge( $defaults, $args );

		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'favorites';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$table,
			array(
				'user_id'    => (int) $args['user_id'],
				'listing_id' => (int) $args['listing_id'],
				'collection' => (string) $args['collection'],
				'created_at' => current_time( 'mysql', true ),
			)
		);

		return array(
			'user_id'    => (int) $args['user_id'],
			'listing_id' => (int) $args['listing_id'],
			'collection' => (string) $args['collection'],
		);
	}
}
