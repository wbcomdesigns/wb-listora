<?php
/**
 * REST Search Controller — high-performance search endpoint.
 *
 * @package WBListora\REST
 */

namespace WBListora\REST;

defined( 'ABSPATH' ) || exit;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Handles GET /listora/v1/search and GET /listora/v1/search/suggest
 */
class Search_Controller extends WP_REST_Controller {

	/**
	 * @var string
	 */
	protected $namespace = WB_LISTORA_REST_NAMESPACE;

	/**
	 * @var string
	 */
	protected $rest_base = 'search';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_search_args(),
				),
			)
		);

		// GET /search/map-clusters — aggregate counts for a map viewport.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/map-clusters',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'map_clusters' ),
					'permission_callback' => '__return_true',
					'args'                => array_merge(
						$this->get_search_args(),
						array(
							'zoom' => array(
								'type'        => 'integer',
								'default'     => 10,
								'minimum'     => 0,
								'maximum'     => 22,
								'description' => __( 'Map zoom level. Selects the clustering grid size.', 'wb-listora' ),
							),
						)
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/suggest',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'suggest' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'q'     => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => __( 'Search query for autocomplete.', 'wb-listora' ),
						),
						'type'  => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'default'           => '',
						),
						'limit' => array(
							'type'    => 'integer',
							'default' => 8,
							'minimum' => 1,
							'maximum' => 20,
						),
					),
				),
			)
		);
	}

	/**
	 * Define search endpoint arguments with validation.
	 *
	 * @return array
	 */
	private function get_search_args() {
		return array(
			'keyword'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
			// Alias — /search/suggest uses `q=`, so accept the same name here
			// to avoid consumer confusion. Coalesced in search() below.
			'q'           => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
			'type'        => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
			// `category` and `location` accept either:
			//  - a numeric term ID (selected from a list / autocomplete), or
			//  - a string slug or human name (typed by the user in the
			//    search bar — e.g. "brooklyn", "italian").
			// The previous integer-only contract silently dropped any
			// non-numeric value via `absint('Brooklyn') === 0`, which
			// hit the `! empty()` gate in Search_Engine and skipped
			// the filter entirely — every search returned every listing.
			// Sanitisation is intentionally `sanitize_text_field` so a
			// numeric ID arrives as the string "42" and is resolved
			// downstream; the engine handles both shapes.
			'category'    => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
			'location'    => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
			// Features accepts the same dual contract as `category` /
			// `location`: numeric IDs (from a checkbox-bound term ID)
			// OR slugs (from the search-bar checkbox UI which posts
			// `features=credit-cards,delivery`). The previous
			// integer-only contract silently dropped slug values via
			// `(int) "credit-cards" === 0`, which then short-circuited
			// the search engine's per-feature filter and returned nothing.
			'features'    => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
			// Tags accept the same comma/space-separated slug-or-ID contract
			// as features. Declared here because the engine now consumes it —
			// `?tags=sushi` used to be accepted and silently ignored, so the
			// endpoint answered 200 with the entire unfiltered directory
			// (BC 10199195886).
			'tags'        => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
			'lat'         => array(
				'type'    => 'number',
				'default' => null,
			),
			'lng'         => array(
				'type'    => 'number',
				'default' => null,
			),
			'radius'      => array(
				'type'    => 'number',
				'default' => 0,
				'minimum' => 0,
			),
			'radius_unit' => array(
				'type'    => 'string',
				'enum'    => array( 'km', 'mi' ),
				'default' => wb_listora_get_setting( 'distance_unit', 'km' ),
			),
			'bounds'      => array(
				'type'       => 'object',
				'properties' => array(
					'ne_lat' => array( 'type' => 'number' ),
					'ne_lng' => array( 'type' => 'number' ),
					'sw_lat' => array( 'type' => 'number' ),
					'sw_lng' => array( 'type' => 'number' ),
				),
				'default'    => null,
			),
			'min_rating'  => array(
				'type'    => 'number',
				'default' => 0,
				'minimum' => 0,
				'maximum' => 5,
			),
			/*
			 * These three were read by the engine and never declared here, so
			 * they were ACCEPTED AND IGNORED: asking for featured listings
			 * returned the whole directory, and the caller got a 200 with no
			 * signal that the filter had done nothing (LST-F-21).
			 *
			 * `author` is safe to expose because the candidate query hardcodes
			 * `status = 'publish'` — it can only ever return published
			 * listings, never drafts, pending or rejected. "All listings from
			 * this business" is a normal directory expectation; the filter
			 * below exists for owners who read it as member enumeration
			 * instead.
			 */
			'featured_only' => array(
				'type'              => 'boolean',
				'default'           => false,
				'validate_callback' => 'rest_validate_request_arg',
				'description'       => __( 'Return only featured listings.', 'wb-listora' ),
			),
			'verified_only' => array(
				'type'              => 'boolean',
				'default'           => false,
				'validate_callback' => 'rest_validate_request_arg',
				'description'       => __( 'Return only verified listings.', 'wb-listora' ),
			),
			'author'      => array(
				'type'              => 'integer',
				'default'           => 0,
				'minimum'           => 0,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'absint',
				'description'       => __( 'Return only listings owned by this member. Published listings only.', 'wb-listora' ),
			),
			'open_now'    => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'date_filter' => array(
				'type'              => 'string',
				'enum'              => array( '', 'today', 'weekend', 'happening_now' ),
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
				'description'       => __( 'Preset date filter for events.', 'wb-listora' ),
			),
			'date_from'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
				'description'       => __( 'Start date for custom date range (Y-m-d).', 'wb-listora' ),
				'validate_callback' => array( $this, 'validate_date_param' ),
			),
			'date_to'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
				'description'       => __( 'End date for custom date range (Y-m-d).', 'wb-listora' ),
				'validate_callback' => array( $this, 'validate_date_param' ),
			),
			'sort'        => array(
				'type'    => 'string',
				'enum'    => array( 'featured', 'newest', 'rating', 'distance', 'price_asc', 'price_desc', 'most_reviewed', 'alphabetical', 'relevance' ),
				'default' => 'featured',
			),
			'page'        => array(
				'type'    => 'integer',
				'default' => 1,
				'minimum' => 1,
			),
			'per_page'    => array(
				'type'    => 'integer',
				'default' => (int) wb_listora_get_setting( 'per_page', 20 ),
				'minimum' => 1,
				'maximum' => 100,
			),
			'facets'      => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}

	/**
	 * Aggregate map counts for a viewport.
	 *
	 * A map cannot page, and `/search` caps `per_page` at 100 — so a directory
	 * of any size could only ever place an arbitrary hundred pins, and the
	 * client was left clustering whatever that page happened to contain, which
	 * describes the page rather than the data.
	 *
	 * Honours every filter `/search` does, through the same parser, so the map
	 * and the list beneath it always describe the same result set.
	 *
	 * @since 1.5.0
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function map_clusters( $request ) {
		// Same limiter as /search — this runs the same WHERE plus a GROUP BY,
		// and a map that re-requests on every pan is the easiest thing to loop.
		$gate = \WBListora\Rate_Limiter::check( 'search' );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$args = $this->parse_search_args( $request );
		if ( is_wp_error( $args ) ) {
			return $args;
		}

		$args['zoom'] = (int) $request->get_param( 'zoom' );

		$engine = new \WBListora\Search\Search_Engine();
		$result = $engine->map_clusters( $args );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Resolve the `author` filter, honouring the site's own policy.
	 *
	 * Exposing `author` turns "all listings from this business" into a public
	 * query, which is what a directory or marketplace visitor expects — eBay
	 * seller pages, Airbnb host profiles. It can only ever return PUBLISHED
	 * listings, because the candidate query hardcodes `status = 'publish'`, so
	 * it cannot leak drafts or pending submissions.
	 *
	 * The residual objection is enumeration: iterate ids and you map members to
	 * listings. That map is already derivable by crawling published listings,
	 * each of which shows its owner, so the incremental exposure is small. It is
	 * still a judgement a site owner may make differently — and until 1.5.0 the
	 * parameter did nothing at all, so honouring it IS a behaviour change.
	 * Hence the escape hatch.
	 *
	 * @since 1.5.0
	 *
	 * @param int $author_id Requested author id.
	 * @return int The id to filter on, or 0 to ignore the filter.
	 */
	private static function resolve_author_filter( $author_id ) {
		$author_id = max( 0, (int) $author_id );

		if ( ! $author_id ) {
			return 0;
		}

		/**
		 * Whether the public `author` search filter is honoured.
		 *
		 * Return false to have the parameter ignored, restoring pre-1.5.0
		 * behaviour for sites that read it as member enumeration.
		 *
		 * @since 1.5.0
		 *
		 * @param bool $enabled   Whether to honour the filter.
		 * @param int  $author_id The requested author.
		 */
		return apply_filters( 'wb_listora_search_author_filter_enabled', true, $author_id ) ? $author_id : 0;
	}

	/**
	 * Build engine args from a request, with bounds validated.
	 *
	 * Shared by /search and /search/map-clusters so the two cannot diverge on
	 * which filters they honour or how they validate a bounding box — a map
	 * that counted a different result set from the list beneath it would be
	 * worse than no map.
	 *
	 * @since 1.5.0
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>|WP_Error Args for Search_Engine, or a 400.
	 */
	private function parse_search_args( $request ) {
		// Accept `keyword` or `q` — first non-empty wins. Lets callers of
		// /search/suggest reuse their param name on /search.
		$keyword_param = $request->get_param( 'keyword' );
		$q_param       = $request->get_param( 'q' );
		$keyword       = '' !== (string) $keyword_param ? $keyword_param : $q_param;

		$args = array(
			'keyword'     => $keyword,
			'type'        => $request->get_param( 'type' ),
			'category'    => $request->get_param( 'category' ),
			'location'    => $request->get_param( 'location' ),
			'features'    => $request->get_param( 'features' ),
			'tags'        => $request->get_param( 'tags' ),
			'lat'         => $request->get_param( 'lat' ),
			'lng'         => $request->get_param( 'lng' ),
			'radius'      => $request->get_param( 'radius' ),
			'radius_unit' => $request->get_param( 'radius_unit' ),
			'min_rating'  => $request->get_param( 'min_rating' ),
			'featured_only' => (bool) $request->get_param( 'featured_only' ),
			'verified_only' => (bool) $request->get_param( 'verified_only' ),
			'author'      => self::resolve_author_filter( (int) $request->get_param( 'author' ) ),
			'open_now'    => $request->get_param( 'open_now' ),
			'date_filter' => $request->get_param( 'date_filter' ),
			'date_from'   => $request->get_param( 'date_from' ),
			'date_to'     => $request->get_param( 'date_to' ),
			'sort'        => $request->get_param( 'sort' ),
			'page'        => $request->get_param( 'page' ),
			'per_page'    => $request->get_param( 'per_page' ),
			'facets'      => $request->get_param( 'facets' ),
		);

		/*
		 * Handle bounds.
		 *
		 * A bounding box is all-or-nothing: it needs all four corners. The guard
		 * used to test `isset( $bounds['ne_lat'] )` alone, so a partial box got
		 * through and the three missing keys were then read unguarded — PHP
		 * undefined-key warnings printed straight into a 200 response (and into
		 * the JSON itself wherever WP_DEBUG_DISPLAY is on).
		 *
		 * Reject a partial box with a 400 instead of half-applying it. Silently
		 * ignoring it would be worse than the warning: the caller would get a
		 * full unfiltered result set that looks like a working search.
		 */
		$bounds = $request->get_param( 'bounds' );
		if ( ! empty( $bounds ) && is_array( $bounds ) ) {
			$required = array( 'ne_lat', 'ne_lng', 'sw_lat', 'sw_lng' );
			$missing  = array_diff(
				$required,
				array_keys(
					array_filter(
						$bounds,
						static function ( $v ) {
							return '' !== $v && null !== $v;
						}
					)
				)
			);

			if ( ! empty( $missing ) ) {
				return new WP_Error(
					'rest_invalid_param',
					sprintf(
						/* translators: %s: comma-separated list of missing bounds keys. */
						__( 'The bounds parameter needs all four corners. Missing: %s', 'wb-listora' ),
						implode( ', ', $missing )
					),
					array( 'status' => 400 )
				);
			}

			$args['bounds'] = $bounds;
		}

		// Parse custom field filters from remaining query params.
		$args['field_filters'] = $this->extract_field_filters( $request, $args['type'] );

		/**
		 * Filter search args before execution.
		 *
		 * @param array           $args    Search arguments.
		 * @param WP_REST_Request $request REST request.
		 */
		$args = apply_filters( 'wb_listora_search_args', $args, $request );

		return $args;
	}

	/**
	 * Handle search request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function search( $request ) {
		// F-03: rate-limit public search. Each call hits FULLTEXT + meta
		// filters + geo joins; a non-debounced scraper loop can DoS the DB.
		// Legitimate use (initial render + a few filter/sort changes) is
		// well inside the 60/min IP cap.
		$gate = \WBListora\Rate_Limiter::check( 'search' );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$args = $this->parse_search_args( $request );
		if ( is_wp_error( $args ) ) {
			return $args;
		}

		// Execute search.
		$engine = new \WBListora\Search\Search_Engine();
		$result = $engine->search( $args );

		// Hydrate listings.
		$listings = $this->hydrate_listings( $result['listing_ids'], $result['distances'] );

		$offset   = ( $args['page'] - 1 ) * $args['per_page'];
		$has_more = ( $offset + count( $listings ) ) < $result['total'];

		$response_data = array(
			'listings' => $listings,
			'total'    => $result['total'],
			'pages'    => $result['pages'],
			'has_more' => $has_more,
		);

		if ( ! empty( $args['facets'] ) ) {
			// Cast to object so JSON encoding produces `{}` (no facets) or
			// `{field_key: {value: count}}` (facets present) — never `[]`.
			// Same payload key must emit the same JSON shape across calls,
			// per the no-UX-gaps directive 2026-05-18. PHP's empty array
			// JSON-encodes as `[]`, which trips type-strict headless
			// consumers expecting a record. Casting to object normalises.
			$response_data['facets'] = (object) $result['facets'];
		}

		/**
		 * Filter search results before response.
		 *
		 * @param array           $response_data Response data.
		 * @param array           $args          Search arguments.
		 * @param WP_REST_Request $request       REST request.
		 */
		$response_data = apply_filters( 'wb_listora_search_results', $response_data, $args, $request );

		/**
		 * Filters the search result response data so Pro/extensions can add fields.
		 *
		 * @param array           $response_data Full search response data including listings array.
		 * @param WP_REST_Request $request       REST request.
		 */
		$response_data = apply_filters( 'wb_listora_rest_prepare_search_result', $response_data, $request );

		$response = new WP_REST_Response( $response_data, 200 );

		// Pagination headers.
		$response->header( 'X-WP-Total', $result['total'] );
		$response->header( 'X-WP-TotalPages', $result['pages'] );

		return $response;
	}

	/**
	 * Extract custom field filters from the request.
	 *
	 * Any query param that matches a filterable field key for the listing type
	 * becomes a field filter.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @param string          $type    Listing type slug.
	 * @return array
	 */
	private function extract_field_filters( $request, $type ) {
		if ( empty( $type ) ) {
			return array();
		}

		$registry     = \WBListora\Core\Listing_Type_Registry::instance();
		$listing_type = $registry->get( $type );

		if ( ! $listing_type ) {
			return array();
		}

		$filters    = array();
		$filterable = $listing_type->get_filterable_fields();
		$all_params = $request->get_query_params();

		foreach ( $filterable as $field ) {
			$key = $field->get_key();

			if ( ! isset( $all_params[ $key ] ) || '' === $all_params[ $key ] ) {
				continue;
			}

			$value = $all_params[ $key ];

			// Handle min/max for range fields.
			if ( isset( $all_params[ $key . '_min' ] ) || isset( $all_params[ $key . '_max' ] ) ) {
				$filters[ $key ] = array(
					'min' => isset( $all_params[ $key . '_min' ] ) ? $all_params[ $key . '_min' ] : '',
					'max' => isset( $all_params[ $key . '_max' ] ) ? $all_params[ $key . '_max' ] : '',
				);
				continue;
			}

			// Handle comma-separated values (multiselect).
			if ( is_string( $value ) && false !== strpos( $value, ',' ) ) {
				$filters[ $key ] = array_map( 'trim', explode( ',', $value ) );
			} elseif ( is_array( $value ) ) {
				$filters[ $key ] = array_map( 'sanitize_text_field', $value );
			} else {
				$filters[ $key ] = sanitize_text_field( $value );
			}
		}

		return $filters;
	}

	/**
	 * Hydrate listing IDs into full response objects.
	 *
	 * @param int[] $ids       Listing IDs.
	 * @param array $distances Distance map (id => distance).
	 * @return array
	 */
	private function hydrate_listings( array $ids, array $distances = array() ) {
		if ( empty( $ids ) ) {
			return array();
		}

		// Batch fetch posts.
		$posts = get_posts(
			array(
				'post_type'      => 'listora_listing',
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'posts_per_page' => count( $ids ),
				'post_status'    => 'publish',
			)
		);

		// Batch prime meta cache.
		update_meta_cache( 'post', $ids );

		// Batch load ratings from search index (avoids per-row query).
		$ratings_map = array();
		if ( ! empty( $ids ) ) {
			global $wpdb;
			$prefix       = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$idx_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT listing_id, avg_rating, review_count FROM {$prefix}search_index WHERE listing_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					...$ids
				),
				ARRAY_A
			);
			foreach ( $idx_rows as $idx_row ) {
				$ratings_map[ (int) $idx_row['listing_id'] ] = $idx_row;
			}
		}

		// Batch load coordinates for the whole page in one query.
		//
		// Without this /search returns no lat/lng at all, so a map client has
		// nothing to plot: `distance` is only present when the caller passed
		// lat/lng, and a scalar distance cannot place a pin. The web
		// listing-map block never hit this — it emits its own markers blob from
		// render.php — so the REST map surface was silently coordinate-less.
		//
		// The geo table is already joined upstream for bounds/radius filtering,
		// so this is one extra SELECT for the page, never a per-row lookup.
		$geo_map = array();
		if ( ! empty( $ids ) ) {
			global $wpdb;
			$prefix       = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$geo_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT listing_id, lat, lng FROM {$prefix}geo WHERE listing_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					...$ids
				),
				ARRAY_A
			);
			foreach ( $geo_rows as $geo_row ) {
				$geo_map[ (int) $geo_row['listing_id'] ] = $geo_row;
			}
		}

		// Prime taxonomy term cache for all posts.
		update_object_term_cache( $ids, 'listora_listing' );

		// Prime the current user's favorited IDs for the whole page in one
		// query. /search is the app's home screen — every card renders a heart,
		// so without this the field would cost one COUNT(*) per card. No-op for
		// guests (a favourite is a per-user fact).
		\WBListora\Core\Favorites_Cache::prime( $ids );

		// Prime the normalised business-hours block for the whole page in one
		// query (see the `hours` / `timezone` fields assembled below).
		\WBListora\Core\Business_Hours::prime( $ids );

		$listings = array();
		$registry = \WBListora\Core\Listing_Type_Registry::instance();

		foreach ( $posts as $post ) {
			$type     = $registry->get_for_post( $post->ID );
			$all_meta = \WBListora\Core\Meta_Handler::get_all_values( $post->ID );

			$listing = array(
				'id'                => $post->ID,
				'title'             => $post->post_title,
				'slug'              => $post->post_name,
				// Search results only include the excerpt — apply_filters('the_content')
				// on the full body inflated every card response by 30–50%. Apps that
				// need full body fetch /listings/{id}/detail.
				'excerpt'           => get_the_excerpt( $post ),
				'link'              => get_permalink( $post->ID ),
				'status'            => $post->post_status,
				'author'            => (int) $post->post_author,
				'date'              => $post->post_date,
				'listing_type'      => $type ? $type->get_slug() : '',
				'listing_type_name' => $type ? $type->get_name() : '',
				'featured_image'    => $this->get_image_data( get_post_thumbnail_id( $post->ID ) ),
				'meta'              => $all_meta,
				'rating'            => array(
					'average' => (float) ( $all_meta['avg_rating'] ?? 0 ),
					'count'   => (int) ( $all_meta['review_count'] ?? 0 ),
				),
				'is_featured'       => \WBListora\Core\Featured::is_featured( $post->ID ),
				'is_verified'       => wb_listora_is_verified( $post->ID ),
				'is_claimed'        => (bool) get_post_meta( $post->ID, '_listora_is_claimed', true ),
				// Read from the batch primed above — one query per page, not one
				// per card. Always present; false for guests, so a client reads
				// `item.is_favorited` without a presence check. Matches the
				// contract on /listings, /listings/{id}/detail and /listings/bulk.
				'is_favorited'      => \WBListora\Core\Favorites_Cache::is_favorited( $post->ID ),
				// --- Additive hours contract (1.2.3) ---
				// `meta.business_hours` above is UNCHANGED and stays the
				// canonical key for existing consumers. It is, however, not
				// enough to compute "Open now": it carries no timezone, no
				// is_closed and no is_24h, uses HH:MM where /detail uses
				// HH:MM:SS, and is written by a different producer (post meta)
				// than /detail reads (the `hours` table). Evaluating an
				// overnight span like 06:00→01:00 needs the listing's own
				// timezone, not the device's.
				//
				// So we ADD — never rename or restructure — the same normalised
				// block /listings/{id}/detail already returns, letting a client
				// use ONE parser for both endpoints and compute "Open now"
				// honestly from a search card.
				//
				// Long-term convergence (deliberately NOT done here — this is a
				// patch release and `meta.business_hours` is a public key):
				// `meta.business_hours` should eventually be sourced from the
				// same `hours` table and emit the same normalised shape, with
				// the meta-backed variant kept as a documented alias for >= 2
				// minor versions before any removal. Tracked as P5 in
				// docs/PLUGIN-CONTRACT-GAPS.md.
				'hours'             => \WBListora\Core\Business_Hours::get( $post->ID ),
				// Effective zone (unset/'UTC' sentinel -> site timezone) so this
				// matches the /detail payload and the Open-now badge.
				'timezone'          => \WBListora\Core\Business_Hours::get_effective_timezone( $post->ID ),
			);

			// Add rating from search index if not in meta (uses batch-loaded map).
			// `average` is cast to float above, so the zero check must compare a
			// float literal — `0 === 0.0` is false under strict typing, which
			// previously suppressed the search-index fallback entirely.
			if ( 0.0 === $listing['rating']['average'] && isset( $ratings_map[ $post->ID ] ) ) {
				$listing['rating']['average'] = (float) $ratings_map[ $post->ID ]['avg_rating'];
				$listing['rating']['count']   = (int) $ratings_map[ $post->ID ]['review_count'];
			}

			// Add distance if available.
			if ( isset( $distances[ $post->ID ] ) ) {
				$listing['distance'] = $distances[ $post->ID ];
			}

			/*
			 * Coordinates, so a map client can actually plot the row.
			 *
			 * `geo` is null — not an empty object, and not 0,0 — when the
			 * listing has no geocoded row. 0,0 is a real place (Null Island, in
			 * the Gulf of Guinea), so defaulting to it would scatter every
			 * un-geocoded listing off the coast of Africa. null is the only
			 * honest "unknown", and a client skips those rows.
			 *
			 * Cast to float: MySQL returns DECIMAL as a string, and a client
			 * should not have to coerce a coordinate before using it.
			 */
			$listing['geo'] = isset( $geo_map[ $post->ID ] )
				? array(
					'lat' => (float) $geo_map[ $post->ID ]['lat'],
					'lng' => (float) $geo_map[ $post->ID ]['lng'],
				)
				: null;

			// Add taxonomy terms.
			$listing['categories'] = $this->get_term_data( $post->ID, 'listora_listing_cat' );
			$listing['locations']  = $this->get_term_data( $post->ID, 'listora_listing_location' );
			$listing['features']   = $this->get_term_data( $post->ID, 'listora_listing_feature' );
			$listing['tags']       = $this->get_term_data( $post->ID, 'listora_listing_tag' );

			/**
			 * Filter the listing data in search response.
			 *
			 * @param array    $listing Listing data.
			 * @param \WP_Post $post    Post object.
			 */
			$listings[] = apply_filters( 'wb_listora_rest_listing_response', $listing, $post );
		}

		return $listings;
	}

	/**
	 * Get image data for a response.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|null
	 */
	private function get_image_data( $attachment_id ) {
		if ( ! $attachment_id ) {
			return null;
		}

		$full   = wp_get_attachment_image_src( $attachment_id, 'full' );
		$medium = wp_get_attachment_image_src( $attachment_id, 'medium' );
		$thumb  = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );

		return array(
			'id'        => (int) $attachment_id,
			'full'      => $full ? $full[0] : '',
			'medium'    => $medium ? $medium[0] : '',
			'thumbnail' => $thumb ? $thumb[0] : '',
			'alt'       => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * Get taxonomy term data for a post.
	 *
	 * Uses get_the_terms() (cache-aware) instead of wp_get_object_terms()
	 * so when hydrate_listings() has already called update_object_term_cache()
	 * on the whole batch, each call here is a cache read rather than a
	 * fresh DB query. On a 20-item search page this cuts 80 taxonomy DB
	 * queries to zero.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array
	 */
	private function get_term_data( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		return array_map(
			function ( $term ) {
				return array(
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);
			},
			$terms
		);
	}

	/**
	 * Validate a date parameter is in Y-m-d format or empty.
	 *
	 * @param string          $value   Parameter value.
	 * @param WP_REST_Request $request REST request.
	 * @param string          $param   Parameter name.
	 * @return true|WP_Error
	 */
	public function validate_date_param( $value, $request, $param ) {
		if ( empty( $value ) ) {
			return true;
		}

		// Validate Y-m-d format.
		$date = \DateTime::createFromFormat( 'Y-m-d', $value );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
			return new WP_Error(
				'rest_invalid_date',
				/* translators: %s: parameter name */
				sprintf( __( 'The %s parameter must be a valid date in Y-m-d format.', 'wb-listora' ), $param ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Handle autocomplete suggestions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function suggest( $request ) {
		// F-03: rate-limit autocomplete. Frontend already debounces ~250ms,
		// so a real user types around 4-8 calls/sec briefly, settling near
		// 30/min sustained. The 30/min IP cap catches non-debounced scraper
		// loops while keeping legitimate typing comfortable.
		$gate = \WBListora\Rate_Limiter::check( 'search_suggest' );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$query = $request->get_param( 'q' );
		$type  = $request->get_param( 'type' );
		$limit = $request->get_param( 'limit' );

		if ( strlen( $query ) < 2 ) {
			return new WP_REST_Response( array( 'suggestions' => array() ), 200 );
		}

		$cache_key = 'listora_suggest_' . md5( $query . $type );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return new WP_REST_Response( $cached, 200 );
		}

		global $wpdb;
		$prefix      = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$suggestions = array();

		// Search listing titles.
		$where  = "s.status = 'publish' AND s.title LIKE %s";
		$params = array( '%' . $wpdb->esc_like( $query ) . '%' );

		if ( ! empty( $type ) ) {
			$where   .= ' AND s.listing_type = %s';
			$params[] = $type;
		}

		$params[] = (int) ceil( $limit / 2 ); // Half for listings.

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$listings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.listing_id, s.title, s.listing_type, s.city
			FROM {$prefix}search_index s
			WHERE {$where}
			ORDER BY s.is_featured DESC, s.avg_rating DESC
			LIMIT %d",
				...$params
			),
			ARRAY_A
		);

		foreach ( $listings as $row ) {
			$suggestions[] = array(
				'type' => 'listing',
				'id'   => (int) $row['listing_id'],
				'text' => $row['title'],
				'meta' => $row['city'] ? $row['city'] : $row['listing_type'],
				'url'  => get_permalink( (int) $row['listing_id'] ),
			);
		}

		// Search category names.
		$cat_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, t.slug FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			WHERE tt.taxonomy = 'listora_listing_cat' AND t.name LIKE %s
			ORDER BY t.name LIMIT %d",
				$wpdb->esc_like( $query ) . '%',
				3
			),
			ARRAY_A
		);

		foreach ( $cat_results as $cat ) {
			$suggestions[] = array(
				'type' => 'category',
				'id'   => (int) $cat['term_id'],
				'text' => $cat['name'],
				'meta' => __( 'Category', 'wb-listora' ),
				'url'  => get_term_link( (int) $cat['term_id'], 'listora_listing_cat' ),
			);
		}

		// Search location names.
		$loc_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, t.slug FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			WHERE tt.taxonomy = 'listora_listing_location' AND t.name LIKE %s
			ORDER BY t.name LIMIT %d",
				$wpdb->esc_like( $query ) . '%',
				3
			),
			ARRAY_A
		);

		foreach ( $loc_results as $loc ) {
			$suggestions[] = array(
				'type' => 'location',
				'id'   => (int) $loc['term_id'],
				'text' => $loc['name'],
				'meta' => __( 'Location', 'wb-listora' ),
				'url'  => get_term_link( (int) $loc['term_id'], 'listora_listing_location' ),
			);
		}

		// Limit total suggestions.
		$suggestions = array_slice( $suggestions, 0, $limit );

		$data = array( 'suggestions' => $suggestions );

		set_transient( $cache_key, $data, 15 * MINUTE_IN_SECONDS );

		return new WP_REST_Response( $data, 200 );
	}
}
