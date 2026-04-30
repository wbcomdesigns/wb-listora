<?php
/**
 * Theme defenses — keep Listora layout-owning blocks consistent across themes.
 *
 * Listora has its own page layouts (User Dashboard, Search, etc.) that need the
 * full content width to render correctly. WordPress themes vary wildly in how
 * they expose page templates — some default to a sidebar-and-content layout,
 * some serve a narrow `max-width` content area, some inject widget areas as
 * siblings to the post content. When that happens to a page where a Listora
 * layout-owning block lives, the block ends up cramped or visibly overlapped
 * by the theme's widget area (Basecamp 9834124720).
 *
 * Rather than ask users to manually pick "Full Width" page templates per theme
 * (and rather than instructing site owners to remove the theme sidebar), we
 * detect the block at render time, add a body class, and ship CSS that
 * neutralizes the most common theme sidebar conventions.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a body class on pages where a Listora layout-owning block is present
 * so theme-isolation CSS can suppress sidebars and force full-width content.
 */
class Theme_Defenses {

	/**
	 * Listora blocks whose layout requires the full content width. Adding a
	 * block to this list opts the page it sits on into the full-width body
	 * class, which is consumed by `assets/css/shared.css`.
	 *
	 * Derived from `audit/manifest.json` — every block where
	 * `blocks[].layout_owning === true`, plus `listing-search` which the
	 * static detector misses because its multi-column layout (filters /
	 * map / results) is composed client-side via the Interactivity API
	 * rather than as a top-level CSS grid in render.php.
	 *
	 * @var string[]
	 */
	private const FULLWIDTH_BLOCKS = array(
		'listora/listing-grid',
		'listora/listing-map',
		'listora/listing-detail',
		'listora/listing-reviews',
		'listora/listing-submission',
		'listora/listing-categories',
		'listora/listing-featured',
		'listora/listing-calendar',
		'listora/listing-search',
		'listora/user-dashboard',
	);

	/**
	 * Hook into WordPress.
	 */
	public function register() {
		add_filter( 'body_class', array( $this, 'maybe_add_fullwidth_class' ), 20 );
	}

	/**
	 * Append `wb-listora-fullwidth` to the <body> classes when the singular
	 * post being rendered contains any of the layout-owning Listora blocks.
	 *
	 * Filterable via `wb_listora_fullwidth_blocks` so themes/extensions can
	 * register additional blocks without modifying core.
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public function maybe_add_fullwidth_class( $classes ) {
		if ( ! is_singular() ) {
			return $classes;
		}

		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return $classes;
		}

		/**
		 * Filter the list of Listora blocks that opt their host page into
		 * the full-width layout class.
		 *
		 * @param string[] $blocks Block names (e.g. 'wb-listora/user-dashboard').
		 * @param \WP_Post $post   The post being rendered.
		 */
		$blocks = (array) apply_filters( 'wb_listora_fullwidth_blocks', self::FULLWIDTH_BLOCKS, $post );

		foreach ( $blocks as $block_name ) {
			if ( has_block( $block_name, $post ) ) {
				$classes[] = 'wb-listora-fullwidth';
				return $classes;
			}
		}

		return $classes;
	}
}
