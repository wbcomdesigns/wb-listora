<?php
/**
 * CSV Importer — import listings from CSV files.
 *
 * @package WBListora\ImportExport
 */

namespace WBListora\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Handles CSV import with column mapping and batch processing.
 */
class CSV_Importer {

	/**
	 * Parse a CSV file and return headers + preview rows.
	 *
	 * @param string $file_path Path to CSV file.
	 * @param int    $preview_rows Number of preview rows.
	 * @return array { headers: string[], preview: array[], total: int }
	 */
	public static function parse_preview( $file_path, $preview_rows = 3 ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'CSV file not found.', 'wb-listora' ) );
		}

		$handle  = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$headers = fgetcsv( $handle );
		$preview = array();
		$total   = 0;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			++$total;
			if ( count( $preview ) < $preview_rows ) {
				$preview[] = $row;
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return array(
			'headers' => $headers,
			'preview' => $preview,
			'total'   => $total,
		);
	}

	/**
	 * Get available fields for mapping.
	 *
	 * @param string $type_slug Listing type slug.
	 * @return array Key => label pairs.
	 */
	public static function get_mappable_fields( $type_slug ) {
		$fields = array(
			'title'       => __( 'Title', 'wb-listora' ),
			'description' => __( 'Description', 'wb-listora' ),
			'category'    => __( 'Category', 'wb-listora' ),
			'tags'        => __( 'Tags', 'wb-listora' ),
			'location'    => __( 'Location', 'wb-listora' ),
			'image_url'   => __( 'Featured Image URL', 'wb-listora' ),
			'_skip'       => __( '— Skip this column —', 'wb-listora' ),
		);

		if ( $type_slug ) {
			$registry = \WBListora\Core\Listing_Type_Registry::instance();
			$type     = $registry->get( $type_slug );

			if ( $type ) {
				foreach ( $type->get_all_fields() as $field ) {
					$fields[ 'meta_' . $field->get_key() ] = $field->get_label();
				}
			}
		}

		return $fields;
	}

	/**
	 * Import listings from a CSV file.
	 *
	 * @param string $file_path Path to CSV file.
	 * @param string $type_slug Listing type slug.
	 * @param array  $mapping   Column index => field key mapping.
	 * @param bool   $dry_run   If true, only validate without creating.
	 * @return array { imported: int, errors: int, skipped: int, messages: string[] }
	 */
	public static function import( $file_path, $type_slug, array $mapping, $dry_run = false ) {
		if ( ! file_exists( $file_path ) ) {
			return array(
				'imported' => 0,
				'errors'   => 1,
				'skipped'  => 0,
				'messages' => array( __( 'File not found.', 'wb-listora' ) ),
			);
		}

		$handle  = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$headers = fgetcsv( $handle ); // Skip header row.

		$stats   = array(
			'imported' => 0,
			'errors'   => 0,
			'skipped'  => 0,
			'messages' => array(),
		);
		$row_num = 1;

		/**
		 * Cap the number of data rows a single CSV import will process.
		 *
		 * Guards against a runaway import (an accidental multi-hundred-MB file,
		 * or a hostile upload) tying up the request and creating an unbounded
		 * number of listings. Return 0 to disable the cap.
		 *
		 * @param int $max_rows Default 10000.
		 */
		$max_rows = (int) apply_filters( 'wb_listora_csv_import_max_rows', 10000 );

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			++$row_num;

			if ( $max_rows > 0 && ( $row_num - 1 ) > $max_rows ) {
				/* translators: %s: maximum number of rows */
				$stats['messages'][] = sprintf( __( 'Import stopped at the %s-row limit. Split the file into smaller batches, or raise the wb_listora_csv_import_max_rows filter.', 'wb-listora' ), number_format_i18n( $max_rows ) );
				break;
			}

			$data = self::map_row( $row, $mapping );

			if ( empty( $data['title'] ) ) {
				++$stats['skipped'];
				/* translators: %d: row number */
				$stats['messages'][] = sprintf( __( 'Row %d: Missing title, skipped.', 'wb-listora' ), $row_num );
				continue;
			}

			if ( $dry_run ) {
				++$stats['imported'];
				continue;
			}

			$result = self::create_listing( $data, $type_slug );

			if ( is_wp_error( $result ) ) {
				++$stats['errors'];
				/* translators: 1: row number, 2: error message */
				$stats['messages'][] = sprintf( __( 'Row %1$d: %2$s', 'wb-listora' ), $row_num, $result->get_error_message() );
			} else {
				++$stats['imported'];
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return $stats;
	}

	/**
	 * Map a CSV row to field data using the column mapping.
	 *
	 * @param array $row     CSV row values.
	 * @param array $mapping Column index => field key.
	 * @return array
	 */
	private static function map_row( $row, $mapping ) {
		$data = array();

		foreach ( $mapping as $col_idx => $field_key ) {
			if ( '_skip' === $field_key || ! isset( $row[ $col_idx ] ) ) {
				continue;
			}

			$value = trim( $row[ $col_idx ] );

			if ( '' === $value ) {
				continue;
			}

			$data[ $field_key ] = $value;
		}

		return $data;
	}

	/**
	 * Create a listing from mapped data.
	 *
	 * @param array  $data      Mapped data.
	 * @param string $type_slug Listing type slug.
	 * @return int|\WP_Error Post ID or error.
	 */
	private static function create_listing( $data, $type_slug ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'listora_listing',
				'post_title'   => sanitize_text_field( $data['title'] ),
				'post_content' => sanitize_textarea_field( $data['description'] ?? '' ),
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set type.
		wp_set_object_terms( $post_id, $type_slug, 'listora_listing_type' );

		// Set category, tags, and location via Term_Helper — the single
		// canonical term-setter shared with the JSON / GeoJSON importers.
		// It normalizes each name (entity-decode → unslash → sanitize) so a
		// CSV cell exported as `B&amp;B` lands as `B&B` instead of surviving
		// as a literal entity that renders double-escaped in dropdowns
		// (Basecamp #9927392446), and creates missing terms on the fly.
		if ( ! empty( $data['category'] ) ) {
			Term_Helper::set_terms( $post_id, array( (string) $data['category'] ), 'listora_listing_cat' );
		}

		if ( ! empty( $data['tags'] ) ) {
			$tags = array_map( 'trim', explode( ',', $data['tags'] ) );
			Term_Helper::set_terms( $post_id, $tags, 'listora_listing_tag' );
		}

		// Set location terms. The column accepts comma-separated term names,
		// matching the category/tags convention; each name is resolved (or
		// created) in the listora_listing_location taxonomy.
		if ( ! empty( $data['location'] ) ) {
			$locations = array_map( 'trim', explode( ',', $data['location'] ) );
			Term_Helper::set_terms( $post_id, $locations, 'listora_listing_location' );
		}

		// Set meta fields.
		foreach ( $data as $key => $value ) {
			if ( 0 === strpos( $key, 'meta_' ) ) {
				$field_key = substr( $key, 5 );
				\WBListora\Core\Meta_Handler::set_value( $post_id, $field_key, $value );
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
