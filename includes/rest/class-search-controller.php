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
			'type'        => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
			'category'    => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			),
			'location'    => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			),
			'features'    => array(
				'type'    => 'array',
				'items'   => array( 'type' => 'integer' ),
				'default' => array(),
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
			'open_now'    => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'date_filter' => array(
				'type'              => 'string',
				'enum'              => array( '', 'today', 'weekend', 'happening_now' ),
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
	 * Handle search request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function search( $request ) {
		$args = array(
			'keyword'     => $request->get_param( 'keyword' ),
			'type'        => $request->get_param( 'type' ),
			'category'    => $request->get_param( 'category' ),
			'location'    => $request->get_param( 'location' ),
			'features'    => $request->get_param( 'features' ),
			'lat'         => $request->get_param( 'lat' ),
			'lng'         => $request->get_param( 'lng' ),
			'radius'      => $request->get_param( 'radius' ),
			'radius_unit' => $request->get_param( 'radius_unit' ),
			'min_rating'  => $request->get_param( 'min_rating' ),
			'open_now'    => $request->get_param( 'open_now' ),
			'date_filter' => $request->get_param( 'date_filter' ),
			'date_from'   => $request->get_param( 'date_from' ),
			'date_to'     => $request->get_param( 'date_to' ),
			'sort'        => $request->get_param( 'sort' ),
			'page'        => $request->get_param( 'page' ),
			'per_page'    => $request->get_param( 'per_page' ),
			'facets'      => $request->get_param( 'facets' ),
		);

		// Handle bounds.
		$bounds = $request->get_param( 'bounds' );
		if ( $bounds && isset( $bounds['ne_lat'] ) ) {
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

		// Execute search.
		$engine = new \WBListora\Search\Search_Engine();
		$result = $engine->search( $args );

		// Hydrate listings.
		$listings = $this->hydrate_listings( $result['listing_ids'], $result['distances'] );

		$response_data = array(
			'listings' => $listings,
			'total'    => $result['total'],
			'pages'    => $result['pages'],
		);

		if ( ! empty( $args['facets'] ) ) {
			$response_data['facets'] = $result['facets'];
		}

		/**
		 * Filter search results before response.
		 *
		 * @param array           $response_data Response data.
		 * @param array           $args          Search arguments.
		 * @param WP_REST_Request $request       REST request.
		 */
		$response_data = apply_filters( 'wb_listora_search_results', $response_data, $args, $request );

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

		// Prime taxonomy term cache for all posts.
		update_object_term_cache( $ids, 'listora_listing' );

		$listings = array();
		$registry = \WBListora\Core\Listing_Type_Registry::instance();

		foreach ( $posts as $post ) {
			$type     = $registry->get_for_post( $post->ID );
			$all_meta = \WBListora\Core\Meta_Handler::get_all_values( $post->ID );

			$listing = array(
				'id'                => $post->ID,
				'title'             => $post->post_title,
				'slug'              => $post->post_name,
				'excerpt'           => get_the_excerpt( $post ),
				'content'           => apply_filters( 'the_content', $post->post_content ),
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
				'is_featured'       => (bool) get_post_meta( $post->ID, '_listora_is_featured', true ),
				'is_verified'       => (bool) get_post_meta( $post->ID, '_listora_is_verified', true ),
				'is_claimed'        => (bool) get_post_meta( $post->ID, '_listora_is_claimed', true ),
			);

			// Add rating from search index if not in meta (uses batch-loaded map).
			if ( 0 === $listing['rating']['average'] && isset( $ratings_map[ $post->ID ] ) ) {
				$listing['rating']['average'] = (float) $ratings_map[ $post->ID ]['avg_rating'];
				$listing['rating']['count']   = (int) $ratings_map[ $post->ID ]['review_count'];
			}

			// Add distance if available.
			if ( isset( $distances[ $post->ID ] ) ) {
				$listing['distance'] = $distances[ $post->ID ];
			}

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
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array
	 */
	private function get_term_data( $post_id, $taxonomy ) {
		$terms = wp_get_object_terms( $post_id, $taxonomy );

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
