<?php
/**
 * REST Dashboard Controller — user dashboard data endpoints.
 *
 * @package WBListora\REST
 */

namespace WBListora\REST;

defined( 'ABSPATH' ) || exit;

use WBListora\Core\Cache;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Provides dashboard data — user's listings, reviews, stats, profile.
 */
class Dashboard_Controller extends WP_REST_Controller {

	protected $namespace = WB_LISTORA_REST_NAMESPACE;
	protected $rest_base = 'dashboard';

	/**
	 * Notification event types that we recognise.
	 *
	 * @var string[]
	 */
	private $notification_events = array(
		'listing_submitted',
		'listing_approved',
		'listing_rejected',
		'listing_expired',
		'listing_expiring_soon',
		'review_received',
		'review_reply',
		'review_helpful',
		'claim_submitted',
		'claim_approved',
		'claim_rejected',
	);

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET /dashboard/stats
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
				),
			)
		);

		// GET /dashboard/listings
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/listings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_listings' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
					'args'                => array(
						'status'   => array(
							'type'    => 'string',
							'default' => '',
						),
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
						// OPTIONAL cursor pagination — pass the last-seen post
						// ID (or `next_cursor` from the previous response) to
						// switch from O(N) OFFSET to O(1) keyset pagination.
						// Omit `cursor` to keep the existing OFFSET behaviour.
						// See SKILL.md Part 2.3 / scale-and-cache.md §2.2.
						'cursor'   => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Cursor pagination — last-seen listing ID. When present, results are returned with id < cursor ORDER BY id DESC.',
						),
					),
				),
			)
		);

		// GET /dashboard/reviews
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reviews',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_reviews' ),
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
						// OPTIONAL cursor pagination — last-seen review ID for
						// the written list. Switches to keyset pagination on
						// the `written` query (received-list still uses
						// OFFSET, gated by total). See scale-and-cache.md §2.2.
						'cursor'   => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Cursor pagination — last-seen review ID for the written list.',
						),
					),
				),
			)
		);

		// GET /dashboard/claims — user's own claims with status.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/claims',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_my_claims' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
				),
			)
		);

		// GET + PUT /dashboard/profile
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/profile',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_profile' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_profile' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
					'args'                => array(
						'display_name' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description'  => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		// GET /dashboard/notifications
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/notifications',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_notifications' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
					'args'                => array(
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 20,
							'minimum'           => 1,
							'maximum'           => 50,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// PUT /dashboard/notifications/read
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/notifications/read',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'mark_notifications_read' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
				),
			)
		);
	}

	/**
	 * Dashboard stats summary.
	 *
	 * Uses the WP-core `wp_cache_set_last_changed( $group )` incrementor
	 * pattern. When a listing/review/favorite write fires, Cache::bump()
	 * flips the dashboard group's last-changed value and the next read
	 * builds a new cache key — orphaned keys evict via the object-cache
	 * backend's normal eviction. No transient LIKE-DELETE, no manual
	 * key tracking. See Cache::on_settings_updated() and Cache::init().
	 */
	public function get_stats( $request ) {
		global $wpdb;
		$prefix  = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$user_id = get_current_user_id();

		$cache_key = Cache::key( Cache::GROUP_DASHBOARD, "stats:user:{$user_id}" );
		$cached    = wp_cache_get( $cache_key, Cache::GROUP_DASHBOARD );

		if ( false !== $cached ) {
			return new WP_REST_Response( $cached, 200 );
		}

		// Listing counts by status.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$listing_counts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_status, COUNT(*) as cnt FROM {$wpdb->posts}
			WHERE post_type = 'listora_listing' AND post_author = %d
			GROUP BY post_status",
				$user_id
			),
			OBJECT_K
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$review_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}reviews WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$favorite_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}favorites WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);

		$data = array(
			'listings'  => array(
				'published' => (int) ( $listing_counts['publish']->cnt ?? 0 ),
				'pending'   => (int) ( $listing_counts['pending']->cnt ?? 0 ),
				'expired'   => (int) ( $listing_counts['listora_expired']->cnt ?? 0 ),
				'draft'     => (int) ( $listing_counts['draft']->cnt ?? 0 ),
			),
			'reviews'   => $review_count,
			'favorites' => $favorite_count,
		);

		wp_cache_set( $cache_key, $data, Cache::GROUP_DASHBOARD );

		/**
		 * Filters the dashboard stats REST response data.
		 *
		 * @param array           $data    Dashboard stats data.
		 * @param int             $user_id Current user ID.
		 * @param WP_REST_Request $request REST request.
		 */
		$data = apply_filters( 'wb_listora_rest_prepare_dashboard_stats', $data, $user_id, $request );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * User's listings.
	 *
	 * Two pagination modes:
	 *
	 * 1. OFFSET (default, back-compat): pass `page` + `per_page` and the
	 *    response includes `total`, `pages`, `has_more`.
	 * 2. CURSOR (opt-in): pass `cursor` (last-seen post ID). The response
	 *    additionally includes `cursor` (echoed input) and `next_cursor`
	 *    (the last ID in this slice — pass it back to fetch the next page
	 *    in O(1) regardless of page depth).
	 *
	 * Both modes return the standard list envelope so a client that
	 * doesn't understand cursors still renders correctly.
	 */
	public function get_listings( $request ) {
		$user_id  = get_current_user_id();
		$status   = (string) $request->get_param( 'status' );
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );
		$has_cursor_param = null !== $request->get_param( 'cursor' ) && '' !== $request->get_param( 'cursor' );
		$cursor   = $has_cursor_param ? max( 0, (int) $request->get_param( 'cursor' ) ) : null;

		$post_status = $status
			? array( $status )
			: array( 'publish', 'pending', 'draft', 'listora_expired', 'listora_rejected', 'listora_deactivated', 'pending_verification' );

		// `total` is the same in both modes — UI uses it to render counts.
		$total = $this->count_user_listings( $user_id, $post_status );

		if ( null !== $cursor ) {
			// Cursor mode — keyset pagination via WHERE id < ?.
			$post_ids = $this->fetch_listing_ids_after_cursor( $user_id, $post_status, $cursor, $per_page );
			$posts    = empty( $post_ids ) ? array() : get_posts(
				array(
					'post_type'      => 'listora_listing',
					'post__in'       => $post_ids,
					'orderby'        => 'post__in',
					'posts_per_page' => count( $post_ids ),
					'post_status'    => $post_status,
				)
			);

			$listings    = array_map( array( $this, 'shape_dashboard_listing' ), $posts );
			$next_cursor = ! empty( $post_ids ) ? (int) end( $post_ids ) : null;
			// `has_more` here is "did we return a full page" — if a partial
			// page came back, there's nothing left below the cursor.
			$has_more = count( $post_ids ) >= $per_page;

			return new WP_REST_Response(
				array(
					'listings'    => $listings,
					'total'       => (int) $total,
					'cursor'      => $cursor,
					'next_cursor' => $has_more ? $next_cursor : null,
					'has_more'    => $has_more,
					// Page-mode keys kept for envelope compatibility.
					'pages'       => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
				),
				200
			);
		}

		// OFFSET mode — unchanged behaviour for existing clients.
		$args = array(
			'post_type'      => 'listora_listing',
			'author'         => $user_id,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_status'    => $post_status,
		);

		$query    = new \WP_Query( $args );
		$listings = array_map( array( $this, 'shape_dashboard_listing' ), $query->posts );

		$offset      = ( $page - 1 ) * $per_page;
		$has_more    = ( $offset + count( $query->posts ) ) < $query->found_posts;
		$next_cursor = ! empty( $query->posts ) && $has_more ? (int) end( $query->posts )->ID : null;

		return new WP_REST_Response(
			array(
				'listings'    => $listings,
				'total'       => (int) $query->found_posts,
				'pages'       => (int) $query->max_num_pages,
				'has_more'    => $has_more,
				// Surface cursor + next_cursor in offset responses too so a
				// client can switch modes mid-session without needing a
				// separate "first page" call.
				'cursor'      => null,
				'next_cursor' => $next_cursor,
			),
			200
		);
	}

	/**
	 * Count a user's listings across the given post statuses.
	 *
	 * @param int      $user_id    User ID.
	 * @param string[] $post_status Allowed post statuses.
	 * @return int
	 */
	private function count_user_listings( $user_id, array $post_status ): int {
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $post_status ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				WHERE post_type = 'listora_listing'
				AND post_author = %d
				AND post_status IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...array_merge( array( $user_id ), $post_status )
			)
		);
	}

	/**
	 * Cursor-mode SELECT — fetch listing IDs strictly below the cursor,
	 * ordered by id DESC. Returns IDs only so the caller can run a single
	 * `get_posts( post__in )` and preserve cache priming + `the_post`
	 * filters from WP core.
	 *
	 * Cursor of 0 is treated as "first page" by using PHP_INT_MAX.
	 *
	 * @param int      $user_id    User ID.
	 * @param string[] $post_status Allowed post statuses.
	 * @param int      $cursor      Last-seen ID (0 = first page).
	 * @param int      $per_page    Page size.
	 * @return int[]
	 */
	private function fetch_listing_ids_after_cursor( $user_id, array $post_status, $cursor, $per_page ): array {
		global $wpdb;
		$cursor       = $cursor > 0 ? (int) $cursor : PHP_INT_MAX;
		$placeholders = implode( ',', array_fill( 0, count( $post_status ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'listora_listing'
				AND post_author = %d
				AND post_status IN ({$placeholders})
				AND ID < %d
				ORDER BY ID DESC
				LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...array_merge( array( $user_id ), $post_status, array( $cursor, $per_page ) )
			)
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * Shape a listing row for the dashboard list response.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	private function shape_dashboard_listing( $post ): array {
		$type = \WBListora\Core\Listing_Type_Registry::instance()->get_for_post( $post->ID );
		return array(
			'id'        => (int) $post->ID,
			'title'     => $post->post_title,
			'status'    => $post->post_status,
			'type'      => $type ? $type->get_name() : '',
			'url'       => get_permalink( $post->ID ),
			'edit_url'  => $this->get_submission_page_url( $post->ID ),
			'date'      => $post->post_date,
			'expiry'    => get_post_meta( $post->ID, '_listora_expiration_date', true ),
			'thumbnail' => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ),
		);
	}

	/**
	 * User's own claim submissions with current status.
	 *
	 * Returns the standard list envelope `{claims, total, pages, has_more}`
	 * so app clients can rely on the same pagination shape used everywhere
	 * else in the API.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_my_claims( $request ) {
		global $wpdb;
		$prefix  = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$user_id = get_current_user_id();

		$per_page = (int) $request->get_param( 'per_page' );
		$per_page = $per_page > 0 ? min( $per_page, 100 ) : 20;
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$offset   = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}claims WHERE user_id = %d",
				$user_id
			)
		);

		$claims = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.id, c.listing_id, c.status, c.proof_text, c.admin_notes, c.created_at, c.updated_at,
					p.post_title AS listing_title
				FROM {$prefix}claims c
				LEFT JOIN {$wpdb->posts} p ON c.listing_id = p.ID
				WHERE c.user_id = %d
				ORDER BY c.created_at DESC, c.id DESC
				LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$data = array();
		foreach ( $claims as $claim ) {
			$data[] = array(
				'id'            => (int) $claim['id'],
				'listing_id'    => (int) $claim['listing_id'],
				'listing_title' => $claim['listing_title'] ?? '',
				'listing_url'   => get_permalink( (int) $claim['listing_id'] ),
				'status'        => $claim['status'],
				'proof_text'    => $claim['proof_text'],
				'admin_notes'   => $claim['admin_notes'] ?? '',
				'created_at'    => $claim['created_at'],
				'updated_at'    => $claim['updated_at'],
			);
		}

		return new \WP_REST_Response(
			array(
				'claims'   => $data,
				'total'    => $total,
				'pages'    => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
				'has_more' => ( $offset + count( $data ) ) < $total,
				'page'     => $page,
				'per_page' => $per_page,
			),
			200
		);
	}

	/**
	 * User's reviews (written + received).
	 *
	 * Pagination modes:
	 * - OFFSET (default): pass `page` + `per_page`. Both `written` and
	 *   `received` lists use the same offset (page 2 fetches the next
	 *   `per_page` of each).
	 * - CURSOR (opt-in): pass `cursor` (last-seen review ID). The `written`
	 *   query switches to `WHERE r.id < ? ORDER BY r.id DESC` keyset
	 *   pagination. The `received` list still uses OFFSET (it joins through
	 *   posts.post_author so the keyset key would be ambiguous — apps that
	 *   need a deeply-paginated received list should request it separately).
	 */
	public function get_reviews( $request ) {
		global $wpdb;
		$prefix  = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$user_id = get_current_user_id();

		$per_page = (int) $request->get_param( 'per_page' );
		$per_page = $per_page > 0 ? min( $per_page, 100 ) : 20;
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$offset   = ( $page - 1 ) * $per_page;
		$has_cursor_param = null !== $request->get_param( 'cursor' ) && '' !== $request->get_param( 'cursor' );
		$cursor   = $has_cursor_param ? max( 0, (int) $request->get_param( 'cursor' ) ) : null;

		// Cache via the dashboard group's last-changed incrementor — bumps
		// on review write hooks (Cache::bump_reviews) so the next read
		// re-computes. Cursor included in the base so cursor + offset
		// responses cache independently.
		$cache_disc = null === $cursor ? "p{$page}:n{$per_page}" : "c{$cursor}:n{$per_page}";
		$base_key   = "reviews:user:{$user_id}:{$cache_disc}";
		$cache_key  = Cache::key( Cache::GROUP_DASHBOARD, $base_key );
		$cached     = wp_cache_get( $cache_key, Cache::GROUP_DASHBOARD );

		if ( false !== $cached ) {
			return new WP_REST_Response( $cached, 200 );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( null !== $cursor ) {
			// Cursor mode — keyset pagination on the written list.
			$cursor_id = $cursor > 0 ? (int) $cursor : PHP_INT_MAX;
			$written   = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.*, si.title as listing_title FROM {$prefix}reviews r
				LEFT JOIN {$prefix}search_index si ON r.listing_id = si.listing_id
				WHERE r.user_id = %d AND r.id < %d
				ORDER BY r.id DESC LIMIT %d",
					$user_id,
					$cursor_id,
					$per_page
				),
				ARRAY_A
			);
		} else {
			$written = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.*, si.title as listing_title FROM {$prefix}reviews r
				LEFT JOIN {$prefix}search_index si ON r.listing_id = si.listing_id
				WHERE r.user_id = %d ORDER BY r.created_at DESC, r.id DESC LIMIT %d OFFSET %d",
					$user_id,
					$per_page,
					$offset
				),
				ARRAY_A
			);
		}

		$received = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, si.title as listing_title FROM {$prefix}reviews r
			INNER JOIN {$wpdb->posts} p ON r.listing_id = p.ID
			LEFT JOIN {$prefix}search_index si ON r.listing_id = si.listing_id
			WHERE p.post_author = %d AND r.user_id != %d AND r.status = 'approved'
			ORDER BY r.created_at DESC, r.id DESC LIMIT %d OFFSET %d",
				$user_id,
				$user_id,
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Totals for pagination (unchanged regardless of page/per_page).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$written_total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}reviews WHERE user_id = %d",
				$user_id
			)
		);

		$received_total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}reviews r
				INNER JOIN {$wpdb->posts} p ON r.listing_id = p.ID
				WHERE p.post_author = %d AND r.user_id != %d AND r.status = 'approved'",
				$user_id,
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$written_pages  = $per_page > 0 ? (int) ceil( $written_total / $per_page ) : 0;
		$received_pages = $per_page > 0 ? (int) ceil( $received_total / $per_page ) : 0;

		$data = array(
			'written'           => $written,
			'received'          => $received,
			'written_total'     => $written_total,
			'received_total'    => $received_total,
			'written_pages'     => $written_pages,
			'received_pages'    => $received_pages,
			'written_has_more'  => ( $offset + count( $written ) ) < $written_total,
			'received_has_more' => ( $offset + count( $received ) ) < $received_total,
			'page'              => $page,
			'per_page'          => $per_page,
		);

		wp_cache_set( $cache_key, $data, Cache::GROUP_DASHBOARD );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get current user profile.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_profile( $request ) {
		global $wpdb;
		$prefix  = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$user_id = get_current_user_id();
		$user    = wp_get_current_user();

		// Listing count (published).
		$listing_count = (int) count_user_posts( $user_id, 'listora_listing', true );

		// Review count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$review_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}reviews WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);

		// Favorite count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$favorite_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}favorites WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);

		// Notification preferences.
		$notification_prefs = array();
		foreach ( $this->notification_events as $event ) {
			$meta_value                   = get_user_meta( $user_id, '_listora_notify_' . $event, true );
			$notification_prefs[ $event ] = '' === $meta_value ? true : (bool) $meta_value;
		}

		$data = array(
			'id'                       => $user_id,
			'display_name'             => $user->display_name,
			'email'                    => $user->user_email,
			'first_name'               => $user->first_name,
			'last_name'                => $user->last_name,
			'avatar_url'               => get_avatar_url( $user_id, array( 'size' => 96 ) ),
			'bio'                      => $user->description,
			'notification_preferences' => $notification_prefs,
			'listing_count'            => $listing_count,
			'review_count'             => $review_count,
			'favorite_count'           => $favorite_count,
			'member_since'             => $user->user_registered,
		);

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Update user profile.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_profile( $request ) {
		$user_id = get_current_user_id();
		$data    = array( 'ID' => $user_id );

		if ( $request->has_param( 'display_name' ) ) {
			$data['display_name'] = $request->get_param( 'display_name' );
		}

		if ( $request->has_param( 'description' ) ) {
			$data['description'] = $request->get_param( 'description' );
		}

		$result = wp_update_user( $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update notification preferences — stored as individual meta keys.
		$prefs = $request->get_param( 'notification_prefs' );
		if ( is_array( $prefs ) ) {
			foreach ( $this->notification_events as $event ) {
				$value = ! empty( $prefs[ $event ] ) ? '1' : '0';
				update_user_meta( $user_id, '_listora_notify_' . $event, $value );
			}
		}

		return new WP_REST_Response( array( 'updated' => true ), 200 );
	}

	/**
	 * Get notification feed for current user.
	 *
	 * Builds notifications from recent reviews on user's listings and recent claim updates.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_notifications( $request ) {
		global $wpdb;
		$prefix   = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$user_id  = get_current_user_id();
		$per_page = $request->get_param( 'per_page' );

		// Last-read timestamp for determining unread status.
		$read_at = get_user_meta( $user_id, '_listora_notifications_read_at', true );
		if ( ! $read_at ) {
			$read_at = '1970-01-01 00:00:00';
		}

		$notifications = array();

		// 1. Recent reviews on user's listings.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.id, r.listing_id, r.overall_rating, r.user_id as reviewer_id, r.created_at, si.title as listing_title
				FROM {$prefix}reviews r
				JOIN {$wpdb->posts} p ON r.listing_id = p.ID
				LEFT JOIN {$prefix}search_index si ON r.listing_id = si.listing_id
				WHERE p.post_author = %d AND r.user_id != %d AND r.status = 'approved'
				ORDER BY r.created_at DESC LIMIT %d",
				$user_id,
				$user_id,
				$per_page
			),
			ARRAY_A
		);

		foreach ( $reviews as $review ) {
			$listing_title = $review['listing_title'] ?: __( 'your listing', 'wb-listora' );

			$notifications[] = array(
				'type'       => 'review_received',
				'message'    => sprintf(
					/* translators: 1: star rating, 2: listing title */
					__( 'New %1$d-star review on %2$s', 'wb-listora' ),
					(int) $review['overall_rating'],
					$listing_title
				),
				'listing_id' => (int) $review['listing_id'],
				'date'       => $review['created_at'],
				'read'       => $review['created_at'] <= $read_at,
			);
		}

		// 2. Recent claim updates for the user.
		$claims = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.id, c.listing_id, c.status, c.created_at, c.updated_at, si.title as listing_title
				FROM {$prefix}claims c
				LEFT JOIN {$prefix}search_index si ON c.listing_id = si.listing_id
				WHERE c.user_id = %d
				ORDER BY c.updated_at DESC LIMIT %d",
				$user_id,
				$per_page
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( $claims as $claim ) {
			$listing_title = $claim['listing_title'] ?: __( 'a listing', 'wb-listora' );
			$event_date    = $claim['updated_at'];

			switch ( $claim['status'] ) {
				case 'approved':
					$notifications[] = array(
						'type'       => 'claim_approved',
						/* translators: %s: listing title */
						'message'    => sprintf( __( 'Your claim for "%s" was approved', 'wb-listora' ), $listing_title ),
						'listing_id' => (int) $claim['listing_id'],
						'date'       => $event_date,
						'read'       => $event_date <= $read_at,
					);
					break;

				case 'rejected':
					$notifications[] = array(
						'type'       => 'claim_rejected',
						/* translators: %s: listing title */
						'message'    => sprintf( __( 'Your claim for "%s" was rejected', 'wb-listora' ), $listing_title ),
						'listing_id' => (int) $claim['listing_id'],
						'date'       => $event_date,
						'read'       => $event_date <= $read_at,
					);
					break;

				case 'pending':
					$notifications[] = array(
						'type'       => 'claim_submitted',
						/* translators: %s: listing title */
						'message'    => sprintf( __( 'Your claim for "%s" is pending review', 'wb-listora' ), $listing_title ),
						'listing_id' => (int) $claim['listing_id'],
						'date'       => $claim['created_at'],
						'read'       => $claim['created_at'] <= $read_at,
					);
					break;
			}
		}

		// 3. Listing status changes — check for recently approved/rejected/expired listings.
		$status_posts = get_posts(
			array(
				'post_type'      => 'listora_listing',
				'author'         => $user_id,
				'post_status'    => array( 'publish', 'listora_rejected', 'listora_expired' ),
				'posts_per_page' => $per_page,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		foreach ( $status_posts as $sp ) {
			$event_date = $sp->post_modified;

			switch ( $sp->post_status ) {
				case 'publish':
					$notifications[] = array(
						'type'       => 'listing_approved',
						/* translators: %s: listing title */
						'message'    => sprintf( __( 'Your listing "%s" was approved', 'wb-listora' ), $sp->post_title ),
						'listing_id' => $sp->ID,
						'date'       => $event_date,
						'read'       => $event_date <= $read_at,
					);
					break;

				case 'listora_rejected':
					$notifications[] = array(
						'type'       => 'listing_rejected',
						/* translators: %s: listing title */
						'message'    => sprintf( __( 'Your listing "%s" was rejected', 'wb-listora' ), $sp->post_title ),
						'listing_id' => $sp->ID,
						'date'       => $event_date,
						'read'       => $event_date <= $read_at,
					);
					break;

				case 'listora_expired':
					$notifications[] = array(
						'type'       => 'listing_expired',
						/* translators: %s: listing title */
						'message'    => sprintf( __( 'Your listing "%s" has expired', 'wb-listora' ), $sp->post_title ),
						'listing_id' => $sp->ID,
						'date'       => $event_date,
						'read'       => $event_date <= $read_at,
					);
					break;
			}
		}

		// Sort all notifications by date descending.
		usort(
			$notifications,
			function ( $a, $b ) {
				return strtotime( $b['date'] ) - strtotime( $a['date'] );
			}
		);

		// Limit to requested per_page.
		$notifications = array_slice( $notifications, 0, $per_page );

		// Unread count.
		$unread_count = count(
			array_filter(
				$notifications,
				function ( $n ) {
					return ! $n['read'];
				}
			)
		);

		return new WP_REST_Response(
			array(
				'notifications' => $notifications,
				'unread_count'  => $unread_count,
			),
			200
		);
	}

	/**
	 * Mark all notifications as read by updating the last-read timestamp.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function mark_notifications_read( $request ) {
		$user_id = get_current_user_id();

		update_user_meta( $user_id, '_listora_notifications_read_at', current_time( 'mysql', true ) );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Notifications marked as read.', 'wb-listora' ),
			),
			200
		);
	}

	/**
	 * Get the submission page URL for editing a listing.
	 *
	 * @param int $post_id Post ID to edit.
	 * @return string
	 */
	private function get_submission_page_url( $post_id ) {
		return add_query_arg( 'edit', $post_id, wb_listora_get_submit_url() );
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
}
