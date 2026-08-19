<?php
/**
 * Business Directory Plugin (BDP) Migrator.
 *
 * @package WBListora\ImportExport
 */

namespace WBListora\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Migrates listings, categories, and tags from Business Directory Plugin
 * (CPT: wpbdp_listing). BDP uses custom field definitions stored in
 * {prefix}wpbdp_form_fields and values in wp_postmeta with keys like
 * _wpbdp[fields][{id}].
 */
class BDP_Migrator extends Migration_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->source_slug        = 'bdp';
		$this->source_name        = 'Business Directory Plugin';
		$this->source_description = __( 'Migrate listings, categories, and tags from Business Directory Plugin.', 'wb-listora' );
	}

	/**
	 * Detect if BDP data exists.
	 *
	 * Checks CPT existence and the wpbdp_form_fields table.
	 *
	 * @return bool
	 */
	public function detect() {
		global $wpdb;

		// Check if CPT is registered.
		if ( post_type_exists( 'wpbdp_listing' ) ) {
			return true;
		}

		// Check if posts exist.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'wpbdp_listing' AND post_status IN ('publish', 'pending', 'draft', 'private')"
		);

		if ( $post_count > 0 ) {
			return true;
		}

		// Check if the form fields table exists.
		$table = $wpdb->prefix . 'wpbdp_form_fields';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		return null !== $table_exists;
	}

	/**
	 * Count source BDP listings.
	 *
	 * @return int
	 */
	public function get_source_count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'wpbdp_listing' AND post_status IN ('publish', 'pending', 'draft', 'private')"
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpbdp_listing' AND post_status IN ('publish', 'pending', 'draft', 'private') ORDER BY ID ASC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		return array_map( 'absint', $ids );
	}

	/**
	 * Migrate a single BDP listing.
	 *
	 * @param int $source_id Source post ID.
	 * @return array Migration result.
	 */
	public function migrate_listing( $source_id ) {
		$post = get_post( $source_id );

		if ( ! $post ) {
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

		// Build listing data.
		$data = array(
			'title'      => $post->post_title,
			'content'    => $post->post_content,
			'status'     => $post->post_status,
			'author_id'  => $post->post_author,
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

		// Attempt geo migration from BDP custom fields.
		$this->migrate_geo( $source_id, $post_id );

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
	 * Return the WPBDP field catalog the visual mapper UI shows.
	 *
	 * Sourced from `audit/architecture/competitor-schemas/wpbdp.md`
	 * §4.1 (`wpbdp_form_fields` catalog) + §5 (storage model — field
	 * values land in `wp_postmeta` at `_wpbdp[fields][{id}]` where
	 * `{id}` is the numeric form-field row id) + §6 (other fixed
	 * postmeta) + §10 (taxonomies).
	 *
	 * The schema is admin-defined (every install can have different
	 * fields), so the catalog MUST be built dynamically from
	 * `wpbdp_form_fields`. The `tag` column is the semantic-tag fast
	 * path (schema §5) — when present it identifies the field's role
	 * regardless of the human label.
	 *
	 * `source_key` is the numeric field id stringified (so that
	 * Pro's UI can persist it as JSON without losing type fidelity).
	 *
	 * @since 1.1.0
	 * @return array<int, array<string, mixed>>
	 */
	public function detect_source_fields(): array {
		global $wpdb;

		$fields = array();

		foreach ( $this->get_field_definitions() as $definition ) {
			$field_id = isset( $definition['id'] ) ? (int) $definition['id'] : 0;
			if ( $field_id <= 0 ) {
				continue;
			}

			$label = isset( $definition['label'] ) ? (string) $definition['label'] : '';
			$tag   = isset( $definition['tag'] ) ? (string) $definition['tag'] : '';

			$fields[] = array(
				'source_key'   => (string) $field_id,
				'label'        => '' !== $label ? $label : sprintf(
					/* translators: %d: WPBDP form field id */
					__( 'Field #%d', 'wb-listora' ),
					$field_id
				),
				'type'         => $this->wpbdp_field_type_to_ui_type(
					(string) ( $definition['field_type'] ?? '' ),
					$tag
				),
				'source_table' => 'wp_postmeta',
				'meta'         => array(
					// 'meta_key' here is descriptive metadata for Pro's UI —
					// not a runtime WP_Query argument — so the slow-query
					// sniff (WordPress.DB.SlowDBQuery.slow_db_query_meta_key)
					// is a false positive.
					'meta_key'    => '_wpbdp[fields][' . $field_id . ']', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'association' => (string) ( $definition['association'] ?? '' ),
					'tag'         => $tag,
				),
			);
		}

		// Fixed postmeta keys outside the form-fields catalog
		// (schema doc §6).
		$fields[] = array(
			'source_key'   => '_wpbdp[images]',
			'label'        => __( 'Gallery images', 'wb-listora' ),
			'type'         => 'gallery',
			'source_table' => 'wp_postmeta',
		);
		$fields[] = array(
			'source_key'   => '_thumbnail_id',
			'label'        => __( 'Featured image', 'wb-listora' ),
			'type'         => 'image',
			'source_table' => 'wp_postmeta',
		);

		// Lifecycle metadata from `wpbdp_listings` (schema doc §4.2).
		$fields[] = array(
			'source_key'   => 'wpbdp_listings.listing_status',
			'label'        => __( 'Listing status', 'wb-listora' ),
			'type'         => 'text',
			'source_table' => $wpdb->prefix . 'wpbdp_listings',
		);
		$fields[] = array(
			'source_key'   => 'wpbdp_listings.expiration_date',
			'label'        => __( 'Expiration date', 'wb-listora' ),
			'type'         => 'datetime',
			'source_table' => $wpdb->prefix . 'wpbdp_listings',
		);
		$fields[] = array(
			'source_key'   => 'wpbdp_listings.is_sticky',
			'label'        => __( 'Featured (sticky)', 'wb-listora' ),
			'type'         => 'boolean',
			'source_table' => $wpdb->prefix . 'wpbdp_listings',
		);

		// Taxonomies (schema doc §10).
		$fields[] = array(
			'source_key'   => 'wpbdp_category',
			'label'        => __( 'Categories', 'wb-listora' ),
			'type'         => 'taxonomy',
			'source_table' => 'wp_term_relationships',
		);
		$fields[] = array(
			'source_key'   => 'wpbdp_tag',
			'label'        => __( 'Tags', 'wb-listora' ),
			'type'         => 'taxonomy',
			'source_table' => 'wp_term_relationships',
		);

		return $fields;
	}

	/**
	 * Default Listora → WPBDP source-key mapping the visual mapper UI
	 * pre-fills. WPBDP's field IDs are not stable across sites
	 * (schema §13), so the mapping is built at runtime from the
	 * semantic-tag fast path (`wpbdp_form_fields.tag`, schema §5).
	 *
	 * @since 1.1.0
	 * @return array<string, string>
	 */
	public function get_default_mapping(): array {
		$tag_to_field_id = array();

		foreach ( $this->get_field_definitions() as $definition ) {
			$tag      = isset( $definition['tag'] ) ? (string) $definition['tag'] : '';
			$field_id = isset( $definition['id'] ) ? (int) $definition['id'] : 0;

			if ( '' === $tag || $field_id <= 0 || isset( $tag_to_field_id[ $tag ] ) ) {
				continue;
			}

			$tag_to_field_id[ $tag ] = (string) $field_id;
		}

		$mapping = array();

		// Per schema §5 the standard WPBDP semantic-tag vocabulary is
		// title / website / email / phone / fax / address / zip.
		$tag_to_listora = array(
			'title'   => 'title',
			'website' => 'website',
			'email'   => 'email',
			'phone'   => 'phone',
			'fax'     => 'fax',
			'address' => 'address',
			'zip'     => 'postal',
		);

		foreach ( $tag_to_listora as $tag => $listora_key ) {
			if ( isset( $tag_to_field_id[ $tag ] ) ) {
				$mapping[ $listora_key ] = $tag_to_field_id[ $tag ];
			}
		}

		// Fixed (tag-independent) defaults.
		$mapping['gallery']        = '_wpbdp[images]';
		$mapping['featured_image'] = '_thumbnail_id';
		$mapping['categories']     = 'wpbdp_category';
		$mapping['tags']           = 'wpbdp_tag';

		return $mapping;
	}

	/**
	 * Translate a `wpbdp_form_fields.field_type` (with optional `tag`
	 * hint) to the UI type vocabulary used by {@see detect_source_fields()}.
	 *
	 * @since 1.1.0
	 * @param string $field_type WPBDP field type (e.g. 'textfield', 'url').
	 * @param string $tag        Semantic tag from `wpbdp_form_fields.tag`.
	 * @return string Visual mapper UI type.
	 */
	private function wpbdp_field_type_to_ui_type( string $field_type, string $tag ): string {
		if ( '' !== $tag ) {
			switch ( $tag ) {
				case 'email':
					return 'email';
				case 'phone':
				case 'fax':
					return 'phone';
				case 'website':
					return 'url';
				case 'address':
				case 'zip':
				case 'title':
					return 'text';
			}
		}

		switch ( $field_type ) {
			case 'url':
				return 'url';
			case 'phone':
				return 'phone';
			case 'image':
				return 'image';
			case 'date':
				return 'datetime';
			case 'checkbox':
			case 'select':
			case 'multiselect':
			case 'radio':
				return 'text';
			default:
				return 'text';
		}
	}

	/**
	 * Map BDP custom fields to Listora meta.
	 *
	 * BDP stores field definitions in wpbdp_form_fields and values in postmeta
	 * with keys like _wpbdp[fields][{field_id}].
	 *
	 * Field mapping: BDP (by label) → Listora
	 *
	 * email / e-mail                → email
	 * phone / telephone             → phone
	 * website / url / web site      → website
	 * address                       → address
	 * price                         → price
	 * price range                   → price_range
	 * business hours / hours        → business_hours
	 * year established / founded    → year_established
	 * latitude / lat                → geo table (lat)
	 * longitude / lng / lon         → geo table (lng)
	 *
	 * @param int $source_id Source post ID.
	 * @return array Key => value pairs for Listora meta.
	 */
	private function map_meta( $source_id ) {
		$meta       = array();
		$field_defs = $this->get_field_definitions();

		foreach ( $field_defs as $field ) {
			$field_id = (int) $field['id'];
			$label    = strtolower( trim( $field['label'] ?? '' ) );
			$value    = $this->get_source_meta( $source_id, '_wpbdp[fields][' . $field_id . ']' );

			if ( '' === $value ) {
				continue;
			}

			// Map known fields by label.
			$mapped_key = $this->label_to_listora_key( $label );
			if ( $mapped_key ) {
				$meta[ $mapped_key ] = $value;
			}
		}

		return $meta;
	}

	/**
	 * Map a BDP field label to a Listora meta key.
	 *
	 * Field mapping: BDP label → Listora key
	 *
	 * email / e-mail                → email
	 * phone / telephone             → phone
	 * website / url / web site      → website
	 * address                       → address
	 * price                         → price
	 * price range                   → price_range
	 * business hours / hours        → business_hours
	 * year established / founded    → year_established
	 *
	 * Note: Labels like zip, city, state, country are used only for
	 * geo table population (see migrate_geo) and are NOT stored as
	 * standalone meta keys. Label 'fax' has no Listora equivalent
	 * and is intentionally dropped.
	 *
	 * @param string $label Lowercase field label.
	 * @return string|false Listora meta key or false if not mapped.
	 */
	private function label_to_listora_key( $label ) {
		$map = array(
			'email'            => 'email',
			'e-mail'           => 'email',
			'phone'            => 'phone',
			'telephone'        => 'phone',
			'website'          => 'website',
			'url'              => 'website',
			'web site'         => 'website',
			'address'          => 'address',
			'price'            => 'price',
			'price range'      => 'price_range',
			/*
			 * BDP stores hours as a FREE-TEXT form field ("Mon-Fri 9-5"), not
			 * a day-keyed structure. Writing that into `business_hours` looked
			 * like a mapping and produced nothing: the value is a string, the
			 * hours indexer only accepts arrays, so it was dropped without a
			 * warning and the listing imported with no hours at all
			 * (BC 10184420962).
			 *
			 * There is no honest structured conversion — "Mon-Fri 9-5" and
			 * "by appointment" are the same field. So the text is preserved
			 * under the same raw key the other migrators use when mapping
			 * fails, where an owner can find it and re-enter it, rather than
			 * being silently discarded into a typed field it cannot satisfy.
			 */
			'business hours'   => '_migrated_hours_raw',
			'hours'            => '_migrated_hours_raw',
			'year established' => 'year_established',
			'founded'          => 'year_established',
		);

		return $map[ $label ] ?? false;
	}

	/**
	 * Get BDP field definitions from the wpbdp_form_fields table.
	 *
	 * @return array[] Array of field definition rows.
	 */
	private function get_field_definitions() {
		global $wpdb;

		$table = $wpdb->prefix . 'wpbdp_form_fields';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		if ( ! $table_exists ) {
			return array();
		}

		// `tag` is best-effort — WPBDP <6.x installs may lack the column,
		// in which case the SELECT silently drops back to the legacy
		// shape. Wrap in try/catch so a missing column doesn't fatal.
		$columns = array( 'id', 'label', 'field_type', 'association' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$column_rows = $wpdb->get_col( "SHOW COLUMNS FROM {$table} LIKE 'tag'" );
		if ( ! empty( $column_rows ) ) {
			$columns[] = 'tag';
		}

		$select = implode( ', ', $columns );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$fields = $wpdb->get_results(
			"SELECT {$select} FROM {$table} ORDER BY weight ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $fields ? $fields : array();
	}

	/**
	 * Attempt to migrate geo data from BDP custom fields.
	 *
	 * BDP may store latitude/longitude in custom fields. We search
	 * for fields with lat/lng related labels.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $post_id   Listora listing ID.
	 */
	private function migrate_geo( $source_id, $post_id ) {
		$field_defs = $this->get_field_definitions();

		$lat     = '';
		$lng     = '';
		$address = '';

		foreach ( $field_defs as $field ) {
			$field_id = (int) $field['id'];
			$label    = strtolower( trim( $field['label'] ?? '' ) );
			$value    = $this->get_source_meta( $source_id, '_wpbdp[fields][' . $field_id . ']' );

			if ( '' === $value ) {
				continue;
			}

			if ( in_array( $label, array( 'latitude', 'lat' ), true ) ) {
				$lat = $value;
			} elseif ( in_array( $label, array( 'longitude', 'lng', 'lon' ), true ) ) {
				$lng = $value;
			} elseif ( 'address' === $label ) {
				$address = $value;
			}
		}

		if ( $lat && $lng ) {
			$this->insert_geo( $post_id, (float) $lat, (float) $lng, array( 'address' => $address ) );
		}
	}

	/**
	 * Map BDP taxonomies to Listora taxonomies.
	 *
	 * @param int $source_id Source post ID.
	 * @return array Taxonomy => terms array.
	 */
	private function map_taxonomies( $source_id ) {
		$taxonomies = array();

		// Categories: wpbdp_category -> listora_listing_cat.
		$cats = $this->get_source_terms( $source_id, 'wpbdp_category' );
		if ( ! empty( $cats ) ) {
			$taxonomies['listora_listing_cat'] = $cats;
		}

		// Tags: wpbdp_tag -> listora_listing_feature.
		$tags = $this->get_source_terms( $source_id, 'wpbdp_tag' );
		if ( ! empty( $tags ) ) {
			$taxonomies['listora_listing_feature'] = $tags;
		}

		return $taxonomies;
	}
}
