<?php
/**
 * Test factory for `wp_listora_claims` rows.
 *
 * @package WBListora\Tests\Factories
 */

namespace WBListora\Tests\Factories;

defined( 'ABSPATH' ) || exit;

/**
 * Claim factory — direct INSERT bypassing REST validation (REST blocks
 * self-claim; tests often need to create claims by the listing owner).
 */
class Claim_Factory {

	/**
	 * @param array<string, mixed> $args Override:
	 *     - listing_id  (int,    required)
	 *     - user_id     (int,    default 1)
	 *     - owner_name  (string, default 'Test Claimant')
	 *     - email       (string, default 'claim@test.example')
	 *     - phone       (string, default '+1 555-0000')
	 *     - message     (string, default 'Factory-generated claim.')
	 *     - proof_text  (string, default 'Factory proof text.')
	 *     - status      (string, default 'pending')
	 *
	 * @return int Claim ID.
	 */
	public static function create( array $args = array() ): int {
		global $wpdb;

		if ( empty( $args['listing_id'] ) ) {
			throw new \InvalidArgumentException( 'Claim_Factory::create() requires a listing_id.' );
		}

		$defaults = array(
			'user_id'    => 1,
			'owner_name' => 'Test Claimant',
			'email'      => 'claim@test.example',
			'phone'      => '+1 555-0000',
			'message'    => 'Factory-generated claim.',
			'proof_text' => 'Factory proof text.',
			'status'     => 'pending',
		);

		$args = array_merge( $defaults, $args );

		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'claims';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$table,
			array(
				'listing_id' => (int) $args['listing_id'],
				'user_id'    => (int) $args['user_id'],
				'owner_name' => (string) $args['owner_name'],
				'email'      => (string) $args['email'],
				'phone'      => (string) $args['phone'],
				'message'    => (string) $args['message'],
				'proof_text' => (string) $args['proof_text'],
				'status'     => (string) $args['status'],
				'created_at' => current_time( 'mysql', true ),
			)
		);

		return (int) $wpdb->insert_id;
	}
}
