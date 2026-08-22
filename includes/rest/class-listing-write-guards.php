<?php
/**
 * Guards every REST route that writes a listing, not just Listora's own.
 *
 * The CPT registers `show_in_rest` with `rest_base => listings`, so WordPress
 * publishes a second, fully functional write endpoint at `/wp/v2/listings`.
 * Listora's guards lived on {@see Listings_Controller}, which serves only
 * `listora/v1`, so the core route had none of them. Both of these were
 * reproduced against `/wp/v2/listings` before this file existed:
 *
 * - An author holding `edit_listora_listings` but NOT
 *   `publish_listora_listings` POSTed `status=listora_expired` and got HTTP 201
 *   with the status applied, skipping moderation on a publicly queryable
 *   status.
 * - The same author POSTed `featured_media` pointing at another member's
 *   upload and got HTTP 201 with it attached.
 *
 * The obvious fix — point `rest_controller_class` at Listings_Controller — was
 * tried and reverted. That controller also reshapes the collection response
 * into `{ listings: [...] }` with cursor pagination, which is right for its own
 * route and wrong for `/wp/v2`, where it would break every standard WordPress
 * REST client including the block editor. Verified: the core route started
 * returning the wrong shape.
 *
 * So the guards hook `rest_pre_insert_listora_listing` instead. That filter is
 * fired from `WP_REST_Posts_Controller::prepare_item_for_database()`, which
 * BOTH controllers inherit — one definition, both routes, and no response
 * touched. It also runs before the post is written, so a refusal is a real
 * refusal rather than a 201 that quietly dropped what was asked for.
 *
 * @package WBListora\Rest
 * @since 1.7.0
 */

namespace WBListora\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Capability and ownership guards for listing writes on any REST route.
 */
class Listing_Write_Guards {

	/**
	 * Register the guard.
	 */
	public static function init() {
		add_filter( 'rest_pre_insert_listora_listing', array( __CLASS__, 'guard' ), 10, 2 );
	}

	/**
	 * Refuse a write the caller is not entitled to make.
	 *
	 * @param \stdClass        $prepared Post about to be written.
	 * @param \WP_REST_Request $request  Incoming request.
	 * @return \stdClass|\WP_Error
	 */
	public static function guard( $prepared, $request ) {
		$status = self::check_status( $request );
		if ( is_wp_error( $status ) ) {
			return $status;
		}

		$media = self::check_media( $request );
		if ( is_wp_error( $media ) ) {
			return $media;
		}

		return $prepared;
	}

	/**
	 * Anything beyond draft/pending counts as publishing.
	 *
	 * Core's own `handle_status_param()` checks the publish capability for
	 * `publish`, `future` and `private`, then falls through to a default branch
	 * that accepts ANY registered status with no capability check. Listora
	 * registers `listora_expired` as public and publicly queryable, so that
	 * branch is a way to publish without the capability to publish.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return true|\WP_Error
	 */
	private static function check_status( $request ) {
		$status = isset( $request['status'] ) ? (string) $request['status'] : '';

		if ( '' === $status || in_array( $status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
			return true;
		}

		$post_type = get_post_type_object( 'listora_listing' );

		if ( $post_type && current_user_can( $post_type->cap->publish_posts ) ) {
			return true;
		}

		return new \WP_Error(
			'rest_cannot_publish',
			__( 'Sorry, you are not allowed to publish listings with that status.', 'wb-listora' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * An attachment must belong to the person attaching it.
	 *
	 * Core sets a featured image from any ID that resolves to an attachment,
	 * with no ownership test. On a directory that accepts ID documents for
	 * claim verification, "any attachment" includes those.
	 *
	 * Same helper `/submit` uses, so the rule has one definition.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return true|\WP_Error
	 */
	private static function check_media( $request ) {
		if ( ! isset( $request['featured_media'] ) ) {
			return true;
		}

		$attachment_id = (int) $request['featured_media'];

		if ( $attachment_id <= 0 ) {
			return true;
		}

		if ( ! function_exists( 'wb_listora_user_can_attach' ) || wb_listora_user_can_attach( $attachment_id ) ) {
			return true;
		}

		return new \WP_Error(
			'rest_cannot_attach_media',
			__( 'Sorry, you are not allowed to use that attachment.', 'wb-listora' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
