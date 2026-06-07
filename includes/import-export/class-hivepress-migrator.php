<?php
/**
 * HivePress Migrator — imports listings from the HivePress directory plugin.
 *
 * @package WBListora\ImportExport
 */

namespace WBListora\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Migrates listings, categories, locations, images, and reviews from
 * HivePress (CPT: `hp_listing`, hierarchical taxonomy `hp_listing_category`).
 *
 * Schema source-of-truth: `audit/architecture/competitor-schemas/hivepress.md`
 * (compiled against HivePress v1.7.23). Notable shape decisions taken from
 * that audit:
 *
 * - Everything lives in standard WP tables — HivePress core declares NO
 *   custom tables (§5), so detection / counting / pagination read `wp_posts`
 *   + `wp_postmeta` directly.
 * - The Model abstraction auto-prefixes meta keys with `hp_` (§4); code-level
 *   field names (`featured`) are NOT the stored meta keys (`hp_featured`).
 * - Gallery images are SEPARATE child attachment posts linked back by
 *   `post_parent` + `hp_parent_field = 'images'` meta (§8) — a different
 *   mechanism from the singular featured image (`_thumbnail_id`).
 * - HivePress core has NO reviews/ratings (§7) and NO geo (§9); both only
 *   exist via separate extensions. The migrator skips them gracefully but
 *   still sweeps WP comments carrying a rating, so sites running a HivePress
 *   reviews extension that stores ratings as comment meta are covered
 *   defensively without assuming a specific extension schema.
 * - Ownership is two-level: User -> Vendor -> Listing (§11). `post_parent`
 *   may be 0 (no vendor, owner is `post_author`) or a `hp_vendor` post whose
 *   own `post_author` is the owning user. We resolve the effective owner.
 */
class Hivepress_Migrator extends Migration_Base {

	/**
	 * HivePress listing CPT slug (schema §2).
	 *
	 * @var string
	 */
	const SOURCE_POST_TYPE = 'hp_listing';

	/**
	 * HivePress vendor CPT slug (schema §2, §11).
	 *
	 * @var string
	 */
	const VENDOR_POST_TYPE = 'hp_vendor';

	/**
	 * HivePress listing category taxonomy (schema §3, §10).
	 *
	 * @var string
	 */
	const SOURCE_TAXONOMY = 'hp_listing_category';

	/**
	 * Post statuses considered migratable (schema §6 status enum, minus the
	 * internal `auto-draft` / `inherit` values).
	 *
	 * SQL-literal form — these are fixed, trusted literals (never user input),
	 * so they are inlined into the `IN (...)` clauses the same way the sibling
	 * migrators (BDP / ListingPro) do, keeping `$wpdb->prepare()` free of
	 * dynamically-built placeholder lists.
	 *
	 * @var string
	 */
	const SOURCE_STATUS_SQL = "'publish', 'pending', 'draft', 'private', 'future'";

