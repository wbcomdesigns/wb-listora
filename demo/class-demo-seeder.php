<?php
/**
 * Demo Seeder — shared helpers for all demo packs.
 *
 * @package WBListora\Demo
 */

namespace WBListora\Demo;

defined( 'ABSPATH' ) || exit;

/**
 * Provides reusable methods for seeding listings, reviews, categories, images,
 * services, claims, favorites, and test users.
 */
class Demo_Seeder {

	/**
	 * Static review user counter to ensure unique user IDs.
	 *
	 * @var int
	 */
	private static $review_user_id = 200;

	/**
	 * When true, image-related helpers are no-ops (used by --skip-images CLI flag).
	 *
	 * @var bool
	 */
	private static $skip_images = false;

	/**
	 * Curated Picsum seed slugs per listing type. Each slug deterministically
	 * resolves to the same image, which keeps demo content stable across runs.
	 *
	 * @var array<string,string[]>
	 */
	private static $image_seeds = array(
		'restaurant'  => array( 'rest-1', 'rest-2', 'rest-3', 'rest-4', 'rest-5', 'rest-6', 'rest-7', 'rest-8', 'rest-9', 'rest-10' ),
		'hotel'       => array( 'hotel-1', 'hotel-2', 'hotel-3', 'hotel-4', 'hotel-5', 'hotel-6', 'hotel-7', 'hotel-8', 'hotel-9', 'hotel-10' ),
		'real-estate' => array( 'home-1', 'home-2', 'home-3', 'home-4', 'home-5', 'home-6', 'home-7', 'home-8', 'home-9', 'home-10' ),
		'job'         => array( 'job-1', 'job-2', 'job-3', 'job-4', 'job-5', 'job-6', 'job-7', 'job-8', 'job-9', 'job-10' ),
		'business'    => array( 'biz-1', 'biz-2', 'biz-3', 'biz-4', 'biz-5', 'biz-6', 'biz-7', 'biz-8', 'biz-9', 'biz-10' ),
		'classified'  => array( 'cls-1', 'cls-2', 'cls-3', 'cls-4', 'cls-5', 'cls-6', 'cls-7', 'cls-8', 'cls-9', 'cls-10' ),
		'education'   => array( 'edu-1', 'edu-2', 'edu-3', 'edu-4', 'edu-5', 'edu-6', 'edu-7', 'edu-8' ),
		'healthcare'  => array( 'med-1', 'med-2', 'med-3', 'med-4', 'med-5', 'med-6', 'med-7', 'med-8' ),
		'place'       => array( 'place-1', 'place-2', 'place-3', 'place-4', 'place-5', 'place-6', 'place-7', 'place-8', 'place-9', 'place-10' ),
		'event'       => array( 'evt-1', 'evt-2', 'evt-3', 'evt-4', 'evt-5', 'evt-6', 'evt-7', 'evt-8' ),
	);

	/**
	 * Toggle image sideloading globally. CLI's --skip-images flag flips this off.
	 *
	 * @param bool $skip True to disable image sideloading.
	 */
	public static function set_skip_images( $skip ) {
		self::$skip_images = (bool) $skip;
	}

	/**
	 * Whether image sideloading is currently disabled.
	 *
	 * @return bool
	 */
	public static function is_skipping_images() {
		return self::$skip_images;
	}

