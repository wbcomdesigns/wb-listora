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
				"SELECT avg_rating, review_count FROM {$prefix}search_index WHERE listing_id = %d",
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
					"SELECT COUNT(*) FROM {$prefix}favorites WHERE user_id = %d AND listing_id = %d",
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
}