	/**
	 * Post statuses considered migratable, as an array for in-PHP comparisons.
	 *
	 * @var string[]
	 */
	const SOURCE_STATUSES = array( 'publish', 'pending', 'draft', 'private', 'future' );

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->source_slug        = 'hivepress';
		$this->source_name        = 'HivePress';
		$this->source_description = __( 'Migrate listings, categories, locations, images, and reviews from HivePress.', 'wb-listora' );
	}

	/**
	 * Detect if HivePress data exists.
	 *
	 * Works even when HivePress is deactivated: first checks CPT registration,
	 * then falls back to a direct `wp_posts` count, then confirms it is genuine
	 * HivePress data by probing for a HivePress-specific `hp_*` postmeta key.
	 *
	 * @return bool
	 */
	public function detect() {
		global $wpdb;

		// Fast path: HivePress active and CPT + taxonomy registered.
		if ( post_type_exists( self::SOURCE_POST_TYPE ) && taxonomy_exists( self::SOURCE_TAXONOMY ) ) {
			return true;
		}

		// Slow path: HivePress deactivated — inspect the database directly.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
				self::SOURCE_POST_TYPE
			)
		);

		if ( $post_count < 1 ) {
			return false;
		}

		// Confirm it is HivePress data (every HivePress meta key is `hp_`-prefixed,
		// schema §1) rather than an unrelated `hp_listing` CPT.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$has_hp_meta = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ('hp_drafted', 'hp_featured', 'hp_verified', 'hp_expired_time') LIMIT 1"
		);

		return $has_hp_meta > 0;
	}

	/**
	 * Count source HivePress listings.
	 *
	 * @return int
	 */
	public function get_source_count() {
		global $wpdb;

		// SOURCE_STATUS_SQL is a fixed literal list (no user input); only the
		// post type is parameterised.
		$sql = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ( " . self::SOURCE_STATUS_SQL . ' )'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare( $sql, self::SOURCE_POST_TYPE ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Get source post IDs for pagination.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return int[]
	 */
	protected function get_source_ids( $offset, $limit ) {
		global $wpdb;

		// SOURCE_STATUS_SQL is a fixed literal list (no user input); the post
		// type, limit, and offset are parameterised.
		$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ( " . self::SOURCE_STATUS_SQL . ' ) ORDER BY ID ASC LIMIT %d OFFSET %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare( $sql, self::SOURCE_POST_TYPE, absint( $limit ), absint( $offset ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return array_map( 'absint', $ids );
	}

	/**
	 * Migrate a single HivePress listing.
	 *
	 * @param int $source_id Source post ID.
	 * @return array<string, mixed> Migration result.
	 */
	public function migrate_listing( $source_id ) {
		$post = get_post( $source_id );

		if ( ! $post || self::SOURCE_POST_TYPE !== $post->post_type ) {
			return array(
				'status'  => 'error',
				'post_id' => 0,
				'message' => sprintf(
					/* translators: %d: source post ID */
					__( 'Source listing #%d not found.', 'wb-listora' ),
					$source_id
				),
			);
		}

		$data = array(
			'title'      => $post->post_title,
			'content'    => $post->post_content,
			'status'     => $this->map_status( $post ),
			'author_id'  => $this->resolve_owner( $post ),
			'date'       => $post->post_date,
			'source_id'  => $source_id,
			'thumbnail'  => get_post_thumbnail_id( $source_id ),
			'meta'       => $this->map_meta( $source_id ),
			'taxonomies' => $this->map_taxonomies( $source_id ),
		);

		$post_id = $this->create_listing( $data );

		if ( is_wp_error( $post_id ) ) {
			return array(
				'status'  => 'error',
				'post_id' => 0,
				'message' => sprintf(
					/* translators: 1: source ID, 2: error message */
					__( 'Listing #%1$d: %2$s', 'wb-listora' ),
					$source_id,
					$post_id->get_error_message()
				),
			);
		}

		// Gallery: HivePress child attachments (schema §8).
		$this->migrate_gallery( $source_id, $post_id );

		// Reviews: HivePress core has none (schema §7); defensively sweep any
		// rating-carrying WP comments left by a reviews extension.
		$this->migrate_reviews( $source_id, $post_id );

		// Index the listing.
		$this->index_listing( $post_id );

		/**
		 * Mirrors the other Free migrators' fire-site so bulk-migrated listings
		 * reach the context-aware `wb_listora_listing_submitted` listeners. The
		 * 4th `$context` arg with `source => 'migration'` keeps notification +
		 * activity-feed + credit listeners quiet for bulk imports while the
		 * audit-log + moderator listeners still run (see CLAUDE.md
		 * "Migration-context arg" + tests/qa/journeys/regression/migrator-context-arg.md).
		 */
		do_action(
			'wb_listora_listing_submitted',
			$post_id,
			'publish',
			null,
			array(
				'source'   => 'migration',
				'migrator' => static::class,
			)
		);

		return array(
			'status'  => 'imported',
			'post_id' => $post_id,
			'message' => sprintf(
				/* translators: 1: source ID, 2: new listing ID */
				__( 'Listing #%1$d migrated as #%2$d.', 'wb-listora' ),
				$source_id,
				$post_id
			),
		);
	}

	/**
	 * Return the HivePress field catalog Pro's visual mapper UI shows.
	 *
	 * Sourced from `audit/architecture/competitor-schemas/hivepress.md`
	 * §6 (listing field map) + §8 (image/gallery storage) + §10 (categories).
	 * HivePress core stores everything in standard WP tables (§5), so the
	 * implicit `source_table` is `wp_postmeta` for meta rows and
	 * `wp_term_relationships` for the taxonomy row.
	 *
	 * `source_key` uses the real STORED meta key (`hp_featured`, not the
	 * model-level field name `featured`) per the model-prefix gotcha (§13).
	 *
	 * @since 1.2.0
	 * @return array<int, array<string, mixed>>
	 */
	public function detect_source_fields(): array {
		return array(
			array(
				'source_key'   => 'post_title',
				'label'        => __( 'Title', 'wb-listora' ),
				'type'         => 'text',
				'source_table' => 'wp_posts',
			),
			array(
				'source_key'   => 'post_content',
				'label'        => __( 'Description', 'wb-listora' ),
				'type'         => 'text',
				'source_table' => 'wp_posts',
			),
			array(
				'source_key'   => 'hp_featured',
				'label'        => __( 'Featured', 'wb-listora' ),
				'type'         => 'boolean',
				'source_table' => 'wp_postmeta',
			),
			array(
				'source_key'   => 'hp_verified',
				'label'        => __( 'Verified', 'wb-listora' ),
				'type'         => 'boolean',
				'source_table' => 'wp_postmeta',
			),
			array(
				'source_key'   => 'hp_drafted',
				'label'        => __( 'Drafted', 'wb-listora' ),
				'type'         => 'boolean',
				'source_table' => 'wp_postmeta',
			),
			array(
				'source_key'   => 'hp_expired_time',
				'label'        => __( 'Expiration time', 'wb-listora' ),
				'type'         => 'datetime',
				'source_table' => 'wp_postmeta',
			),
			array(
				'source_key'   => 'hp_featured_time',
				'label'        => __( 'Featured until', 'wb-listora' ),
				'type'         => 'datetime',
				'source_table' => 'wp_postmeta',
			),
			array(
				'source_key'   => '_thumbnail_id',
				'label'        => __( 'Featured image', 'wb-listora' ),
				'type'         => 'image',
				'source_table' => 'wp_postmeta',
			),
			array(
				'source_key'   => 'hp_images',
				'label'        => __( 'Gallery images', 'wb-listora' ),
				'type'         => 'gallery',
				'source_table' => 'wp_posts',
			),
			array(
				'source_key'   => 'post_author',
				'label'        => __( 'Owner', 'wb-listora' ),
				'type'         => 'number',
				'source_table' => 'wp_posts',
			),
			array(
				'source_key'   => self::SOURCE_TAXONOMY,
				'label'        => __( 'Categories', 'wb-listora' ),
				'type'         => 'taxonomy',
				'source_table' => 'wp_term_relationships',
			),
		);
	}

	/**
	 * Default Listora -> HivePress source-key mapping the visual mapper UI
	 * pre-fills. Keys are Listora canonical fields; values are the
	 * `source_key`s from {@see detect_source_fields()}.
	 *
	 * Sourced from `audit/architecture/competitor-schemas/hivepress.md` §6.
	 *
	 * @since 1.2.0
	 * @return array<string, string>
	 */
	public function get_default_mapping(): array {
		return array(
			'title'          => 'post_title',
			'content'        => 'post_content',
			'featured'       => 'hp_featured',
			'verified'       => 'hp_verified',
			'featured_image' => '_thumbnail_id',
			'gallery'        => 'hp_images',
			'categories'     => self::SOURCE_TAXONOMY,
		);
	}

	/**
	 * Single-listing extraction for Pro's preview surface — returns the mapped
	 * data for ONE source listing WITHOUT writing anything. Mirrors the shape
	 * {@see migrate_listing()} would create so the admin can confirm a sample
	 * before committing a bulk run.
	 *
	 * @since 1.2.0
	 * @param int $source_id Source-plugin listing ID.
	 * @return array<string, mixed>
	 */
	public function extract_for_preview( int $source_id ): array {
		$post = get_post( $source_id );

		if ( ! $post || self::SOURCE_POST_TYPE !== $post->post_type ) {
			return array();
		}

		return array(
			'source_id'  => $source_id,
			'title'      => $post->post_title,
			'content'    => $post->post_content,
			'status'     => $this->map_status( $post ),
			'author_id'  => $this->resolve_owner( $post ),
			'date'       => $post->post_date,
			'thumbnail'  => get_post_thumbnail_id( $source_id ),
			'gallery'    => $this->get_gallery_attachment_ids( $source_id ),
			'meta'       => $this->map_meta( $source_id ),
			'taxonomies' => $this->map_taxonomies( $source_id ),
		);
	}

	/**
	 * Map HivePress listing status to a Listora post status.
	 *
	 * HivePress's `hp_drafted` checkbox (schema §13) is SEPARATE from
	 * `post_status` — a `publish` post flagged `hp_drafted=1` is semantically
	 * a draft. We honour the flag so half-finished HivePress listings don't
	 * land published on the new site.
	 *
	 * @param \WP_Post $post Source listing.
	 * @return string Listora post status.
	 */
	private function map_status( $post ) {
		if ( (bool) $this->get_source_meta( $post->ID, 'hp_drafted' ) ) {
			return 'draft';
		}

		return in_array( $post->post_status, self::SOURCE_STATUSES, true ) ? $post->post_status : 'draft';
	}

	/**
	 * Resolve the effective owning user for a HivePress listing.
	 *
	 * Ownership is two-level (schema §11): a listing's `post_parent` may point
	 * at a `hp_vendor` post, whose own `post_author` is the owning user. When
	 * there is no vendor (`post_parent = 0`), ownership is directly the
	 * listing's `post_author`.
	 *
	 * @param \WP_Post $post Source listing.
	 * @return int Owning user ID.
	 */
	private function resolve_owner( $post ) {
		$author = (int) $post->post_author;

		$vendor_id = (int) $post->post_parent;
		if ( $vendor_id > 0 ) {
			$vendor = get_post( $vendor_id );
			if ( $vendor && self::VENDOR_POST_TYPE === $vendor->post_type && (int) $vendor->post_author > 0 ) {
				return (int) $vendor->post_author;
			}
		}

		return $author;
	}

	/**
	 * Map HivePress listing meta to Listora meta.
	 *
	 * HivePress core's listing fields are mostly lifecycle flags (schema §6) —
	 * contact / address / price fields only exist via extensions and are not
	 * assumed here. We carry the flags that have a Listora equivalent.
	 *
	 * @param int $source_id Source post ID.
	 * @return array<string, mixed> Key => value pairs for Listora meta.
	 */
	private function map_meta( $source_id ) {
		$meta = array();

		if ( (bool) $this->get_source_meta( $source_id, 'hp_featured' ) ) {
			$meta['is_featured'] = 1;
		}

		if ( (bool) $this->get_source_meta( $source_id, 'hp_verified' ) ) {
			$meta['is_verified'] = 1;
		}

		return $meta;
	}

	/**
	 * Map HivePress taxonomies to Listora taxonomies.
	 *
	 * HivePress core has one listing taxonomy, `hp_listing_category`
	 * (hierarchical, schema §3/§10). Locations in HivePress core are NOT a
	 * taxonomy — they only exist via the Geolocation extension (schema §9), so
	 * there is no core location taxonomy to map. The direction's "locations"
	 * coverage is therefore satisfied at the geo/term layer when an extension
	 * is present, which is out of core scope and recorded as a known gap.
	 *
	 * The parent->child hierarchy of the source categories is preserved by
	 * reading each term with its parent name and letting the base
	 * {@see Migration_Base::map_taxonomy_terms()} resolve hierarchical entries
	 * (which itself routes flat names through the canonical Term_Helper).
	 *
	 * @param int $source_id Source post ID.
	 * @return array<string, array<int, array<string, string>>> Taxonomy => terms array.
	 */
	private function map_taxonomies( $source_id ) {
		$taxonomies = array();

		$category_terms = $this->get_hierarchical_terms( $source_id, self::SOURCE_TAXONOMY );
		if ( ! empty( $category_terms ) ) {
			$taxonomies['listora_listing_cat'] = $category_terms;
		}

		return $taxonomies;
	}

	/**
	 * Read source terms for a taxonomy as hierarchical entries the base
	 * mapper understands: each entry is an array with `name` and an optional
	 * `parent` name, so the Country/State or parent/child category structure
	 * survives the migration.
	 *
	 * @param int    $source_id Source post ID.
	 * @param string $taxonomy  Source taxonomy slug.
	 * @return array<int, array<string, string>> Term entries (`name`, optional `parent`).
	 */
	private function get_hierarchical_terms( $source_id, $taxonomy ) {
		$terms = wp_get_object_terms( $source_id, $taxonomy );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$entries = array();
		foreach ( $terms as $term ) {
			$entry = array( 'name' => $term->name );

			if ( (int) $term->parent > 0 ) {
				$parent = get_term( (int) $term->parent, $taxonomy );
				if ( $parent && ! is_wp_error( $parent ) ) {
					$entry['parent'] = $parent->name;
				}
			}

			$entries[] = $entry;
		}

		return $entries;
	}

	/**
	 * Migrate HivePress gallery images.
	 *
	 * HivePress stores gallery images as SEPARATE child attachment posts
	 * (schema §8) linked back to the listing by `post_parent` plus a
	 * `hp_parent_field = 'images'` postmeta marker, ordered by `menu_order`.
	 * This is distinct from the singular featured image (`_thumbnail_id`),
	 * which {@see create_listing()} already carries.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $post_id   Listora listing ID.
	 */
	private function migrate_gallery( $source_id, $post_id ): void {
		$attachment_ids = $this->get_gallery_attachment_ids( $source_id );

		if ( ! empty( $attachment_ids ) ) {
			\WBListora\Core\Meta_Handler::set_value( $post_id, 'gallery', $attachment_ids );
		}
	}

	/**
	 * Resolve the gallery child-attachment IDs for a HivePress listing.
	 *
	 * Shared by {@see migrate_gallery()} (write path) and
	 * {@see extract_for_preview()} (read-only preview path).
	 *
	 * @param int $source_id Source post ID.
	 * @return int[] Attachment IDs in gallery order.
	 */
	private function get_gallery_attachment_ids( $source_id ) {
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_parent'    => absint( $source_id ),
				'posts_per_page' => 50,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// HivePress marks gallery attachments with this meta (schema §8).
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'       => 'hp_parent_field',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'     => 'images',
			)
		);

		return array_map( 'absint', (array) $attachments );
	}

	/**
	 * Migrate reviews for a HivePress listing.
	 *
	 * HivePress core (v1.7.23) stores NO reviews or ratings (schema §7). This
	 * method therefore makes no assumption about a specific reviews-extension
	 * schema; it sweeps standard WP comments and only migrates those carrying
	 * a numeric rating in the conventional `rating` comment meta. Sites without
	 * a reviews extension simply have nothing to migrate here.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $post_id   Listora listing ID.
	 */
	private function migrate_reviews( $source_id, $post_id ): void {
		$comments = get_comments(
			array(
				'post_id' => absint( $source_id ),
				'status'  => 'all',
			)
		);

		if ( empty( $comments ) || ! is_array( $comments ) ) {
			return;
		}

		foreach ( $comments as $comment ) {
			// Default `get_comments()` returns WP_Comment objects; guard so the
			// `fields => 'ids'` shape (or a filtered override) cannot fatal.
			if ( ! $comment instanceof \WP_Comment ) {
				continue;
			}

			$rating = (int) get_comment_meta( (int) $comment->comment_ID, 'rating', true );

			// No rating means it is an ordinary comment, not a review — skip.
			if ( $rating < 1 ) {
				continue;
			}

			// Skip spam / trashed comments.
			if ( in_array( (string) $comment->comment_approved, array( 'spam', 'trash' ), true ) ) {
				continue;
			}

			$this->insert_review(
				array(
					'listing_id'     => $post_id,
					'user_id'        => (int) $comment->user_id,
					'overall_rating' => min( 5, max( 1, $rating ) ),
					'title'          => '',
					'content'        => $comment->comment_content,
					'status'         => '1' === (string) $comment->comment_approved ? 'approved' : 'pending',
					'created_at'     => $comment->comment_date,
				)
			);
		}
	}
}
