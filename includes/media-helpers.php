<?php
/**
 * Media ownership helpers.
 *
 * Attachment IDs arrive from clients as bare integers, and an integer is a
 * guess away from someone else's file. Every endpoint that binds an attachment
 * to a listing, a service or a meta field asks this first.
 *
 * @package WBListora
 * @since 1.7.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wb_listora_user_can_attach' ) ) {

	/**
	 * Whether a user may attach a given media item to their own content.
	 *
	 * Media IDs are sequential, so without this check a member could bind any
	 * library item (another member's photos, a claim-proof ID scan) and the
	 * listing's public response would expose its URL. Ownership first, then
	 * `edit_post`, so staff keep the whole library and members their own uploads.
	 *
	 * @since 1.7.0
	 *
	 * @param int $attachment_id Attachment to bind.
	 * @param int $user_id       Optional. Defaults to the current user.
	 * @return bool True when the attachment exists and this user may use it.
	 */
	function wb_listora_user_can_attach( $attachment_id, $user_id = 0 ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return false;
		}

		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return false;
		}

		$user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}

		if ( (int) $attachment->post_author === $user_id ) {
			return true;
		}

		if ( get_current_user_id() === $user_id && in_array( $attachment_id, wb_listora_keep_listing_media(), true ) ) {
			return true;
		}

		return user_can( $user_id, 'edit_post', $attachment_id );
	}
}

if ( ! function_exists( 'wb_listora_keep_listing_media' ) ) {

	/**
	 * Media already on the listing being saved, which its editor may keep.
	 *
	 * Re-saving re-posts every file the listing carries; keeping one that is
	 * already there binds nothing new, so the ownership rule must not drop it.
	 * The sanitizer runs without the post ID, so each write path names its
	 * listing first and the set lives for the request. Only the owner or
	 * someone who may edit the listing adds to it.
	 *
	 * @since 1.8.0
	 *
	 * @param int $listing_id Optional. Listing about to be saved.
	 * @return int[] Attachment IDs kept for this request.
	 */
	function wb_listora_keep_listing_media( $listing_id = 0 ) {
		static $kept = array();

		$listing_id = absint( $listing_id );
		if ( $listing_id < 1 || 'listora_listing' !== get_post_type( $listing_id ) ) {
			return $kept;
		}

		// The owner (the frontend edit form is owner-only, and a member holds
		// no edit_post on listings) or anyone wp-admin lets edit it.
		$is_owner = get_current_user_id() > 0 && (int) get_post_field( 'post_author', $listing_id ) === get_current_user_id();
		if ( ! $is_owner && ! current_user_can( 'edit_post', $listing_id ) ) {
			return $kept;
		}

		$ids  = array( (int) get_post_thumbnail_id( $listing_id ) );
		$ids  = array_merge( $ids, (array) get_post_meta( $listing_id, WB_LISTORA_META_PREFIX . 'gallery', true ) );
		$type = \WBListora\Core\Listing_Type_Registry::instance()->get_for_post( $listing_id );

		if ( $type ) {
			foreach ( $type->get_all_fields() as $field ) {
				if ( in_array( $field->get_type(), array( 'gallery', 'file' ), true ) ) {
					$ids = array_merge( $ids, (array) get_post_meta( $listing_id, WB_LISTORA_META_PREFIX . $field->get_key(), true ) );
				}
			}
		}

		// Service photos belong to the listing too: an admin-added photo was
		// dropped when the member edited that service.
		foreach ( \WBListora\Core\Services::get_services( $listing_id, 'all' ) as $service ) {
			$ids[] = (int) ( is_array( $service ) ? ( $service['image_id'] ?? 0 ) : ( $service->image_id ?? 0 ) );
		}

		$kept = array_values( array_unique( array_merge( $kept, array_filter( array_map( 'absint', $ids ) ) ) ) );

		return $kept;
	}
}

if ( ! function_exists( 'wb_listora_filter_attachable_ids' ) ) {

	/**
	 * Reduce a list of attachment IDs to the ones this user may attach.
	 *
	 * Silently dropping the rest is deliberate. A gallery is often assembled
	 * over several uploads and one stale ID should not fail the whole
	 * submission — the member would have no way to tell which item was the
	 * problem, and the listing they had just written would be refused wholesale.
	 *
	 * @since 1.7.0
	 *
	 * @param array<int|string> $ids     Candidate attachment IDs.
	 * @param int   $user_id Optional. Defaults to the current user.
	 * @return int[] The IDs this user may use, reindexed.
	 */
	function wb_listora_filter_attachable_ids( $ids, $user_id = 0 ) {
		$ids = array_map( 'absint', (array) $ids );

		return array_values(
			array_filter(
				$ids,
				static function ( $id ) use ( $user_id ) {
					return wb_listora_user_can_attach( $id, $user_id );
				}
			)
		);
	}
}

if ( ! function_exists( 'wb_listora_title_is_url' ) ) {

	/**
	 * Whether a title is nothing but a web address.
	 *
	 * Refuses a title with a scheme or beginning `www.` (URL-title spam), and
	 * nothing else: `Booking.com` and `Best Pizza, see foo.com` are allowed,
	 * because a bare domain cannot be told apart from a real brand name.
	 *
	 * @since 1.7.0
	 *
	 * @param string $title Proposed title.
	 * @return bool True when the title is only a web address.
	 */
	function wb_listora_title_is_url( $title ) {
		$title = trim( wp_strip_all_tags( (string) $title ) );

		if ( '' === $title ) {
			return false;
		}

		// A name can contain a web address; only a title that is ENTIRELY one
		// is refused, so anything with whitespace is someone's actual name.
		if ( preg_match( '/\s/', $title ) ) {
			return false;
		}

		if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $title ) ) {
			return true;
		}

		return (bool) preg_match( '#^www\.[^.]+\.#i', $title );
	}
}

if ( ! function_exists( 'wb_listora_attach_media_to_listing' ) ) {

	/**
	 * Parent a listing's images to the listing.
	 *
	 * Uploads happen before the listing exists, so they land with
	 * `post_parent = 0` and show as Unattached in the Media Library. Only an
	 * unattached file is re-parented; one already belonging to something else
	 * keeps its parent. Ownership is not re-checked: callers pass IDs already
	 * filtered by wb_listora_user_can_attach() / wb_listora_filter_attachable_ids().
	 *
	 * @since 1.7.0
	 *
	 * @param int   $listing_id     Listing to parent the images to.
	 * @param array<int|string> $attachment_ids Attachment IDs, already ownership-checked.
	 * @return int Number of attachments re-parented.
	 */
	function wb_listora_attach_media_to_listing( $listing_id, $attachment_ids ) {
		$listing_id = absint( $listing_id );
		if ( $listing_id < 1 || ! is_array( $attachment_ids ) ) {
			return 0;
		}

		$attached = 0;

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = absint( $attachment_id );
			if ( $attachment_id < 1 ) {
				continue;
			}

			$attachment = get_post( $attachment_id );
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				continue;
			}

			// Already spoken for — leave it where it is.
			if ( (int) $attachment->post_parent > 0 ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'          => $attachment_id,
					'post_parent' => $listing_id,
				)
			);

			++$attached;
		}

		/**
		 * Fires after a listing's images have been parented to it.
		 *
		 * @since 1.7.0
		 *
		 * @param int   $listing_id     Listing the images now belong to.
		 * @param array $attachment_ids IDs that were considered.
		 * @param int   $attached       How many were actually re-parented.
		 */
		do_action( 'wb_listora_media_attached_to_listing', $listing_id, $attachment_ids, $attached );

		return $attached;
	}
}
