<?php
/**
 * GeoJSON Importer — import listings from GeoJSON FeatureCollection files.
 *
 * @package WBListora\ImportExport
 */

namespace WBListora\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Handles GeoJSON import, mapping Feature properties to listing fields
 * and Feature geometry coordinates to geo data.
 */
class GeoJSON_Importer {

	/**
	 * Property keys treated as core listing fields (not meta).
	 *
	 * @var array
	 */
	private static $core_property_keys = array(
		'title',
		'name',
		'description',
		'content',
		'type',
		'category',
		'categories',
		'tags',
		'location',
		'features',
		'image_url',
		'image',
		'gallery',
		'status',
		'address',
	);

	/**
	 * Parse a GeoJSON file and return a preview.
	 *
	 * @param string $file_path    Path to GeoJSON file.
	 * @param int    $preview_rows Number of preview rows.
	 * @return array|\WP_Error { fields: string[], preview: array[], total: int, geometry_types: string[] }
	 */
	public static function parse_preview( $file_path, $preview_rows = 3 ) {
		$collection = self::read_geojson_file( $file_path );

		if ( is_wp_error( $collection ) ) {
			return $collection;
		}

		$features       = $collection['features'];
		$fields          = array();
		$geometry_types  = array();
		$preview         = array();

		foreach ( $features as $index => $feature ) {
			if ( ! isset( $feature['properties'] ) || ! is_array( $feature['properties'] ) ) {
				continue;
			}

			$fields = array_unique( array_merge( $fields, array_keys( $feature['properties'] ) ) );

			if ( isset( $feature['geometry']['type'] ) ) {
				$geometry_types[] = $feature['geometry']['type'];
			}

			if ( count( $preview ) < $preview_rows ) {
				$preview[] = array(
					'properties' => $feature['properties'],
					'geometry'   => $feature['geometry'] ?? null,
				);
			}
		}

		return array(
			'fields'         => $fields,
			'preview'        => $preview,
			'total'          => count( $features ),
			'geometry_types' => array_unique( $geometry_types ),
		);
	}

	/**
	 * Import listings from a GeoJSON file.
	 *
	 * @param string $file_path Path to GeoJSON file.
	 * @param string $type_slug Listing type slug.
	 * @param bool   $dry_run   If true, only validate without creating.
	 * @return array { imported: int, errors: int, skipped: int, messages: string[] }
	 */
	public static function import( $file_path, $type_slug, $dry_run = false ) {
		$collection = self::read_geojson_file( $file_path );

		if ( is_wp_error( $collection ) ) {
			return array(
				'imported' => 0,
				'errors'   => 1,
				'skipped'  => 0,
				'messages' => array( $collection->get_error_message() ),
			);
		}

		$features = $collection['features'];

		$stats = array(
			'imported' => 0,
			'errors'   => 0,
			'skipped'  => 0,
			'messages' => array(),
		);

		foreach ( $features as $index => $feature ) {
			$row_num = $index + 1;

			if ( ! isset( $feature['type'] ) || 'Feature' !== $feature['type'] ) {
				++$stats['skipped'];
				/* translators: %d: feature number */
				$stats['messages'][] = sprintf( __( 'Feature %d: Not a valid GeoJSON Feature, skipped.', 'wb-listora' ), $row_num );
				continue;
			}

			if ( ! isset( $feature['properties'] ) || ! is_array( $feature['properties'] ) ) {
				++$stats['skipped'];
				/* translators: %d: feature number */
				$stats['messages'][] = sprintf( __( 'Feature %d: Missing properties, skipped.', 'wb-listora' ), $row_num );
				continue;
			}

			$mapped = self::map_feature( $feature );

			if ( empty( $mapped['title'] ) ) {
				++$stats['skipped'];
				/* translators: %d: feature number */
				$stats['messages'][] = sprintf( __( 'Feature %d: Missing title, skipped.', 'wb-listora' ), $row_num );
				continue;
			}

			if ( $dry_run ) {
				++$stats['imported'];
				continue;
			}

			$result = self::create_listing( $mapped, $type_slug );

			if ( is_wp_error( $result ) ) {
				++$stats['errors'];
				/* translators: 1: feature number, 2: error message */
				$stats['messages'][] = sprintf( __( 'Feature %1$d: %2$s', 'wb-listora' ), $row_num, $result->get_error_message() );
			} else {
				++$stats['imported'];
			}
		}

		return $stats;
	}

