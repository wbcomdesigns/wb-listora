<?php
/**
 * Canonical trigger payload builders.
 *
 * @package WBListora\Automation
 */

namespace WBListora\Automation;

defined( 'ABSPATH' ) || exit;

/**
 * Serializes entities for trigger payloads.
 *
 * Every method here delegates to the serializer the REST API already uses.
 * That is the entire point: Pro previously carried private
 * build_listing_payload() / build_review_payload() / build_claim_payload() /
 * build_user_data() methods, so a listing in a webhook and a listing in the
 * API were two shapes maintained by two people. When they drift, the person
 * who finds out is an integrator whose automation quietly stopped mapping a
 * field.
 */
class Payload {

	/**
	 * Canonical listing payload.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return array<string, mixed>|null Null when the listing does not exist.
	 */
	public static function listing( $listing_id ) {
		$listing_id = (int) $listing_id;

		if ( $listing_id <= 0 || ! get_post( $listing_id ) ) {
			return null;
		}

		$cards = wb_listora_get_listing_cards( array( $listing_id ) );

		return isset( $cards[ $listing_id ] ) ? $cards[ $listing_id ] : null;
	}

	/**
	 * Canonical review payload.
	 *
	 * Delegates row-shaping to {@see \WBListora\REST\Reviews_Controller::format_review_row()}
	 * — the same method `GET /listings/{id}/reviews` uses per row — so a
	 * review in a webhook and a review in the API are the same shape from
	 * the same code. The `reviews` table has no dedicated read model class
	 * (unlike claims), so the row is fetched directly, mirroring how the
	 * REST controller itself reads a single review row elsewhere in this
	 * plugin.
	 *
	 * The automation/webhook contract additionally documents the rating
	 * under the key `rating` (see wb-listora-pro's
	 * `webhook-review-payload-carries-real-rating.md` regression journey),
	 * distinct from the REST list's SQL-column-derived `overall_rating`.
	 * Both keys are present so listeners written against either name work.
	 *
	 * @param int $review_id Review ID.
	 * @return array<string, mixed>|null Null when the review does not exist.
	 */
	public static function review( $review_id ) {
		$review_id = (int) $review_id;

		if ( $review_id <= 0 ) {
			return null;
		}

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prefix}reviews WHERE id = %d", $review_id ), ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		$payload = \WBListora\REST\Reviews_Controller::format_review_row( $row );

		$payload['rating'] = (float) $row['overall_rating'];
		$payload['status'] = $row['status'];

		return $payload;
	}

	/**
	 * Canonical claim payload.
	 *
	 * Delegates row-shaping to {@see \WBListora\REST\Claims_Controller::format_claim_row()}
	 * — the same method `GET /claims` uses per row — fed by the single-claim
	 * lookup on {@see \WBListora\Core\Claims_Model::get_list()}, which already
	 * joins the listing title and claimant identity the REST shape needs.
	 *
	 * @param int $claim_id Claim ID.
	 * @return array<string, mixed>|null Null when the claim does not exist.
	 */
	public static function claim( $claim_id ) {
		$claim_id = (int) $claim_id;

		if ( $claim_id <= 0 ) {
			return null;
		}

		$rows = \WBListora\Core\Claims_Model::get_list(
			array(
				'id'    => $claim_id,
				'limit' => 1,
			)
		);

		if ( empty( $rows ) ) {
			return null;
		}

		return \WBListora\REST\Claims_Controller::format_claim_row( $rows[0] );
	}

	/**
	 * Canonical user payload.
	 *
	 * Allow-list, never a spread of the WP_User object. A webhook payload
	 * leaves the site and lands in a third-party log; `user_pass` and
	 * `user_activation_key` are one careless array_merge away from being in
	 * it, and neither is recoverable once sent.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>|null Null when the user does not exist.
	 */
	public static function user( $user_id ) {
		$user_id = (int) $user_id;
		$user    = $user_id > 0 ? get_userdata( $user_id ) : false;

		if ( ! $user ) {
			return null;
		}

		return array(
			'id'           => (int) $user->ID,
			'display_name' => (string) $user->display_name,
			'email'        => (string) $user->user_email,
			'registered'   => (string) $user->user_registered,
		);
	}
}
