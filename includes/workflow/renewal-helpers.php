<?php
/**
 * Renewal helpers — the public way to renew a listing outside a REST request.
 *
 * Renewal is a money path (quote, hold, write, commit-or-cancel) implemented
 * once, in {@see \WBListora\REST\Listings_Controller::renew_listing()}. This is
 * the documented entry point for callers with no REST request, such as the
 * expiry sweep and Pro (INV-3). Add missing behaviour to the controller, never
 * a second implementation here.
 *
 * @package WBListora
 * @since 1.7.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wb_listora_renew_listing' ) ) {

	/**
	 * Renew a listing, charging its owner's credit balance.
	 *
	 * Runs the owner's own renewal code, charging the LISTING AUTHOR. No
	 * capability check: callers are trusted system paths, not requests.
	 *
	 * @since 1.7.0
	 *
	 * @param int   $listing_id Listing to renew.
	 * @param array<string,mixed> $context    Optional. Free-form context for listeners, e.g.
	 *                          `array( 'source' => 'auto_renew' )`.
	 * @return true|\WP_Error True on success; WP_Error on refusal. Callers
	 *                        should expect `insufficient_credits` as an ordinary
	 *                        outcome, not an exceptional one.
	 */
	function wb_listora_renew_listing( $listing_id, array $context = array() ) {
		$listing_id = (int) $listing_id;
		$post       = get_post( $listing_id );

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new WP_Error(
				'listora_not_found',
				__( 'Listing not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		$author_id = (int) $post->post_author;
		if ( $author_id <= 0 ) {
			return new WP_Error(
				'listora_renewal_no_owner',
				__( 'Listing has no owner to charge.', 'wb-listora' ),
				array( 'status' => 400 )
			);
		}

		/**
		 * Fires before a listing is renewed outside a REST request.
		 *
		 * @since 1.7.0
		 *
		 * @param int   $listing_id Listing being renewed.
		 * @param array $context    Caller-supplied context.
		 */
		do_action( 'wb_listora_before_system_renew_listing', $listing_id, $context );

		$previous_user = get_current_user_id();
		wp_set_current_user( $author_id );

		try {
			$controller = new \WBListora\REST\Listings_Controller();

			$request = new WP_REST_Request(
				'POST',
				'/' . WB_LISTORA_REST_NAMESPACE . '/listings/' . $listing_id . '/renew'
			);
			$request->set_param( 'id', $listing_id );

			$result = $controller->renew_listing( $request );
		} finally {
			// Restore in a finally so a throw inside the controller cannot
			// leave the request running as somebody else.
			wp_set_current_user( $previous_user );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}
}
