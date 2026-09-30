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
	 * class, which is consumed by `assets/css/listora-base.css`.
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

		// Theme isolation (owner decision 2026-09-25, cards 10340592969 and
		// 10337175822): on the frontend every Listora stylesheet is printed
		// inside `@layer listora`, and assets/css/listora-isolation.css (the
		// one unlayered rule) reverts theme styles inside Listora roots back
		// to that layer. wp-admin and the block editor are untouched: they
		// load the same files and have no reset, so layering there would let
		// every unlayered admin rule win.
		if ( ! is_admin() ) {
			add_filter( 'style_loader_tag', array( $this, 'layer_listora_styles' ), 10, 4 );
			add_action( 'wp_head', array( $this, 'keep_listora_styles_linked' ), 0 );
			add_action( 'wp_footer', array( $this, 'keep_listora_styles_linked' ), 0 );
		}
	}

	/**
	 * Print a Listora stylesheet inside the listora cascade layer.
	 *
	 * `<link>` has no layer attribute, so the sheet is imported into the layer
	 * from a `<style>` element instead, with a preload so the fetch still
	 * starts as early as the link would have.
	 *
	 * @since 1.9.0
	 *
	 * @param string $tag    The link tag.
	 * @param string $handle Style handle.
	 * @param string $href   Stylesheet URL (HTML-escaped).
	 * @param string $media  Media attribute.
	 * @return string
	 */
	public function layer_listora_styles( $tag, $handle, $href, $media ) {
		if ( 'listora-isolation' === $handle || ! self::is_listora_style( (string) $href ) ) {
			return $tag;
		}

		// Inside <style>, entities are not decoded, so the URL goes in raw,
		// escaped for a CSS string. Registered handle URLs are trusted; '<'
		// is dropped so nothing can close the element.
		$url = str_replace( array( '\\', '"', '<' ), array( '\\\\', '\\"', '' ), html_entity_decode( (string) $href, ENT_QUOTES ) );

		return sprintf(
			'<link rel="preload" href="%1$s" as="style" />' . "\n" . '<style id="%2$s-css" media="%3$s">@import url("%4$s") layer(listora);</style>' . "\n",
			esc_url( $url ),
			esc_attr( $handle ),
			esc_attr( $media ? $media : 'all' ),
			$url
		);
	}

	/**
	 * Stop WordPress inlining small Listora block stylesheets.
	 *
	 * wp_maybe_inline_styles() prints a stylesheet with a `path` as a plain
	 * <style> of its contents, which would sit outside the layer and be
	 * reverted by the isolation rule. Without the path it is printed as a
	 * link, which layer_listora_styles() then layers.
	 *
	 * @since 1.9.0
	 *
	 * @return void
	 */
	public function keep_listora_styles_linked() {
		$styles = wp_styles();
		foreach ( $styles->registered as $handle => $style ) {
			if ( 'listora-isolation' !== $handle && is_string( $style->src ) && self::is_listora_style( $style->src ) ) {
				unset( $styles->registered[ $handle ]->extra['path'] );
			}
		}
	}

	/**
	 * Whether a stylesheet URL belongs to Listora.
	 *
	 * @param string $src Stylesheet URL.
	 * @return bool
	 */
	private static function is_listora_style( $src ) {
		/**
		 * Filter the plugin URLs whose stylesheets are printed inside the
		 * listora cascade layer on the frontend.
		 *
		 * Pro adds its own URL; an add-on whose styles are meant to sit with
		 * Listora's can add its own.
		 *
		 * @since 1.9.0
		 *
		 * @param string[] $bases Plugin base URLs.
		 */
		$bases = (array) apply_filters( 'wb_listora_layered_style_bases', array( WB_LISTORA_PLUGIN_URL ) );
		$src   = html_entity_decode( $src, ENT_QUOTES );
		foreach ( $bases as $base ) {
			if ( '' !== (string) $base && 0 === strpos( $src, (string) $base ) ) {
				return true;
			}
		}
		return false;
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
