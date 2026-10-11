<?php
/**
 * Listing term archives render the Directory page.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Category, location, tag, feature and type archives fell through to the
 * theme's blog loop (cards 10370410858, 10355778337). Owner decision
 * 2026-10-11: keep the term URL and render the owner's Directory page there,
 * pre-filtered to the term, with the term title, description and a
 * breadcrumb. The bare listing post-type archive redirects to the Directory.
 *
 * Works on block and classic themes because WordPress itself loads the
 * Directory page; the term only becomes the filter the Directory already
 * reads from the URL (wb_listora_url_arg()).
 */
class Term_Archives {

	/**
	 * Taxonomy => the Directory's URL filter name.
	 */
	private const FILTERS = array(
		'listora_listing_cat'      => 'category',
		'listora_listing_location' => 'location',
		'listora_listing_tag'      => 'tags',
		'listora_listing_feature'  => 'features',
		'listora_listing_type'     => 'type',
	);

	/**
	 * The term being shown, when this request is a term archive.
	 *
	 * @var \WP_Term|null
	 */
	private $term = null;

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'request', array( $this, 'route_term_archive' ) );
		add_action( 'template_redirect', array( $this, 'redirect_post_type_archive' ) );
		add_filter( 'document_title_parts', array( $this, 'document_title' ) );
		add_filter( 'the_title', array( $this, 'page_title' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'intro' ), 5 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_filter( 'get_canonical_url', array( $this, 'canonical' ) );
		add_filter( 'wpseo_canonical', array( $this, 'canonical' ) );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'canonical' ) );
	}

	/**
	 * Swap a listing term request for the Directory page, filtered to the term.
	 *
	 * @param array<string, mixed> $vars Parsed query vars.
	 * @return array<string, mixed>
	 */
	public function route_term_archive( $vars ) {
		if ( is_admin() || ! is_array( $vars ) ) {
			return $vars;
		}

		/**
		 * Filter: map of listing taxonomy => Directory URL filter used for term archives.
		 *
		 * Return an empty array to leave term archives to the theme.
		 *
		 * @since 1.9.1
		 *
		 * @param array<string, string> $filters Taxonomy => filter name.
		 */
		$filters = (array) apply_filters( 'wb_listora_term_archive_filters', self::FILTERS );

		foreach ( $filters as $taxonomy => $filter ) {
			$tax = get_taxonomy( $taxonomy );
			$qv  = $tax && $tax->query_var ? $tax->query_var : '';
			if ( '' === $qv || empty( $vars[ $qv ] ) || ! is_string( $vars[ $qv ] ) ) {
				continue;
			}

			$slug = basename( $vars[ $qv ] );
			$term = get_term_by( 'slug', $slug, $taxonomy );
			$page = Page_Registry::get_id( 'directory' );
			if ( ! $term || ! $page ) {
				return $vars; // Unknown term: let WordPress 404. No Directory page: theme archive.
			}

			$this->term = $term;
			// The Directory blocks read their filters from the URL.
			$_GET[ 'listora_' . $filter ] = $term->slug; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter, same as a ?listora_category= link.

			return array( 'page_id' => $page );
		}

		return $vars;
	}

	/**
	 * The bare /listing/ archive is the theme's blog loop; send it to the Directory.
	 *
	 * @return void
	 */
	public function redirect_post_type_archive() {
		if ( ! is_post_type_archive( 'listora_listing' ) || is_feed() ) {
			return;
		}
		$url = wb_listora_get_page_url( 'directory' );
		if ( '' !== $url ) {
			wp_safe_redirect( $url, 301 );
			exit;
		}
	}

	/**
	 * Browser tab and SEO title: the term, not "Directory".
	 *
	 * @param array<string, string> $parts Title parts.
	 * @return array<string, string>
	 */
	public function document_title( $parts ) {
		if ( $this->term ) {
			$parts['title'] = $this->term->name;
		}
		return $parts;
	}

	/**
	 * Page heading: the term name instead of the Directory page title.
	 *
	 * @param string $title   Post title.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public function page_title( $title, $post_id = 0 ) {
		if ( $this->term && (int) $post_id === Page_Registry::get_id( 'directory' ) && in_the_loop() && is_main_query() ) {
			return $this->term->name;
		}
		return $title;
	}

	/**
	 * Breadcrumb and term description above the directory.
	 *
	 * @param string $content Page content.
	 * @return string
	 */
	public function intro( $content ) {
		if ( ! $this->term || ! in_the_loop() || ! is_main_query() || get_the_ID() !== Page_Registry::get_id( 'directory' ) ) {
			return $content;
		}

		$crumbs = sprintf(
			'<nav class="listora-term-archive__crumbs" aria-label="%1$s"><a href="%2$s">%3$s</a> <span aria-hidden="true">›</span> <span aria-current="page">%4$s</span></nav>',
			esc_attr__( 'Breadcrumb', 'wb-listora' ),
			esc_url( wb_listora_get_page_url( 'directory' ) ),
			// Raw title: get_the_title() runs page_title() above and would print the term twice.
			esc_html( get_post( Page_Registry::get_id( 'directory' ) )->post_title ?? '' ),
			esc_html( $this->term->name )
		);
		$description = term_description( $this->term->term_id );
		$intro       = '<div class="listora-term-archive">' . $crumbs . ( $description ? '<div class="listora-term-archive__desc">' . wp_kses_post( $description ) . '</div>' : '' ) . '</div>';

		/**
		 * Filter: the markup printed above the directory on a term archive.
		 *
		 * @since 1.9.1
		 *
		 * @param string   $intro Breadcrumb + description HTML.
		 * @param \WP_Term $term  The term.
		 */
		return apply_filters( 'wb_listora_term_archive_intro', $intro, $this->term ) . $content;
	}

	/**
	 * Body classes a theme or site can style the term view with.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function body_class( $classes ) {
		if ( $this->term ) {
			$classes[] = 'listora-term-archive';
			$classes[] = 'listora-term-archive--' . sanitize_html_class( $this->term->taxonomy );
		}
		return $classes;
	}

	/**
	 * Canonical stays the term URL, not the Directory page.
	 *
	 * @param string $url Canonical URL.
	 * @return string
	 */
	public function canonical( $url ) {
		if ( ! $this->term ) {
			return $url;
		}
		$link = get_term_link( $this->term );
		return is_wp_error( $link ) ? $url : $link;
	}
}
