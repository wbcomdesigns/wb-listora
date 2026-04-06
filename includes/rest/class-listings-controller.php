<?php
/**
 * REST Listings Controller — extends WP_REST_Posts_Controller.
 *
 * @package WBListora\REST
 */

namespace WBListora\REST;

defined( 'ABSPATH' ) || exit;

use WP_REST_Posts_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Extends the WP posts controller for listora_listing with custom meta and relations.
 *
 * Standard CRUD is inherited. We add:
 * - Custom meta in responses
 * - Rating data from search_index
 * - Related listings endpoint
 * - Type-specific field validation on create/update
 */
class Listings_Controller extends WP_REST_Posts_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'listora_listing' );
		$this->namespace = WB_LISTORA_REST_NAMESPACE;
		$this->rest_base = 'listings';
	}

	/**
	 * Register routes — parent handles CRUD, we add custom endpoints.
	 */
	public function register_routes() {
		parent::register_routes();

		// GET /listings/{id}/detail — Enriched single listing for mobile/app.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/detail',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_listing' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		// DELETE /listings/{id} — Owner can soft-delete their own listing.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_listing' ),
					'permission_callback' => array( $this, 'delete_listing_permissions' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		// GET /listings/{id}/related
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/related',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_related' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'id'    => array(
							'type'     => 'integer',
							'required' => true,
						),
						'limit' => array(
							'type'    => 'integer',
							'default' => 4,
							'minimum' => 1,
							'maximum' => 12,
						),
					),
				),
			)
		);
	}

	/**
	 * Add custom fields to the response.
	 *
	 * @param \WP_Post        $post    Post object.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $post, $request ) {
		$response = parent::prepare_item_for_response( $post, $request );
		$data     = $response->get_data();

		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get_for_post( $post->ID );

		// Add listing type info.
		$data['listing_type']      = $type ? $type->get_slug() : '';
		$data['listing_type_name'] = $type ? $type->get_name() : '';

		// Add all listora meta in a clean format.
		$data['listora_meta'] = \WBListora\Core\Meta_Handler::get_all_values( $post->ID );

		// Add rating from search index.
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$idx    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT avg_rating, review_count FROM {$prefix}search_index WHERE listing_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$post->ID
			),
			ARRAY_A
		);

		$data['rating'] = array(
			'average' => $idx ? (float) $idx['avg_rating'] : 0,
			'count'   => $idx ? (int) $idx['review_count'] : 0,
		);

		// Add flags.
		$data['is_featured'] = (bool) get_post_meta( $post->ID, '_listora_is_featured', true );
		$data['is_verified'] = (bool) get_post_meta( $post->ID, '_listora_is_verified', true );
		$data['is_claimed']  = (bool) get_post_meta( $post->ID, '_listora_is_claimed', true );

		// Add taxonomy terms.
		$data['listing_categories'] = $this->get_terms_for_response( $post->ID, 'listora_listing_cat' );
		$data['listing_locations']  = $this->get_terms_for_response( $post->ID, 'listora_listing_location' );
		$data['listing_features']   = $this->get_terms_for_response( $post->ID, 'listora_listing_feature' );
		$data['listing_tags']       = $this->get_terms_for_response( $post->ID, 'listora_listing_tag' );

		// Is favorited (for authenticated users).
		$data['is_favorited'] = false;
		if ( is_user_logged_in() ) {
			$user_id              = get_current_user_id();
			$fav                  = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$prefix}favorites WHERE user_id = %d AND listing_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$post->ID
				)
			);
			$data['is_favorited'] = (bool) $fav;
		}

		$response->set_data( $data );

		return $response;
	}

	/**
	 * Get enriched single listing — everything an app screen needs in one call.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_listing( $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new \WP_Error(
				'listora_not_found',
				__( 'Listing not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		// Only show published listings to non-authors.
		if ( 'publish' !== $post->post_status ) {
			$current_user = get_current_user_id();
			if ( (int) $post->post_author !== $current_user && ! current_user_can( 'edit_others_posts' ) ) {
				return new \WP_Error(
					'listora_not_found',
					__( 'Listing not found.', 'wb-listora' ),
					array( 'status' => 404 )
				);
			}
		}

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get_for_post( $post_id );

		// --- Post data ---
		$featured_image = array();
		$thumb_id       = get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) {
			$featured_image = array(
				'url'    => wp_get_attachment_url( $thumb_id ),
				'medium' => wp_get_attachment_image_url( $thumb_id, 'medium' ),
				'large'  => wp_get_attachment_image_url( $thumb_id, 'large' ),
				'full'   => wp_get_attachment_image_url( $thumb_id, 'full' ),
			);
		}

		$data = array(
			'id'             => $post->ID,
			'title'          => $post->post_title,
			'content'        => apply_filters( 'the_content', $post->post_content ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
			'excerpt'        => get_the_excerpt( $post ),
			'status'         => $post->post_status,
			'author_id'      => (int) $post->post_author,
			'date'           => $post->post_date,
			'modified'       => $post->post_modified,
			'featured_image' => $featured_image,
			'url'            => get_permalink( $post_id ),
		);

		// --- Listing type ---
		$data['listing_type'] = array(
			'slug'  => $type ? $type->get_slug() : '',
			'label' => $type ? $type->get_name() : '',
			'icon'  => $type ? $type->get_icon() : '',
		);

		// --- Taxonomies ---
		$data['categories'] = $this->get_terms_for_response( $post_id, 'listora_listing_cat' );
		$data['locations']  = $this->get_hierarchical_terms( $post_id, 'listora_listing_location' );
		$data['features']   = $this->get_feature_terms( $post_id );
		$data['tags']       = $this->get_terms_for_response( $post_id, 'listora_listing_tag' );

		// --- All meta ---
		$data['meta'] = \WBListora\Core\Meta_Handler::get_all_values( $post_id );

		// --- Geo data from listora_geo table ---
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$geo_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT lat, lng, address, city, state, country, postal_code FROM {$prefix}geo WHERE listing_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$post_id
			),
			ARRAY_A
		);

		$data['geo'] = $geo_row
			? array(
				'lat'         => (float) $geo_row['lat'],
				'lng'         => (float) $geo_row['lng'],
				'address'     => $geo_row['address'],
				'city'        => $geo_row['city'],
				'state'       => $geo_row['state'],
				'country'     => $geo_row['country'],
				'postal_code' => $geo_row['postal_code'],
			)
			: null;

		// --- Reviews summary with star distribution ---
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$review_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					AVG(overall_rating) as avg_rating,
					COUNT(*) as review_count,
					SUM(CASE WHEN overall_rating = 5 THEN 1 ELSE 0 END) as star_5,
					SUM(CASE WHEN overall_rating = 4 THEN 1 ELSE 0 END) as star_4,
					SUM(CASE WHEN overall_rating = 3 THEN 1 ELSE 0 END) as star_3,
					SUM(CASE WHEN overall_rating = 2 THEN 1 ELSE 0 END) as star_2,
					SUM(CASE WHEN overall_rating = 1 THEN 1 ELSE 0 END) as star_1
				FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$post_id
			),
			ARRAY_A
		);

		$data['reviews_summary'] = array(
			'avg_rating'        => $review_stats ? round( (float) $review_stats['avg_rating'], 1 ) : 0,
			'review_count'      => $review_stats ? (int) $review_stats['review_count'] : 0,
			'star_distribution' => array(
				5 => (int) ( $review_stats['star_5'] ?? 0 ),
				4 => (int) ( $review_stats['star_4'] ?? 0 ),
				3 => (int) ( $review_stats['star_3'] ?? 0 ),
				2 => (int) ( $review_stats['star_2'] ?? 0 ),
				1 => (int) ( $review_stats['star_1'] ?? 0 ),
			),
		);

		// --- Favorite count + is_favorited ---
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$data['favorite_count'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}favorites WHERE listing_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$post_id
			)
		);

		$data['is_favorited'] = false;
		if ( is_user_logged_in() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$fav_exists           = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$prefix}favorites WHERE user_id = %d AND listing_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					get_current_user_id(),
					$post_id
				)
			);
			$data['is_favorited'] = (bool) $fav_exists;
		}

		// --- Claim status ---
		$data['is_claimed'] = (bool) get_post_meta( $post_id, '_listora_is_claimed', true );
		$data['claimed_by'] = null;

		if ( $data['is_claimed'] && is_user_logged_in() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$claim = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT user_id, status FROM {$prefix}claims WHERE listing_id = %d AND status = 'approved' ORDER BY updated_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$post_id
				)
			);

			if ( $claim && (int) $claim->user_id === get_current_user_id() ) {
				$data['claimed_by'] = (int) $claim->user_id;
			}
		}

		// --- Services (check if listora_services table exists) ---
		$data['services'] = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$services_table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'services' )
		);

		if ( null !== $services_table_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$data['services'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, name, description, price, duration FROM {$prefix}services WHERE listing_id = %d ORDER BY sort_order ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$post_id
				),
				ARRAY_A
			);

			if ( null === $data['services'] ) {
				$data['services'] = array();
			}
		}

		// --- Gallery ---
		$gallery_ids = \WBListora\Core\Meta_Handler::get_all_values( $post_id )['gallery'] ?? array();
		$gallery     = array();

		if ( is_array( $gallery_ids ) && ! empty( $gallery_ids ) ) {
			foreach ( $gallery_ids as $attachment_id ) {
				$attachment_id = (int) $attachment_id;
				if ( ! $attachment_id ) {
					continue;
				}
				$gallery[] = array(
					'id'        => $attachment_id,
					'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
					'medium'    => wp_get_attachment_image_url( $attachment_id, 'medium' ),
					'large'     => wp_get_attachment_image_url( $attachment_id, 'large' ),
					'full'      => wp_get_attachment_image_url( $attachment_id, 'full' ),
					'alt'       => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				);
			}
		}
		$data['gallery'] = $gallery;

		// --- Business hours ---
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$hours_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT day_of_week, open_time, close_time, is_closed, is_24h, timezone FROM {$prefix}hours WHERE listing_id = %d ORDER BY day_of_week ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$post_id
			),
			ARRAY_A
		);

		$day_names = array(
			0 => __( 'Sunday', 'wb-listora' ),
			1 => __( 'Monday', 'wb-listora' ),
			2 => __( 'Tuesday', 'wb-listora' ),
			3 => __( 'Wednesday', 'wb-listora' ),
			4 => __( 'Thursday', 'wb-listora' ),
			5 => __( 'Friday', 'wb-listora' ),
			6 => __( 'Saturday', 'wb-listora' ),
		);

		$data['business_hours'] = array();
		if ( ! empty( $hours_rows ) ) {
			foreach ( $hours_rows as $h ) {
				$data['business_hours'][] = array(
					'day'        => (int) $h['day_of_week'],
					'day_name'   => $day_names[ (int) $h['day_of_week'] ] ?? '',
					'open_time'  => $h['open_time'],
					'close_time' => $h['close_time'],
					'is_closed'  => (bool) $h['is_closed'],
					'is_24h'     => (bool) $h['is_24h'],
					'timezone'   => $h['timezone'],
				);
			}
		}

		// --- Related listings (inline, up to 4) ---
		$related_request = new WP_REST_Request( 'GET' );
		$related_request->set_param( 'id', $post_id );
		$related_request->set_param( 'limit', 4 );
		$related_response = $this->get_related( $related_request );
		$data['related']  = $related_response->get_data();

		// --- Author info ---
		$author         = get_user_by( 'ID', $post->post_author );
		$data['author'] = array(
			'id'           => (int) $post->post_author,
			'display_name' => $author ? $author->display_name : __( 'Unknown', 'wb-listora' ),
			'avatar_url'   => get_avatar_url( $post->post_author, array( 'size' => 96 ) ),
		);

		// --- Flags ---
		$data['is_featured'] = (bool) get_post_meta( $post_id, '_listora_is_featured', true );
		$data['is_verified'] = (bool) get_post_meta( $post_id, '_listora_is_verified', true );

		// --- Schema ---
		$schema         = \WBListora\Schema\Schema_Generator::for_listing( $post_id );
		$data['schema'] = $schema ? $schema->get_data() : null;

		/**
		 * Filters the single listing detail REST response data.
		 *
		 * @param array           $data    Listing detail data.
		 * @param \WP_Post        $post    Post object.
		 * @param WP_REST_Request $request REST request.
		 */
		$data = apply_filters( 'wb_listora_rest_prepare_listing', $data, $post, $request );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Permission check for deleting a listing (owner only).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function delete_listing_permissions( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'listora_unauthorized',
				__( 'You must be logged in to delete a listing.', 'wb-listora' ),
				array( 'status' => 401 )
			);
		}

		$post = get_post( (int) $request->get_param( 'id' ) );

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new \WP_Error(
				'listora_not_found',
				__( 'Listing not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( 'delete_others_posts' ) ) {
			return new \WP_Error(
				'listora_forbidden',
				__( 'You do not have permission to delete this listing.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Delete a listing — move to trash (soft delete).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function delete_listing( $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new \WP_Error(
				'listora_not_found',
				__( 'Listing not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		/**
		 * Filters whether to allow deleting a listing. Return WP_Error to abort.
		 *
		 * @param bool|\WP_Error  $check   True to proceed, WP_Error to abort.
		 * @param int             $post_id Listing post ID.
		 * @param WP_REST_Request $request REST request.
		 */
		$check = apply_filters( 'wb_listora_before_delete_listing', true, $post_id, $request );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$result = wp_trash_post( $post_id );

		if ( ! $result ) {
			return new \WP_Error(
				'listora_delete_failed',
				__( 'Unable to delete listing. Please try again.', 'wb-listora' ),
				array( 'status' => 500 )
			);
		}

		// Invalidate dashboard stats cache for the listing author.
		wp_cache_delete( 'listora_dashboard_stats_' . $post->post_author, 'listora' );

		/**
		 * Fires after a listing is trashed via the REST API.
		 *
		 * @param int $post_id Listing post ID.
		 * @param int $user_id User who deleted the listing.
		 */
		do_action( 'wb_listora_listing_trashed', $post_id, get_current_user_id() );

		/**
		 * Fires after a listing is deleted (trashed) via the REST API.
		 *
		 * @param int             $post_id Listing post ID.
		 * @param WP_REST_Request $request REST request.
		 */
		do_action( 'wb_listora_after_delete_listing', $post_id, $request );

		return new WP_REST_Response(
			array(
				'deleted' => true,
				'message' => __( 'Listing deleted successfully.', 'wb-listora' ),
			),
			200
		);
	}

	/**
	 * Get related listings for a listing.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_related( $request ) {
		$post_id = $request->get_param( 'id' );
		$limit   = $request->get_param( 'limit' );
		$post    = get_post( $post_id );

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new \WP_Error( 'listora_not_found', __( 'Listing not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get_for_post( $post_id );

		// Find related: same type, same category, nearby location.
		$args = array(
			'post_type'      => 'listora_listing',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'post__not_in'   => array( $post_id ),
		);

		// Same listing type.
		if ( $type ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'listora_listing_type',
				'field'    => 'slug',
				'terms'    => $type->get_slug(),
			);
		}

		// Same category (if available).
		$cats = wp_get_object_terms( $post_id, 'listora_listing_cat', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'listora_listing_cat',
				'field'    => 'term_id',
				'terms'    => $cats,
			);
			if ( count( $args['tax_query'] ) > 1 ) {
				$args['tax_query']['relation'] = 'AND';
			}
		}

		$query    = new \WP_Query( $args );
		$listings = array();

		foreach ( $query->posts as $related_post ) {
			$resp       = $this->prepare_item_for_response( $related_post, $request );
			$listings[] = $resp->get_data();
		}

		return new WP_REST_Response( $listings, 200 );
	}

	/**
	 * Get taxonomy terms formatted for response.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array
	 */
	private function get_terms_for_response( $post_id, $taxonomy ) {
		$terms = wp_get_object_terms( $post_id, $taxonomy );

		if ( is_wp_error( $terms ) ) {
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
	 * Get hierarchical taxonomy terms with parent info for response.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array
	 */
	private function get_hierarchical_terms( $post_id, $taxonomy ) {
		$terms = wp_get_object_terms( $post_id, $taxonomy );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_map(
			function ( $term ) {
				$item = array(
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);

				if ( $term->parent ) {
					$parent_term = get_term( $term->parent );
					if ( $parent_term && ! is_wp_error( $parent_term ) ) {
						$item['parent'] = array(
							'id'   => $parent_term->term_id,
							'name' => $parent_term->name,
							'slug' => $parent_term->slug,
						);
					}
				}

				return $item;
			},
			$terms
		);
	}

	/**
	 * Get feature terms with icon meta for response.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function get_feature_terms( $post_id ) {
		$terms = wp_get_object_terms( $post_id, 'listora_listing_feature' );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_map(
			function ( $term ) {
				return array(
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
					'icon' => get_term_meta( $term->term_id, '_listora_icon', true ) ?: '',
				);
			},
			$terms
		);
	}
}
