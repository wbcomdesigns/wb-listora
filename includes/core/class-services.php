<?php
/**
 * Services CRUD class.
 *
 * Manages listing services stored in the listora_services custom table.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Handles CRUD operations for listing services.
 */
class Services {

	/**
	 * Valid price types.
	 *
	 * @var array
	 */
	private static $price_types = array( 'fixed', 'starting_from', 'hourly', 'free', 'contact' );

	/**
	 * Valid statuses.
	 *
	 * @var array
	 */
	private static $statuses = array( 'active', 'inactive', 'deleted' );

	/**
	 * Get the services table name.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'services';
	}

	/**
	 * Get all services for a listing.
	 *
	 * @param int    $listing_id Listing post ID.
	 * @param string $status     Status filter. Default 'active'.
	 * @return array Array of service rows.
	 */
	public static function get_services( $listing_id, $status = 'active' ) {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE listing_id = %d AND status = %s ORDER BY sort_order ASC, id ASC",
				$listing_id,
				$status
			),
			ARRAY_A
		);

		return $rows ? $rows : array();
	}

	/**
	 * Get a single service by ID.
	 *
	 * @param int $service_id Service ID.
	 * @return array|null Service row or null.
	 */
	public static function get_service( $service_id ) {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE id = %d",
				$service_id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Create a new service.
	 *
	 * @param array $data Service data.
	 * @return int|\WP_Error Service ID on success, WP_Error on failure.
	 */
	public static function create_service( $data ) {
		global $wpdb;

		$data = self::sanitize_data( $data );

		if ( empty( $data['listing_id'] ) ) {
			return new \WP_Error( 'listora_missing_listing', __( 'Listing ID is required.', 'wb-listora' ), array( 'status' => 400 ) );
		}

		if ( empty( $data['title'] ) ) {
			return new \WP_Error( 'listora_missing_title', __( 'Service title is required.', 'wb-listora' ), array( 'status' => 400 ) );
		}

		/**
		 * Filter before creating a service. Return WP_Error to abort.
		 *
		 * @param array $data Service data.
		 */
		$data = apply_filters( 'wb_listora_before_create_service', $data );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$table = self::table();

		// Get next sort_order for this listing.
		if ( ! isset( $data['sort_order'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$max_order = $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT MAX(sort_order) FROM {$table} WHERE listing_id = %d",
					$data['listing_id']
				)
			);

			$data['sort_order'] = ( null !== $max_order ) ? ( (int) $max_order + 1 ) : 0;
		}

		$now = current_time( 'mysql', true );

		$insert_data = array(
			'listing_id'       => (int) $data['listing_id'],
			'title'            => $data['title'],
			'description'      => $data['description'] ?? '',
			'price'            => isset( $data['price'] ) ? (float) $data['price'] : null,
			'price_type'       => $data['price_type'] ?? 'fixed',
			'duration_minutes' => isset( $data['duration_minutes'] ) ? (int) $data['duration_minutes'] : null,
			'image_id'         => isset( $data['image_id'] ) ? (int) $data['image_id'] : null,
			'video_url'        => '',
			'gallery'          => null,
			'sort_order'       => (int) $data['sort_order'],
			'status'           => 'active',
			'created_at'       => $now,
			'updated_at'       => $now,
		);

		$format = array( '%d', '%s', '%s', '%f', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s' );

		// Handle nullable fields.
		if ( null === $insert_data['price'] ) {
			$format[3] = null;
		}
		if ( null === $insert_data['duration_minutes'] ) {
			$format[5] = null;
		}
		if ( null === $insert_data['image_id'] ) {
			$format[6] = null;
		}
		if ( null === $insert_data['gallery'] ) {
			$format[8] = null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$insert_data
		);

		if ( false === $result ) {
			return new \WP_Error( 'listora_db_error', __( 'Unable to create service. Please try again.', 'wb-listora' ), array( 'status' => 500 ) );
		}

		$service_id = (int) $wpdb->insert_id;

		// Set categories if provided.
		if ( ! empty( $data['categories'] ) ) {
			self::set_service_categories( $service_id, $data['categories'] );
		}

		/**
		 * Fires after a service is created.
		 *
		 * @param int   $service_id Service ID.
		 * @param array $data       Service data.
		 */
		do_action( 'wb_listora_after_create_service', $service_id, $data );

		// Re-index the parent listing so service text appears in search.
		self::reindex_listing( (int) $data['listing_id'] );

		return $service_id;
	}

	/**
	 * Update an existing service.
	 *
	 * @param int   $service_id Service ID.
	 * @param array $data       Data to update.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public static function update_service( $service_id, $data ) {
		global $wpdb;

		$existing = self::get_service( $service_id );
		if ( ! $existing ) {
			return new \WP_Error( 'listora_service_not_found', __( 'Service not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		$data = self::sanitize_data( $data );

		/**
		 * Filter before updating a service. Return WP_Error to abort.
		 *
		 * @param array $data       Data to update.
		 * @param int   $service_id Service ID.
		 */
		$data = apply_filters( 'wb_listora_before_update_service', $data, $service_id );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$update = array();
		$format = array();

		$allowed = array(
			'title'            => '%s',
			'description'      => '%s',
			'price'            => '%f',
			'price_type'       => '%s',
			'duration_minutes' => '%d',
			'image_id'         => '%d',
			'sort_order'       => '%d',
			'status'           => '%s',
		);

		foreach ( $allowed as $key => $fmt ) {
			if ( array_key_exists( $key, $data ) ) {
				$update[ $key ] = $data[ $key ];
				$format[]       = $fmt;
			}
		}

		if ( empty( $update ) && empty( $data['categories'] ) ) {
			return true;
		}

		if ( ! empty( $update ) ) {
			$update['updated_at'] = current_time( 'mysql', true );
			$format[]             = '%s';

			$table = self::table();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$table, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$update,
				array( 'id' => $service_id ),
				$format,
				array( '%d' )
			);

			if ( false === $result ) {
				return new \WP_Error( 'listora_db_error', __( 'Unable to update service. Please try again.', 'wb-listora' ), array( 'status' => 500 ) );
			}
		}

		// Update categories if provided.
		if ( isset( $data['categories'] ) ) {
			self::set_service_categories( $service_id, $data['categories'] );
		}

		/**
		 * Fires after a service is updated.
		 *
		 * @param int   $service_id Service ID.
		 * @param array $data       Updated data.
		 */
		do_action( 'wb_listora_after_update_service', $service_id, $data );

		// Re-index the parent listing.
		self::reindex_listing( (int) $existing['listing_id'] );

		return true;
	}

	/**
	 * Soft delete a service (set status to 'deleted').
	 *
	 * @param int $service_id Service ID.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public static function delete_service( $service_id ) {
		global $wpdb;

		$existing = self::get_service( $service_id );
		if ( ! $existing ) {
			return new \WP_Error( 'listora_service_not_found', __( 'Service not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		/**
		 * Filter before deleting a service. Return WP_Error to abort.
		 *
		 * @param int $service_id Service ID.
		 */
		$result = apply_filters( 'wb_listora_before_delete_service', $service_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array(
				'status'     => 'deleted',
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $service_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		/**
		 * Fires after a service is deleted.
		 *
		 * @param int   $service_id Service ID.
		 * @param array $existing   The service data before deletion.
		 */
		do_action( 'wb_listora_after_delete_service', $service_id, $existing );

		// Re-index the parent listing.
		self::reindex_listing( (int) $existing['listing_id'] );

		return true;
	}

	/**
	 * Reorder services for a listing.
	 *
	 * @param int   $listing_id  Listing post ID.
	 * @param array $order_array Array of service IDs in desired order.
	 * @return bool True on success.
	 */
	public static function reorder_services( $listing_id, $order_array ) {
		global $wpdb;

		$table = self::table();

		foreach ( $order_array as $position => $service_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array( 'sort_order' => (int) $position ),
				array(
					'id'         => (int) $service_id,
					'listing_id' => (int) $listing_id,
				),
				array( '%d' ),
				array( '%d', '%d' )
			);
		}

		return true;
	}

	/**
	 * Get service category term IDs.
	 *
	 * Uses wp_term_relationships with the service ID as object_id.
	 *
	 * @param int $service_id Service ID.
	 * @return array Array of term IDs.
	 */
	public static function get_service_categories( $service_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$term_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT tt.term_id
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE tr.object_id = %d AND tt.taxonomy = 'listora_service_cat'",
				$service_id
			)
		);

		return array_map( 'intval', $term_ids );
	}

	/**
	 * Set service category terms.
	 *
	 * Stores in wp_term_relationships using service ID as object_id.
	 *
	 * @param int   $service_id Service ID.
	 * @param array $term_ids   Array of term IDs.
	 */
	public static function set_service_categories( $service_id, $term_ids ) {
		global $wpdb;

		// Remove existing relationships.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_tt_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT tr.term_taxonomy_id
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE tr.object_id = %d AND tt.taxonomy = 'listora_service_cat'",
				$service_id
			)
		);

		if ( ! empty( $existing_tt_ids ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $existing_tt_ids ), '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"DELETE FROM {$wpdb->term_relationships} WHERE object_id = %d AND term_taxonomy_id IN ({$placeholders})",
					array_merge( array( $service_id ), array_map( 'intval', $existing_tt_ids ) )
				)
			);
		}

		// Insert new relationships.
		if ( ! empty( $term_ids ) ) {
			foreach ( array_map( 'intval', $term_ids ) as $term_id ) {
				$tt_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = 'listora_service_cat'",
						$term_id
					)
				);

				if ( $tt_id ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->replace(
						$wpdb->term_relationships,
						array(
							'object_id'        => $service_id,
							'term_taxonomy_id' => (int) $tt_id,
							'term_order'       => 0,
						),
						array( '%d', '%d', '%d' )
					);
				}
			}
		}
	}

	/**
	 * Get the count of active services for a listing.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return int Service count.
	 */
	public static function get_service_count( $listing_id ) {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$table} WHERE listing_id = %d AND status = 'active'",
				$listing_id
			)
		);
	}

	/**
	 * Sanitize service data.
	 *
	 * @param array $data Raw data.
	 * @return array Sanitized data.
	 */
	private static function sanitize_data( $data ) {
		$clean = array();

		if ( isset( $data['listing_id'] ) ) {
			$clean['listing_id'] = absint( $data['listing_id'] );
		}

		if ( isset( $data['title'] ) ) {
			$clean['title'] = sanitize_text_field( $data['title'] );
		}

		if ( isset( $data['description'] ) ) {
			$clean['description'] = sanitize_textarea_field( $data['description'] );
		}

		if ( array_key_exists( 'price', $data ) ) {
			$clean['price'] = ( null !== $data['price'] && '' !== $data['price'] ) ? (float) $data['price'] : null;
		}

		if ( isset( $data['price_type'] ) ) {
			$clean['price_type'] = in_array( $data['price_type'], self::$price_types, true ) ? $data['price_type'] : 'fixed';
		}

		if ( array_key_exists( 'duration_minutes', $data ) ) {
			$clean['duration_minutes'] = ( null !== $data['duration_minutes'] && '' !== $data['duration_minutes'] ) ? absint( $data['duration_minutes'] ) : null;
		}

		if ( array_key_exists( 'image_id', $data ) ) {
			$clean['image_id'] = ( null !== $data['image_id'] && '' !== $data['image_id'] ) ? absint( $data['image_id'] ) : null;
		}

		if ( isset( $data['sort_order'] ) ) {
			$clean['sort_order'] = (int) $data['sort_order'];
		}

		if ( isset( $data['status'] ) ) {
			$clean['status'] = in_array( $data['status'], self::$statuses, true ) ? $data['status'] : 'active';
		}

		if ( isset( $data['categories'] ) ) {
			$clean['categories'] = array_map( 'absint', (array) $data['categories'] );
		}

		return $clean;
	}

	/**
	 * Trigger a re-index of the parent listing.
	 *
	 * @param int $listing_id Listing post ID.
	 */
	private static function reindex_listing( $listing_id ) {
		$post = get_post( $listing_id );
		if ( $post && 'listora_listing' === $post->post_type ) {
			$indexer = new \WBListora\Search\Search_Indexer();
			$indexer->index_listing( $listing_id, $post );
		}
	}
}
