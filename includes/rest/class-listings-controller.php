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
				'listings' => $listings,
				'total'    => count( $listings ),
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

		// Add rating from search index.
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$idx    = $wpdb->get_row(
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

		// Add flags.
		$data['is_featured']    = \WBListora\Core\Featured::is_featured( $post->ID );
		$data['featured_until'] = \WBListora\Core\Featured::get_featured_until( $post->ID );
		$data['is_verified']    = (bool) get_post_meta( $post->ID, '_listora_is_verified', true );
		$data['is_claimed']     = (bool) get_post_meta( $post->ID, '_listora_is_claimed', true );

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
			$current_user = get_current_user_id();
			$is_owner_view = (int) $post->post_author === $current_user || current_user_can( 'edit_others_posts' );
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
					"SELECT id, name, description, price, duration FROM {$prefix}services WHERE listing_id = %d ORDER BY sort_order ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
		$data['is_verified']    = (bool) get_post_meta( $post_id, '_listora_is_verified', true );

		// --- Schema ---
		$schema         = \WBListora\Schema\Schema_Generator::for_listing( $post_id );
		$data['schema'] = $schema ? $schema->get_data() : null;

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

		// Invalidate dashboard stats cache for the listing author.
		wp_cache_delete( 'listora_dashboard_stats_' . $post->post_author, 'listora' );

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
		$user_id = get_current_user_id();

		// Credit balance check — only if a cost is set and the SDK is active.
		if ( $cost > 0 && class_exists( '\Wbcom\Credits\Credits' ) ) {
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
		}

		$days = \WBListora\Core\Featured::get_default_duration_days();
		$ok   = \WBListora\Core\Featured::feature_listing( $post_id, $days );

		if ( is_wp_error( $ok ) ) {
			// A `wb_listora_before_feature_listing` listener aborted (e.g. SDK
			// hold rejected for insufficient credits).
			return $ok;
		}

		if ( ! $ok ) {
			return new \WP_Error(
				'listora_feature_failed',
				__( 'Unable to feature this listing. Please try again.', 'wb-listora' ),
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
