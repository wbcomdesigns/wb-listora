<?php
/**
 * Search filters carried in the URL - one parser for every surface.
 *
 * The Search block navigates to the current URL with listora_* query args so
 * each block can render the filtered view server-side. Any surface that
 * renders a filtered set of listings MUST build its args here and resolve them
 * through Search_Engine; a second parser or a second query against
 * search_index lets the grid and map disagree on the same page.
 *
 * @package WB_Listora
 * @since   1.5.0
 */

defined( 'ABSPATH' ) || exit;

/*
 * Every directory filter travels in the page URL as `listora_{name}`. Bare
 * names (`category`, `type`, `page`, `location`) collide with WordPress query
 * vars and other plugins (`?page=2` is redirected by core), so they are still
 * READ for old links but never written. REST routes keep their own names.
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
	 * Read-only public filtering, so no nonce; every value is sanitized here
	 * and the engine re-validates.
	 *
	 * @since 1.5.0
	 *
	 * @param array<string, mixed> $overrides Values that win over the URL, e.g.
	 *                                        a `type` pinned by a block
	 *                                        attribute, or `per_page`.
	 * @return array<string, mixed> Args accepted by
	 *                              \WBListora\Search\Search_Engine::search().
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
			// Tag chips on a listing detail page link to `?listora_tags=<slug>`;
			// read here so the server render is already filtered, without JS.
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

		return array_merge( $args, wb_listora_search_geo_from_url(), $overrides );
	}
}

if ( ! function_exists( 'wb_listora_search_geo_from_url' ) ) {
	/**
	 * Parse a "Near me" centre and radius from the URL.
	 *
	 * Empty unless both coordinates are present and in range. The radius falls
	 * back to the owner's Near me radius and is clamped to the settings bounds.
	 *
	 * @since 1.9.1
	 *
	 * @return array{lat?:float,lng?:float,radius?:int}
	 */
	function wb_listora_search_geo_from_url() {
		$lat = wb_listora_url_arg( 'lat' );
		$lng = wb_listora_url_arg( 'lng' );
		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) || abs( (float) $lat ) > 90 || abs( (float) $lng ) > 180 ) {
			return array();
		}

		$radius = wb_listora_url_arg( 'radius' );
		$radius = is_numeric( $radius ) ? (int) $radius : (int) wb_listora_get_setting( 'near_me_radius' );

		return array(
			'lat'    => (float) $lat,
			'lng'    => (float) $lng,
			'radius' => max( 1, min( 500, $radius ) ),
		);
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
