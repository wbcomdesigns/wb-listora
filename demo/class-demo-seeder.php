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
	 * Curated Unsplash photo IDs per listing type. Each ID points to a specific,
	 * type-themed photo on the Unsplash CDN — stable URLs, no API key required
	 * at sideload time, license-free for embedding.
	 *
	 * Format: array of `photo-XXXX...` segments. The full URL is built in
	 * seed_featured_image() / seed_gallery() with Unsplash's resize params.
	 *
	 * To refresh / replace photos, use the wp-blog MCP `stock_unsplash_search`
	 * action and copy the photo URL stem (everything after `images.unsplash.com/`
	 * up to but not including `?`).
	 *
	 * @var array<string,string[]>
	 */
	private static $image_seeds = array(
		// Restaurant — gourmet plates, dining, food spreads.
		'restaurant'  => array(
			'photo-1600891964599-f61ba0e24092',
			'photo-1414235077428-338989a2e8c0',
			'photo-1544025162-d76694265947',
			'photo-1457460866886-40ef8d4b42a0',
			'photo-1651978595428-b79169f223a5',
			'photo-1522906456132-bac22adad34e',
			'photo-1598214886806-c87b84b7078b',
			'photo-1651440204296-a79fa9988007',
			'photo-1692197275441-40c874f40385',
			'photo-1513442542250-854d436a73f2',
			'photo-1543992321-cefacfc2322e',
			'photo-1625861910621-e9385ba1d993',
		),
		// Hotel — rooms, suites, lobbies.
		'hotel'       => array(
			'photo-1731336478850-6bce7235e320',
			'photo-1777016844282-46fa8713cdae',
			'photo-1729605411476-defbdab14c54',
			'photo-1776763018821-8feeaeeee0a5',
			'photo-1592229506151-845940174bb0',
			'photo-1592229505801-77b31918d822',
			'photo-1775866914767-7e4646f2481a',
			'photo-1666813721996-42956e40788e',
			'photo-1777169794972-12095816073b',
			'photo-1776763255122-3d35e32aee64',
		),
		// Real estate — modern home exteriors + interiors.
		'real-estate' => array(
			'photo-1600596542815-ffad4c1539a9',
			'photo-1582268611958-ebfd161ef9cf',
			'photo-1671621556339-d833f511ab5d',
			'photo-1613977257363-707ba9348227',
			'photo-1706808849802-8f876ade0d1f',
			'photo-1513584684374-8bab748fbf90',
			'photo-1706809019043-c16ada0165e9',
			'photo-1706808958118-48ca527f5a45',
			'photo-1627141234469-24711efb373c',
			'photo-1706808886508-e21834b4672c',
		),
		// Job — office workspaces, boardrooms, desks.
		'job'         => array(
			'photo-1718220216044-006f43e3a9b1',
			'photo-1572521165329-b197f9ea3da6',
			'photo-1497215728101-856f4ea42174',
			'photo-1499951360447-b19be8fe80f5',
			'photo-1497366754035-f200968a6e72',
			'photo-1497366811353-6870744d04b2',
			'photo-1462826303086-329426d1aef5',
			'photo-1583593687341-04ea577f0550',
			'photo-1688560952189-ef386cea744e',
			'photo-1678733405763-ecaf19dbccbe',
		),
		// Business — storefronts, shops, small businesses.
		'business'    => array(
			'photo-1610320022580-5295faad847c',
			'photo-1609023332227-9ff6324956b2',
			'photo-1549665332-82009840ecf0',
			'photo-1777151409209-8bdbbe478497',
			'photo-1776142519732-6d45cc4f5374',
			'photo-1758642177708-464dae4192e1',
			'photo-1707257049987-455830ccdfe9',
			'photo-1765637946011-5ff479760433',
			'photo-1665891118442-7857f0158b39',
			'photo-1705522330693-8efe3d9d8262',
		),
		// Classified — marketplace, goods for sale, items.
		'classified'  => array(
			'photo-1774082290292-eeef41f4d889',
			'photo-1674837012539-2b95066dcbcb',
			'photo-1716305443743-f57022d4d058',
			'photo-1767627242092-abefe2de63f3',
			'photo-1508589452764-4e017240add7',
			'photo-1674027392851-7b34f21b07ee',
			'photo-1716146755954-4f197a5b6031',
			'photo-1711982267134-884bc631ad2d',
			'photo-1715159999677-2dabb5cbaf6a',
			'photo-1699581913577-cc877cdae36b',
		),
		// Education — schools, classrooms, libraries, campus.
		'education'   => array(
			'photo-1641958070110-46b2ac7fe186',
			'photo-1728206313441-281ef4ea5d62',
			'photo-1728206415817-edd426280277',
			'photo-1728206348193-9b5ae74a7d32',
		),
		// Healthcare — clinics, doctors, medical offices.
		'healthcare'  => array(
			'photo-1774979161296-bb930552543a',
			'photo-1758691461516-7e716e0ca135',
			'photo-1758691462126-2ee47c8bf9e7',
			'photo-1766299892549-b56b257d1ddd',
			'photo-1758691463333-c79215e8bc3b',
			'photo-1758691463384-771db2f192b3',
			'photo-1710074213379-2a9c2653046a',
			'photo-1758691462878-6edc3d3da1be',
			'photo-1758691462858-f1286e5daf40',
			'photo-1758691462123-8a17ae95d203',
		),
		// Place — city landmarks, scenic destinations.
		'place'       => array(
			'photo-1697198649995-8a9807c19083',
			'photo-1764564180747-755550bab9b1',
			'photo-1703693932229-e0b2fb41f275',
			'photo-1632776265574-9a02142ed6cb',
			'photo-1769981620581-905c40ffe4a7',
			'photo-1771843870291-331498e7b41a',
			'photo-1636834620871-d22004dd9e07',
			'photo-1775582854287-83568dbc9c8e',
			'photo-1760543329069-8e2dddd0f214',
			'photo-1768211412332-259052acef63',
		),
		// Event — concerts, stages, audiences, festivals.
		'event'       => array(
			'photo-1669670617524-5f08060c8dcc',
			'photo-1767969457898-51d5e9cf81d2',
			'photo-1550697797-f01b4e83a1be',
			'photo-1542626333-39c5051198ef',
			'photo-1767990376277-2b5069d9a13a',
			'photo-1678705544620-5054cb2f6e6f',
			'photo-1646265780630-b639fcc8fc28',
			'photo-1621594761158-aa740720f806',
			'photo-1651439401606-fd2e05286dcb',
			'photo-1574672009742-218e990bec89',
		),
	);

	/**
	 * Build an Unsplash CDN URL for a given photo seed and dimensions.
	 *
	 * @param string $seed   Photo ID (e.g., `photo-1414235077428-338989a2e8c0`).
	 * @param int    $width  Target width.
	 * @param int    $height Target height.
	 * @return string Full HTTPS URL.
	 */
	private static function build_image_url( $seed, $width, $height ) {
		// crop=entropy keeps the most interesting region; q=80 balances
		// quality and file size; fm=jpg forces JPEG output regardless of
		// the source format on Unsplash's CDN.
		return sprintf(
			'https://images.unsplash.com/%s?w=%d&h=%d&fit=crop&crop=entropy&fm=jpg&q=80',
			rawurlencode( $seed ),
			(int) $width,
			(int) $height
		);
	}

	/**
	 * Detect the real image extension from the bytes of a downloaded file.
	 *
	 * Used by sideload_image() because remote URLs (Unsplash, Pexels, signed
	 * S3 URLs) often have no recognisable extension in the path, so the
	 * core wp_check_filetype_and_ext() refuses to import them. We sniff the
	 * file once we have it locally and force a sane filename for sideload.
	 *
	 * @param string $path Absolute path to a local file.
	 * @return string Extension without dot (jpg|png|gif|webp), or '' if not an image.
	 */
	private static function detect_image_extension( $path ) {
		if ( ! function_exists( 'getimagesize' ) || ! is_readable( $path ) ) {
			return '';
		}
		$info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $info ) || empty( $info[2] ) ) {
			return '';
		}
		switch ( (int) $info[2] ) {
			case IMAGETYPE_JPEG:
				return 'jpg';
			case IMAGETYPE_PNG:
				return 'png';
			case IMAGETYPE_GIF:
				return 'gif';
			case IMAGETYPE_WEBP:
				return 'webp';
			default:
				return '';
		}
	}

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
	 * Downloads to a tmp file, sniffs the real image type from bytes, then
	 * hands off to media_handle_sideload() with a forced filename. Avoids
	 * the URL-based filetype check in media_sideload_image(), which rejects
	 * extension-less CDN URLs (Unsplash, signed S3, etc). Failures are logged
	 * and return 0 so seeders keep working on slow networks or in CI.
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

		// We can't use media_sideload_image() because it calls wp_check_filetype_and_ext()
		// against the URL path, and CDNs like Unsplash serve images from extension-less
		// URLs (e.g. /photo-XYZ?fm=jpg). Download to tmp, then sideload with a forced
		// .jpg filename so the filetype check has something to bite on.
		try {
			$tmp = \download_url( $url, 30 );
		} catch ( \Throwable $e ) {
			error_log( '[wb-listora demo] download threw for ' . $url . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return 0;
		}

		if ( is_wp_error( $tmp ) ) {
			error_log( '[wb-listora demo] download failed for ' . $url . ': ' . $tmp->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return 0;
		}

		$ext = self::detect_image_extension( $tmp );
		if ( ! $ext ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.WP.AlternativeFunctions.unlink_unlink
			error_log( '[wb-listora demo] sideload failed for ' . $url . ': not a recognised image' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return 0;
		}

		$file_array = array(
			'name'     => 'listora-demo-' . md5( $url ) . '.' . $ext,
			'tmp_name' => $tmp,
		);

		try {
			$attachment_id = \media_handle_sideload( $file_array, $post_id, $alt );
		} catch ( \Throwable $e ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			error_log( '[wb-listora demo] sideload threw for ' . $url . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return 0;
		}

		// media_handle_sideload() removes tmp on success; clean up on error.
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
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
		$url   = self::build_image_url( $seed, 1200, 800 );

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
		// Each gallery image picks a different photo seed (no per-instance cropping
		// offset since Unsplash IDs are unique photos, not seed-derived variations).
		for ( $i = 0; $i < $count; $i++ ) {
			$seed_idx = ( $i + 2 ) % count( $seeds );
			$seed     = $seeds[ $seed_idx ];
			$url      = self::build_image_url( $seed, 1000, 700 );

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
