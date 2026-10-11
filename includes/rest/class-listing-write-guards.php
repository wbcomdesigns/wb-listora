<?php
/**
 * Guards every REST route that writes a listing, not just Listora's own.
 *
 * The CPT's `show_in_rest` also publishes `/wp/v2/listings`, which lacked
 * Listings_Controller's guards (status escalation, binding another member's
 * media). Swapping `rest_controller_class` is not an option: that controller
 * reshapes the collection response and would break core REST clients,
 * including the block editor. So the guards hook
 * `rest_pre_insert_listora_listing`, which both controllers inherit and which
 * runs before the post is written, so a refusal is a real refusal.
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
	public static function init(): void {
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

		// The block editor saves here; files already on the listing stay
		// usable by whoever may edit it.
		if ( isset( $request['id'] ) ) {
			wb_listora_keep_listing_media( (int) $request['id'] );
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
