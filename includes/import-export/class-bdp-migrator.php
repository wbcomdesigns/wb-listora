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
	 * Map BDP custom fields to Listora meta.
	 *
	 * BDP stores field definitions in wpbdp_form_fields and values in postmeta
	 * with keys like _wpbdp[fields][{field_id}].
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
	 * @param string $label Lowercase field label.
	 * @return string|false Listora meta key or false if not mapped.
	 */
	private function label_to_listora_key( $label ) {
		$map = array(
			'email'     => 'email',
			'e-mail'    => 'email',
			'phone'     => 'phone',
			'telephone' => 'phone',
			'website'   => 'website',
			'url'       => 'website',
			'web site'  => 'website',
			'address'   => 'address_text',
			'price'     => 'price',
			'zip'       => 'postal_code',
			'zip code'  => 'postal_code',
			'city'      => 'city',
			'state'     => 'state',
			'country'   => 'country',
			'fax'       => 'fax',
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$fields = $wpdb->get_results(
			"SELECT id, label, field_type, association FROM {$table} ORDER BY weight ASC",
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
