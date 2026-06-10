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
	 * Extend the parent collection params with our optional `cursor` arg.
	 *
	 * `cursor` is opt-in keyset pagination — see {@see self::get_items()}.
	 * Existing OFFSET/page callers are unaffected.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params() {
		$params           = parent::get_collection_params();
		$params['cursor'] = array(
			'description' => __( 'Cursor pagination — last-seen listing ID. When present, results are returned with id < cursor ORDER BY id DESC, in O(1) regardless of page depth.', 'wb-listora' ),
			'type'        => 'integer',
			'minimum'     => 0,
		);
		return $params;
	}

	/**
	 * Override the collection endpoint to support optional cursor
	 * pagination (`?cursor=<last-seen-id>`).
	 *
	 * - If `cursor` is absent, delegate to parent unchanged — existing
	 *   OFFSET/page callers see no behaviour change.
	 * - If `cursor` is present, run a keyset query: `WHERE ID < ?
	 *   ORDER BY ID DESC LIMIT ?` and shape the response with the same
	 *   prepared items as the parent. Adds `cursor` + `next_cursor` to
	 *   the response so a client can chain pages without OFFSET cost.
	 *
	 * Reference: SKILL.md Part 2.3 / scale-and-cache.md §2.2.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_items( $request ) {
		$cursor_param = $request->get_param( 'cursor' );

		// `null` and empty-string (the standard "absent" representations
		// for optional integer args) → fall through to parent OFFSET path.
		if ( null === $cursor_param || '' === $cursor_param ) {
			$response = parent::get_items( $request );

			if ( ! $response instanceof WP_REST_Response ) {
				return $response;   // WP_Error — pass through unchanged.
			}

			// Backend↔frontend uniformity (D1, no-UX-gaps policy 2026-05-18).
			// Cursor mode (below) returns the Listora envelope
			// { listings, total, pages, has_more, cursor, next_cursor }.
			// /search returns the same envelope. The OFFSET branch used to
			// return a bare array from parent::get_items() with pagination
			// in X-WP-Total / X-WP-TotalPages headers — same payload, three
			// different shapes across one product. Wrap here so every list
			// endpoint emits one canonical shape.
			$items = $response->get_data();
			$items = is_array( $items ) ? $items : array();

			$headers = $response->get_headers();
			$total   = isset( $headers['X-WP-Total'] ) ? (int) $headers['X-WP-Total'] : count( $items );
			$pages   = isset( $headers['X-WP-TotalPages'] ) ? (int) $headers['X-WP-TotalPages'] : 0;

			$per_page     = max( 1, (int) $request->get_param( 'per_page' ) );
			$current_page = max( 1, (int) $request->get_param( 'page' ) );
			$has_more     = $current_page < $pages;

			// next_cursor lets clients switch from OFFSET to CURSOR mode
			// without re-fetching the first page.
			$last        = end( $items );
			$next_cursor = is_array( $last ) && isset( $last['id'] ) ? (int) $last['id'] : null;

			$response->set_data(
				array(
					'listings'    => $items,
					'total'       => $total,
					'pages'       => $pages,
					'has_more'    => $has_more,
					'cursor'      => null,
					'next_cursor' => $next_cursor,
				)
			);
			$response->header( 'X-WP-NextCursor', (string) ( $next_cursor ?? '' ) );

			return $response;
		}

		$cursor   = max( 0, (int) $cursor_param );
		$per_page = (int) $request->get_param( 'per_page' );
		if ( $per_page < 1 ) {
			$per_page = 10;
		}
		$per_page = min( $per_page, 100 );

		global $wpdb;

		$cursor_id = $cursor > 0 ? $cursor : PHP_INT_MAX;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'listora_listing'
				AND post_status = 'publish'
				AND ID < %d
				ORDER BY ID DESC
				LIMIT %d",
				$cursor_id,
				$per_page
			)
		);
		$post_ids = array_map( 'intval', (array) $post_ids );

		// Total uses the same authorless filter — it's a coarse counter
		// for "how many published listings exist". Cached per request.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_type = 'listora_listing'
			AND post_status = 'publish'"
		);

		$items = array();
		if ( ! empty( $post_ids ) ) {
			$posts = get_posts(
				array(
					'post_type'        => 'listora_listing',
					'post__in'         => $post_ids,
					'orderby'          => 'post__in',
					'posts_per_page'   => count( $post_ids ),
					'suppress_filters' => false,
				)
			);

			// Warm post-meta + taxonomy caches for the entire result set in
			// a single query each, so prepare_item_for_response()'s per-post
			// get_post_meta() / get_the_terms() calls become cache hits
			// instead of N round-trips. At 100K listings this is the
			// difference between linear and quadratic load on the listings
			// list endpoint (Phase 1.2 — N+1 fix).
			$listing_ids = wp_list_pluck( $posts, 'ID' );
			if ( ! empty( $listing_ids ) ) {
				update_post_caches( $posts, 'listora_listing', true, true );
				update_object_term_cache( $listing_ids, 'listora_listing' );

				// Prefetch analytics-lite view counts for the whole page in one
				// grouped query so prepare_item_for_response()'s per-item
				// get_views() is a cache hit, not an N+1. Only the owner / admin
				// see the field, but priming is cheap and avoids per-row queries.
				if ( class_exists( '\\WBListora\\Features\\Analytics_Lite' ) ) {
					\WBListora\Features\Analytics_Lite::prepare_views( $listing_ids );
				}
			}

			foreach ( $posts as $post ) {
				$prepared = $this->prepare_item_for_response( $post, $request );
				if ( ! is_wp_error( $prepared ) ) {
					$items[] = $this->prepare_response_for_collection( $prepared );
				}
			}
		}

		$has_more    = count( $post_ids ) >= $per_page;
		$next_cursor = $has_more && ! empty( $post_ids ) ? (int) end( $post_ids ) : null;

		$response = new WP_REST_Response(
			array(
				'listings'    => $items,
				'total'       => $total,
				'pages'       => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
				'has_more'    => $has_more,
				'cursor'      => $cursor,
				'next_cursor' => $next_cursor,
			),
			200
		);

		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-NextCursor', (string) ( $next_cursor ?? '' ) );

		return $response;
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
						'id'          => array(
							'type'     => 'integer',
							'required' => true,
						),
						'fields'      => array(
							'type'        => 'string',
							'default'     => 'detail',
							'enum'        => array( 'card', 'detail' ),
							'description' => 'Response detail level. "card" returns a minimal payload for list views; "detail" returns the full object (default).',
						),
						'image_sizes' => array(
							'type'        => 'string',
							'default'     => '',
							'description' => 'Comma-separated list of image sizes to include (thumbnail,medium,large,full). Empty returns all four.',
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

		// POST /listings/{id}/feature — Owner upgrades their listing to Featured.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/feature',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'feature_listing' ),
					'permission_callback' => array( $this, 'feature_listing_permissions' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		// POST /listings/{id}/deactivate — Owner hides their listing from the directory.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/deactivate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'deactivate_listing' ),
					'permission_callback' => array( $this, 'deactivate_listing_permissions' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		// POST /listings/{id}/reactivate — Owner restores a deactivated listing.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/reactivate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reactivate_listing' ),
					'permission_callback' => array( $this, 'reactivate_listing_permissions' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		// POST /listings/{id}/report — Visitor flags a listing for admin review.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/report',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'report_listing' ),
					'permission_callback' => array( $this, 'report_listing_permissions' ),
					'args'                => array(
						'id'      => array(
							'type'     => 'integer',
							'required' => true,
						),
						'reason'  => array(
							'type'              => 'string',
							'required'          => true,
							'enum'              => array( 'inaccurate', 'spam', 'closed', 'duplicate', 'offensive', 'other' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'details' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_textarea_field',
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

		// GET /listings/{id}/renewal-quote — Renewal pricing/status for the listing.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/renewal-quote',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_renewal_quote' ),
					'permission_callback' => array( $this, 'renew_listing_permissions' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		// POST /listings/{id}/renew — Owner renews their listing.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/renew',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'renew_listing' ),
					'permission_callback' => array( $this, 'renew_listing_permissions' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		// POST /listings/bulk — fetch up to 50 listings by ID in one call.
		// Lets an offline-capable app re-hydrate its cache without firing N
		// individual /listings/{id}/detail requests.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'get_bulk' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'ids'         => array(
							'type'        => 'array',
							'required'    => true,
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Listing IDs to fetch (max 50).',
						),
						'fields'      => array(
							'type'        => 'string',
							'default'     => 'card',
							'enum'        => array( 'card', 'detail' ),
							'description' => 'Response detail level per listing.',
						),
						'image_sizes' => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				),
			)
		);

		// POST /listings/bulk-moderate — apply a moderation action (approve /
		// reject / feature / unfeature / trash) to up to 100 listings in one
		// call. Admins triaging a large pending queue avoid making 100
		// individual REST calls. Per-ID permission re-check keeps the audit
		// trail honest even when the outer cap passes.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk-moderate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'bulk_moderate' ),
					'permission_callback' => array( $this, 'bulk_moderate_permissions' ),
					'args'                => array(
						'ids'    => array(
							'type'        => 'array',
							'required'    => true,
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Listing IDs to act on (max 100).',
						),
						'action' => array(
							'type'        => 'string',
							'required'    => true,
							'enum'        => array( 'approve', 'reject', 'feature', 'unfeature', 'trash' ),
							'description' => 'Moderation action to apply uniformly to each ID.',
						),
						'days'   => array(
							'type'        => 'integer',
							'default'     => 0,
							'description' => 'For action=feature: duration in days (0 = admin default).',
						),
					),
				),
			)
		);
	}

	/**
	 * Permission check for bulk-moderate.
	 *
	 * Requires `edit_others_listora_listings` at the top level. Per-ID
	 * `edit_post` checks happen inside the handler so the user can still
	 * bulk-act on their own listings if the cap-map ever changes.
	 *
	 * @return true|\WP_Error
	 */
	public function bulk_moderate_permissions() {
		if ( ! current_user_can( 'edit_others_listora_listings' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to moderate listings.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * POST /listings/bulk-moderate — apply a moderation action to many listings.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function bulk_moderate( $request ) {
		$ids = (array) $request->get_param( 'ids' );
		$ids = array_slice( array_values( array_unique( array_map( 'absint', $ids ) ) ), 0, 100 );
		$ids = array_filter( $ids );

		$action = (string) $request->get_param( 'action' );
		$days   = (int) $request->get_param( 'days' );

		$ok     = array();
		$failed = array();

		foreach ( $ids as $id ) {
			$id = (int) $id;

			if ( 'listora_listing' !== get_post_type( $id ) ) {
				$failed[] = array(
					'id'    => $id,
					'error' => 'not_a_listing',
				);
				continue;
			}

			if ( ! current_user_can( 'edit_post', $id ) ) {
				$failed[] = array(
					'id'    => $id,
					'error' => 'forbidden',
				);
				continue;
			}

			$result = $this->apply_moderation_action( $id, $action, $days );

			if ( is_wp_error( $result ) ) {
				$failed[] = array(
					'id'    => $id,
					'error' => $result->get_error_code(),
				);
			} else {
				$ok[] = $id;
			}
		}

		/**
		 * Fires after a bulk-moderate batch completes.
		 *
		 * @since 1.0.5
		 *
		 * @param string                                       $action Action applied uniformly across the batch.
		 * @param int[]                                        $ok     Listing IDs the action succeeded on.
		 * @param list<array{id:int,error:int|string}>         $failed Per-ID failures.
		 */
		do_action( 'wb_listora_after_bulk_moderate', $action, $ok, $failed );

		return rest_ensure_response(
			array(
				'action'    => $action,
				'requested' => count( $ids ),
				'succeeded' => $ok,
				'failed'    => $failed,
			)
		);
	}

	/**
	 * Apply a single moderation action to a listing.
	 *
	 * Returns WP_Error on failure (per-action error code) or true on success.
	 *
	 * @param int    $post_id Listing ID.
	 * @param string $action  approve | reject | feature | unfeature | trash.
	 * @param int    $days    Duration for action=feature (0 = admin default).
	 * @return true|\WP_Error
	 */
	private function apply_moderation_action( $post_id, $action, $days = 0 ) {
		switch ( $action ) {
			case 'approve':
				$res = wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'publish',
					),
					true
				);
				return is_wp_error( $res ) ? $res : true;

			case 'reject':
				$res = wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'listora_rejected',
					),
					true
				);
				return is_wp_error( $res ) ? $res : true;

			case 'feature':
				$res = \WBListora\Core\Featured::feature_listing( $post_id, $days );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				return true === $res
					? true
					: new \WP_Error( 'feature_failed', __( 'Could not feature this listing.', 'wb-listora' ) );

			case 'unfeature':
				$res = \WBListora\Core\Featured::unfeature_listing( $post_id, 'manual' );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				return true === $res
					? true
					: new \WP_Error( 'unfeature_failed', __( 'Could not unfeature this listing.', 'wb-listora' ) );

			case 'trash':
				$res = wp_trash_post( $post_id );
				return $res ? true : new \WP_Error( 'trash_failed', __( 'Could not move listing to trash.', 'wb-listora' ) );
		}

		return new \WP_Error( 'unknown_action', __( 'Unknown moderation action.', 'wb-listora' ) );
	}

	/**
	 * POST /listings/bulk — fetch many listings by ID in a single call.
	 *
	 * Limited to 50 IDs per request to keep the payload bounded. Returns
	 * the same per-listing shape as GET /listings/{id}/detail, subject to
	 * the `fields` and `image_sizes` params.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function get_bulk( $request ) {
		// F-01: rate-limit public bulk fetch (each call resolves up to 50
		// listings — heavy joins + meta + image URLs). The Rate_Limiter is
		// IP-aware and proxy-aware; legitimate frontend use (1 call per
		// page navigation) sits comfortably inside the cap.
		$gate = \WBListora\Rate_Limiter::check( 'bulk_listings' );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$ids = (array) $request->get_param( 'ids' );
		$ids = array_slice( array_values( array_unique( array_map( 'absint', $ids ) ) ), 0, 50 );
		$ids = array_filter( $ids );

		$listings = array();
		foreach ( $ids as $id ) {
			$sub = new WP_REST_Request( 'GET' );
			$sub->set_param( 'id', $id );
			$sub->set_param( 'fields', $request->get_param( 'fields' ) );
			$sub->set_param( 'image_sizes', $request->get_param( 'image_sizes' ) );
			$sub->set_param( 'include_related', '0' );

			$response = $this->get_listing( $sub );
			if ( ! is_wp_error( $response ) ) {
				$listings[] = $response->get_data();
			}
		}

		return new WP_REST_Response(
			array(
				'listings'  => $listings,
				'total'     => count( $listings ),
				'requested' => count( $ids ),
			),
			200
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

		// Add rating from search index — only when the Reviews feature is on.
		// Disabling Reviews must hide every read surface, REST included
		// (card 9895809632), so headless / mobile clients don't render
		// ratings the admin turned off.
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		if ( ! function_exists( 'wb_listora_feature_enabled' ) || wb_listora_feature_enabled( 'reviews' ) ) {
			$idx = $wpdb->get_row(
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
		}

		// Add flags.
		$data['is_featured']    = \WBListora\Core\Featured::is_featured( $post->ID );
		$data['featured_until'] = \WBListora\Core\Featured::get_featured_until( $post->ID );
		$data['is_verified']    = wb_listora_is_verified( $post->ID );
		$data['is_claimed']     = (bool) get_post_meta( $post->ID, '_listora_is_claimed', true );

		// ISO-8601 timestamps for headless clients — card 9900590343. The
		// stock WP REST schema exposes `date` / `modified` but not the GMT
		// variants in a stable RFC-3339 shape; expose `created_at` /
		// `updated_at` here so consumers don't have to chain to /wp/v2.
		$data['created_at'] = mysql_to_rfc3339( $post->post_date_gmt );
		$data['updated_at'] = mysql_to_rfc3339( $post->post_modified_gmt );

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

		// View count (analytics-lite) — owner + admin only. Views are an
		// owner-facing insight, not public data, so gate the field to the
		// listing author and anyone who can edit others' posts. The aggregate
		// is read from the per-request cache primed in get_items() (one batched
		// query per page); a single-item prepare falls back to a bounded
		// per-listing lookup inside the service.
		if ( class_exists( '\\WBListora\\Features\\Analytics_Lite' ) && is_user_logged_in() ) {
			$current_user = get_current_user_id();
			$is_owner     = $current_user === (int) $post->post_author;
			if ( $is_owner || current_user_can( 'edit_others_posts' ) ) {
				$data['views'] = \WBListora\Features\Analytics_Lite::get_views( $post->ID );
			}
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
		// Listings in pending_verification are hidden even from the author
		// via the public single endpoint — the verification handler has its
		// own friendly URL.
		if ( 'publish' !== $post->post_status ) {
			$current_user      = get_current_user_id();
			$is_owner_view     = (int) $post->post_author === $current_user || current_user_can( 'edit_others_posts' );
			$is_pending_verify = 'pending_verification' === $post->post_status;
			if ( ! $is_owner_view || $is_pending_verify ) {
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

		// --- Fields selector ---
		// Apps on slow networks request `fields=card` to get a minimal payload
		// for list / grid views. Default `detail` returns everything.
		$fields      = (string) $request->get_param( 'fields' );
		$card_mode   = ( 'card' === $fields );
		$image_sizes = $this->parse_image_sizes( $request->get_param( 'image_sizes' ) );

		// --- Post data ---
		// Featured image — app-stable shape: id, alt, thumbnail, medium, large, full.
		// Matches the shape returned by class-search-controller.php so apps have
		// a single featured_image schema across list and detail views.
		$featured_image = array();
		$thumb_id       = get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) {
			$featured_image = array(
				'id'  => (int) $thumb_id,
				'alt' => (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
			);
			foreach ( $image_sizes as $size ) {
				$featured_image[ $size ] = wp_get_attachment_image_url( $thumb_id, $size );
			}
		}

		$data = array(
			'id'             => $post->ID,
			'title'          => $post->post_title,
			'excerpt'        => get_the_excerpt( $post ),
			'status'         => $post->post_status,
			'author_id'      => (int) $post->post_author,
			'date'           => $post->post_date,
			'modified'       => $post->post_modified,
			'created_at'     => mysql_to_rfc3339( $post->post_date_gmt ),
			'updated_at'     => mysql_to_rfc3339( $post->post_modified_gmt ),
			'featured_image' => $featured_image,
			'url'            => get_permalink( $post_id ),
		);

		// Full content only in detail mode — cuts card payload by 10–50 KB.
		if ( ! $card_mode ) {
			$data['content'] = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
		}

		// --- Listing type ---
		$data['listing_type'] = array(
			'slug'  => $type ? $type->get_slug() : '',
			'label' => $type ? $type->get_name() : '',
			'icon'  => $type ? $type->get_icon() : '',
		);

		// --- Taxonomies ---
		$data['categories'] = $this->get_terms_for_response( $post_id, 'listora_listing_cat' );
		if ( ! $card_mode ) {
			$data['locations'] = $this->get_hierarchical_terms( $post_id, 'listora_listing_location' );
			$data['features']  = $this->get_feature_terms( $post_id );
			$data['tags']      = $this->get_terms_for_response( $post_id, 'listora_listing_tag' );
		}

		// --- All meta --- (skipped in card mode — a listing's full meta payload
		// is the single largest contributor to response size).
		if ( ! $card_mode ) {
			$data['meta'] = \WBListora\Core\Meta_Handler::get_all_values( $post_id );
		}

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
		// Only expose when the Reviews feature is on. Disabling Reviews must
		// hide every read surface, REST detail stats included (card 9895809632).
		if ( ! function_exists( 'wb_listora_feature_enabled' ) || wb_listora_feature_enabled( 'reviews' ) ) {
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
		}

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

		// --- View count (analytics-lite) --- owner + admin only.
		// Views are an owner-facing insight, not public data, so gate to the
		// listing author and editors. Reads the same `view` rows whether Free
		// or Pro recorded them.
		if ( class_exists( '\\WBListora\\Features\\Analytics_Lite' ) && is_user_logged_in() ) {
			$is_owner = get_current_user_id() === (int) $post->post_author;
			if ( $is_owner || current_user_can( 'edit_others_posts' ) ) {
				$data['views'] = \WBListora\Features\Analytics_Lite::get_views( $post_id );
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
					"SELECT id, title, description, price, price_type, duration_minutes, image_id FROM {$prefix}services WHERE listing_id = %d AND status = 'active' ORDER BY sort_order ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$post_id
				),
				ARRAY_A
			);

			if ( null === $data['services'] ) {
				$data['services'] = array();
			}
		}

		// --- Gallery --- (skipped in card mode; typically the second-largest
		// contributor to payload size after meta).
		if ( ! $card_mode ) {
			$gallery_ids = \WBListora\Core\Meta_Handler::get_all_values( $post_id )['gallery'] ?? array();
			$gallery     = array();

			if ( is_array( $gallery_ids ) && ! empty( $gallery_ids ) ) {
				foreach ( $gallery_ids as $attachment_id ) {
					$attachment_id = (int) $attachment_id;
					if ( ! $attachment_id ) {
						continue;
					}
					$item = array(
						'id'  => $attachment_id,
						'alt' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
					);
					foreach ( $image_sizes as $size ) {
						$item[ $size ] = wp_get_attachment_image_url( $attachment_id, $size );
					}
					$gallery[] = $item;
				}
			}
			$data['gallery'] = $gallery;
		}

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

		// --- Related listings (inline, up to 4) — skipped in card mode; also
		// opt-out via ?include_related=0 to save a full inline query on apps
		// that render related listings lazily on scroll.
		$include_related = ! $card_mode && '0' !== (string) $request->get_param( 'include_related' );
		if ( $include_related ) {
			$related_request = new WP_REST_Request( 'GET' );
			$related_request->set_param( 'id', $post_id );
			$related_request->set_param( 'limit', 4 );
			$related_response = $this->get_related( $related_request );
			$data['related']  = $related_response->get_data();
		}

		// --- Author info ---
		$author         = get_user_by( 'ID', $post->post_author );
		$data['author'] = array(
			'id'           => (int) $post->post_author,
			'display_name' => $author ? $author->display_name : __( 'Unknown', 'wb-listora' ),
			'avatar_url'   => get_avatar_url( $post->post_author, array( 'size' => 96 ) ),
		);

		// --- Flags ---
		$data['is_featured']    = \WBListora\Core\Featured::is_featured( $post_id );
		$data['featured_until'] = \WBListora\Core\Featured::get_featured_until( $post_id );
		$data['is_verified']    = wb_listora_is_verified( $post_id );

		// --- Schema --- (gated by the Schema.org toggle, mirroring Plugin::output_schema()).
		if ( wb_listora_feature_enabled( 'schema' ) ) {
			$schema         = \WBListora\Schema\Schema_Generator::for_listing( $post_id );
			$data['schema'] = $schema ? $schema->get_data() : null;
		} else {
			$data['schema'] = null;
		}

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

		// Dashboard stats cache invalidation is handled by
		// \WBListora\Core\Cache via the `wb_listora_after_delete_listing`
		// action fired below — no manual key delete required.

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
	 * Permission check for deactivating a listing (owner only).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function deactivate_listing_permissions( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'listora_unauthorized',
				__( 'You must be logged in to deactivate a listing.', 'wb-listora' ),
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

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( 'edit_others_posts' ) ) {
			return new \WP_Error(
				'listora_forbidden',
				__( 'You do not have permission to deactivate this listing.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Deactivate a listing — set post_status to listora_deactivated so it is
	 * hidden from the public directory but recoverable by the owner.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function deactivate_listing( $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new \WP_Error(
				'listora_not_found',
				__( 'Listing not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		if ( 'listora_deactivated' === $post->post_status ) {
			return new WP_REST_Response(
				array(
					'deactivated' => true,
					'message'     => __( 'Listing is already deactivated.', 'wb-listora' ),
				),
				200
			);
		}

		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'listora_deactivated',
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires after a listing is deactivated by its owner via REST.
		 *
		 * @param int             $post_id Listing post ID.
		 * @param WP_REST_Request $request REST request.
		 */
		do_action( 'wb_listora_after_deactivate_listing', $post_id, $request );

		return new WP_REST_Response(
			array(
				'deactivated' => true,
				'message'     => __( 'Listing deactivated successfully.', 'wb-listora' ),
			),
			200
		);
	}

	/**
	 * Permission check for reporting a listing.
	 *
	 * Requires the `report_listings` feature to be enabled and the user to be
	 * logged in (the frontend opens the login modal for guests). The listing
	 * must exist, be published, and not belong to the reporter (you can't flag
	 * your own listing).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function report_listing_permissions( $request ) {
		if ( ! function_exists( 'wb_listora_feature_enabled' ) || ! wb_listora_feature_enabled( 'report_listings' ) ) {
			return new \WP_Error(
				'listora_reports_disabled',
				__( 'Listing reports are not enabled.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'listora_unauthorized',
				__( 'You must be logged in to report a listing.', 'wb-listora' ),
				array( 'status' => 401 )
			);
		}

		$post = get_post( (int) $request->get_param( 'id' ) );

		if ( ! $post || 'listora_listing' !== $post->post_type || 'publish' !== $post->post_status ) {
			return new \WP_Error(
				'listora_not_found',
				__( 'Listing not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		if ( (int) $post->post_author === get_current_user_id() ) {
			return new \WP_Error(
				'listora_forbidden',
				__( 'You cannot report your own listing.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Report (flag) a listing for admin review.
	 *
	 * Mirrors the review-report mechanism: rate-limited, deduped per user, and
	 * stored in a non-autoloaded per-listing option (`_listora_listing_reports_{id}`).
	 * Fires `wb_listora_listing_reported` so Pro's moderator queue (or any
	 * extension) can surface the flag in a unified queue. Admins see flagged
	 * listings via the Reports column + metabox on the listings admin screen.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function report_listing( $request ) {
		$listing_id = (int) $request->get_param( 'id' );
		$user_id    = get_current_user_id();

		$rate_check = \WBListora\Rate_Limiter::check( 'listing_report' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$option  = '_listora_listing_reports_' . $listing_id;
		$reports = get_option( $option, array() );
		if ( ! is_array( $reports ) ) {
			$reports = array();
		}

		foreach ( $reports as $report ) {
			if ( isset( $report['user_id'] ) && (int) $report['user_id'] === $user_id ) {
				return new \WP_Error(
					'listora_already_reported',
					__( 'You have already reported this listing.', 'wb-listora' ),
					array( 'status' => 409 )
				);
			}
		}

		$report = array(
			'user_id' => $user_id,
			'reason'  => (string) $request->get_param( 'reason' ),
			'details' => (string) $request->get_param( 'details' ),
			'status'  => 'open',
			'date'    => current_time( 'mysql', true ),
		);

		$reports[] = $report;

		// Non-autoloaded option — reports are low-volume and admin-facing only.
		update_option( $option, $reports, false );

		/**
		 * Fires after a listing is reported.
		 *
		 * @param int   $listing_id Reported listing ID.
		 * @param array $report     The report just stored (user_id, reason, details, status, date).
		 * @param int   $count      Total open + resolved reports now on the listing.
		 */
		do_action( 'wb_listora_listing_reported', $listing_id, $report, count( $reports ) );

		return new \WP_REST_Response( array( 'reported' => true ), 200 );
	}

	/**
	 * Permission check for reactivating a listing — same rule as deactivate
	 * (owner OR a user who can edit_others_posts). Kept as a separate method
	 * so future per-action policy tweaks (e.g. require credit balance to
	 * reactivate after expiry) can land without disturbing deactivate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function reactivate_listing_permissions( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'listora_unauthorized',
				__( 'You must be logged in to reactivate a listing.', 'wb-listora' ),
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

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( 'edit_others_posts' ) ) {
			return new \WP_Error(
				'listora_forbidden',
				__( 'You do not have permission to reactivate this listing.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Reactivate a listing — transition post_status from listora_deactivated
	 * back to publish so the listing reappears in the public directory.
	 * Only valid when current status is listora_deactivated; other states
	 * return a 409 so callers can give the right UX hint (e.g. expired
	 * listings need the renewal flow, not reactivate).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function reactivate_listing( $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new \WP_Error(
				'listora_not_found',
				__( 'Listing not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		if ( 'publish' === $post->post_status ) {
			return new WP_REST_Response(
				array(
					'reactivated' => true,
					'message'     => __( 'Listing is already active.', 'wb-listora' ),
				),
				200
			);
		}

		if ( 'listora_deactivated' !== $post->post_status ) {
			return new \WP_Error(
				'listora_invalid_state',
				__( 'Only deactivated listings can be reactivated. For expired listings, use Renew instead.', 'wb-listora' ),
				array( 'status' => 409 )
			);
		}

		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires after a listing is reactivated by its owner via REST.
		 *
		 * @param int             $post_id Listing post ID.
		 * @param WP_REST_Request $request REST request.
		 */
		do_action( 'wb_listora_after_reactivate_listing', $post_id, $request );

		return new WP_REST_Response(
			array(
				'reactivated' => true,
				'message'     => __( 'Listing reactivated successfully.', 'wb-listora' ),
			),
			200
		);
	}

	/**
	 * Permission check: feature a listing (owner only, unless admin).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function feature_listing_permissions( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'listora_unauthorized',
				__( 'You must be logged in to feature a listing.', 'wb-listora' ),
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

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( 'edit_others_listora_listings' ) ) {
			return new \WP_Error(
				'listora_forbidden',
				__( 'You do not have permission to feature this listing.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Feature a listing for the admin-configured duration.
	 *
	 * Fires `wb_listora_before_feature_listing` (SDK places a hold) and
	 * `wb_listora_after_feature_listing` (SDK deducts credits).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function feature_listing( $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new \WP_Error(
				'listora_not_found',
				__( 'Listing not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		// Already featured? Refuse — don't double-charge.
		if ( \WBListora\Core\Featured::is_featured( $post_id ) ) {
			return new \WP_Error(
				'listora_already_featured',
				__( 'This listing is already featured.', 'wb-listora' ),
				array( 'status' => 409 )
			);
		}

		$cost    = (int) wb_listora_get_setting( 'featured_credit_cost', 0 );
		$days    = \WBListora\Core\Featured::get_default_duration_days();
		$user_id = get_current_user_id();
		$has_sdk = class_exists( '\Wbcom\Credits\Credits' );

		// ─── Hold → Commit pattern ─────────────────────────────────
		//
		// The previous implementation relied on the SDK consumer's
		// `featured_upgrade` registration to hold + deduct credits via
		// the `wb_listora_before_feature_listing` filter, but that
		// hook is a `apply_filters(true, $post_id, $context)` whose
		// first arg is the bool $check, not $post_id — the SDK
		// Consumer's `add_action($hook, ..., 10, 1)` callback received
		// `true` instead of the post ID and silently no-op'd. Result:
		// vendors got Featured for 0 credits regardless of the
		// `featured_credit_cost` setting (revenue leak).
		//
		// Replaced with explicit Hold → Featured → Deduct here, same
		// pattern Pro's Pricing_Plans uses for plan activation. On
		// any failure the hold is cancelled so the vendor's balance
		// is restored and the ledger shows hold + cancel_hold (no
		// orphan debit).
		$hold_placed  = false;
		$feature_note = sprintf(
			/* translators: %d: listing ID */
			__( 'Feature upgrade — listing #%d', 'wb-listora' ),
			$post_id
		);

		if ( $cost > 0 && $has_sdk ) {
			$balance = (int) \Wbcom\Credits\Credits::get_balance( 'wb-listora', $user_id );
			if ( $balance < $cost ) {
				return new \WP_Error(
					'listora_insufficient_credits',
					sprintf(
						/* translators: 1: cost, 2: current balance */
						__( 'You need %1$d credits to feature this listing (you have %2$d).', 'wb-listora' ),
						$cost,
						$balance
					),
					array(
						'status'  => 402,
						'cost'    => $cost,
						'balance' => $balance,
					)
				);
			}

			$hold = \Wbcom\Credits\Credits::hold( 'wb-listora', $user_id, $cost, $post_id, $feature_note );
			if ( false === $hold ) {
				return new \WP_Error(
					'listora_hold_failed',
					__( 'Could not reserve credits for the feature upgrade. Please try again.', 'wb-listora' ),
					array( 'status' => 500 )
				);
			}
			$hold_placed = true;
		}

		try {
			$ok = \WBListora\Core\Featured::feature_listing( $post_id, $days );

			if ( is_wp_error( $ok ) ) {
				throw new \RuntimeException( $ok->get_error_message() );
			}
			if ( ! $ok ) {
				throw new \RuntimeException( __( 'Unable to feature this listing. Please try again.', 'wb-listora' ) );
			}

			// Featured perk applied — commit the hold by recording the
			// permanent deduction. SDK fires `wbcom_credits_deducted`
			// which the event bridge re-fires as the canonical Pro
			// action `wb_listora_pro_credits_deducted` for downstream
			// listeners (Audit_Log, Outgoing_Webhooks).
			if ( $hold_placed ) {
				$deduct_ok = \Wbcom\Credits\Credits::deduct( 'wb-listora', $user_id, $cost, $post_id, $feature_note );
				if ( ! $deduct_ok ) {
					throw new \RuntimeException( 'Credits::deduct() failed to consume the hold for listing #' . $post_id );
				}
				$hold_placed = false;
			}
		} catch ( \Throwable $e ) {
			if ( $hold_placed ) {
				\Wbcom\Credits\Credits::cancel_hold( 'wb-listora', $user_id, $post_id );
			}
			return new \WP_Error(
				'listora_feature_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}

		$until = \WBListora\Core\Featured::get_featured_until( $post_id );

		return new WP_REST_Response(
			array(
				'featured'       => true,
				'permanent'      => ( 0 === $until ),
				'featured_until' => $until ? (int) $until : 0,
				'days'           => (int) $days,
				'message'        => 0 === $until
					? __( 'Your listing is now featured permanently.', 'wb-listora' )
					: sprintf(
						/* translators: %s: expiration date */
						__( 'Your listing is now featured until %s.', 'wb-listora' ),
						wp_date( get_option( 'date_format' ), (int) $until )
					),
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

	// ────────────────────────────────────────────────────────────────────
	// Renewal flow
	// ────────────────────────────────────────────────────────────────────

	/**
	 * Permission check for renewal endpoints (author or admin).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function renew_listing_permissions( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'listora_unauthorized',
				__( 'You must be logged in to renew a listing.', 'wb-listora' ),
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

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( 'edit_others_posts' ) ) {
			return new \WP_Error(
				'listora_forbidden',
				__( 'You do not have permission to renew this listing.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Build a renewal quote — pricing + eligibility — for the given listing.
	 *
	 * @param int $post_id Listing post ID.
	 * @return array{plan_id:int,plan_name:string,cost:int,currency:string,duration_days:int,days_until_expiry:int,can_renew_now:bool,is_expired:bool,renewal_window_days:int,reason:string,balance:int,expiry:string}
	 */
	private function build_renewal_quote( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$expiration_setting = (int) wb_listora_get_setting( 'default_renewal_duration_days', (int) wb_listora_get_setting( 'default_expiration', 365 ) );
		$default_cost       = (int) wb_listora_get_setting( 'default_renewal_credit_cost', 0 );
		$window_days        = (int) wb_listora_get_setting( 'renewal_window_days', 7 );

		$plan_id       = (int) get_post_meta( $post_id, '_listora_plan_id', true );
		$plan_name     = '';
		$cost          = $default_cost;
		$duration_days = $expiration_setting > 0 ? $expiration_setting : 365;

		if ( $plan_id > 0 ) {
			$plan = get_post( $plan_id );
			if ( $plan && 'listora_plan' === $plan->post_type ) {
				$plan_name        = $plan->post_title;
				$plan_credit_cost = get_post_meta( $plan_id, '_listora_plan_credits', true );
				$plan_duration    = (int) get_post_meta( $plan_id, '_listora_plan_duration_days', true );
				if ( '' !== $plan_credit_cost ) {
					$cost = (int) $plan_credit_cost;
				}
				if ( $plan_duration > 0 ) {
					$duration_days = $plan_duration;
				}
			}
		}

		// Allow Pro features and 3rd-parties to override the renewal cost.
		$cost = (int) apply_filters( 'wb_listora_renewal_cost', $cost, $post_id, $plan_id );

		// Allow override of duration (e.g., promotions).
		$duration_days = (int) apply_filters( 'wb_listora_renewal_duration_days', $duration_days, $post_id, $plan_id );

		$expiry_raw = get_post_meta( $post_id, '_listora_expiration_date', true );
		$expiry_ts  = $expiry_raw ? (int) strtotime( $expiry_raw ) : 0;
		$now_ts     = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		$days_until_expiry = $expiry_ts > 0 ? (int) ceil( ( $expiry_ts - $now_ts ) / DAY_IN_SECONDS ) : 0;
		$is_expired        = ( 'listora_expired' === $post->post_status ) || ( $expiry_ts > 0 && $expiry_ts < $now_ts );

		$can_renew_now = false;
		$reason        = '';

		if ( ! wb_listora_feature_enabled( 'renewal' ) ) {
			$reason = __( 'Renewal is disabled on this site.', 'wb-listora' );
		} elseif ( $is_expired ) {
			$can_renew_now = true;
		} elseif ( $expiry_ts > 0 && $days_until_expiry <= $window_days ) {
			$can_renew_now = true;
		} else {
			$reason = sprintf(
				/* translators: %d: number of days */
				__( 'You can renew within %d days of expiry.', 'wb-listora' ),
				$window_days
			);
		}

		$balance = 0;
		if ( $cost > 0 && class_exists( '\Wbcom\Credits\Credits' ) ) {
			$balance = (int) \Wbcom\Credits\Credits::get_balance( 'wb-listora', get_current_user_id() );
		}

		return array(
			'plan_id'             => $plan_id,
			'plan_name'           => $plan_name,
			'cost'                => $cost,
			'currency'            => (string) wb_listora_get_setting( 'currency', 'USD' ),
			'duration_days'       => $duration_days,
			'days_until_expiry'   => $days_until_expiry,
			'is_expired'          => $is_expired,
			'can_renew_now'       => $can_renew_now,
			'renewal_window_days' => $window_days,
			'reason'              => $reason,
			'balance'             => $balance,
			'expiry'              => $expiry_raw ? (string) $expiry_raw : '',
		);
	}

	/**
	 * GET /listings/{id}/renewal-quote — pricing + eligibility for renewal.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_renewal_quote( $request ) {
		$post_id = (int) $request->get_param( 'id' );

		$quote = $this->build_renewal_quote( $post_id );

		if ( empty( $quote ) ) {
			return new \WP_Error(
				'listora_not_found',
				__( 'Listing not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $quote, 200 );
	}

	/**
	 * POST /listings/{id}/renew — process a listing renewal.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function renew_listing( $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new \WP_Error(
				'listora_not_found',
				__( 'Listing not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		if ( ! wb_listora_feature_enabled( 'renewal' ) ) {
			return new \WP_Error(
				'listora_renewal_disabled',
				__( 'Renewal is disabled on this site.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		$user_id = get_current_user_id();
		$quote   = $this->build_renewal_quote( $post_id );

		// Inside renewal window or already expired? Otherwise reject.
		if ( ! $quote['can_renew_now'] ) {
			return new \WP_Error(
				'listora_renewal_too_early',
				$quote['reason'] ? $quote['reason'] : __( 'Listing not ready to renew yet.', 'wb-listora' ),
				array(
					'status'              => 400,
					'days_until_expiry'   => (int) $quote['days_until_expiry'],
					'renewal_window_days' => (int) $quote['renewal_window_days'],
				)
			);
		}

		$cost          = (int) $quote['cost'];
		$plan_id       = (int) $quote['plan_id'];
		$duration_days = (int) $quote['duration_days'] > 0 ? (int) $quote['duration_days'] : 365;

		// Pre-renewal hook — Pro can enforce caps (e.g., max renewals/period).
		$context = array(
			'user_id'       => $user_id,
			'cost'          => $cost,
			'plan_id'       => $plan_id,
			'duration_days' => $duration_days,
		);

		/**
		 * Cancellable filter fired before a listing is renewed. Return a WP_Error
		 * to abort with a custom message (e.g. plan caps).
		 *
		 * @param true|\WP_Error  $check   True to proceed, WP_Error to abort.
		 * @param int             $post_id Listing post ID.
		 * @param array           $context Renewal context (user_id, cost, plan_id, duration_days).
		 */
		$check = apply_filters( 'wb_listora_before_renew_listing', true, $post_id, $context );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// ─── Hold → Commit credit charge ───
		// Renewal previously called `Credits::adjust(-cost)` directly after the
		// write-side work, which (a) skipped the ledger-visible hold step the
		// rest of the credit-flow already uses (plan activation, featured
		// upgrade, need responses) and (b) opened a race window between the
		// balance pre-check and the deduct.
		//
		// New flow: hold up front, do the renewal writes, deduct on success or
		// cancel_hold on failure. Same shape as Pricing_Plans::activate_plan_for_listing.
		$has_cost    = ( $cost > 0 );
		$hold_placed = false;
		$has_sdk     = class_exists( '\Wbcom\Credits\Credits' );

		if ( $has_cost ) {
			if ( ! $has_sdk ) {
				return new \WP_Error(
					'listora_credits_unavailable',
					__( 'Renewal requires the credit system, which is not active.', 'wb-listora' ),
					array( 'status' => 500 )
				);
			}

			$balance = (int) \Wbcom\Credits\Credits::get_balance( 'wb-listora', $user_id );
			if ( $balance < $cost ) {
				$purchase_url = (string) \Wbcom\Credits\Credits::get_purchase_url( 'wb-listora' );
				return new \WP_Error(
					'insufficient_credits',
					sprintf(
						/* translators: 1: required credits, 2: current balance */
						__( 'You need %1$d credits to renew this listing (you have %2$d).', 'wb-listora' ),
						$cost,
						$balance
					),
					array(
						'status'       => 402,
						'required'     => $cost,
						'balance'      => $balance,
						'purchase_url' => $purchase_url,
					)
				);
			}

			$renewal_note = sprintf(
				/* translators: 1: listing title, 2: listing ID */
				__( 'Renewal: %1$s (#%2$d)', 'wb-listora' ),
				$post->post_title,
				$post_id
			);

			$hold_result = \Wbcom\Credits\Credits::hold( 'wb-listora', (int) $user_id, (int) $cost, (int) $post_id, $renewal_note );
			if ( false === $hold_result ) {
				return new \WP_Error(
					'listora_hold_failed',
					__( 'Could not reserve credits for this renewal. Please try again.', 'wb-listora' ),
					array( 'status' => 500 )
				);
			}
			$hold_placed = true;
		}

		// Wrap every write that comes after the hold in try/catch so a single
		// failed `wp_update_post` doesn't strand the hold on the vendor's
		// balance — the catch path runs `cancel_hold` so the ledger always
		// shows either `hold + deduct` or `hold + cancel_hold`, never an
		// orphan reservation.
		$credits_deducted = 0;
		try {
			// Calculate new expiry from now + duration.
			$now_ts        = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			$new_expiry_ts = $now_ts + ( $duration_days * DAY_IN_SECONDS );
			$new_expiry    = gmdate( 'Y-m-d H:i:s', $new_expiry_ts - ( (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) );
			// Use site time so the value matches what the cron compares against.
			$new_expiry_local = gmdate( 'Y-m-d H:i:s', $new_expiry_ts );

			/** This filter is documented in includes/workflow/class-status-manager.php */
			$new_expiry_local = (string) apply_filters( 'wb_listora_listing_expiration_date', $new_expiry_local, $post_id, array( 'context' => 'renew' ) );

			update_post_meta( $post_id, '_listora_expiration_date', $new_expiry_local );
			update_post_meta( $post_id, '_listora_renewed_at', current_time( 'mysql' ) );

			$count = (int) get_post_meta( $post_id, '_listora_renewal_count', true );
			update_post_meta( $post_id, '_listora_renewal_count', $count + 1 );

			delete_post_meta( $post_id, '_listora_expiry_reminded_7d' );
			delete_post_meta( $post_id, '_listora_expiry_reminded_1d' );

			if ( 'listora_expired' === $post->post_status ) {
				$update_result = wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'publish',
					),
					true
				);
				if ( is_wp_error( $update_result ) ) {
					throw new \RuntimeException( 'wp_update_post failed: ' . $update_result->get_error_message() );
				}
			}

			if ( $hold_placed ) {
				$deduct_ok = \Wbcom\Credits\Credits::deduct( 'wb-listora', (int) $user_id, (int) $cost, (int) $post_id, $renewal_note );
				if ( ! $deduct_ok ) {
					throw new \RuntimeException( 'Credits::deduct() failed to consume the hold for renewal #' . (int) $post_id );
				}
				$credits_deducted = (int) $cost;
				$hold_placed      = false;
			}
		} catch ( \Throwable $e ) {
			if ( $hold_placed && $has_sdk ) {
				\Wbcom\Credits\Credits::cancel_hold( 'wb-listora', (int) $user_id, (int) $post_id );
			}
			return new \WP_Error(
				'listora_renewal_failed',
				/* translators: %s: underlying error */
				sprintf( __( 'Renewal failed: %s', 'wb-listora' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}

		// Dashboard stats cache invalidates via the dashboard group
		// last-changed incrementor (Cache::bump_listings on the
		// `wb_listora_after_renew_listing` listener path) — no manual
		// key delete required.

		/**
		 * Fires after a listing is renewed.
		 *
		 * @param int   $post_id Listing post ID.
		 * @param array $context Renewal context.
		 */
		do_action(
			'wb_listora_after_renew_listing',
			$post_id,
			array_merge(
				$context,
				array(
					'new_expiry'       => $new_expiry_local,
					'credits_deducted' => $credits_deducted,
					'renewal_count'    => $count + 1,
				)
			)
		);

		// Trigger renewal notification.
		do_action( 'wb_listora_listing_renewed', $post_id );

		$balance_after = 0;
		if ( class_exists( '\Wbcom\Credits\Credits' ) ) {
			$balance_after = (int) \Wbcom\Credits\Credits::get_balance( 'wb-listora', $user_id );
		}

		return new WP_REST_Response(
			array(
				'renewed'          => true,
				'new_expiry'       => $new_expiry_local,
				'new_expiry_human' => wp_date( get_option( 'date_format' ), $new_expiry_ts ),
				'credits_deducted' => $credits_deducted,
				'balance'          => $balance_after,
				'renewal_count'    => $count + 1,
				'status'           => 'publish',
				'message'          => sprintf(
					/* translators: %s: new expiration date */
					__( 'Listing renewed until %s.', 'wb-listora' ),
					wp_date( get_option( 'date_format' ), $new_expiry_ts )
				),
			),
			200
		);
	}

	/**
	 * Parse the `image_sizes` REST parameter into a validated list.
	 *
	 * Apps pass `?image_sizes=thumbnail,medium` to avoid paying for large /
	 * full URLs they will not render. Empty or missing returns the full set
	 * (thumbnail, medium, large, full) so existing clients see no change.
	 *
	 * @param mixed $raw Raw parameter value.
	 * @return string[]
	 */
	private function parse_image_sizes( $raw ): array {
		$all = array( 'thumbnail', 'medium', 'large', 'full' );

		if ( empty( $raw ) ) {
			return $all;
		}

		$requested = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$requested = array_values(
			array_intersect(
				$all,
				array_map( 'strtolower', array_map( 'trim', $requested ) )
			)
		);

		return empty( $requested ) ? $all : $requested;
	}
}
