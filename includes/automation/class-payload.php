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
}
