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
					'permission_callback' => array( $this, 'logged_in_permissions' ),
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
					'permission_callback' => array( $this, 'logged_in_permissions' ),
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
					'permission_callback' => array( $this, 'logged_in_permissions' ),
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

		// Per-user favorites cache (keyed by user + generation + page + per_page).
		$gen       = (int) wp_cache_get( 'listora_favorites_gen_' . $user_id, 'listora' );
		$cache_key = 'listora_favorites_user_' . $user_id . '_v' . $gen . '_' . $page . '_' . $per_page;
		$cached    = wp_cache_get( $cache_key, 'listora' );

		if ( false !== $cached ) {
			$response = new WP_REST_Response( $cached, 200 );
			$response->header( 'X-WP-Total', $cached['total'] );
			return $response;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}favorites WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT f.listing_id, f.collection, f.created_at, p.post_title
			FROM {$prefix}favorites f
			LEFT JOIN {$wpdb->posts} p ON f.listing_id = p.ID
			WHERE f.user_id = %d AND p.post_status = 'publish'
			ORDER BY f.created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$favorites = array_map(
			function ( $row ) use ( $request ) {
				$fav_data = array(
					'listing_id' => (int) $row['listing_id'],
					'title'      => $row['post_title'] ?: '',
					'collection' => $row['collection'],
					'url'        => get_permalink( (int) $row['listing_id'] ),
					'created_at' => $row['created_at'],
				);

				/**
				 * Filters a single favorite in the REST response list.
				 *
				 * @param array           $fav_data   Favorite data.
				 * @param int             $listing_id Listing ID.
				 * @param WP_REST_Request $request    REST request.
				 */
				return apply_filters( 'wb_listora_rest_prepare_favorite', $fav_data, (int) $row['listing_id'], $request );
			},
			$rows
		);

		$has_more = ( $offset + count( $rows ) ) < $total;

		$data = array(
			'favorites' => $favorites,
			'total'     => $total,
			'pages'     => (int) ceil( $total / $per_page ),
			'has_more'  => $has_more,
		);

		wp_cache_set( $cache_key, $data, 'listora', HOUR_IN_SECONDS );

		$response = new WP_REST_Response( $data, 200 );

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

		/**
		 * Filters whether to allow adding a favorite. Return WP_Error to abort.
		 *
		 * @param bool|WP_Error   $check      True to proceed, WP_Error to abort.
		 * @param int             $listing_id Listing ID.
		 * @param WP_REST_Request $request    REST request.
		 */
		$check = apply_filters( 'wb_listora_before_add_favorite', true, $listing_id, $request );
		if ( is_wp_error( $check ) ) {
			return $check;
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

		// Invalidate favorites and dashboard stats caches for this user.
		$this->bump_favorites_generation( $user_id );
		wp_cache_delete( 'listora_dashboard_stats_' . $user_id, 'listora' );

		/**
		 * Fires after a listing is favorited.
		 *
		 * @param int $listing_id Listing ID.
		 * @param int $user_id    User ID.
		 */
		do_action( 'wb_listora_favorite_added', $listing_id, $user_id );

		/**
		 * Fires after a favorite is added.
		 *
		 * @param int             $listing_id Listing ID.
		 * @param int             $user_id    User ID.
		 * @param WP_REST_Request $request    REST request.
		 */
		do_action( 'wb_listora_after_add_favorite', $listing_id, $user_id, $request );

		$response_data = array( 'favorited' => true );

		/**
		 * Filters the favorite add REST response data.
		 *
		 * @param array           $response_data Response data.
		 * @param int             $listing_id    Listing ID.
		 * @param WP_REST_Request $request       REST request.
		 */
		$response_data = apply_filters( 'wb_listora_rest_prepare_favorite', $response_data, $listing_id, $request );

		return new WP_REST_Response( $response_data, 201 );
	}

	/**
	 * Remove a listing from favorites.
	 */
	public function remove_favorite( $request ) {
		global $wpdb;
		$prefix     = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$user_id    = get_current_user_id();
		$listing_id = $request->get_param( 'listing_id' );

		/**
		 * Filters whether to allow removing a favorite. Return WP_Error to abort.
		 *
		 * @param bool|WP_Error   $check      True to proceed, WP_Error to abort.
		 * @param int             $listing_id Listing ID.
		 * @param WP_REST_Request $request    REST request.
		 */
		$check = apply_filters( 'wb_listora_before_remove_favorite', true, $listing_id, $request );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$wpdb->delete(
			"{$prefix}favorites", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array(
				'user_id'    => $user_id,
				'listing_id' => $listing_id,
			)
		);

		// Invalidate favorites and dashboard stats caches for this user.
		$this->bump_favorites_generation( $user_id );
		wp_cache_delete( 'listora_dashboard_stats_' . $user_id, 'listora' );

		do_action( 'wb_listora_favorite_removed', $listing_id, $user_id );

		/**
		 * Fires after a favorite is removed.
		 *
		 * @param int             $listing_id Listing ID.
		 * @param int             $user_id    User ID.
		 * @param WP_REST_Request $request    REST request.
		 */
		do_action( 'wb_listora_after_remove_favorite', $listing_id, $user_id, $request );

		$response_data = array( 'favorited' => false );

		/**
		 * Filters the favorite removal REST response data.
		 *
		 * @param array           $response_data Response data.
		 * @param int             $listing_id    Listing ID.
		 * @param WP_REST_Request $request       REST request.
		 */
		$response_data = apply_filters( 'wb_listora_rest_prepare_favorite', $response_data, $listing_id, $request );

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Check that the user is logged in, returning WP_Error if not.
	 *
	 * @return bool|\WP_Error
	 */
	public function logged_in_permissions() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'listora_unauthorized',
				__( 'You do not have permission to perform this action.', 'wb-listora' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Bump the favorites generation counter so all cached pages become stale.
	 *
	 * @param int $user_id User ID.
	 */
	private function bump_favorites_generation( $user_id ) {
		$gen_key = 'listora_favorites_gen_' . $user_id;
		if ( false === wp_cache_incr( $gen_key, 1, 'listora' ) ) {
			wp_cache_set( $gen_key, 1, 'listora', DAY_IN_SECONDS );
		}
	}
}
