<?php
/**
 * Image_Schema — the single shape for every image REST returns.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the canonical `featured_image` payload.
 *
 * Before 1.6.0 three separate hand-maintained builders produced three
 * different shapes for the same attachment (BC 10194450677 / 10203381688).
 * Measured on one listing whose attachment had every size registered, so the
 * divergence was code and not data:
 *
 *     /detail   id, alt, thumbnail, medium, large,        full
 *     /related  id, alt, thumbnail, medium, medium_large, full
 *     /search   id, alt, thumbnail, medium,               full
 *
 * They disagreed on three further points nobody had noticed:
 *
 * - A missing size was `false` from the detail builder and `''` from the
 *   other two, so the field's TYPE depended on the endpoint.
 * - A listing with no image was `[]` on detail and `null` elsewhere.
 * - The detail builder's docblock claimed it matched the search controller.
 *   It did not, and had not for some time.
 *
 * The shape here is the UNION of what the three already returned, so every
 * endpoint gains sizes and none loses one — additive for existing clients.
 *
 * @since 1.6.0
 */
class Image_Schema {

	/**
	 * Every size the canonical payload carries, in ascending order.
	 *
	 * Adding one is additive and safe. REMOVING one is a breaking API change
	 * for the mobile app and any integrator — treat it as such.
	 *
	 * @var string[]
	 */
	public const SIZES = array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' );

	/**
	 * Build the canonical image payload for an attachment.
	 *
	 * @since 1.6.0
	 *
	 * @param int|false     $attachment_id Attachment ID. Falsy returns null —
	 *                                     get_post_thumbnail_id() returns false
	 *                                     for a post with no thumbnail, so
	 *                                     callers may hand that straight over.
	 * @param string[]|null $sizes         Subset of self::SIZES to include.
	 *                                     Null means all of them.
	 * @return array<string,mixed>|null Null when there is no image — never an
	 *                                  empty array, so "no image" is one value
	 *                                  across every endpoint.
	 */
	public static function for_attachment( $attachment_id, $sizes = null ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 ) {
			return null;
		}

		$sizes = self::normalize_sizes( $sizes );

		$image = array(
			'id'  => $attachment_id,
			'alt' => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		);

		foreach ( $sizes as $size ) {
			$url = wp_get_attachment_image_url( $attachment_id, $size );

			// Always a string. wp_get_attachment_image_url() returns false for
			// a size the attachment does not have, which used to leak a boolean
			// into JSON on one endpoint and an empty string on the others.
			$image[ $size ] = $url ? (string) $url : '';
		}

		return $image;
	}

	/**
	 * Build the canonical payload from a post's featured image.
	 *
	 * @since 1.6.0
	 *
	 * @param int           $post_id Post ID.
	 * @param string[]|null $sizes   Subset of self::SIZES; null means all.
	 * @return array<string,mixed>|null
	 */
	public static function for_post( $post_id, $sizes = null ) {
		return self::for_attachment( get_post_thumbnail_id( (int) $post_id ), $sizes );
	}

	/**
	 * Validate a requested size list against the canonical set.
	 *
	 * An unknown or empty request falls back to the full set rather than
	 * erroring — a client asking for a size we do not publish should get the
	 * standard payload, not a broken one.
	 *
	 * @since 1.6.0
	 *
	 * @param mixed $raw Array, or a comma-separated string.
	 * @return string[]
	 */
	public static function normalize_sizes( $raw ) {
		if ( empty( $raw ) ) {
			return self::SIZES;
		}

		$requested = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$requested = array_map( 'strtolower', array_map( 'trim', $requested ) );
		$requested = array_values( array_intersect( self::SIZES, $requested ) );

		return empty( $requested ) ? self::SIZES : $requested;
	}
}
