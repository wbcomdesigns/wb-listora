<?php
/**
 * Search filters carried in the URL - one parser for every surface.
 *
 * The Search block navigates to the current URL with ?keyword=&type=&category=
 * &location=&features=&min_rating=&date_*=&sort=&bounds[] so each block can
 * render the filtered view server-side.
 *
 * Before this helper the grid parsed all eleven of those and resolved them
 * through Search_Engine, while the map block parsed exactly ONE (bounds) and
 * hand-rolled its own SQL against search_index. On the Directory page the two
 * render side by side, so searching "cafe" produced a grid reading "No results"
 * next to a map still showing pins for every non-matching listing. Two views of
 * one query, disagreeing in public.
 *
 * Any surface that renders a filtered set of listings MUST build its args here
 * and resolve them through Search_Engine. Parsing the URL a second time, or
 * writing a second query against search_index, reintroduces the same class of
 * bug and it will not be visible on the screen you are testing.
 *
 * @package WB_Listora
 * @since   1.5.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wb_listora_search_args_from_url' ) ) {
	/**
	 * Build Search_Engine args from the current request's URL filters.
	 *
	 * Read-only public filtering, so no nonce is involved; every value is
	 * sanitized here and the engine re-validates on its own side.
	 *
	 * @since 1.5.0
	 *
	 * @param array<string, mixed> $overrides Values that win over the URL, e.g.
	 *                                        a `type` pinned by a block
	 *                                        attribute, or `per_page`.
	 * @return array<string, mixed> Args accepted by
	 *                              \WBListora\Search\Search_Engine::search().
	 *                              Values are mixed because the engine takes
	 *                              strings, ints and the `bounds` float map.
	 */
	function wb_listora_search_args_from_url( array $overrides = array() ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended

		$args = array(
			'type'        => isset( $_GET['type'] ) ? sanitize_key( wp_unslash( (string) $_GET['type'] ) ) : '',
			'keyword'     => isset( $_GET['keyword'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['keyword'] ) ) : '',
			// Category and location accept a slug, a numeric term ID, or (for
			// location) free-form geo text. Pass the raw string through - the
			// engine resolves it and falls back to geo-text matching.
			'category'    => isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['category'] ) ) : '',
			'location'    => isset( $_GET['location'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['location'] ) ) : '',
			'features'    => isset( $_GET['features'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['features'] ) ) : '',
			// Tag chips on a listing detail page link to `?tags=<slug>`. This
			// helper feeds the SERVER render of the grid and map, so without
			// it the first paint showed the whole directory and only the JS
			// hydration narrowed it — a visible flash, and nothing at all
			// without JS (BC 10199195886).
			'tags'        => isset( $_GET['tags'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['tags'] ) ) : '',
			'min_rating'  => isset( $_GET['min_rating'] ) ? (int) $_GET['min_rating'] : 0,
			'date_filter' => isset( $_GET['date_filter'] ) ? sanitize_key( wp_unslash( (string) $_GET['date_filter'] ) ) : '',
			'date_from'   => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_from'] ) ) : '',
			'date_to'     => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_to'] ) ) : '',
		);

		$bounds = wb_listora_search_bounds_from_url();
		if ( $bounds ) {
			$args['bounds'] = $bounds;
		}

		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return array_merge( $args, $overrides );
	}
}

if ( ! function_exists( 'wb_listora_search_bounds_from_url' ) ) {
	/**
	 * Parse the map viewport bounds from the URL.
	 *
	 * Returns an empty array unless all four corners are present - a partial
	 * box would silently constrain the query to a nonsense region.
	 *
	 * @since 1.5.0
	 *
	 * @return array{ne_lat?:float,ne_lng?:float,sw_lat?:float,sw_lng?:float}
	 */
	function wb_listora_search_bounds_from_url() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['bounds'] ) || ! is_array( $_GET['bounds'] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended -- every value cast to float below.
		$raw = wp_unslash( $_GET['bounds'] );

		if ( ! isset( $raw['ne_lat'], $raw['ne_lng'], $raw['sw_lat'], $raw['sw_lng'] ) ) {
			return array();
		}

		return array(
			'ne_lat' => (float) $raw['ne_lat'],
			'ne_lng' => (float) $raw['ne_lng'],
			'sw_lat' => (float) $raw['sw_lat'],
			'sw_lng' => (float) $raw['sw_lng'],
		);
	}
}

if ( ! function_exists( 'wb_listora_search_sort_from_url' ) ) {
	/**
	 * Resolve the sort key from the URL against the allowlist.
	 *
	 * Defence in depth: an unknown value falls back to the default rather than
	 * reaching the engine's ORDER BY mapping.
	 *
	 * @since 1.5.0
	 *
	 * @param string $default Fallback when absent or not allowed.
	 * @return string
	 */
	function wb_listora_search_sort_from_url( $default = 'featured' ) {
		$allowed = array( 'featured', 'newest', 'rating', 'price_asc', 'price_desc', 'most_reviewed', 'alphabetical', 'distance', 'relevance' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sort = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( (string) $_GET['sort'] ) ) : '';

		return in_array( $sort, $allowed, true ) ? $sort : $default;
	}
}
