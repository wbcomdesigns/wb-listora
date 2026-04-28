<?php
/**
 * REST Reviews Controller.
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
 * Handles review CRUD, helpful votes, owner replies, and reporting.
 */
class Reviews_Controller extends WP_REST_Controller {

	protected $namespace = WB_LISTORA_REST_NAMESPACE;
	protected $rest_base = 'reviews';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET /listings/{id}/reviews
		register_rest_route(
			$this->namespace,
			'/listings/(?P<listing_id>[\d]+)/reviews',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_listing_reviews' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'listing_id' => array(
							'type'     => 'integer',
							'required' => true,
						),
						'page'       => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page'   => array(
							'type'    => 'integer',
							'default' => 10,
							'minimum' => 1,
							'maximum' => 50,
						),
						'sort'       => array(
							'type'    => 'string',
							'default' => 'newest',
							'enum'    => array( 'newest', 'oldest', 'highest', 'lowest', 'helpful' ),
						),
						// OPTIONAL cursor pagination — pass the last-seen review
						// ID. Switches the listing's review query from O(N)
						// OFFSET to O(1) keyset pagination. Only available when
						// `sort` is omitted or `newest` (the default), since
						// other sorts use a non-monotonic key. See
						// scale-and-cache.md §2.2.
						'cursor'     => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Cursor pagination — last-seen review ID (only valid with sort=newest).',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_review' ),
					'permission_callback' => array( $this, 'create_review_permissions' ),
					'args'                => array(
						'listing_id'       => array(
							'type'     => 'integer',
							'required' => true,
						),
						'overall_rating'   => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 1,
							'maximum'  => 5,
						),
						'title'            => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'content'          => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'criteria_ratings' => array(
							'type'        => 'object',
							'required'    => false,
							'description' => 'Per-criterion star ratings keyed by criterion slug (values 1-5).',
							'properties'  => array(),
						),
					),
				),
			)
		);

		// PUT/DELETE /reviews/{id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_review' ),
					'permission_callback' => array( $this, 'update_review_permissions' ),
					'args'                => array(
						'id'             => array(
							'type'     => 'integer',
							'required' => true,
						),
						'overall_rating' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 5,
						),
						'title'          => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'content'        => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_review' ),
					'permission_callback' => array( $this, 'delete_review_permissions' ),
				),
			)
		);

		// POST /reviews/{id}/helpful
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/helpful',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'vote_helpful' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
				),
			)
		);

		// POST /reviews/{id}/reply
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/reply',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'owner_reply' ),
					'permission_callback' => array( $this, 'owner_reply_permissions' ),
					'args'                => array(
						'content' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		// POST /reviews/{id}/report
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/report',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'report_review' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
					'args'                => array(
						'reason'  => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'details' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Get reviews for a listing.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_listing_reviews( $request ) {
		global $wpdb;
		$prefix     = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$listing_id = $request->get_param( 'listing_id' );
		$page       = $request->get_param( 'page' );
		$per_page   = $request->get_param( 'per_page' );
		$sort       = $request->get_param( 'sort' );
		$has_cursor_param = null !== $request->get_param( 'cursor' ) && '' !== $request->get_param( 'cursor' );
		$cursor     = $has_cursor_param ? max( 0, (int) $request->get_param( 'cursor' ) ) : null;

		$sort_map = array(
			'oldest'  => 'r.created_at ASC',
			'highest' => 'r.overall_rating DESC, r.created_at DESC',
			'lowest'  => 'r.overall_rating ASC, r.created_at DESC',
			'helpful' => 'r.helpful_count DESC, r.created_at DESC',
		);
		$order_by = isset( $sort_map[ $sort ] ) ? $sort_map[ $sort ] : 'r.created_at DESC';

		// Cursor pagination is only valid when the order is monotonically
		// decreasing by ID (i.e. default `newest`). Other sort modes have a
		// non-monotonic key so the cursor would skip rows. Silently fall
		// back to OFFSET in those cases — clients can keep sending cursor
		// without breakage.
		$cursor_active = ( null !== $cursor ) && ( 'newest' === $sort || empty( $sort ) );
		$offset        = ( $page - 1 ) * $per_page;

		// Get total count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved'",
				$listing_id
			)
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $prefix is safe table prefix, $order_by is from whitelist $sort_map.
		if ( $cursor_active ) {
			$cursor_id = $cursor > 0 ? (int) $cursor : PHP_INT_MAX;
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.* FROM {$prefix}reviews r
				WHERE r.listing_id = %d AND r.status = 'approved' AND r.id < %d
				ORDER BY r.id DESC LIMIT %d",
					$listing_id,
					$cursor_id,
					$per_page
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.* FROM {$prefix}reviews r
				WHERE r.listing_id = %d AND r.status = 'approved'
				ORDER BY {$order_by} LIMIT %d OFFSET %d",
					$listing_id,
					$per_page,
					$offset
				),
				ARRAY_A
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Get rating summary (cached per listing).
		$stats_cache_key = 'listora_review_stats_' . $listing_id;
		$summary         = wp_cache_get( $stats_cache_key, 'listora' );

		if ( false === $summary ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$summary = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
					AVG(overall_rating) as avg_rating,
					COUNT(*) as total_reviews,
					SUM(CASE WHEN overall_rating = 5 THEN 1 ELSE 0 END) as star_5,
					SUM(CASE WHEN overall_rating = 4 THEN 1 ELSE 0 END) as star_4,
					SUM(CASE WHEN overall_rating = 3 THEN 1 ELSE 0 END) as star_3,
					SUM(CASE WHEN overall_rating = 2 THEN 1 ELSE 0 END) as star_2,
					SUM(CASE WHEN overall_rating = 1 THEN 1 ELSE 0 END) as star_1
				FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$listing_id
				),
				ARRAY_A
			);

			wp_cache_set( $stats_cache_key, $summary, 'listora', HOUR_IN_SECONDS );
		}

		// Batch-load all review authors in a single query (avoids N+1 get_user_by calls).
		$user_ids  = array_unique( array_filter( array_column( $rows, 'user_id' ) ) );
		$users_map = array();
		if ( ! empty( $user_ids ) ) {
			$users = get_users(
				array(
					'include' => array_map( 'intval', $user_ids ),
					'fields'  => array( 'ID', 'display_name', 'user_email' ),
				)
			);
			foreach ( $users as $u ) {
				$users_map[ (int) $u->ID ] = $u;
			}
		}

		// Format reviews.
		$reviews = array_map(
			function ( $row ) use ( $users_map, $request ) {
				$user        = $users_map[ (int) $row['user_id'] ] ?? null;
				$review_data = array(
					'id'             => (int) $row['id'],
					'listing_id'     => (int) $row['listing_id'],
					'user_id'        => (int) $row['user_id'],
					'user_name'      => $user ? $user->display_name : __( 'Anonymous', 'wb-listora' ),
					'user_avatar'    => $user ? get_avatar_url( $row['user_id'], array( 'size' => 48 ) ) : '',
					'overall_rating' => (int) $row['overall_rating'],
					'title'          => $row['title'],
					'content'        => $row['content'],
					'helpful_count'  => (int) $row['helpful_count'],
					'owner_reply'    => $row['owner_reply'] ?: null,
					'owner_reply_at' => $row['owner_reply_at'] ?: null,
					'created_at'     => $row['created_at'],
				);

				/**
				 * Filters a single review in the REST response list.
				 *
				 * @param array           $review_data Review data.
				 * @param int             $review_id   Review ID.
				 * @param WP_REST_Request $request     REST request.
				 */
				return apply_filters( 'wb_listora_rest_prepare_review', $review_data, (int) $row['id'], $request );
			},
			$rows
		);

		// In cursor mode `has_more` is "did we return a full page". In
		// offset mode it's the standard ($offset + count) < $total formula.
		if ( $cursor_active ) {
			$has_more    = count( $rows ) >= $per_page;
			$next_cursor = ! empty( $rows ) ? (int) end( $rows )['id'] : null;
		} else {
			$has_more    = ( $offset + count( $rows ) ) < $total;
			$next_cursor = ( ! empty( $rows ) && $has_more ) ? (int) end( $rows )['id'] : null;
		}

		$response = new WP_REST_Response(
			array(
				'reviews'     => $reviews,
				'summary'     => array(
					'average'      => $summary ? round( (float) $summary['avg_rating'], 1 ) : 0,
					'total'        => $summary ? (int) $summary['total_reviews'] : 0,
					'distribution' => array(
						5 => (int) ( $summary['star_5'] ?? 0 ),
						4 => (int) ( $summary['star_4'] ?? 0 ),
						3 => (int) ( $summary['star_3'] ?? 0 ),
						2 => (int) ( $summary['star_2'] ?? 0 ),
						1 => (int) ( $summary['star_1'] ?? 0 ),
					),
				),
				'total'       => $total,
				'pages'       => (int) ceil( $total / $per_page ),
				'has_more'    => $has_more,
				// Cursor surfaced on every response so clients can switch
				// modes without a separate first-page call.
				'cursor'      => $cursor_active ? $cursor : null,
				'next_cursor' => $next_cursor,
			),
			200
		);

		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * Create a review.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_review( $request ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		// CAPTCHA verification.
		$captcha_token    = sanitize_text_field( $request->get_param( 'listora_captcha_token' ) ?? '' );
		$captcha_provider = sanitize_text_field( $request->get_param( 'listora_captcha_provider' ) ?? '' );

		$captcha_result = \WBListora\Captcha::verify( $captcha_token, $captcha_provider );
		if ( is_wp_error( $captcha_result ) ) {
			return $captcha_result;
		}

		$listing_id = $request->get_param( 'listing_id' );
		$user_id    = get_current_user_id();

		// Check listing exists.
		$post = get_post( $listing_id );
		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new WP_Error( 'listora_invalid_listing', __( 'Listing not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		// Can't review own listing.
		if ( (int) $post->post_author === $user_id ) {
			return new WP_Error( 'listora_own_listing', __( 'You cannot review your own listing.', 'wb-listora' ), array( 'status' => 403 ) );
		}

		// Check for existing review (one per user per listing).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM {$prefix}reviews WHERE listing_id = %d AND user_id = %d",
				$listing_id,
				$user_id
			)
		);

		if ( $existing ) {
			return new WP_Error( 'listora_already_reviewed', __( 'You have already reviewed this listing.', 'wb-listora' ), array( 'status' => 409 ) );
		}

		// Minimum content length.
		$content = $request->get_param( 'content' );
		if ( strlen( $content ) < 20 ) {
			return new WP_Error( 'listora_review_too_short', __( 'Review must be at least 20 characters.', 'wb-listora' ), array( 'status' => 400 ) );
		}

		/**
		 * Filters whether to allow creating a review. Return WP_Error to abort.
		 *
		 * @param bool|WP_Error   $check      True to proceed, WP_Error to abort.
		 * @param int             $listing_id Listing ID.
		 * @param WP_REST_Request $request    REST request.
		 */
		$check = apply_filters( 'wb_listora_before_create_review', true, $listing_id, $request );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Auto-approve or pending.
		$status = wb_listora_get_setting( 'moderation', 'manual' ) === 'auto_approve' ? 'approved' : 'pending';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			"{$prefix}reviews", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array(
				'listing_id'     => $listing_id,
				'user_id'        => $user_id,
				'overall_rating' => (int) $request->get_param( 'overall_rating' ),
				'title'          => $request->get_param( 'title' ),
				'content'        => $content,
				'status'         => $status,
				'ip_address'     => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
				'created_at'     => current_time( 'mysql', true ),
				'updated_at'     => current_time( 'mysql', true ),
			)
		);

		if ( false === $result ) {
			return new WP_Error( 'listora_review_failed', __( 'Failed to submit review.', 'wb-listora' ), array( 'status' => 500 ) );
		}

		$review_id = $wpdb->insert_id;

		// Invalidate review stats and dashboard caches.
		wp_cache_delete( 'listora_review_stats_' . $listing_id, 'listora' );
		wp_cache_delete( 'listora_dashboard_stats_' . $user_id, 'listora' );
		wp_cache_delete( 'listora_dashboard_reviews_' . $user_id, 'listora' );

		// Update search index rating.
		$this->update_listing_rating( $listing_id );

		// Collect criteria_ratings + review_photos from the REST request body
		// (not $_POST -- this is a JSON request).
		$criteria_ratings = $request->get_param( 'criteria_ratings' );
		if ( ! is_array( $criteria_ratings ) ) {
			$criteria_ratings = array();
		}

		$review_photos_raw = $request->get_param( 'review_photos' );
		$review_photos     = array();
		if ( is_array( $review_photos_raw ) ) {
			$review_photos = array_map( 'absint', $review_photos_raw );
		} elseif ( is_string( $review_photos_raw ) && '' !== $review_photos_raw ) {
			$review_photos = array_map( 'absint', explode( ',', $review_photos_raw ) );
		}
		$review_photos = array_values( array_filter( $review_photos ) );

		/**
		 * Fires after a review is submitted.
		 *
		 * Pro extensions receive criteria_ratings as the 4th parameter -- an associative
		 * array of criterion slug => integer rating (1-5) collected from the REST body.
		 * The 5th parameter carries photo attachment IDs so REST clients (native
		 * apps) can attach media without going through $_POST.
		 *
		 * @param int             $review_id        Review ID.
		 * @param int             $listing_id       Listing ID.
		 * @param int             $user_id          User ID.
		 * @param array           $criteria_ratings Per-criterion ratings, may be empty.
		 * @param int[]           $review_photos    Attachment IDs to attach to the review.
		 * @param WP_REST_Request $request          The originating REST request.
		 */
		do_action( 'wb_listora_review_submitted', $review_id, $listing_id, $user_id, $criteria_ratings, $review_photos, $request );

		/**
		 * Fires after a review is created.
		 *
		 * @param int             $review_id  Review ID.
		 * @param int             $listing_id Listing ID.
		 * @param WP_REST_Request $request    REST request.
		 */
		do_action( 'wb_listora_after_create_review', $review_id, $listing_id, $request );

		$response_data = array(
			'id'      => $review_id,
			'status'  => $status,
			'message' => 'approved' === $status
				? __( 'Review published!', 'wb-listora' )
				: __( 'Review submitted and pending approval.', 'wb-listora' ),
		);

		/**
		 * Filters the review creation REST response data.
		 *
		 * @param array           $response_data Response data.
		 * @param int             $review_id     Review ID.
		 * @param WP_REST_Request $request       REST request.
		 */
		$response_data = apply_filters( 'wb_listora_rest_prepare_review', $response_data, $review_id, $request );

		return new WP_REST_Response( $response_data, 201 );
	}

	/**
	 * Update a review.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_review( $request ) {
		global $wpdb;
		$prefix    = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$review_id = $request->get_param( 'id' );

		/**
		 * Filters whether to allow updating a review. Return WP_Error to abort.
		 *
		 * @param bool|WP_Error   $check     True to proceed, WP_Error to abort.
		 * @param int             $review_id Review ID.
		 * @param WP_REST_Request $request   REST request.
		 */
		$check = apply_filters( 'wb_listora_before_update_review', true, $review_id, $request );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$data = array( 'updated_at' => current_time( 'mysql', true ) );

		if ( $request->has_param( 'overall_rating' ) ) {
			$data['overall_rating'] = (int) $request->get_param( 'overall_rating' );
		}
		if ( $request->has_param( 'title' ) ) {
			$data['title'] = $request->get_param( 'title' );
		}
		if ( $request->has_param( 'content' ) ) {
			$data['content'] = $request->get_param( 'content' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->update( "{$prefix}reviews", $data, array( 'id' => $review_id ) );

		// Update rating in search index.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$review = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT listing_id FROM {$prefix}reviews WHERE id = %d",
				$review_id
			)
		);

		if ( $review ) {
			wp_cache_delete( 'listora_review_stats_' . $review->listing_id, 'listora' );
			wp_cache_delete( 'listora_dashboard_reviews_' . get_current_user_id(), 'listora' );
			$this->update_listing_rating( $review->listing_id );
		}

		/**
		 * Fires after a review is updated.
		 *
		 * @param int             $review_id Review ID.
		 * @param WP_REST_Request $request   REST request.
		 */
		do_action( 'wb_listora_after_update_review', $review_id, $request );

		$response_data = array( 'updated' => true );

		/**
		 * Filters the review update REST response data.
		 *
		 * @param array           $response_data Response data.
		 * @param int             $review_id     Review ID.
		 * @param WP_REST_Request $request       REST request.
		 */
		$response_data = apply_filters( 'wb_listora_rest_prepare_review', $response_data, $review_id, $request );

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Delete a review.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function delete_review( $request ) {
		global $wpdb;
		$prefix    = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$review_id = $request->get_param( 'id' );

		/**
		 * Filters whether to allow deleting a review. Return WP_Error to abort.
		 *
		 * @param bool|WP_Error   $check     True to proceed, WP_Error to abort.
		 * @param int             $review_id Review ID.
		 * @param WP_REST_Request $request   REST request.
		 */
		$check = apply_filters( 'wb_listora_before_delete_review', true, $review_id, $request );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$review = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT listing_id FROM {$prefix}reviews WHERE id = %d",
				$review_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete( "{$prefix}reviews", array( 'id' => $review_id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete( "{$prefix}review_votes", array( 'review_id' => $review_id ) );

		if ( $review ) {
			wp_cache_delete( 'listora_review_stats_' . $review->listing_id, 'listora' );
			wp_cache_delete( 'listora_dashboard_stats_' . get_current_user_id(), 'listora' );
			wp_cache_delete( 'listora_dashboard_reviews_' . get_current_user_id(), 'listora' );
			$this->update_listing_rating( $review->listing_id );
		}

		/**
		 * Fires after a review is deleted.
		 *
		 * @param int             $review_id  Review ID.
		 * @param int|null        $listing_id Listing ID (null if review was not found).
		 * @param WP_REST_Request $request    REST request.
		 */
		do_action( 'wb_listora_after_delete_review', $review_id, $review ? (int) $review->listing_id : null, $request );

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Vote a review as helpful.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function vote_helpful( $request ) {
		global $wpdb;
		$prefix    = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$review_id = $request->get_param( 'id' );
		$user_id   = get_current_user_id();

		// Check not already voted.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$prefix}review_votes WHERE user_id = %d AND review_id = %d",
				$user_id,
				$review_id
			)
		);

		if ( $existing ) {
			return new WP_Error( 'listora_already_voted', __( 'You have already voted on this review.', 'wb-listora' ), array( 'status' => 409 ) );
		}

		// Can't vote on own review.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$review = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT user_id FROM {$prefix}reviews WHERE id = %d",
				$review_id
			)
		);

		if ( $review && (int) $review->user_id === $user_id ) {
			return new WP_Error( 'listora_own_review', __( 'You cannot vote on your own review.', 'wb-listora' ), array( 'status' => 403 ) );
		}

		// Insert vote.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->insert(
			"{$prefix}review_votes",
			array(
				'user_id'    => $user_id,
				'review_id'  => $review_id,
				'created_at' => current_time( 'mysql', true ),
			)
		);

		// Update helpful count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$prefix}reviews SET helpful_count = helpful_count + 1 WHERE id = %d",
				$review_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$new_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT helpful_count FROM {$prefix}reviews WHERE id = %d",
				$review_id
			)
		);

		// Check for helpful vote milestone and trigger notification.
		$milestones = array( 1, 5, 10, 25, 50, 100 );
		if ( in_array( $new_count, $milestones, true ) ) {
			/**
			 * Fires when a review reaches a helpful-vote milestone.
			 *
			 * @param int $review_id     Review ID.
			 * @param int $helpful_count Current helpful vote count (matches a milestone).
			 */
			do_action( 'wb_listora_review_helpful_milestone', $review_id, $new_count );
		}

		return new WP_REST_Response( array( 'helpful_count' => $new_count ), 200 );
	}

	/**
	 * Owner reply to a review.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function owner_reply( $request ) {
		global $wpdb;
		$prefix    = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$review_id = $request->get_param( 'id' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->update(
			"{$prefix}reviews",
			array(
				'owner_reply'    => $request->get_param( 'content' ),
				'owner_reply_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $review_id )
		);

		// Invalidate dashboard reviews cache for the listing owner.
		wp_cache_delete( 'listora_dashboard_reviews_' . get_current_user_id(), 'listora' );

		do_action( 'wb_listora_review_reply', $review_id );

		return new WP_REST_Response( array( 'replied' => true ), 200 );
	}

	/**
	 * Report a review.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function report_review( $request ) {
		global $wpdb;
		$prefix    = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$review_id = $request->get_param( 'id' );
		$user_id   = get_current_user_id();

		// Get existing reports.
		$reports = get_option( '_listora_review_reports_' . $review_id, array() );

		// Check not already reported by this user.
		foreach ( $reports as $report ) {
			if ( (int) $report['user_id'] === $user_id ) {
				return new WP_Error( 'listora_already_reported', __( 'You have already reported this review.', 'wb-listora' ), array( 'status' => 409 ) );
			}
		}

		$reports[] = array(
			'user_id' => $user_id,
			'reason'  => $request->get_param( 'reason' ),
			'details' => $request->get_param( 'details' ),
			'date'    => current_time( 'mysql', true ),
		);

		// Store in options (simple -- not high volume). Disable autoload to prevent options bloat.
		update_option( '_listora_review_reports_' . $review_id, $reports, false );

		return new WP_REST_Response( array( 'reported' => true ), 200 );
	}

	/**
	 * Update listing average rating in search_index.
	 *
	 * @param int $listing_id Listing ID.
	 */
	private function update_listing_rating( $listing_id ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(overall_rating) as avg_r, COUNT(*) as cnt
			FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$listing_id
			),
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->update(
			"{$prefix}search_index",
			array(
				'avg_rating'   => $stats ? round( (float) $stats['avg_r'], 2 ) : 0,
				'review_count' => $stats ? (int) $stats['cnt'] : 0,
			),
			array( 'listing_id' => $listing_id )
		);
	}

	// --- Permission Callbacks ---

	/**
	 * Check create review permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function create_review_permissions( $request ) {
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
	 * Check update review permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function update_review_permissions( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'listora_unauthorized',
				__( 'You do not have permission to perform this action.', 'wb-listora' ),
				array( 'status' => 401 )
			);
		}

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$review = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT user_id FROM {$prefix}reviews WHERE id = %d",
				$request->get_param( 'id' )
			)
		);

		if ( ! $review ) {
			return new \WP_Error(
				'listora_not_found',
				__( 'Review not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		if ( (int) $review->user_id !== get_current_user_id() && ! current_user_can( 'moderate_listora_reviews' ) ) {
			return new \WP_Error(
				'listora_forbidden',
				__( 'You do not have permission to perform this action.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check delete review permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function delete_review_permissions( $request ) {
		return $this->update_review_permissions( $request );
	}

	/**
	 * Check owner reply permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function owner_reply_permissions( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'listora_unauthorized',
				__( 'You do not have permission to perform this action.', 'wb-listora' ),
				array( 'status' => 401 )
			);
		}

		// Site administrators can always reply (matches former admin_post handler).
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$review = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT listing_id FROM {$prefix}reviews WHERE id = %d",
				$request->get_param( 'id' )
			)
		);

		if ( ! $review ) {
			return new \WP_Error(
				'listora_not_found',
				__( 'Review not found.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		$post = get_post( $review->listing_id );
		if ( ! $post || ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( 'moderate_listora_reviews' ) ) ) {
			return new \WP_Error(
				'listora_forbidden',
				__( 'You do not have permission to perform this action.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		return true;
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