	/**
	 * Seed a single listing. Skips if a listing with the same title already exists.
	 *
	 * @param array $data Listing data array with keys: title, type, content, meta, categories, features, tags, featured.
	 * @return int|false Post ID on success, false if duplicate.
	 */
	public static function seed_listing( $data ) {
		// Idempotency: skip if listing with this title already exists.
		$existing = get_posts(
			array(
				'post_type'      => 'listora_listing',
				'title'          => $data['title'],
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing ) ) {
			return false;
		}

		$author_id = isset( $data['author_id'] ) ? (int) $data['author_id'] : ( get_current_user_id() ?: 1 );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'listora_listing',
				'post_title'   => $data['title'],
				'post_content' => $data['content'],
				'post_status'  => 'draft',
				'post_author'  => $author_id,
			)
		);

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		wp_set_object_terms( $post_id, $data['type'], 'listora_listing_type' );

		if ( ! empty( $data['categories'] ) ) {
			wp_set_object_terms( $post_id, $data['categories'], 'listora_listing_cat' );
		}
		if ( ! empty( $data['features'] ) ) {
			wp_set_object_terms( $post_id, $data['features'], 'listora_listing_feature' );
		}
		if ( ! empty( $data['tags'] ) ) {
			wp_set_object_terms( $post_id, $data['tags'], 'listora_listing_tag' );
		}

		// Create and assign hierarchical location terms (country > state > city) from address data.
		if ( ! empty( $data['address'] ) && is_array( $data['address'] ) ) {
			$addr           = $data['address'];
			$location_terms = array();

			// Country (top level).
			$country      = $addr['country'] ?? 'United States';
			$country_term = term_exists( $country, 'listora_listing_location' );
			if ( ! $country_term ) {
				$country_term = wp_insert_term( $country, 'listora_listing_location' );
			}
			$country_id       = is_array( $country_term ) ? (int) $country_term['term_id'] : (int) $country_term;
			$location_terms[] = $country_id;

			// State (child of country).
			$state_name = $addr['state'] ?? '';
			if ( $state_name ) {
				$state_term = term_exists( $state_name, 'listora_listing_location', $country_id );
				if ( ! $state_term ) {
					$state_term = wp_insert_term( $state_name, 'listora_listing_location', array( 'parent' => $country_id ) );
				}
				$state_id         = is_array( $state_term ) ? (int) $state_term['term_id'] : (int) $state_term;
				$location_terms[] = $state_id;

				// City (child of state).
				$city_name = $addr['city'] ?? '';
				if ( $city_name ) {
					$city_term = term_exists( $city_name, 'listora_listing_location', $state_id );
					if ( ! $city_term ) {
						$city_term = wp_insert_term( $city_name, 'listora_listing_location', array( 'parent' => $state_id ) );
					}
					$city_id          = is_array( $city_term ) ? (int) $city_term['term_id'] : (int) $city_term;
					$location_terms[] = $city_id;
				}
			}

			if ( ! empty( $location_terms ) ) {
				wp_set_object_terms( $post_id, $location_terms, 'listora_listing_location' );
			}
		}

		foreach ( $data['meta'] as $key => $value ) {
			\WBListora\Core\Meta_Handler::set_value( $post_id, $key, $value );
		}

		if ( ! empty( $data['featured'] ) ) {
			update_post_meta( $post_id, '_listora_is_featured', true );
		}

		update_post_meta( $post_id, '_listora_demo_content', true );
		update_post_meta( $post_id, '_listora_timezone', 'America/New_York' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		return $post_id;
	}

	/**
	 * Seed a review for a listing.
	 *
	 * @param int    $listing_id Listing post ID.
	 * @param float  $rating     Overall rating (1-5).
	 * @param string $title      Review title.
	 * @param string $content    Review content.
	 * @param int    $user_id    Optional. User ID for the review (overrides auto-counter).
	 */
	public static function seed_review( $listing_id, $rating, $title, $content, $user_id = 0 ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		if ( $user_id > 0 ) {
			$reviewer_id = (int) $user_id;
		} else {
			++self::$review_user_id;
			$reviewer_id = self::$review_user_id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			"{$prefix}reviews", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array(
				'listing_id'     => $listing_id,
				'user_id'        => $reviewer_id,
				'overall_rating' => $rating,
				'title'          => $title,
				'content'        => $content,
				'status'         => 'approved',
				'helpful_count'  => wp_rand( 0, 15 ),
				'created_at'     => gmdate( 'Y-m-d H:i:s', strtotime( '-' . wp_rand( 1, 90 ) . ' days' ) ),
				'updated_at'     => current_time( 'mysql', true ),
			)
		);

		// Update rating in search_index.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(overall_rating) as avg_r, COUNT(*) as cnt FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$listing_id
			),
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			"{$prefix}search_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array(
				'avg_rating'   => round( (float) $stats['avg_r'], 2 ),
				'review_count' => (int) $stats['cnt'],
			),
			array( 'listing_id' => $listing_id )
		);
	}

	/**
	 * Ensure listing categories exist for a pack. Creates terms if missing.
	 *
	 * @param array $categories Associative array of slug => name pairs.
	 */
	public static function ensure_categories( $categories ) {
		foreach ( $categories as $slug => $name ) {
			if ( ! term_exists( $slug, 'listora_listing_cat' ) ) {
				wp_insert_term(
					$name,
					'listora_listing_cat',
					array( 'slug' => $slug )
				);
			}
		}
	}

	/**
	 * Ensure feature terms exist. Creates terms if missing.
	 *
	 * @param array $features Associative array of slug => name pairs.
	 */
	public static function ensure_features( $features ) {
		foreach ( $features as $slug => $name ) {
			if ( ! term_exists( $slug, 'listora_listing_feature' ) ) {
				wp_insert_term(
					$name,
					'listora_listing_feature',
					array( 'slug' => $slug )
				);
			}
		}
	}

	/**
	 * Generate standard business hours.
	 *
	 * @param string $open_time  Opening time (e.g. '09:00').
	 * @param string $close_time Closing time (e.g. '22:00').
	 * @param bool   $closed_sun Whether Sunday is closed.
	 * @return array Business hours array.
	 */
	public static function make_hours( $open_time = '09:00', $close_time = '21:00', $closed_sun = false ) {
		$hours = array();
		for ( $day = 1; $day <= 6; $day++ ) {
			$hours[] = array(
				'day'   => $day,
				'open'  => $open_time,
				'close' => $close_time,
			);
		}
		if ( $closed_sun ) {
			$hours[] = array(
				'day'    => 0,
				'closed' => true,
			);
		} else {
			$hours[] = array(
				'day'   => 0,
				'open'  => $open_time,
				'close' => $close_time,
			);
		}
		return $hours;
	}

	// ─── Image Helpers ───

	/**
	 * Sideload an external image into the media library and attach it to a post.
	 *
	 * Wraps WordPress's media_sideload_image() with the upload helpers loaded
	 * on demand. Failures are logged and return 0 so seeders keep working on
	 * slow networks or in CI.
	 *
	 * @param string $url     Source image URL.
	 * @param int    $post_id Post to attach the image to.
	 * @param string $alt     Optional alt text.
	 * @return int Attachment ID on success, 0 on failure.
	 */
	public static function sideload_image( $url, $post_id, $alt = '' ) {
		if ( self::$skip_images ) {
			return 0;
		}

		if ( empty( $url ) || ! $post_id ) {
			return 0;
		}

		// Idempotency — if the same source URL was already imported for this
		// post, return the existing attachment instead of fetching again.
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_parent'    => $post_id,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'       => '_listora_demo_image_src',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'     => $url,
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		if ( ! empty( $existing ) ) {
			return (int) $existing[0];
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		try {
			// media_sideload_image() returns the attachment ID when 'id' is requested.
			$attachment_id = \media_sideload_image( $url, $post_id, $alt, 'id' );
		} catch ( \Throwable $e ) {
			error_log( '[wb-listora demo] sideload threw for ' . $url . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return 0;
		}

		if ( is_wp_error( $attachment_id ) ) {
			error_log( '[wb-listora demo] sideload failed for ' . $url . ': ' . $attachment_id->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return 0;
		}

		$attachment_id = (int) $attachment_id;
		if ( $attachment_id > 0 ) {
			update_post_meta( $attachment_id, '_listora_demo_content', true );
			update_post_meta( $attachment_id, '_listora_demo_image_src', $url );
			if ( $alt ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
			}
		}

		return $attachment_id;
	}

	/**
	 * Pick an image seed for a listing type (deterministic) and set it as the
	 * post's featured image.
	 *
	 * @param int    $post_id Listing post ID.
	 * @param string $type    Listing type slug (restaurant, hotel, ...).
	 * @param int    $index   Stable index — use the listing's position in the pack so reruns produce the same image.
	 * @return int Attachment ID, or 0 on failure / when images skipped.
	 */
	public static function seed_featured_image( $post_id, $type, $index = 0 ) {
		if ( self::$skip_images ) {
			return 0;
		}
		if ( has_post_thumbnail( $post_id ) ) {
			return (int) get_post_thumbnail_id( $post_id );
		}

		$seeds = self::$image_seeds[ $type ] ?? self::$image_seeds['business'];
		$seed  = $seeds[ $index % count( $seeds ) ];
		// Append .jpg so WordPress's URL-extension check in media_sideload_image() passes.
		$url   = sprintf( 'https://picsum.photos/seed/%s/1200/800.jpg', rawurlencode( $seed ) );

		$attachment_id = self::sideload_image( $url, $post_id, get_the_title( $post_id ) );

		if ( $attachment_id > 0 ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}

		return $attachment_id;
	}

	/**
	 * Sideload a small gallery for a listing and store the IDs in the
	 * `_listora_gallery` meta key (the same key the submission UI writes to).
	 *
	 * @param int    $post_id Listing post ID.
	 * @param string $type    Listing type slug.
	 * @param int    $count   Number of gallery images. Default 4.
	 * @return int[] Attachment IDs added to the gallery (may be empty).
	 */
	public static function seed_gallery( $post_id, $type, $count = 4 ) {
		if ( self::$skip_images || $count < 1 ) {
			return array();
		}

		$existing = get_post_meta( $post_id, '_listora_gallery', true );
		if ( ! empty( $existing ) && is_array( $existing ) && count( $existing ) >= $count ) {
			return array_map( 'intval', $existing );
		}

		$seeds = self::$image_seeds[ $type ] ?? self::$image_seeds['business'];
		$ids   = is_array( $existing ) ? array_map( 'intval', $existing ) : array();

		// Offset by 2 so gallery seeds differ from the featured image seed.
		for ( $i = 0; $i < $count; $i++ ) {
			$seed_idx = ( $i + 2 ) % count( $seeds );
			$seed     = $seeds[ $seed_idx ] . '-g' . $i;
			// Append .jpg so WordPress's URL-extension check in media_sideload_image() passes.
			$url      = sprintf( 'https://picsum.photos/seed/%s/1000/700.jpg', rawurlencode( $seed ) );

			$att_id = self::sideload_image( $url, $post_id, get_the_title( $post_id ) . ' gallery' );
			if ( $att_id > 0 ) {
				$ids[] = $att_id;
			}
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( ! empty( $ids ) ) {
			update_post_meta( $post_id, '_listora_gallery', $ids );
		}

		return $ids;
	}

	// ─── Service / Claim / Favorite Helpers ───

	/**
	 * Seed a service row attached to a listing. Idempotent on (listing_id, title).
	 *
	 * @param int         $listing_id     Parent listing ID.
	 * @param string      $title          Service title.
	 * @param float|null  $price          Price (null = unset).
	 * @param int|null    $duration_min   Duration in minutes.
	 * @param string      $description    Long description.
	 * @param string|null $category       Optional service category name.
	 * @return int|false Service ID on success, false on duplicate / failure.
	 */
	public static function seed_service( $listing_id, $title, $price = null, $duration_min = null, $description = '', $category = null ) {
		if ( ! $listing_id || empty( $title ) ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'services';

		// Idempotency check.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM {$table} WHERE listing_id = %d AND title = %s LIMIT 1",
				$listing_id,
				$title
			)
		);
		if ( $existing ) {
			return (int) $existing;
		}

		$data = array(
			'listing_id'  => (int) $listing_id,
			'title'       => $title,
			'description' => (string) $description,
		);

		if ( null !== $price ) {
			$data['price']      = (float) $price;
			$data['price_type'] = 'fixed';
		}
		if ( null !== $duration_min ) {
			$data['duration_minutes'] = (int) $duration_min;
		}
		if ( $category ) {
			$data['categories'] = array( $category );
		}

		if ( ! class_exists( '\WBListora\Core\Services' ) ) {
			return false;
		}

		$service_id = \WBListora\Core\Services::create_service( $data );

		if ( is_wp_error( $service_id ) || ! $service_id ) {
			return false;
		}

		return (int) $service_id;
	}

	/**
	 * Seed a claim row in the listora_claims table. Idempotent on (listing_id, user_id).
	 *
	 * @param int    $listing_id  Listing ID.
	 * @param int    $user_id     User submitting the claim.
	 * @param string $proof_text  Claim proof text.
	 * @param string $status      Status: pending|approved|rejected. Default pending.
	 * @return int|false Claim ID, or false on failure / duplicate.
	 */
	public static function seed_claim( $listing_id, $user_id, $proof_text, $status = 'pending' ) {
		if ( ! $listing_id || ! $user_id ) {
			return false;
		}

		$status = in_array( $status, array( 'pending', 'approved', 'rejected' ), true ) ? $status : 'pending';

		global $wpdb;
		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'claims';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM {$table} WHERE listing_id = %d AND user_id = %d LIMIT 1",
				$listing_id,
				$user_id
			)
		);
		if ( $existing ) {
			return (int) $existing;
		}

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$table,
			array(
				'listing_id' => (int) $listing_id,
				'user_id'    => (int) $user_id,
				'status'     => $status,
				'proof_text' => $proof_text,
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Seed a favorite row. Idempotent on (user_id, listing_id) primary key.
	 *
	 * @param int $user_id    User ID.
	 * @param int $listing_id Listing ID.
	 * @return bool True on success or already exists, false on failure.
	 */
	public static function seed_favorite( $user_id, $listing_id ) {
		if ( ! $user_id || ! $listing_id ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'favorites';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT user_id FROM {$table} WHERE user_id = %d AND listing_id = %d",
				$user_id,
				$listing_id
			)
		);
		if ( $existing ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'    => (int) $user_id,
				'listing_id' => (int) $listing_id,
				'collection' => 'default',
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		return false !== $inserted;
	}

	// ─── Test User Helpers ───

	/**
	 * Default test user definitions. Order matters — index 0 is the "primary"
	 * test contributor, used as default author for some demo listings.
	 *
	 * @return array<int,array{login:string,role:string,display:string,email:string}>
	 */
	public static function get_test_user_defs() {
		return array(
			array(
				'login'   => 'contributor1',
				'role'    => 'contributor',
				'display' => 'Casey Contributor',
				'email'   => 'contributor1@listora.test',
			),
			array(
				'login'   => 'author1',
				'role'    => 'author',
				'display' => 'Avery Author',
				'email'   => 'author1@listora.test',
			),
			array(
				'login'   => 'subscriber2',
				'role'    => 'subscriber',
				'display' => 'Sam Subscriber',
				'email'   => 'subscriber2@listora.test',
			),
			array(
				'login'   => 'subscriber3',
				'role'    => 'subscriber',
				'display' => 'Riley Reviewer',
				'email'   => 'subscriber3@listora.test',
			),
		);
	}

	/**
	 * Ensure all default test users exist. Creates missing ones with password
	 * "password". Returns a map of login => user ID for callers.
	 *
	 * @return array<string,int> Login => user ID.
	 */
	public static function ensure_test_users() {
		$ids = array();

		foreach ( self::get_test_user_defs() as $def ) {
			$user = get_user_by( 'login', $def['login'] );
			if ( $user ) {
				$ids[ $def['login'] ] = (int) $user->ID;
				continue;
			}

			$user_id = wp_insert_user(
				array(
					'user_login'   => $def['login'],
					'user_pass'    => 'password',
					'user_email'   => $def['email'],
					'display_name' => $def['display'],
					'first_name'   => explode( ' ', $def['display'] )[0],
					'last_name'    => explode( ' ', $def['display'] )[1] ?? '',
					'role'         => $def['role'],
				)
			);

			if ( is_wp_error( $user_id ) ) {
				continue;
			}

			update_user_meta( $user_id, '_listora_demo_user', true );
			$ids[ $def['login'] ] = (int) $user_id;
		}

		return $ids;
	}

	/**
	 * Pick a random non-admin test user. Prefers the demo test users when
	 * present and falls back to any user with the given role.
	 *
	 * @param string $role WP role (default 'subscriber').
	 * @return int User ID, or 1 (admin) as last resort.
	 */
	public static function get_random_user_id( $role = 'subscriber' ) {
		// First preference: demo test users matching the requested role.
		$users = get_users(
			array(
				'role'       => $role,
				'meta_key'   => '_listora_demo_user', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 20,
				'fields'     => 'ID',
			)
		);

		if ( empty( $users ) ) {
			$users = get_users(
				array(
					'role'    => $role,
					'number'  => 20,
					'fields'  => 'ID',
					'exclude' => array( 1 ),
				)
			);
		}

		if ( empty( $users ) ) {
			return 1;
		}

		return (int) $users[ array_rand( $users ) ];
	}

	// ─── Pack Convenience ───

	/**
	 * After a listing is seeded, attach images, services, an occasional claim,
	 * and an occasional favorite. Pack files call this in their seed loop so
	 * each pack stays focused on listing data, not boilerplate.
	 *
	 * @param int    $post_id    Seeded listing ID (return value of seed_listing()).
	 * @param string $type       Listing type slug.
	 * @param int    $index      Position in pack (used for deterministic image picking).
	 * @param array  $services   Optional. List of services. Each item:
	 *                           [ title, price?, duration_min?, description?, category? ].
	 * @param array  $opts       {
	 *     Optional behavior flags.
	 *     @type bool $featured_image  Add featured image. Default true.
	 *     @type int  $gallery_count   Gallery image count. Default 4.
	 *     @type bool $claim           Maybe seed a claim. Default true.
	 *     @type bool $favorite        Maybe seed a favorite. Default true.
	 * }
	 */
	public static function seed_pack_extras( $post_id, $type, $index = 0, $services = array(), $opts = array() ) {
		if ( ! $post_id ) {
			return;
		}

		$opts = array_merge(
			array(
				'featured_image' => true,
				'gallery_count'  => 4,
				'claim'          => true,
				'favorite'       => true,
			),
			$opts
		);

		if ( $opts['featured_image'] ) {
			self::seed_featured_image( $post_id, $type, $index );
		}
		if ( $opts['gallery_count'] > 0 ) {
			self::seed_gallery( $post_id, $type, (int) $opts['gallery_count'] );
		}

		// Services.
		foreach ( $services as $svc ) {
			$title    = $svc[0] ?? '';
			$price    = $svc[1] ?? null;
			$duration = $svc[2] ?? null;
			$desc     = $svc[3] ?? '';
			$cat      = $svc[4] ?? null;
			if ( $title ) {
				self::seed_service( $post_id, $title, $price, $duration, $desc, $cat );
			}
		}

		// Occasional claim — every 3rd listing gets a pending claim.
		if ( $opts['claim'] && 0 === $index % 3 ) {
			$claimer = self::get_random_user_id( 'author' );
			if ( $claimer && $claimer > 1 ) {
				self::seed_claim(
					$post_id,
					$claimer,
					sprintf( 'I am the verified owner / manager of "%s". Please confirm my claim so I can keep the listing up to date.', get_the_title( $post_id ) ),
					0 === $index % 6 ? 'approved' : 'pending'
				);
			}
		}

		// Occasional favorite — every 2nd listing gets a favorite from a subscriber.
		if ( $opts['favorite'] && 0 === $index % 2 ) {
			$user = self::get_random_user_id( 'subscriber' );
			if ( $user && $user > 1 ) {
				self::seed_favorite( $user, $post_id );
			}
		}
	}
}
