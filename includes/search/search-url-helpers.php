<?php
/**
 * Search filters carried in the URL - one parser for every surface.
 *
 * The Search block navigates to the current URL with ?listora_keyword=
 * &listora_type=&listora_category=&listora_location=&listora_features=
 * &listora_min_rating=&listora_date_*=&listora_sort=&listora_bounds[] so each
 * block can render the filtered view server-side.
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

/*
 * Every directory filter travels in the page URL as `listora_{name}`
 * (`listora_category`, `listora_type`, `listora_price_min`, ...). The bare
 * names used before 1.9.0 - `category`, `type`, `page`, `location` - are
 * WordPress query vars or names other plugins claim: a plugin whose post
 * type uses `category` as its query var 404s every `?category=` request
 * before Listora runs, and `?page=2` is 301'd back to page 1 by core
 * (card 10335750932). The bare names are still READ, so old links and
 * bookmarks keep filtering on sites where nothing else owns them; they are
 * never written. REST routes keep their own parameter names.
 */

if ( ! function_exists( 'wb_listora_url_arg' ) ) {
	/**
	 * Read one directory filter from the page URL.
	 *
	 * `listora_{name}` wins; the pre-1.9.0 bare `{name}` is the fallback.
	 *
	 * @since 1.9.0
	 *
	 * @param string $name Filter name without the prefix, e.g. `category`.
	 * @return string|array<string, mixed> Unslashed, unsanitised value ('' when absent); an
	 *                                     array only for array params such as `bounds`.
	 */
	function wb_listora_url_arg( $name ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only filtering; every caller sanitises.
		foreach ( array( 'listora_' . $name, $name ) as $key ) {
			if ( isset( $_GET[ $key ] ) ) {
				$value = wp_unslash( $_GET[ $key ] );
				return is_array( $value ) ? $value : (string) $value;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return '';
	}
}

if ( ! function_exists( 'wb_listora_url_args' ) ) {
	/**
	 * Turn search args into page-URL query args: `listora_`-prefixed, empties dropped.
	 *
	 * For links that land on a filtered directory (saved-search links, alert
	 * emails, noscript pagination). Pass to add_query_arg().
	 *
	 * @since 1.9.0
	 *
	 * @param array<string, mixed> $args Search args keyed by engine name (`category`, `type`, ...).
	 * @return array<string, mixed>
	 */
	function wb_listora_url_args( array $args ) {
		$out = array();
		foreach ( $args as $key => $value ) {
			if ( '' === $value || null === $value || array() === $value || 0 === $value ) {
				continue;
			}
			$out[ 'listora_' . preg_replace( '/^listora_/', '', (string) $key ) ] = $value;
		}

		return $out;
	}
}

if ( ! function_exists( 'wb_listora_search_args_from_query' ) ) {
	/**
	 * Normalise a stored query-string map to search-engine arg names.
	 *
	 * Strips the `listora_` prefix; when both `listora_x` and a bare `x` are
	 * present, the prefixed one wins. Saved searches store engine names, so
	 * rows saved before 1.9.0 and after it share one shape.
	 *
	 * @since 1.9.0
	 *
	 * @param array<string, mixed> $query Query-string pairs, e.g. a copy of the page URL's params.
	 * @return array<string, mixed>
	 */
	function wb_listora_search_args_from_query( array $query ) {
		$out = array();
		foreach ( $query as $key => $value ) {
			if ( 0 !== strpos( (string) $key, 'listora_' ) ) {
				$out[ (string) $key ] = $value;
			}
		}
		foreach ( $query as $key => $value ) {
			if ( 0 === strpos( (string) $key, 'listora_' ) ) {
				$out[ substr( (string) $key, 8 ) ] = $value;
			}
		}

		return $out;
	}
}

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
		$text = static function ( $name ) {
			$value = wb_listora_url_arg( $name );
			return is_array( $value ) ? '' : sanitize_text_field( $value );
		};
		$key  = static function ( $name ) {
			$value = wb_listora_url_arg( $name );
			return is_array( $value ) ? '' : sanitize_key( $value );
		};

		$args = array(
			'type'        => $key( 'type' ),
			'keyword'     => $text( 'keyword' ),
			// Category and location accept a slug, a numeric term ID, or (for
			// location) free-form geo text. Pass the raw string through - the
			// engine resolves it and falls back to geo-text matching.
			'category'    => $text( 'category' ),
			'location'    => $text( 'location' ),
			'features'    => $text( 'features' ),
			// Tag chips on a listing detail page link to `?listora_tags=<slug>`.
			// This helper feeds the SERVER render of the grid and map, so
			// without it the first paint showed the whole directory and only
			// the JS hydration narrowed it — a visible flash, and nothing at
			// all without JS (BC 10199195886).
			'tags'        => $text( 'tags' ),
			'min_rating'  => (int) $text( 'min_rating' ),
			'date_filter' => $key( 'date_filter' ),
			'date_from'   => $text( 'date_from' ),
			'date_to'     => $text( 'date_to' ),
		);

		$bounds = wb_listora_search_bounds_from_url();
		if ( $bounds ) {
			$args['bounds'] = $bounds;
		}

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
		$raw = wb_listora_url_arg( 'bounds' ); // Every value is cast to float below.
		if ( ! is_array( $raw ) ) {
			return array();
		}

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

		$sort = wb_listora_url_arg( 'sort' );
		$sort = is_array( $sort ) ? '' : sanitize_key( $sort );

		return in_array( $sort, $allowed, true ) ? $sort : $default;
	}
}
