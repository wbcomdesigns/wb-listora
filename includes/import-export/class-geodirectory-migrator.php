<?php
/**
 * GeoDirectory Migrator — imports listings from GeoDirectory plugin.
 *
 * @package WBListora\ImportExport
 */

namespace WBListora\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Migrates listings, categories, tags, reviews, and images
 * from GeoDirectory (CPT: gd_place and custom gd_* CPTs).
 *
 * GeoDirectory stores listing details in its own table ({prefix}geodir_gd_place_detail)
 * rather than standard post meta.
 */
class Geodirectory_Migrator extends Migration_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->source_slug        = 'geodirectory';
		$this->source_name        = 'GeoDirectory';
		$this->source_description = __( 'Migrate listings, categories, locations, and reviews from GeoDirectory.', 'wb-listora' );
	}

	/**
	 * Detect if GeoDirectory data exists.
	 *
	 * Checks if the geodir_gd_place_detail table exists.
	 *
	 * @return bool
	 */
	public function detect() {
		global $wpdb;

		$table = $wpdb->prefix . 'geodir_gd_place_detail';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		return null !== $result;
	}

	/**
	 * Count source GeoDirectory listings.
	 *
	 * @return int
	 */
	public function get_source_count() {
		global $wpdb;

		$table = $wpdb->prefix . 'geodir_gd_place_detail';

		if ( ! $this->detect() ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
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

		$table = $wpdb->prefix . 'geodir_gd_place_detail';

		if ( ! $this->detect() ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$table} ORDER BY post_id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit,
				$offset
			)
		);

		return array_map( 'absint', $ids );
	}

	/**
	 * Migrate a single GeoDirectory listing.
	 *
	 * @param int $source_id Source post ID.
	 * @return array Migration result.
	 */
	public function migrate_listing( $source_id ) {
		global $wpdb;

		$post  = get_post( $source_id );
		$table = $wpdb->prefix . 'geodir_gd_place_detail';

		if ( ! $post ) {
			return array(
				'status'  => 'error',
				'post_id' => 0,
				'message' => sprintf(
					/* translators: %d: source post ID */
					__( 'Source listing #%d not found in wp_posts.', 'wb-listora' ),
					$source_id
				),
			);
		}

		// Get the detail row.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$detail = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$source_id
			),
			ARRAY_A
		);

		if ( ! $detail ) {
			return array(
				'status'  => 'error',
				'post_id' => 0,
				'message' => sprintf(
					/* translators: %d: source post ID */
					__( 'Listing #%d: No detail data found in GeoDirectory table.', 'wb-listora' ),
					$source_id
				),
			);
		}

		// Build listing data.
		$data = array(
			'title'      => $post->post_title,
			'content'    => $post->post_content,
			'status'     => $post->post_status,
			'author_id'  => $post->post_author,
			'date'       => $post->post_date,
			'source_id'  => $source_id,
			'thumbnail'  => get_post_thumbnail_id( $source_id ),
			'meta'       => $this->map_meta( $detail ),
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

		// Migrate geo data.
		$lat = $detail['latitude'] ?? '';
		$lng = $detail['longitude'] ?? '';

		if ( $lat && $lng ) {
			$this->insert_geo(
				$post_id,
				(float) $lat,
				(float) $lng,
				array(
					'address'     => $detail['street'] ?? '',
					'city'        => $detail['city'] ?? '',
					'state'       => $detail['region'] ?? '',
					'country'     => $detail['country'] ?? '',
					'postal_code' => $detail['zip'] ?? '',
				)
			);
		}

		// Migrate images from geodir_attachments table.
		$this->migrate_images( $source_id, $post_id );

		// Migrate reviews.
		$this->migrate_reviews( $source_id, $post_id );

		// Index the listing.
		$this->index_listing( $post_id );

		/**
		 * Mirrors Pro's `Base_Migrator::import_listing()` fire-site so
		 * Free's bulk-migrated listings reach the context-aware
		 * `wb_listora_listing_submitted` listeners introduced in
		 * Phase 3. The `'migration'` source ensures notification +
		 * activity-feed listeners stay quiet for bulk imports.
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
	 * Return the GeoDirectory field catalog the visual mapper UI shows.
	 *
	 * Sourced from `audit/architecture/competitor-schemas/geodirectory.md`
	 * §4 (`wp_geodir_gd_place_detail` columns), §6 (detail row JOIN),
	 * §8 (gallery via `wp_geodir_attachments`), §9 (geo + split address),
	 * §10 (categories), §11 (dynamic custom-field columns).
	 *
	 * GeoDirectory does NOT use postmeta for listing data (schema §5) —
	 * it stores everything as columns on the detail table. The
	 * `source_table` hint tells Pro's UI that these are column reads,
	 * not `get_post_meta()` calls.
	 *
	 * The standard columns are emitted first; admin-defined custom
	 * fields are then appended by introspecting `wp_geodir_custom_fields`.
	 *
	 * @since 1.1.0
	 * @return array<int, array<string, mixed>>
	 */
	public function detect_source_fields(): array {
		global $wpdb;

		$detail_table = $wpdb->prefix . 'geodir_gd_place_detail';

		$fields = array(
			array(
				'source_key'   => 'phone',
				'label'        => __( 'Phone', 'wb-listora' ),
				'type'         => 'phone',
				'source_table' => $detail_table,
			),
			array(
				'source_key'   => 'email',
				'label'        => __( 'Email', 'wb-listora' ),
				'type'         => 'email',
				'source_table' => $detail_table,
			),
			array(
				'source_key'   => 'website',
				'label'        => __( 'Website', 'wb-listora' ),
				'type'         => 'url',
				'source_table' => $detail_table,
			),
			array(
				'source_key'   => 'latitude',
				'label'        => __( 'Latitude', 'wb-listora' ),
				'type'         => 'geo',
				'source_table' => $detail_table,
			),
			array(
				'source_key'   => 'longitude',
				'label'        => __( 'Longitude', 'wb-listora' ),
				'type'         => 'geo',
				'source_table' => $detail_table,
			),
			array(
				'source_key'   => 'street',
				'label'        => __( 'Street', 'wb-listora' ),
				'type'         => 'text',
				'source_table' => $detail_table,
			),
			array(
				'source_key'   => 'street2',
				'label'        => __( 'Street (line 2)', 'wb-listora' ),
				'type'         => 'text',
				'source_table' => $detail_table,
			),
			array(
				'source_key'   => 'city',
				'label'        => __( 'City', 'wb-listora' ),
				'type'         => 'text',
				'source_table' => $detail_table,
			),
			array(
				'source_key'   => 'region',
				'label'        => __( 'Region / State', 'wb-listora' ),
				'type'         => 'text',
				'source_table' => $detail_table,
			),
			array(
				'source_key'   => 'country',
				'label'        => __( 'Country', 'wb-listora' ),
				'type'         => 'text',
				'source_table' => $detail_table,
			),
			array(
				'source_key'   => 'zip',
				'label'        => __( 'Postal code', 'wb-listora' ),
				'type'         => 'text',
				'source_table' => $detail_table,
			),
			array(
				'source_key'   => 'featured',
				'label'        => __( 'Featured flag', 'wb-listora' ),
				'type'         => 'boolean',
				'source_table' => $detail_table,
			),
			array(
				'source_key'   => 'overall_rating',
				'label'        => __( 'Overall rating', 'wb-listora' ),
				'type'         => 'number',
				'source_table' => $detail_table,
			),
			array(
				'source_key'   => 'default_category',
				'label'        => __( 'Primary category', 'wb-listora' ),
				'type'         => 'taxonomy',
				'source_table' => $detail_table,
			),
			array(
				'source_key'   => 'wp_geodir_attachments',
				'label'        => __( 'Gallery (attachments table)', 'wb-listora' ),
				'type'         => 'gallery',
				'source_table' => $wpdb->prefix . 'geodir_attachments',
			),
			array(
				'source_key'   => 'wp_geodir_post_review JOIN wp_comments',
				'label'        => __( 'Reviews', 'wb-listora' ),
				'type'         => 'reviews',
				'source_table' => $wpdb->prefix . 'geodir_post_review',
			),
			array(
				'source_key'   => 'gd_placecategory',
				'label'        => __( 'Categories', 'wb-listora' ),
				'type'         => 'taxonomy',
				'source_table' => 'wp_term_relationships',
			),
			array(
				'source_key'   => 'gd_place_tags',
				'label'        => __( 'Tags', 'wb-listora' ),
				'type'         => 'taxonomy',
				'source_table' => 'wp_term_relationships',
			),
		);

		// Discover admin-defined custom fields (schema doc §4.2 + §11).
		// Each `htmlvar_name` is a dynamic column on the detail table.
		$custom_fields_table = $wpdb->prefix . 'geodir_custom_fields';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $custom_fields_table )
		);

		if ( null === $table_exists ) {
			return $fields;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$custom_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT htmlvar_name, admin_title, frontend_title, field_type FROM {$custom_fields_table} WHERE post_type = %s AND is_active = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'gd_place'
			),
			ARRAY_A
		);

		if ( empty( $custom_rows ) ) {
			return $fields;
		}

		$seen = array();
		foreach ( $fields as $existing ) {
			$seen[ $existing['source_key'] ] = true;
		}

		foreach ( $custom_rows as $row ) {
			$htmlvar = isset( $row['htmlvar_name'] ) ? (string) $row['htmlvar_name'] : '';
			if ( '' === $htmlvar || isset( $seen[ $htmlvar ] ) ) {
				continue;
			}

			$label = isset( $row['frontend_title'] ) && '' !== $row['frontend_title']
				? (string) $row['frontend_title']
				: (string) ( $row['admin_title'] ?? $htmlvar );

			$fields[]         = array(
				'source_key'   => $htmlvar,
				'label'        => $label,
				'type'         => $this->geodir_field_type_to_ui_type( (string) ( $row['field_type'] ?? '' ) ),
				'source_table' => $detail_table,
			);
			$seen[ $htmlvar ] = true;
		}

		return $fields;
	}

	/**
	 * Default Listora → GeoDirectory source-key mapping the visual
	 * mapper UI pre-fills.
	 *
	 * Sourced from `audit/architecture/competitor-schemas/geodirectory.md`
	 * §4 + §9 + §10.
	 *
	 * @since 1.1.0
	 * @return array<string, string>
	 */
	public function get_default_mapping(): array {
		return array(
			'phone'            => 'phone',
			'email'            => 'email',
			'website'          => 'website',
			'lat'              => 'latitude',
			'lng'              => 'longitude',
			'address'          => 'street',
			'address_line2'    => 'street2',
			'city'             => 'city',
			'state'            => 'region',
			'country'          => 'country',
			'postal'           => 'zip',
			'featured'         => 'featured',
			'rating_avg'       => 'overall_rating',
			'primary_category' => 'default_category',
			'gallery'          => 'wp_geodir_attachments',
			'reviews'          => 'wp_geodir_post_review JOIN wp_comments',
			'categories'       => 'gd_placecategory',
			'tags'             => 'gd_place_tags',
		);
	}

	/**
	 * Translate a GeoDirectory `wp_geodir_custom_fields.field_type` to
	 * the UI type vocabulary used by {@see detect_source_fields()}.
	 *
	 * @since 1.1.0
	 * @param string $field_type GeoDirectory field type (e.g. 'email').
	 * @return string Visual mapper UI type.
	 */
	private function geodir_field_type_to_ui_type( string $field_type ): string {
		switch ( $field_type ) {
			case 'email':
				return 'email';
			case 'phone':
				return 'phone';
			case 'url':
				return 'url';
			case 'file':
			case 'image':
				return 'image';
			case 'multiselect':
			case 'select':
			case 'radio':
			case 'checkbox':
				return 'text';
			case 'datepicker':
			case 'date':
			case 'time':
				return 'datetime';
			case 'int':
			case 'float':
			case 'number':
				return 'number';
			default:
				return 'text';
		}
	}

	/**
	 * Map GeoDirectory detail table fields to Listora meta.
	 *
	 * Field mapping: GeoDirectory → Listora
	 *
	 * phone              → phone
	 * email              → email
	 * website            → website
	 * street+city+region → address (composite text via Meta_Handler)
	 * business_hours     → business_hours
	 * price_range        → price_range
	 * facebook/twitter/… → social_links
	 * latitude           → geo table (lat)
	 * longitude          → geo table (lng)
	 * street             → geo table (address)
	 * city               → geo table (city)
	 * region             → geo table (state)
	 * country            → geo table (country)
	 * zip                → geo table (postal_code)
	 * images             → gallery (handled in migrate_images)
	 *
	 * @param array $detail Detail row from geodir_gd_place_detail.
	 * @return array Key => value pairs for Listora meta.
	 */
	private function map_meta( $detail ) {
		$meta = array();

		$field_map = array(
			'phone'   => 'phone',
			'email'   => 'email',
			'website' => 'website',
		);

		foreach ( $field_map as $gd_key => $listora_key ) {
			if ( ! empty( $detail[ $gd_key ] ) ) {
				$meta[ $listora_key ] = $detail[ $gd_key ];
			}
		}

		// Address as composite text.
		$address_parts = array_filter(
			array(
				$detail['street'] ?? '',
				$detail['city'] ?? '',
				$detail['region'] ?? '',
				$detail['country'] ?? '',
			)
		);
		if ( ! empty( $address_parts ) ) {
			$meta['address'] = implode( ', ', $address_parts );
		}

		/*
		 * Business hours. GeoDirectory stores a schema.org-ish STRING, e.g.
		 * `["Mo 09:00-17:00","Tu 09:00-12:00,13:00-17:00","Su Closed"],["UTC":"+0"]`.
		 * Passing that through unmapped produced zero rows in `listora_hours`,
		 * no hours on the listing, no "Open now" match and no
		 * openingHoursSpecification — silently (BC 10184420962).
		 */
		if ( ! empty( $detail['business_hours'] ) ) {
			$hours = Hours_Mapper::from_geodirectory( maybe_unserialize( $detail['business_hours'] ) );
			if ( ! empty( $hours ) ) {
				$meta['business_hours'] = $hours;
			} else {
				// Keep the unreadable original so nothing is lost and the
				// existing migrated-hours reporting can flag it for the owner.
				$meta['_migrated_hours_raw'] = $detail['business_hours'];
			}
		}

		// Price range.
		if ( ! empty( $detail['price_range'] ) ) {
			$meta['price_range'] = $detail['price_range'];
		}

		// Social links (GeoDirectory may store individual social fields).
		$social_fields = array( 'facebook', 'twitter', 'instagram', 'linkedin', 'youtube' );
		$social_links  = array();
		foreach ( $social_fields as $social_key ) {
			if ( ! empty( $detail[ $social_key ] ) ) {
				$social_links[ $social_key ] = $detail[ $social_key ];
			}
		}
		if ( ! empty( $social_links ) ) {
			$meta['social_links'] = $social_links;
		}

		return $meta;
	}

	/**
	 * Map GeoDirectory taxonomies to Listora taxonomies.
	 *
	 * @param int $source_id Source post ID.
	 * @return array Taxonomy => terms array.
	 */
	private function map_taxonomies( $source_id ) {
		$taxonomies = array();

		// Categories: gd_placecategory -> listora_listing_cat.
		$cats = $this->get_source_terms( $source_id, 'gd_placecategory' );
		if ( ! empty( $cats ) ) {
			$taxonomies['listora_listing_cat'] = $cats;
		}

		// Tags: gd_place_tags -> listora_listing_feature.
		$tags = $this->get_source_terms( $source_id, 'gd_place_tags' );
		if ( ! empty( $tags ) ) {
			$taxonomies['listora_listing_feature'] = $tags;
		}

		return $taxonomies;
	}

	/**
	 * Migrate images from GeoDirectory's geodir_attachments table.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $post_id   Listora listing ID.
	 */
	private function migrate_images( $source_id, $post_id ) {
		global $wpdb;

		$attachments_table = $wpdb->prefix . 'geodir_attachments';

		// Check if the table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $attachments_table )
		);

		if ( ! $table_exists ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$images = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$attachments_table} WHERE post_id = %d AND mime_type LIKE %s ORDER BY menu_order ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$source_id,
				'image/%'
			),
			ARRAY_A
		);

		if ( empty( $images ) ) {
			return;
		}

		$gallery_ids = array();
		foreach ( $images as $image ) {
			// GeoDirectory attachments may store file paths or have WP attachment IDs.
			if ( ! empty( $image['ID'] ) ) {
				$attachment = get_post( $image['ID'] );
				if ( $attachment && 'attachment' === $attachment->post_type ) {
					$gallery_ids[] = (int) $image['ID'];
				}
			}
		}

		if ( ! empty( $gallery_ids ) ) {
			\WBListora\Core\Meta_Handler::set_value( $post_id, 'gallery', $gallery_ids );
		}
	}

	/**
	 * Migrate GeoDirectory reviews (WP comments with custom rating meta).
	 *
	 * @param int $source_id Source post ID.
	 * @param int $post_id   Listora listing ID.
	 */
	private function migrate_reviews( $source_id, $post_id ) {
		$comments = get_comments(
			array(
				'post_id' => $source_id,
				'status'  => 'all',
				'type'    => 'comment',
			)
		);

		if ( empty( $comments ) ) {
			return;
		}

		foreach ( $comments as $comment ) {
			// GeoDirectory stores the overall rating in comment meta.
			$rating = (int) get_comment_meta( (int) $comment->comment_ID, 'geodir-overall_rating', true );

			if ( $rating < 1 ) {
				continue; // Skip comments without ratings (non-review comments).
			}

			$status = '1' === $comment->comment_approved ? 'approved' : 'pending';
			if ( 'trash' === $comment->comment_approved || 'spam' === $comment->comment_approved ) {
				continue;
			}

			$this->insert_review(
				array(
					'listing_id'     => $post_id,
					'user_id'        => (int) $comment->user_id,
					'overall_rating' => min( 5, $rating ),
					'title'          => '',
					'content'        => $comment->comment_content,
					'status'         => $status,
					'created_at'     => $comment->comment_date,
				)
			);
		}
	}
}