	/**
	 * Read and validate a GeoJSON file.
	 *
	 * @param string $file_path Path to GeoJSON file.
	 * @return array|\WP_Error Decoded GeoJSON FeatureCollection or error.
	 */
	private static function read_geojson_file( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'GeoJSON file not found.', 'wb-listora' ) );
		}

		$contents = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $contents ) {
			return new \WP_Error( 'file_read_error', __( 'Unable to read GeoJSON file.', 'wb-listora' ) );
		}

		$data = json_decode( $contents, true );

		if ( null === $data || ! is_array( $data ) ) {
			return new \WP_Error(
				'invalid_json',
				/* translators: %s: JSON error message */
				sprintf( __( 'Invalid JSON: %s', 'wb-listora' ), json_last_error_msg() )
			);
		}

		// Validate GeoJSON structure.
		if ( ! isset( $data['type'] ) ) {
			return new \WP_Error(
				'invalid_geojson',
				__( 'Invalid GeoJSON: missing "type" property.', 'wb-listora' )
			);
		}

		// Accept FeatureCollection (standard) or wrap a single Feature.
		if ( 'FeatureCollection' === $data['type'] ) {
			if ( ! isset( $data['features'] ) || ! is_array( $data['features'] ) ) {
				return new \WP_Error(
					'invalid_geojson',
					__( 'Invalid GeoJSON: FeatureCollection missing "features" array.', 'wb-listora' )
				);
			}
			return $data;
		}

		if ( 'Feature' === $data['type'] ) {
			return array(
				'type'     => 'FeatureCollection',
				'features' => array( $data ),
			);
		}

		return new \WP_Error(
			'invalid_geojson',
			/* translators: %s: GeoJSON type value */
			sprintf( __( 'Unsupported GeoJSON type: %s. Expected FeatureCollection or Feature.', 'wb-listora' ), sanitize_text_field( $data['type'] ) )
		);
	}

	/**
	 * Map a GeoJSON Feature to the internal field format.
	 *
	 * @param array $feature GeoJSON Feature.
	 * @return array Mapped data.
	 */
	private static function map_feature( $feature ) {
		$props = $feature['properties'];
		$data  = array();

		// Title.
		if ( isset( $props['title'] ) ) {
			$data['title'] = sanitize_text_field( $props['title'] );
		} elseif ( isset( $props['name'] ) ) {
			$data['title'] = sanitize_text_field( $props['name'] );
		}

		// Description.
		if ( isset( $props['description'] ) ) {
			$data['description'] = sanitize_textarea_field( $props['description'] );
		} elseif ( isset( $props['content'] ) ) {
			$data['description'] = sanitize_textarea_field( $props['content'] );
		}

		// Categories.
		if ( isset( $props['categories'] ) ) {
			$data['categories'] = self::normalize_term_list( $props['categories'] );
		} elseif ( isset( $props['category'] ) ) {
			$data['categories'] = self::normalize_term_list( $props['category'] );
		}

		// Tags.
		if ( isset( $props['tags'] ) ) {
			$data['tags'] = self::normalize_term_list( $props['tags'] );
		}

		// Location taxonomy.
		if ( isset( $props['location'] ) && is_string( $props['location'] ) ) {
			$data['location'] = sanitize_text_field( $props['location'] );
		}

		// Features taxonomy.
		if ( isset( $props['features'] ) ) {
			$data['features'] = self::normalize_term_list( $props['features'] );
		}

		// Featured image URL.
		if ( isset( $props['image_url'] ) ) {
			$data['image_url'] = esc_url_raw( $props['image_url'] );
		} elseif ( isset( $props['image'] ) ) {
			$data['image_url'] = esc_url_raw( $props['image'] );
		}

		// Gallery.
		if ( isset( $props['gallery'] ) && is_array( $props['gallery'] ) ) {
			$data['gallery'] = array_map( 'esc_url_raw', $props['gallery'] );
		}

		// Post status.
		if ( isset( $props['status'] ) && in_array( $props['status'], array( 'publish', 'draft', 'pending' ), true ) ) {
			$data['status'] = $props['status'];
		}

		// Address from properties.
		if ( isset( $props['address'] ) ) {
			if ( is_array( $props['address'] ) ) {
				$data['address'] = self::sanitize_address( $props['address'] );
			} else {
				$data['address'] = array(
					'address' => sanitize_text_field( $props['address'] ),
				);
			}
		}

		// Collect remaining properties as meta.
		$meta = array();
		foreach ( $props as $key => $value ) {
			if ( ! in_array( $key, self::$core_property_keys, true ) ) {
				$meta[ sanitize_key( $key ) ] = $value;
			}
		}
		if ( ! empty( $meta ) ) {
			$data['meta'] = $meta;
		}

		// Extract coordinates from geometry.
		$coordinates = self::extract_coordinates( $feature['geometry'] ?? null );
		if ( $coordinates ) {
			$data['lat'] = $coordinates['lat'];
			$data['lng'] = $coordinates['lng'];
		}

		return $data;
	}

	/**
	 * Extract lat/lng from a GeoJSON geometry object.
	 *
	 * Supports Point (direct coordinates), LineString (centroid),
	 * and Polygon (centroid).
	 *
	 * @param array|null $geometry GeoJSON geometry object.
	 * @return array|null { lat: float, lng: float } or null.
	 */
	private static function extract_coordinates( $geometry ) {
		if ( ! is_array( $geometry ) || ! isset( $geometry['type'], $geometry['coordinates'] ) ) {
			return null;
		}

		$coords = $geometry['coordinates'];

		switch ( $geometry['type'] ) {
			case 'Point':
				if ( is_array( $coords ) && count( $coords ) >= 2 ) {
					// GeoJSON coordinates are [longitude, latitude].
					return array(
						'lat' => (float) $coords[1],
						'lng' => (float) $coords[0],
					);
				}
				break;

			case 'LineString':
				if ( is_array( $coords ) && ! empty( $coords ) ) {
					return self::compute_centroid( $coords );
				}
				break;

			case 'Polygon':
				// Polygon coordinates are an array of linear rings.
				// Use the outer ring (first element) for centroid.
				if ( is_array( $coords ) && ! empty( $coords[0] ) && is_array( $coords[0] ) ) {
					return self::compute_centroid( $coords[0] );
				}
				break;

			case 'MultiPoint':
				if ( is_array( $coords ) && ! empty( $coords ) ) {
					return self::compute_centroid( $coords );
				}
				break;
		}

		return null;
	}

	/**
	 * Compute the centroid (geographic center) of an array of GeoJSON coordinate pairs.
	 *
	 * @param array $points Array of [lng, lat] pairs.
	 * @return array|null { lat: float, lng: float } or null.
	 */
	private static function compute_centroid( $points ) {
		if ( empty( $points ) ) {
			return null;
		}

		$lat_sum = 0;
		$lng_sum = 0;
		$count   = 0;

		foreach ( $points as $point ) {
			if ( is_array( $point ) && count( $point ) >= 2 ) {
				$lng_sum += (float) $point[0];
				$lat_sum += (float) $point[1];
				++$count;
			}
		}

		if ( 0 === $count ) {
			return null;
		}

		return array(
			'lat' => $lat_sum / $count,
			'lng' => $lng_sum / $count,
		);
	}

	/**
	 * Create a listing from mapped feature data.
	 *
	 * @param array  $data      Mapped data.
	 * @param string $type_slug Listing type slug.
	 * @return int|\WP_Error Post ID or error.
	 */
	private static function create_listing( $data, $type_slug ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'listora_listing',
				'post_title'   => $data['title'],
				'post_content' => $data['description'] ?? '',
				'post_status'  => $data['status'] ?? 'publish',
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set listing type.
		wp_set_object_terms( $post_id, $type_slug, 'listora_listing_type' );

		// Set categories.
		if ( ! empty( $data['categories'] ) ) {
			self::set_taxonomy_terms( $post_id, $data['categories'], 'listora_listing_cat' );
		}

		// Set tags.
		if ( ! empty( $data['tags'] ) ) {
			wp_set_object_terms( $post_id, $data['tags'], 'listora_listing_tag' );
		}

		// Set location taxonomy.
		if ( ! empty( $data['location'] ) ) {
			self::set_taxonomy_terms( $post_id, array( $data['location'] ), 'listora_listing_location' );
		}

		// Set features taxonomy.
		if ( ! empty( $data['features'] ) ) {
			self::set_taxonomy_terms( $post_id, $data['features'], 'listora_listing_feature' );
		}

		// Set geo data.
		self::set_geo_data( $post_id, $data );

		// Set meta fields.
		if ( ! empty( $data['meta'] ) ) {
			foreach ( $data['meta'] as $key => $value ) {
				\WBListora\Core\Meta_Handler::set_value( $post_id, $key, $value );
			}
		}

		// Download and set featured image from URL.
		if ( ! empty( $data['image_url'] ) ) {
			$image_id = self::sideload_image( $data['image_url'], $post_id );
			if ( $image_id && ! is_wp_error( $image_id ) ) {
				set_post_thumbnail( $post_id, $image_id );
			}
		}

		// Trigger indexing.
		$indexer = new \WBListora\Search\Search_Indexer();
		$indexer->index_listing( $post_id, get_post( $post_id ) );

		return $post_id;
	}

	/**
	 * Set taxonomy terms for a listing, creating terms if they don't exist.
	 *
	 * @param int    $post_id  Post ID.
	 * @param array  $terms    Array of term names.
	 * @param string $taxonomy Taxonomy name.
	 */
	private static function set_taxonomy_terms( $post_id, $terms, $taxonomy ) {
		$term_ids = array();

		foreach ( $terms as $term_name ) {
			$term_name = sanitize_text_field( $term_name );
			if ( empty( $term_name ) ) {
				continue;
			}

			$existing = term_exists( $term_name, $taxonomy );

			if ( ! $existing ) {
				$existing = wp_insert_term( $term_name, $taxonomy );
			}

			if ( ! is_wp_error( $existing ) ) {
				$term_ids[] = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
			}
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, $taxonomy );
		}
	}

	/**
	 * Set geo data (address meta and listora_geo table) for a listing.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Mapped listing data.
	 */
	private static function set_geo_data( $post_id, $data ) {
		$address = $data['address'] ?? array();
		$lat     = $data['lat'] ?? 0;
		$lng     = $data['lng'] ?? 0;

		if ( empty( $lat ) && empty( $address ) ) {
			return;
		}

		// Build address meta value.
		$address_meta = array(
			'address'     => $address['address'] ?? '',
			'city'        => $address['city'] ?? '',
			'state'       => $address['state'] ?? '',
			'country'     => $address['country'] ?? '',
			'postal_code' => $address['postal_code'] ?? '',
			'lat'         => (float) $lat,
			'lng'         => (float) $lng,
		);

		\WBListora\Core\Meta_Handler::set_value( $post_id, 'address', $address_meta );

		// Insert into geo table if we have coordinates.
		if ( $lat && $lng ) {
			global $wpdb;

			$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

			$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				"{$prefix}geo", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array(
					'listing_id'  => $post_id,
					'lat'         => (float) $lat,
					'lng'         => (float) $lng,
					'address'     => $address_meta['address'],
					'city'        => $address_meta['city'],
					'state'       => $address_meta['state'],
					'country'     => $address_meta['country'],
					'postal_code' => $address_meta['postal_code'],
					'geohash'     => \WBListora\Search\Geo_Query::encode_geohash( (float) $lat, (float) $lng ),
					'timezone'    => '',
				)
			);
		}
	}

	/**
	 * Normalize a term list input (string, comma-separated, or array).
	 *
	 * @param mixed $input Term input.
	 * @return array Array of term name strings.
	 */
	private static function normalize_term_list( $input ) {
		if ( is_array( $input ) ) {
			return array_map( 'sanitize_text_field', array_filter( $input ) );
		}

		if ( is_string( $input ) ) {
			return array_map( 'trim', array_filter( explode( ',', $input ) ) );
		}

		return array();
	}

	/**
	 * Sanitize an address array.
	 *
	 * @param array $address Raw address data.
	 * @return array Sanitized address data.
	 */
	private static function sanitize_address( $address ) {
		$sanitized = array();
		$keys      = array( 'address', 'city', 'state', 'country', 'postal_code', 'lat', 'lng' );

		foreach ( $keys as $key ) {
			if ( isset( $address[ $key ] ) ) {
				if ( 'lat' === $key || 'lng' === $key ) {
					$sanitized[ $key ] = (float) $address[ $key ];
				} else {
					$sanitized[ $key ] = sanitize_text_field( $address[ $key ] );
				}
			}
		}

		return $sanitized;
	}

	/**
	 * Download an image from URL and attach to a post.
	 *
	 * @param string $url     Image URL.
	 * @param int    $post_id Post ID.
	 * @return int|\WP_Error Attachment ID or error.
	 */
	private static function sideload_image( $url, $post_id ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$file_array = array(
			'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		);

		$id = media_handle_sideload( $file_array, $post_id );

		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
		}

		return $id;
	}
}
