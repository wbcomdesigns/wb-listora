<?php
/**
 * REST Dashboard Controller — user dashboard data endpoints.
 *
 * @package WBListora\REST
 */

namespace WBListora\REST;

defined( 'ABSPATH' ) || exit;

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
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
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
	 */
	public function get_stats( $request ) {
		global $wpdb;
		$prefix  = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$user_id = get_current_user_id();

		// Check cache first.
		$cache_key = 'listora_dashboard_stats_' . $user_id;
		$cached    = wp_cache_get( $cache_key, 'listora' );

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

		wp_cache_set( $cache_key, $data, 'listora', HOUR_IN_SECONDS );

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
	 */
	public function get_listings( $request ) {
		$user_id  = get_current_user_id();
		$status   = $request->get_param( 'status' );
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		$args = array(
			'post_type'      => 'listora_listing',
			'author'         => $user_id,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $status ) {
			$args['post_status'] = $status;
		} else {
			$args['post_status'] = array( 'publish', 'pending', 'draft', 'listora_expired', 'listora_rejected', 'listora_deactivated' );
		}

		$query = new \WP_Query( $args );

		$listings = array_map(
			function ( $post ) {
				$type = \WBListora\Core\Listing_Type_Registry::instance()->get_for_post( $post->ID );
				return array(
					'id'        => $post->ID,
					'title'     => $post->post_title,
					'status'    => $post->post_status,
					'type'      => $type ? $type->get_name() : '',
					'url'       => get_permalink( $post->ID ),
					'edit_url'  => $this->get_submission_page_url( $post->ID ),
					'date'      => $post->post_date,
					'expiry'    => get_post_meta( $post->ID, '_listora_expiration_date', true ),
					'thumbnail' => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ),
				);
			},
			$query->posts
		);

		$offset   = ( $page - 1 ) * $per_page;
		$has_more = ( $offset + count( $query->posts ) ) < $query->found_posts;

		return new WP_REST_Response(
			array(
				'listings' => $listings,
				'total'    => $query->found_posts,
				'pages'    => $query->max_num_pages,
				'has_more' => $has_more,
			),
			200
		);
	}

	/**
	 * User's own claim submissions with current status.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_my_claims( $request ) {
		global $wpdb;
		$prefix  = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$user_id = get_current_user_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$claims = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.id, c.listing_id, c.status, c.proof_text, c.admin_notes, c.created_at, c.updated_at,
					p.post_title AS listing_title
				FROM {$prefix}claims c
				LEFT JOIN {$wpdb->posts} p ON c.listing_id = p.ID
				WHERE c.user_id = %d
				ORDER BY c.created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			),
			ARRAY_A
		);

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

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * User's reviews (written + received).
	 */
	public function get_reviews( $request ) {
		global $wpdb;
		$prefix  = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$user_id = get_current_user_id();

		// Check cache first.
		$cache_key = 'listora_dashboard_reviews_' . $user_id;
		$cached    = wp_cache_get( $cache_key, 'listora' );

		if ( false !== $cached ) {
			return new WP_REST_Response( $cached, 200 );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$written = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, si.title as listing_title FROM {$prefix}reviews r
			LEFT JOIN {$prefix}search_index si ON r.listing_id = si.listing_id
			WHERE r.user_id = %d ORDER BY r.created_at DESC LIMIT 20",
				$user_id
			),
			ARRAY_A
		);

		$received = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, si.title as listing_title FROM {$prefix}reviews r
			INNER JOIN {$wpdb->posts} p ON r.listing_id = p.ID
			LEFT JOIN {$prefix}search_index si ON r.listing_id = si.listing_id
			WHERE p.post_author = %d AND r.user_id != %d AND r.status = 'approved'
			ORDER BY r.created_at DESC LIMIT 20",
				$user_id,
				$user_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Count totals for has_more flags (limit is 20 per list).
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

		$data = array(
			'written'           => $written,
			'received'          => $received,
			'written_total'     => $written_total,
			'received_total'    => $received_total,
			'written_has_more'  => count( $written ) < $written_total,
			'received_has_more' => count( $received ) < $received_total,
		);

		wp_cache_set( $cache_key, $data, 'listora', HOUR_IN_SECONDS );

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
