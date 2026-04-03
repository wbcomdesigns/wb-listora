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
					'permission_callback' => 'is_user_logged_in',
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
					'permission_callback' => 'is_user_logged_in',
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
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// PUT /dashboard/profile
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/profile',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_profile' ),
					'permission_callback' => 'is_user_logged_in',
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
	}

	/**
	 * Dashboard stats summary.
	 */
	public function get_stats( $request ) {
		global $wpdb;
		$prefix  = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$user_id = get_current_user_id();

		// Listing counts by status.
		$listing_counts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_status, COUNT(*) as cnt FROM {$wpdb->posts}
			WHERE post_type = 'listora_listing' AND post_author = %d
			GROUP BY post_status",
				$user_id
			),
			OBJECT_K
		);

		$review_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}reviews WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);

		$favorite_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}favorites WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);

		return new WP_REST_Response(
			array(
				'listings'  => array(
					'published' => (int) ( $listing_counts['publish']->cnt ?? 0 ),
					'pending'   => (int) ( $listing_counts['pending']->cnt ?? 0 ),
					'expired'   => (int) ( $listing_counts['listora_expired']->cnt ?? 0 ),
					'draft'     => (int) ( $listing_counts['draft']->cnt ?? 0 ),
				),
				'reviews'   => $review_count,
				'favorites' => $favorite_count,
			),
			200
		);
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

		return new WP_REST_Response(
			array(
				'listings' => $listings,
				'total'    => $query->found_posts,
				'pages'    => $query->max_num_pages,
			),
			200
		);
	}

	/**
	 * User's reviews (written + received).
	 */
	public function get_reviews( $request ) {
		global $wpdb;
		$prefix  = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$user_id = get_current_user_id();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return new WP_REST_Response(
			array(
				'written'  => $written,
				'received' => $received,
			),
			200
		);
	}

	/**
	 * Update user profile.
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

		// Update notification preferences.
		$prefs = $request->get_param( 'notification_prefs' );
		if ( is_array( $prefs ) ) {
			update_user_meta( $user_id, '_listora_notification_prefs', $prefs );
		}

		return new WP_REST_Response( array( 'updated' => true ), 200 );
	}

	/**
	 * Get the submission page URL for editing a listing.
	 *
	 * @param int $post_id Post ID to edit.
	 * @return string
	 */
	private function get_submission_page_url( $post_id ) {
		$page_id = (int) wb_listora_get_setting( 'submission_page', 0 );
		$base    = $page_id > 0 ? get_permalink( $page_id ) : home_url( '/add-listing/' );

		return add_query_arg( 'edit', $post_id, $base );
	}
}
