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
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_review' ),
					'permission_callback' => array( $this, 'create_review_permissions' ),
					'args'                => array(
						'listing_id'     => array(
							'type'     => 'integer',
							'required' => true,
						),
						'overall_rating' => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 1,
							'maximum'  => 5,
						),
						'title'          => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'content'        => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_textarea_field',
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
					'permission_callback' => 'is_user_logged_in',
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
					'permission_callback' => 'is_user_logged_in',
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
	 */
	public function get_listing_reviews( $request ) {
		global $wpdb;
		$prefix     = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$listing_id = $request->get_param( 'listing_id' );
		$page       = $request->get_param( 'page' );
		$per_page   = $request->get_param( 'per_page' );
		$sort       = $request->get_param( 'sort' );

		$sort_map = array(
			'oldest'  => 'r.created_at ASC',
			'highest' => 'r.overall_rating DESC, r.created_at DESC',
			'lowest'  => 'r.overall_rating ASC, r.created_at DESC',
			'helpful' => 'r.helpful_count DESC, r.created_at DESC',
		);
		$order_by = isset( $sort_map[ $sort ] ) ? $sort_map[ $sort ] : 'r.created_at DESC';

		$offset = ( $page - 1 ) * $per_page;

		// Get total count.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved'",
				$listing_id
			)
		);

		// Get reviews.
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

		// Get rating summary.
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
			FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved'",
				$listing_id
			),
			ARRAY_A
		);

		// Format reviews.
		$reviews = array_map(
			function ( $row ) {
				$user = get_user_by( 'id', $row['user_id'] );
				return array(
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
			},
			$rows
		);

		$response = new WP_REST_Response(
			array(
				'reviews' => $reviews,
				'summary' => array(
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
				'total'   => $total,
				'pages'   => (int) ceil( $total / $per_page ),
			),
			200
		);

		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * Create a review.
	 */
	public function create_review( $request ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

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
		$existing = $wpdb->get_var(
			$wpdb->prepare(
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

		// Auto-approve or pending.
		$status = wb_listora_get_setting( 'moderation', 'manual' ) === 'auto_approve' ? 'approved' : 'pending';

		$result = $wpdb->insert(
			"{$prefix}reviews",
			array(
				'listing_id'     => $listing_id,
				'user_id'        => $user_id,
				'overall_rating' => (int) $request->get_param( 'overall_rating' ),
				'title'          => $request->get_param( 'title' ),
				'content'        => $content,
				'status'         => $status,
				'ip_address'     => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
				'created_at'     => current_time( 'mysql', true ),
				'updated_at'     => current_time( 'mysql', true ),
			)
		);

		if ( false === $result ) {
			return new WP_Error( 'listora_review_failed', __( 'Failed to submit review.', 'wb-listora' ), array( 'status' => 500 ) );
		}

		$review_id = $wpdb->insert_id;

		// Update search index rating.
		$this->update_listing_rating( $listing_id );

		/**
		 * Fires after a review is submitted.
		 *
		 * @param int $review_id  Review ID.
		 * @param int $listing_id Listing ID.
		 * @param int $user_id    User ID.
		 */
		do_action( 'wb_listora_review_submitted', $review_id, $listing_id, $user_id );

		return new WP_REST_Response(
			array(
				'id'      => $review_id,
				'status'  => $status,
				'message' => 'approved' === $status
					? __( 'Review published!', 'wb-listora' )
					: __( 'Review submitted and pending approval.', 'wb-listora' ),
			),
			201
		);
	}

	/**
	 * Update a review.
	 */
	public function update_review( $request ) {
		global $wpdb;
		$prefix    = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$review_id = $request->get_param( 'id' );

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

		$wpdb->update( "{$prefix}reviews", $data, array( 'id' => $review_id ) );

		// Update rating in search index.
		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT listing_id FROM {$prefix}reviews WHERE id = %d",
				$review_id
			)
		);

		if ( $review ) {
			$this->update_listing_rating( $review->listing_id );
		}

		return new WP_REST_Response( array( 'updated' => true ), 200 );
	}

	/**
	 * Delete a review.
	 */
	public function delete_review( $request ) {
		global $wpdb;
		$prefix    = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$review_id = $request->get_param( 'id' );

		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT listing_id FROM {$prefix}reviews WHERE id = %d",
				$review_id
			)
		);

		$wpdb->delete( "{$prefix}reviews", array( 'id' => $review_id ) );
		$wpdb->delete( "{$prefix}review_votes", array( 'review_id' => $review_id ) );

		if ( $review ) {
			$this->update_listing_rating( $review->listing_id );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Vote a review as helpful.
	 */
	public function vote_helpful( $request ) {
		global $wpdb;
		$prefix    = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$review_id = $request->get_param( 'id' );
		$user_id   = get_current_user_id();

		// Check not already voted.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}review_votes WHERE user_id = %d AND review_id = %d",
				$user_id,
				$review_id
			)
		);

		if ( $existing ) {
			return new WP_Error( 'listora_already_voted', __( 'You have already voted on this review.', 'wb-listora' ), array( 'status' => 409 ) );
		}

		// Can't vote on own review.
		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id FROM {$prefix}reviews WHERE id = %d",
				$review_id
			)
		);

		if ( $review && (int) $review->user_id === $user_id ) {
			return new WP_Error( 'listora_own_review', __( 'You cannot vote on your own review.', 'wb-listora' ), array( 'status' => 403 ) );
		}

		// Insert vote.
		$wpdb->insert(
			"{$prefix}review_votes",
			array(
				'user_id'    => $user_id,
				'review_id'  => $review_id,
				'created_at' => current_time( 'mysql', true ),
			)
		);

		// Update helpful count.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$prefix}reviews SET helpful_count = helpful_count + 1 WHERE id = %d",
				$review_id
			)
		);

		$new_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT helpful_count FROM {$prefix}reviews WHERE id = %d",
				$review_id
			)
		);

		return new WP_REST_Response( array( 'helpful_count' => $new_count ), 200 );
	}

	/**
	 * Owner reply to a review.
	 */
	public function owner_reply( $request ) {
		global $wpdb;
		$prefix    = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$review_id = $request->get_param( 'id' );

		$wpdb->update(
			"{$prefix}reviews",
			array(
				'owner_reply'    => $request->get_param( 'content' ),
				'owner_reply_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $review_id )
		);

		do_action( 'wb_listora_review_reply', $review_id );

		return new WP_REST_Response( array( 'replied' => true ), 200 );
	}

	/**
	 * Report a review.
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

		// Store in options (simple — not high volume).
		update_option( '_listora_review_reports_' . $review_id, $reports );

		return new WP_REST_Response( array( 'reported' => true ), 200 );
	}

	/**
	 * Update listing average rating in search_index.
	 */
	private function update_listing_rating( $listing_id ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(overall_rating) as avg_r, COUNT(*) as cnt
			FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved'",
				$listing_id
			),
			ARRAY_A
		);

		$wpdb->update(
			"{$prefix}search_index",
			array(
				'avg_rating'   => $stats ? round( (float) $stats['avg_r'], 2 ) : 0,
				'review_count' => $stats ? (int) $stats['cnt'] : 0,
			),
			array( 'listing_id' => $listing_id )
		);
	}

	// ─── Permission Callbacks ───

	public function create_review_permissions( $request ) {
		return is_user_logged_in();
	}

	public function update_review_permissions( $request ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id FROM {$prefix}reviews WHERE id = %d",
				$request->get_param( 'id' )
			)
		);
		return $review && ( (int) $review->user_id === get_current_user_id() || current_user_can( 'moderate_listora_reviews' ) );
	}

	public function delete_review_permissions( $request ) {
		return $this->update_review_permissions( $request );
	}

	public function owner_reply_permissions( $request ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT listing_id FROM {$prefix}reviews WHERE id = %d",
				$request->get_param( 'id' )
			)
		);

		if ( ! $review ) {
			return false;
		}

		$post = get_post( $review->listing_id );
		return $post && ( (int) $post->post_author === get_current_user_id() || current_user_can( 'moderate_listora_reviews' ) );
	}
}
