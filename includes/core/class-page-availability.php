<?php
/**
 * Stop a page whose feature is gone from serving a blank 200.
 *
 * Deactivate the plugin that owns a page, or just switch its feature off, and
 * the page stays published while its blocks render nothing. The site then
 * answers `/compare-listings/` with a page that has a title, the theme's
 * chrome, and no content whatsoever. Measured on a real downgrade: four such
 * pages, HTTP 200, zero characters inside the content area.
 *
 * That is worse than a 404 in every direction. A visitor thinks the site is
 * broken, a search engine indexes an empty page, and the owner has no signal
 * anything happened — a 404 at least tells the truth and is reversible the
 * moment the feature comes back.
 *
 * Deliberately cautious, because wrongly 404ing a live page is a far worse
 * failure than the one being fixed:
 *
 *   - Only pages this plugin CREATED are considered, identified by the meta
 *     key stamped at creation. A page the owner built themselves is theirs.
 *   - Only when the page has nothing else on it. Add a paragraph of your own
 *     above the block and the page keeps working, because it now has content
 *     that does not depend on the feature.
 *   - Never in the admin, never for a logged-in user who can edit the page —
 *     they need to be able to see and fix it.
 *   - `wb_listora_hide_unavailable_pages` turns the whole thing off.
 *
 * @package WBListora\Core
 * @since 1.7.0
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Serves 404 for a registered page whose feature is unavailable.
 */
class Page_Availability {

	/**
	 * Register the guard.
	 */
	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_hide' ) );
	}

	/**
	 * Turn a blank feature page into an honest 404.
	 */
	public static function maybe_hide(): void {
		if ( is_admin() || ! is_page() ) {
			return;
		}

		/**
		 * Filter whether to 404 a Listora page whose feature is unavailable.
		 *
		 * @since 1.7.0
		 *
		 * @param bool $enabled True to hide.
		 */
		if ( ! apply_filters( 'wb_listora_hide_unavailable_pages', true ) ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		// Someone who can fix it must be able to see it.
		if ( current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		$key = Page_Registry::key_for_page( (int) $post->ID );
		if ( '' === $key ) {
			return;
		}

		if ( Page_Registry::is_available( $key ) ) {
			return;
		}

		// The owner has added something of their own — that content does not
		// depend on the feature, so the page is not empty and stays up.
		if ( self::has_own_content( $post ) ) {
			return;
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Whether the page holds anything beyond Listora blocks.
	 *
	 * @param \WP_Post $post Page being viewed.
	 * @return bool
	 */
	private static function has_own_content( \WP_Post $post ): bool {
		$content = (string) $post->post_content;

		if ( ! has_blocks( $content ) ) {
			// Classic content: any non-whitespace at all counts as theirs.
			return '' !== trim( wp_strip_all_tags( $content ) );
		}

		foreach ( parse_blocks( $content ) as $block ) {
			$name = (string) ( $block['blockName'] ?? '' );

			// A null blockName is the raw text between blocks — usually just
			// the newlines WordPress puts there.
			if ( '' === $name ) {
				if ( '' !== trim( wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) ) ) ) {
					return true;
				}

				continue;
			}

			if ( 0 !== strpos( $name, 'listora/' ) && 0 !== strpos( $name, 'listora-pro/' ) ) {
				return true;
			}
		}

		return false;
	}
}
