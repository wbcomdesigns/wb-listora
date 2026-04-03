<?php
/**
 * REST Favorites Controller.
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
 * Handles user favorites (bookmarks) — add, remove, list.
 */
class Favorites_Controller extends WP_REST_Controller {

	protected $namespace = WB_LISTORA_REST_NAMESPACE;
	protected $rest_base = 'favorites';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET /favorites — user's saved listings.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_favorites' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
				// POST /favorites — add a favorite.
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_favorite' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'listing_id' => array(
							'type'     => 'integer',
							'required' => true,
						),
						'collection' => array(
							'type'              => 'string',
							'default'           => 'default',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// DELETE /favorites/{listing_id} — remove a favorite.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<listing_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_favorite' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'listing_id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Get user's favorited listings.
	 */
	public function get_favorites( $request ) {
		global $wpdb;
		$prefix   = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$user_id  = get_current_user_id();
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );
		$offset   = ( $page - 1 ) * $per_page;

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}favorites WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT f.listing_id, f.collection, f.created_at, p.post_title
			FROM {$prefix}favorites f // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			LEFT JOIN {$wpdb->posts} p ON f.listing_id = p.ID
			WHERE f.user_id = %d AND p.post_status = 'publish'
			ORDER BY f.created_at DESC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$favorites = array_map(
			function ( $row ) {
				return array(
					'listing_id' => (int) $row['listing_id'],
					'title'      => $row['post_title'] ?: '',
					'collection' => $row['collection'],
					'url'        => get_permalink( (int) $row['listing_id'] ),
					'created_at' => $row['created_at'],
				);
			},
			$rows
		);

		$response = new WP_REST_Response(
			array(
				'favorites' => $favorites,
				'total'     => $total,
				'pages'     => (int) ceil( $total / $per_page ),
			),
			200
		);

		$response->header( 'X-WP-Total', $total );
		return $response;
	}

	/**
	 * Add a listing to favorites.
	 */
	public function add_favorite( $request ) {
		global $wpdb;
		$prefix     = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$user_id    = get_current_user_id();
		$listing_id = $request->get_param( 'listing_id' );
		$collection = $request->get_param( 'collection' );

		// Check listing exists.
		$post = get_post( $listing_id );
		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new WP_Error( 'listora_invalid_listing', __( 'Listing not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		// Check not already favorited.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}favorites WHERE user_id = %d AND listing_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$listing_id
			)
		);

		if ( $existing ) {
			return new WP_REST_Response(
				array(
					'favorited' => true,
					'message'   => __( 'Already saved.', 'wb-listora' ),
				),
				200
			);
		}

		$wpdb->insert(
			"{$prefix}favorites", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array(
				'user_id'    => $user_id,
				'listing_id' => $listing_id,
				'collection' => $collection,
				'created_at' => current_time( 'mysql', true ),
			)
		);

		/**
		 * Fires after a listing is favorited.
		 *
		 * @param int $listing_id Listing ID.
		 * @param int $user_id    User ID.
		 */
		do_action( 'wb_listora_favorite_added', $listing_id, $user_id );

		return new WP_REST_Response( array( 'favorited' => true ), 201 );
	}

	/**
	 * Remove a listing from favorites.
	 */
	public function remove_favorite( $request ) {
		global $wpdb;
		$prefix     = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$user_id    = get_current_user_id();
		$listing_id = $request->get_param( 'listing_id' );

		$wpdb->delete(
			"{$prefix}favorites", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array(
				'user_id'    => $user_id,
				'listing_id' => $listing_id,
			)
		);

		do_action( 'wb_listora_favorite_removed', $listing_id, $user_id );

		return new WP_REST_Response( array( 'favorited' => false ), 200 );
	}
}
