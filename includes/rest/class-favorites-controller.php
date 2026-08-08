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
		if ( function_exists( 'wb_listora_feature_enabled' ) && ! wb_listora_feature_enabled( 'favorites' ) ) {
			return new WP_Error(
				'listora_favorites_disabled',
				__( 'Favorites are not enabled.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

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

		// Enrich the page with the card fields /search already returns, so a
		// client can render a favourite exactly as it renders a search result.
		// The row used to carry only id/title/collection/url/created_at — not
		// enough to draw a card, and enriching client-side would cost one detail
		// request per row. The helper is shared with /listings/{id}/related so the
		// two lists cannot drift into different card shapes.
		$cards = function_exists( 'wb_listora_get_listing_cards' )
			? wb_listora_get_listing_cards( wp_list_pluck( $rows, 'listing_id' ) )
			: array();

		$favorites = array_map(
			function ( $row ) use ( $request, $cards ) {
				$listing_id = (int) $row['listing_id'];

				$fav_data = array(
					'listing_id' => $listing_id,
					'title'      => $row['post_title'] ?: '',
					'collection' => $row['collection'],
					'url'        => get_permalink( $listing_id ),
					'created_at' => $row['created_at'],
				);

				// Existing keys above are unchanged for back-compat; the card
				// payload is merged in alongside them.
				$card = $cards[ $listing_id ] ?? null;
				if ( is_array( $card ) ) {
					$fav_data['featured_image']    = $card['featured_image'];
					$fav_data['rating']            = $card['rating'];
					$fav_data['listing_type']      = $card['listing_type'];
					$fav_data['listing_type_name'] = $card['listing_type_name'];
					$fav_data['is_featured']       = $card['is_featured'];
					$fav_data['location']          = $card['location'];
				}

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

		// Site-wide favorites-feature gate. Without this, an admin who
		// disables Favorites under Settings → Features still accepts POSTs
		// here — the frontend buttons are now hidden (listing-card +
		// listing-detail render.php gates) but a manual REST call or stale
		// JS would still write. Backend↔frontend uniformity per the
		// no-UX-gaps policy 2026-05-18.
		if ( function_exists( 'wb_listora_feature_enabled' ) && ! wb_listora_feature_enabled( 'favorites' ) ) {
			return new WP_Error(
				'listora_favorites_disabled',
				__( 'Favorites are not enabled.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		$rate_check = \WBListora\Rate_Limiter::check( 'favorite' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

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
		$this->bump_favorites_generation( $user_id, (int) $listing_id );
		// The dashboard stats cache is a TRANSIENT (user-dashboard render.php
		// get/set_transient) — wp_cache_delete() never busts it on any setup
		// (and transients live under the 'transient' group besides). BC #9982046916.
		delete_transient( 'listora_dashboard_stats_' . $user_id );

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
		if ( function_exists( 'wb_listora_feature_enabled' ) && ! wb_listora_feature_enabled( 'favorites' ) ) {
			return new WP_Error(
				'listora_favorites_disabled',
				__( 'Favorites are not enabled.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

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
		$this->bump_favorites_generation( $user_id, (int) $listing_id );
		// The dashboard stats cache is a TRANSIENT (user-dashboard render.php
		// get/set_transient) — wp_cache_delete() never busts it on any setup
		// (and transients live under the 'transient' group besides). BC #9982046916.
		delete_transient( 'listora_dashboard_stats_' . $user_id );

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
		// Delegate to the one canonical gate (includes/class-template-helpers.php).
		// This body was copy-pasted into 5 controllers; each copy also said 'You do
		// not have permission', which is wrong for a 401 — it is a login problem,
		// not a permission problem. The helper says so correctly, and it is the
		// natural place a ban/suspension gate will later hook (BC 10100523205).
		return wb_listora_require_logged_in();
	}

	/**
	 * Bump the favorites generation counter so all cached pages become stale.
	 *
	 * @param int $user_id     User ID.
	 * @param int $listing_id  Optional listing whose request-scoped flags should
	 *                         also be dropped (add/remove write paths).
	 */
	private function bump_favorites_generation( $user_id, $listing_id = 0 ) {
		$gen_key = 'listora_favorites_gen_' . $user_id;
		if ( false === wp_cache_incr( $gen_key, 1, 'listora' ) ) {
			wp_cache_set( $gen_key, 1, 'listora', DAY_IN_SECONDS );
		}

		// Drop the request-scoped is_favorited / favorite_count entries too, so
		// a request that toggles a favourite and then re-reads the listing in
		// the same PHP process sees the new value rather than the primed one.
		if ( $listing_id > 0 ) {
			\WBListora\Core\Favorites_Cache::forget( (int) $listing_id, (int) $user_id );
		}
	}
}
