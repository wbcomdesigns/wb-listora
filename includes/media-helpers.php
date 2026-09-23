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
	 * Submission and service endpoints used to take `featured_image`,
	 * `gallery[]`, file meta fields and `image_id` through `absint()` alone,
	 * with nothing asking whose file it was. Media IDs are sequential, so a
	 * member could attach any item in the library — another member's photos, or
	 * a claim-proof ID scan — and the listing's public detail response would
	 * then hand out its uploads URL.
	 *
	 * Reproduced before fixing: a contributor POSTed `featured_image` pointing at
	 * an administrator's attachment and got HTTP 201 with that file set as the
	 * listing thumbnail.
	 *
	 * Ownership first, then `edit_post`, so staff and editors keep working with
	 * the whole library while members are held to their own uploads.
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
	 * The ownership rule above is about BINDING a file. Re-saving a listing
	 * re-posts every file it already carries, so the rule dropped the ones the
	 * editor did not upload: a Listora Moderator's wp-admin Update emptied the
	 * gallery and the file fields (BC 10331918084), and a member's frontend
	 * edit threw away the photos an admin had added (BC 10331922348). Keeping
	 * a file that is already on the listing binds nothing new.
	 *
	 * Each write path names its listing before it sanitizes; the sanitizer
	 * then runs twice per value (once by the caller, once by the meta's
	 * registered sanitize_callback), and neither pass has the post ID, so the
	 * set lives for the request. Only the listing's owner or someone who may
	 * edit it adds to it.
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
	 * A listing title becomes the business name on the card, the detail page,
	 * the browser tab, the search result and the permalink. Submission only
	 * required it to be non-empty, so `https://example.com/thing` was accepted
	 * and shown as the name, leaving a permalink like
	 * `/listing/https-example-com-thing/`. That is what automated spam posting
	 * looks like: flood a directory with URL titles to get links in front of
	 * visitors.
	 *
	 * Deliberately narrow. It refuses a title that IS a web address — one with
	 * a scheme, or one beginning `www.` — and nothing else:
	 *
	 * - `https://example.com/x`  refused
	 * - `www.example.com`        refused
	 * - `Booking.com`            ALLOWED, it is a real company's real name
	 * - `Best Pizza, see foo.com` ALLOWED, a name with an address in it is not
	 *   the same thing as an address standing in for a name
	 *
	 * A bare domain with no scheme and no `www.` is left alone precisely
	 * because of the Booking.com case: there is no way to tell a spammy
	 * `example.com` from a legitimate brand, and refusing real business names is
	 * a worse failure than accepting the occasional bare domain.
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
	 * WHY THIS EXISTS. Uploads arrive before the listing does — a member picks
	 * photos at step 4 of the wizard and the post is not created until step 6 —
	 * so every image lands with `post_parent = 0`. Nothing set it afterwards.
	 * The image was linked to the listing only through `_thumbnail_id` and the
	 * `gallery` meta, which the Media Library does not read, so in wp-admin
	 * every member upload showed an empty "Uploaded to" column and sat under
	 * the Unattached filter. An owner with a few hundred listings had a media
	 * library of loose files with nothing tying them to anything.
	 *
	 * Parenting them fixes that view for free: "Uploaded to" names the listing,
	 * the Unattached filter stops being a dumping ground, and deleting a
	 * listing has something to identify its images BY (see the eraser).
	 *
	 * ONLY RE-PARENTS AN UNATTACHED FILE. `post_parent > 0` means the image
	 * already belongs to something — another listing, a page, a product — and
	 * stealing it would break that owner's "Uploaded to" instead. Re-using one
	 * image across two listings is legitimate; the first listing keeps it and
	 * the second still references it through its own meta.
	 *
	 * Ownership is NOT re-checked here. Every caller passes IDs that have
	 * already been through wb_listora_user_can_attach() or
	 * wb_listora_filter_attachable_ids(), and this function must not become a
	 * second, drifting copy of that rule.
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
