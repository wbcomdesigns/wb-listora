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

		return user_can( $user_id, 'edit_post', $attachment_id );
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
	 * @param array $ids     Candidate attachment IDs.
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
