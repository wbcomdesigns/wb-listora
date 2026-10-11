<?php
/**
 * Stop a page whose feature is gone from serving a blank 200.
 *
 * When a page's feature or owning plugin is off, its blocks render nothing;
 * a 404 is more honest for visitors and search engines. Cautious, because a
 * wrong 404 is worse: only pages this plugin CREATED (creation meta key),
 * only when nothing else is on the page, never in admin or for a user who
 * can edit it, and `wb_listora_hide_unavailable_pages` turns it off.
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

		/**
		 * Filter whether to 404 a Listora page whose feature is unavailable.
		 *
		 * Return false for a page whose block explains itself while its
		 * feature is off (Pro's Buy Credits says purchases are paused).
		 *
		 * @since 1.7.0
		 * @since 1.9.0 Passes the page and its registry key.
		 *
		 * @param bool     $enabled True to hide.
		 * @param \WP_Post $post    Page being viewed.
		 * @param string   $key     Its Page_Registry key.
		 */
		if ( ! apply_filters( 'wb_listora_hide_unavailable_pages', true, $post, $key ) ) {
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
