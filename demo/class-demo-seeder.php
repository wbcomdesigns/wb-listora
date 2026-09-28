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
	 * Per-image download timeout, in seconds.
	 *
	 * Kept deliberately low (10s) so a single slow, blocked, or 404 remote
	 * image cannot stall the whole import request for the WP-HTTP default of
	 * 30s. With the wizard's "all" pack running every pack in one synchronous
	 * POST, a 30s-per-image stall could stack into minutes; 10s caps the
	 * worst-case wait per image to a third of that. Filterable via
	 * `wb_listora_demo_image_timeout` for slow-network installs.
	 *
	 * @var int
	 */
	const IMAGE_DOWNLOAD_TIMEOUT = 10;

	/**
	 * Hard cap on the number of gallery images sideloaded per listing.
	 *
	 * Bounds the per-listing image cost regardless of the `gallery_count`
	 * a pack requests. Anything beyond the cap is skipped gracefully.
	 * Filterable via `wb_listora_demo_gallery_max`.
	 *
	 * @var int
	 */
	const GALLERY_MAX = 4;

	/**
	 * In-run cache of source URL => attachment ID for images already sideloaded
	 * during this request. Lets repeat URLs (the same Unsplash photo reused as a
	 * featured image on one listing and a gallery image on another) reuse the
	 * existing attachment instead of downloading it again. Reset per request —
	 * static lifetime is exactly the synchronous import we are bounding.
	 *
	 * @var array<string,int>
	 */
	private static $url_attachment_cache = array();

	/**
	 * Term IDs resolved by ensure_categories() / ensure_features() this request,
	 * keyed by taxonomy, then by the slug AND the name a pack asked for. Lets
	 * seed_listing() assign the term that actually exists instead of letting
	 * wp_set_object_terms() create a same-named twin.
	 *
	 * @var array<string,array<string,int>>
	 */
	private static $term_ids = array();

	/**
	 * Listing types already checked this request.
	 *
	 * @var array<string,bool>
	 */
	private static $types_ready = array();

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
				'post_status'    => self::all_statuses(),
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

		self::ensure_type( $data['type'] );
		wp_set_object_terms( $post_id, $data['type'], 'listora_listing_type' );

		if ( ! empty( $data['categories'] ) ) {
			$ids = self::resolve_terms( 'listora_listing_cat', $data['categories'] );
			wp_set_object_terms( $post_id, $ids, 'listora_listing_cat' );
			self::allow_terms( $data['type'], '_listora_allowed_categories', $ids );
		}
		if ( ! empty( $data['features'] ) ) {
			$ids = self::resolve_terms( 'listora_listing_feature', $data['features'] );
			wp_set_object_terms( $post_id, $ids, 'listora_listing_feature' );
			self::allow_terms( $data['type'], '_listora_allowed_features', $ids );
		}
		if ( ! empty( $data['tags'] ) ) {
			wp_set_object_terms( $post_id, $data['tags'], 'listora_listing_tag' );
		}

		// Assign hierarchical Country > State > City location terms from the
		// canonical address array. Every pack defines the address under
		// meta.address (city/state/country + lat/lng); four packs also duplicate
		// a top-level 'address'. Prefer meta.address so all nine packs build the
		// same location terms that match the _listora_address meta + geo coords.
		// Delegates to the shared Term_Helper (also consumed by Pro's importers).
		$location_address = array();
		if ( isset( $data['meta']['address'] ) && is_array( $data['meta']['address'] ) ) {
			$location_address = $data['meta']['address'];
		} elseif ( ! empty( $data['address'] ) && is_array( $data['address'] ) ) {
			$location_address = $data['address'];
		}
		if ( ! empty( $location_address ) ) {
			\WBListora\ImportExport\Term_Helper::set_location_terms( $post_id, $location_address );
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
	 * Ensure a pack's categories exist, each with an icon and a colour.
	 *
	 * A term is matched by name before slug, so a pack never creates a second
	 * "Coding Bootcamp" beside the one its listing type already made (card
	 * 10337192941). Icon and colour are only filled in when the term has none,
	 * so an owner's own choice is never overwritten.
	 *
	 * @param array<string,string|array{0:string,1?:string,2?:string}> $categories slug => name, or slug => array( name, Lucide icon, hex colour ).
	 */
	public static function ensure_categories( $categories ) {
		foreach ( $categories as $slug => $spec ) {
			$spec    = (array) $spec;
			$term_id = self::ensure_term( 'listora_listing_cat', (string) $slug, (string) $spec[0] );
			if ( ! $term_id ) {
				continue;
			}
			if ( ! empty( $spec[1] ) && ! get_term_meta( $term_id, '_listora_icon', true ) ) {
				update_term_meta( $term_id, '_listora_icon', sanitize_key( $spec[1] ) );
			}
			if ( ! empty( $spec[2] ) && ! get_term_meta( $term_id, '_listora_color', true ) ) {
				update_term_meta( $term_id, '_listora_color', (string) sanitize_hex_color( $spec[2] ) );
			}
		}
	}

	/**
	 * Ensure a pack's feature terms exist, matched by name before slug.
	 *
	 * @param array<string,string> $features slug => name.
	 */
	public static function ensure_features( $features ) {
		foreach ( $features as $slug => $name ) {
			self::ensure_term( 'listora_listing_feature', (string) $slug, (string) $name );
		}
	}

	/**
	 * Find or create one term and remember its ID under the slug and name asked for.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $slug     Requested slug.
	 * @param string $name     Requested name.
	 * @return int Term ID, 0 on failure.
	 */
	private static function ensure_term( $taxonomy, $slug, $name ) {
		self::track_created_terms();

		$term = get_term_by( 'name', $name, $taxonomy );
		if ( ! $term ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
		}
		if ( $term ) {
			$term_id = (int) $term->term_id;
		} else {
			$inserted = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
			$term_id  = is_wp_error( $inserted ) ? 0 : (int) $inserted['term_id'];
		}

		if ( $term_id ) {
			self::$term_ids[ $taxonomy ][ $slug ] = $term_id;
			self::$term_ids[ $taxonomy ][ $name ] = $term_id;
		}

		return $term_id;
	}

	/**
	 * Turn the slugs or names a listing carries into term IDs.
	 *
	 * Anything ensure_categories() / ensure_features() did not see is passed
	 * through, and wp_set_object_terms() resolves or creates it as before.
	 *
	 * @param string            $taxonomy Taxonomy.
	 * @param array<int,string> $terms    Slugs or names.
	 * @return array<int,int|string>
	 */
	private static function resolve_terms( $taxonomy, array $terms ) {
		$ids = array();
		foreach ( $terms as $term ) {
			$ids[] = self::$term_ids[ $taxonomy ][ $term ] ?? self::ensure_term( $taxonomy, sanitize_title( $term ), $term );
		}
		return array_values( array_filter( $ids ) );
	}

	/**
	 * Add demo terms to a listing type's allow-list.
	 *
	 * An empty allow-list means "every term", so it is left alone. A type that
	 * restricts its categories or features would otherwise hide the demo's own
	 * terms from its editor and filters.
	 *
	 * @param string          $type_slug Listing type slug.
	 * @param string          $meta_key  `_listora_allowed_categories` or `_listora_allowed_features`.
	 * @param array<int,mixed> $ids       Term IDs.
	 */
	private static function allow_terms( $type_slug, $meta_key, array $ids ) {
		$type = get_term_by( 'slug', $type_slug, 'listora_listing_type' );
		$have = $type ? array_map( 'intval', (array) get_term_meta( $type->term_id, $meta_key, true ) ) : array();
		if ( ! $type || ! array_filter( $have ) ) {
			return;
		}
		$merged = array_values( array_unique( array_merge( $have, array_map( 'intval', $ids ) ) ) );
		if ( count( $merged ) !== count( $have ) ) {
			update_term_meta( $type->term_id, $meta_key, $merged );
			\WBListora\Core\Listing_Type_Registry::instance()->flush();
		}
	}

	/**
	 * Make sure a listing's type is a real, configured type.
	 *
	 * Assigning a slug whose type was deleted used to leave a bare term with
	 * no fields and a lower-case name ("business"), which then showed in the
	 * directory chips and importers (card 10337192941).
	 *
	 * @param string $slug Listing type slug.
	 */
	private static function ensure_type( $slug ) {
		if ( isset( self::$types_ready[ $slug ] ) ) {
			return;
		}
		self::track_created_terms();
		\WBListora\Core\Listing_Type_Registry::instance()->install_default( $slug );
		self::$types_ready[ $slug ] = true;
	}

	/**
	 * Mark every Listora term created while seeding, so remove_all() can take
	 * back exactly what the demo added and nothing the owner made.
	 */
	private static function track_created_terms() {
		static $tracking = false;
		if ( $tracking ) {
			return;
		}
		$tracking = true;
		add_action(
			'created_term',
			static function ( $term_id, $tt_id, $taxonomy ) {
				if ( 0 === strpos( (string) $taxonomy, 'listora_' ) ) {
					update_term_meta( (int) $term_id, '_listora_demo_content', 1 );
				}
			},
			10,
			3
		);
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

		// De-dupe (in-run) — if this exact source URL was already sideloaded
		// during this request, reuse that attachment instead of downloading the
		// same remote image again. The demo packs reuse a small pool of Unsplash
		// photo IDs across listings (featured + gallery), so this collapses many
		// redundant 10s-capped downloads into a single fetch per unique URL.
		if ( isset( self::$url_attachment_cache[ $url ] ) && self::$url_attachment_cache[ $url ] > 0 ) {
			return self::$url_attachment_cache[ $url ];
		}

		// De-dupe (persisted) — if the same source URL already exists in the
		// media library from a previous run (matched by the source-URL meta we
		// stamp below), reuse it. Not parent-scoped: a demo photo imported for
		// any listing is fine to share, and skipping the re-download is the win.
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'       => '_listora_demo_image_src',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'     => $url,
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		if ( ! empty( $existing ) ) {
			$existing_id                        = (int) $existing[0];
			self::$url_attachment_cache[ $url ] = $existing_id;
			return $existing_id;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// We can't use media_sideload_image() because it calls wp_check_filetype_and_ext()
		// against the URL path, and CDNs like Unsplash serve images from extension-less
		// URLs (e.g. /photo-XYZ?fm=jpg). Download to tmp, then sideload with a forced
		// .jpg filename so the filetype check has something to bite on.
		// Cap the per-image download wait so one slow/blocked/404 remote image
		// can't stall the synchronous import. Filterable for slow-network sites.
		$timeout = (int) apply_filters( 'wb_listora_demo_image_timeout', self::IMAGE_DOWNLOAD_TIMEOUT, $url );
		if ( $timeout < 1 ) {
			$timeout = self::IMAGE_DOWNLOAD_TIMEOUT;
		}

		try {
			$tmp = \download_url( $url, $timeout );
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
			// Remember for the rest of this run so the same URL isn't re-fetched.
			self::$url_attachment_cache[ $url ] = $attachment_id;
		}

		return $attachment_id;
	}

	/**
	 * Curated Unsplash photo stems for a demo listing.
	 *
	 * demo/photos.php maps each listing title to its own photos: the first is
	 * the featured image, the rest the gallery. Every photo is chosen for that
	 * listing and none repeats within a listing type (card 10337192941), which
	 * a shared per-type pool could not guarantee. A title with no entry gets no
	 * photos: an empty image slot reads better than a wrong one.
	 *
	 * @param int $post_id Listing post ID.
	 * @return string[] Photo stems, featured first.
	 */
	private static function photos_for( $post_id ) {
		static $map = null;
		if ( null === $map ) {
			$map = (array) require __DIR__ . '/photos.php';
		}
		$title = html_entity_decode( (string) get_post_field( 'post_title', $post_id, 'raw' ), ENT_QUOTES, 'UTF-8' );
		return array_values( (array) ( $map[ $title ] ?? array() ) );
	}

	/**
	 * Set a listing's curated featured image.
	 *
	 * @param int $post_id Listing post ID.
	 * @return int Attachment ID, or 0 on failure / when images are skipped.
	 */
	public static function seed_featured_image( $post_id ) {
		if ( self::$skip_images ) {
			return 0;
		}
		if ( has_post_thumbnail( $post_id ) ) {
			return (int) get_post_thumbnail_id( $post_id );
		}

		$photos = self::photos_for( $post_id );
		if ( ! $photos ) {
			return 0;
		}

		$attachment_id = self::sideload_image( self::build_image_url( $photos[0], 1200, 800 ), $post_id, get_the_title( $post_id ) );
		if ( $attachment_id > 0 ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}

		return $attachment_id;
	}

	/**
	 * Sideload a listing's curated gallery into `_listora_gallery` (the key
	 * the submission UI writes to).
	 *
	 * @param int $post_id Listing post ID.
	 * @param int $count   Most gallery images to add. Default GALLERY_MAX.
	 * @return int[] Attachment IDs in the gallery (may be empty).
	 */
	public static function seed_gallery( $post_id, $count = self::GALLERY_MAX ) {
		if ( self::$skip_images || $count < 1 ) {
			return array();
		}

		// Bound the per-listing sideload cost so the synchronous wizard import stays predictable.
		$type   = wp_get_object_terms( $post_id, 'listora_listing_type', array( 'fields' => 'slugs' ) );
		$max    = (int) apply_filters( 'wb_listora_demo_gallery_max', self::GALLERY_MAX, is_array( $type ) ? (string) reset( $type ) : '' );
		$max    = $max > 0 ? $max : self::GALLERY_MAX;
		$photos = array_slice( self::photos_for( $post_id ), 1, min( (int) $count, $max ) );

		$existing = get_post_meta( $post_id, '_listora_gallery', true );
		$ids      = is_array( $existing ) ? array_map( 'intval', $existing ) : array();
		if ( count( $ids ) >= count( $photos ) ) {
			return $ids;
		}

		$alt = get_the_title( $post_id );
		foreach ( $photos as $stem ) {
			$ids[] = self::sideload_image( self::build_image_url( $stem, 1000, 700 ), $post_id, $alt );
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( $ids ) {
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

	/**
	 * Seed 30 days of views and contact clicks for a demo listing.
	 *
	 * A fresh demo otherwise shows 0 views on every dashboard and chart
	 * (card 10337192941). Uses the same one-row-per-listing-event-day shape
	 * Analytics_Lite writes. Skipped when the listing already has history, so
	 * a re-run never inflates it. The rows go with the listing: the data
	 * eraser cascades `analytics` on delete.
	 *
	 * @param int $listing_id Listing post ID.
	 * @param int $days       Days of history. Default 30.
	 */
	public static function seed_analytics( $listing_id, $days = 30 ) {
		global $wpdb;
		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'analytics';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- demo seeding, idx_listing.
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE listing_id = %d LIMIT 1', $table, $listing_id ) ) ) {
			return;
		}

		// Featured listings draw more traffic, as they would on a real site.
		$base   = wp_rand( 4, 16 ) * ( get_post_meta( $listing_id, '_listora_is_featured', true ) ? 2 : 1 );
		$values = array();
		$params = array();
		for ( $d = 0; $d < $days; $d++ ) {
			$date  = gmdate( 'Y-m-d', time() - $d * DAY_IN_SECONDS );
			$views = max( 1, $base + wp_rand( -3, 6 ) );
			$day   = array(
				'view'            => $views,
				'phone_click'     => wp_rand( 0, (int) ceil( $views / 10 ) ),
				'website_click'   => wp_rand( 0, (int) ceil( $views / 8 ) ),
				'direction_click' => wp_rand( 0, (int) ceil( $views / 12 ) ),
			);
			foreach ( array_filter( $day ) as $event => $count ) {
				$values[] = '(%d, %s, %s, %d)';
				array_push( $params, $listing_id, $event, $date, $count );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one bounded multi-row insert; placeholders built above.
		$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (listing_id, event_type, event_date, count) VALUES ' . implode( ', ', $values ), array_merge( array( $table ), $params ) ) );
	}

	// ─── Pack Convenience ───

	/**
	 * After a listing is seeded, attach images, services, an occasional claim,
	 * and an occasional favorite. Pack files call this in their seed loop so
	 * each pack stays focused on listing data, not boilerplate.
	 *
	 * @param int    $post_id    Seeded listing ID (return value of seed_listing()).
	 * @param int    $index      Position in pack (sets the claim / favourite cadence).
	 * @param array  $services   Optional. List of services. Each item:
	 *                           [ title, price?, duration_min?, description?, category? ].
	 * @param array  $opts       {
	 *     Optional behavior flags.
	 *     @type bool $featured_image  Add featured image. Default true.
	 *     @type int  $gallery_count   Most gallery images. Default GALLERY_MAX.
	 *     @type bool $claim           Maybe seed a claim. Default true.
	 *     @type bool $favorite        Maybe seed a favorite. Default true.
	 *     @type bool $analytics       Seed 30 days of views and clicks. Default true.
	 * }
	 */
	public static function seed_pack_extras( $post_id, $index = 0, $services = array(), $opts = array() ) {
		if ( ! $post_id ) {
			return;
		}

		$opts = array_merge(
			array(
				'featured_image' => true,
				'gallery_count'  => self::GALLERY_MAX,
				'claim'          => true,
				'favorite'       => true,
				'analytics'      => true,
			),
			$opts
		);

		if ( $opts['featured_image'] ) {
			self::seed_featured_image( $post_id );
		}
		if ( $opts['gallery_count'] > 0 ) {
			self::seed_gallery( $post_id, (int) $opts['gallery_count'] );
		}
		if ( $opts['analytics'] ) {
			self::seed_analytics( $post_id );
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

	/**
	 * Count demo listings + demo attachments currently in the database.
	 *
	 * Single source of truth for "how much demo content exists", used by both
	 * the WP-CLI command and the admin "Delete demo data" control so the two
	 * can never disagree.
	 *
	 * @return array{listings:int, attachments:int}
	 */
	/**
	 * Every registered post status, so demo queries also reach listings in
	 * the plugin's own statuses. `'any'` skips statuses registered with
	 * `exclude_from_search` (listora_expired and friends), which left expired
	 * demo listings behind on remove and let a reseed duplicate them.
	 *
	 * @return string[]
	 */
	private static function all_statuses() {
		return array_keys( get_post_stati() );
	}

	public static function count_demo_content() {
		$listings = get_posts(
			array(
				'post_type'      => 'listora_listing',
				'post_status'    => self::all_statuses(),
				'posts_per_page' => -1,
				'meta_key'       => '_listora_demo_content', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- demo content lookup, admin-only.
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- demo content lookup, admin-only.
				'fields'         => 'ids',
			)
		);

		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => '_listora_demo_content', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- demo content lookup, admin-only.
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- demo content lookup, admin-only.
				'fields'         => 'ids',
			)
		);

		return array(
			'listings'    => count( $listings ),
			'attachments' => count( $attachments ),
		);
	}

	/**
	 * Permanently delete all demo content (listings + their demo attachments).
	 *
	 * The single canonical remover. Both `wp listora demo remove` and the
	 * admin "Delete demo data" button call this so the deletion logic lives in
	 * exactly one place (card 10020109923). Batched so a large demo dataset
	 * doesn't exhaust memory on shared hosting.
	 *
	 * @return array{listings:int, attachments:int, terms:int, users:int} Counts actually deleted.
	 */
	public static function remove_all() {
		$deleted = array(
			'listings'    => 0,
			'attachments' => 0,
		);

		// Delete demo listings in batches.
		do {
			$listings = get_posts(
				array(
					'post_type'      => 'listora_listing',
					'post_status'    => self::all_statuses(),
					'posts_per_page' => 200,
					'meta_key'       => '_listora_demo_content', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- demo content cleanup, admin-only.
					'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- demo content cleanup, admin-only.
					'fields'         => 'ids',
				)
			);
			foreach ( $listings as $id ) {
				if ( wp_delete_post( (int) $id, true ) ) {
					++$deleted['listings'];
				}
			}
		} while ( ! empty( $listings ) );

		// Delete orphan demo attachments in batches.
		do {
			$attachments = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'any',
					'posts_per_page' => 200,
					'meta_key'       => '_listora_demo_content', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- demo content cleanup, admin-only.
					'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- demo content cleanup, admin-only.
					'fields'         => 'ids',
				)
			);
			foreach ( $attachments as $att_id ) {
				if ( wp_delete_attachment( (int) $att_id, true ) ) {
					++$deleted['attachments'];
				}
			}
		} while ( ! empty( $attachments ) );

		$deleted['terms'] = self::remove_demo_terms();

		// The test accounts all share the password "password", so they must
		// not outlive the demo. Anything they authored goes to the person
		// removing the demo rather than being deleted with them.
		$deleted['users'] = 0;
		$reassign         = get_current_user_id() ? get_current_user_id() : 1;
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( get_users(
			array(
				'meta_key'   => '_listora_demo_user', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- demo cleanup, admin-only.
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- demo cleanup, admin-only.
				'fields'     => 'ID',
				'exclude'    => array( $reassign ),
			)
		) as $user_id ) {
			if ( wp_delete_user( (int) $user_id, $reassign ) ) {
				++$deleted['users'];
			}
		}

		return $deleted;
	}

	/**
	 * Delete the terms seeding created, once nothing uses them.
	 *
	 * Only terms stamped by track_created_terms() are candidates, and only when
	 * empty and childless, so an owner's own categories, and any demo term an
	 * owner has since used, stay. Repeats so a location hierarchy empties
	 * leaf-first. Removes demo-created listing types the same way.
	 *
	 * @return int Terms deleted.
	 */
	private static function remove_demo_terms() {
		$deleted    = 0;
		$taxonomies = array_values( array_filter( get_taxonomies(), static fn( $tax ) => 0 === strpos( $tax, 'listora_' ) ) );

		do {
			$round = 0;
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomies,
					'hide_empty' => false,
					'meta_key'   => '_listora_demo_content', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- demo cleanup, admin-only.
					'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- demo cleanup, admin-only.
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				if ( $term->count > 0 || get_term_children( $term->term_id, $term->taxonomy ) ) {
					continue;
				}
				if ( true === wp_delete_term( $term->term_id, $term->taxonomy ) ) {
					++$round;
				}
			}
			$deleted += $round;
		} while ( $round > 0 );

		if ( $deleted ) {
			\WBListora\Core\Listing_Type_Registry::instance()->flush();
		}

		return $deleted;
	}
}
