<?php
/**
 * CSV Exporter — export listings to CSV.
 *
 * @package WBListora\ImportExport
 */

namespace WBListora\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Exports listings to CSV with all meta fields.
 */
class CSV_Exporter {

	/**
	 * Export arguments with defaults filled in.
	 *
	 * @param array<string, mixed> $args Export arguments.
	 * @return array<string, mixed>
	 */
	private static function parse_args( array $args ): array {
		return wp_parse_args(
			$args,
			array(
				'type'         => '',
				'status'       => 'publish',
				'category'     => 0,
				'date_from'    => '',
				'date_to'      => '',
				'per_page'     => -1,
				'include_meta' => true,
			)
		);
	}

	/**
	 * IDs of the listings an export would contain, newest first.
	 *
	 * IDs only: rows are loaded in small batches while writing, so the export
	 * never holds every listing in memory at once.
	 *
	 * @param array<string, mixed> $args Export arguments.
	 * @return int[]
	 */
	public static function listing_ids( array $args = array() ): array {
		$args = self::parse_args( $args );

		$query_args = array(
			'post_type'      => 'listora_listing',
			'post_status'    => $args['status'],
			'posts_per_page' => $args['per_page'],
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		if ( $args['type'] ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'listora_listing_type',
					'field'    => 'slug',
					'terms'    => $args['type'],
				),
			);
		}

		if ( $args['category'] ) {
			$query_args['tax_query'][] = array(
				'taxonomy' => 'listora_listing_cat',
				'field'    => 'term_id',
				'terms'    => (int) $args['category'],
			);
			if ( isset( $query_args['tax_query'][1] ) ) {
				$query_args['tax_query']['relation'] = 'AND';
			}
		}

		if ( $args['date_from'] ) {
			$query_args['date_query'][] = array( 'after' => $args['date_from'] );
		}
		if ( $args['date_to'] ) {
			$query_args['date_query'][] = array( 'before' => $args['date_to'] );
		}

		return array_map( 'intval', get_posts( $query_args ) );
	}

	/**
	 * Write the header row and one row per listing to an open stream.
	 *
	 * @param array<string, mixed> $args   Export arguments.
	 * @param int[]                $ids    Listing IDs from listing_ids().
	 * @param resource             $handle Writable stream.
	 * @return void
	 */
	private static function write( array $args, array $ids, $handle ): void {
		$args = self::parse_args( $args );

		// Build headers.
		$headers = array( 'ID', 'Title', 'Description', 'Status', 'Author', 'Date', 'Type', 'Categories', 'Tags', 'Location', 'URL' );

		// Collect the meta-field keys to export, plus per-key labels for the
		// header row. When a type is explicitly filtered, use just that type's
		// fields. When the export spans all types ("All Types" / "All Statuses"
		// dropdown), build the UNION across every listing type present in the
		// export so every row in a mixed-type CSV gets its full data column
		// set - the prior behaviour silently dropped every custom field for
		// multi-type exports, causing data loss on round-trip re-import.
		$meta_fields = array();
		if ( $args['include_meta'] ) {
			$registry = \WBListora\Core\Listing_Type_Registry::instance();

			if ( $args['type'] ) {
				$type_slugs = array( $args['type'] => true );
			} else {
				// Every type slug in this export's set: one query for all IDs.
				$present    = wp_get_object_terms( $ids, 'listora_listing_type', array( 'fields' => 'slugs' ) );
				$type_slugs = is_array( $present ) ? array_fill_keys( $present, true ) : array();
			}

			foreach ( array_keys( $type_slugs ) as $slug ) {
				$type = $registry->get( $slug );
				if ( ! $type ) {
					continue;
				}
				foreach ( $type->get_all_fields() as $field ) {
					// Per-key dedupe - when two types share a field key (common:
					// `address`, `phone`), the first label wins and both types'
					// values land in the same column on round-trip.
					$key = $field->get_key();
					if ( ! isset( $meta_fields[ $key ] ) ) {
						$meta_fields[ $key ] = $field->get_label();
					}
				}
			}
		}

		foreach ( $meta_fields as $label ) {
			$headers[] = $label;
		}

		fputcsv( $handle, array_map( array( __CLASS__, 'sanitize_csv_field' ), $headers ), ',', '"', '\\' );

		foreach ( array_chunk( $ids, 200 ) as $batch ) {
			$posts = get_posts(
				array(
					'post_type'      => 'listora_listing',
					'post_status'    => 'any',
					'post__in'       => $batch,
					'orderby'        => 'post__in',
					'posts_per_page' => count( $batch ),
					'no_found_rows'  => true,
				)
			);

			foreach ( $posts as $post ) {
				$row = array(
					$post->ID,
					wb_listora_decode_text( $post->post_title ),
					wb_listora_decode_text( wp_strip_all_tags( $post->post_content ) ),
					$post->post_status,
					get_the_author_meta( 'display_name', $post->post_author ),
					$post->post_date,
					implode( ', ', self::term_names( $post->ID, 'listora_listing_type' ) ),
					implode( ', ', self::term_names( $post->ID, 'listora_listing_cat' ) ),
					implode( ', ', self::term_names( $post->ID, 'listora_listing_tag' ) ),
					implode( ', ', self::term_names( $post->ID, 'listora_listing_location' ) ),
					get_permalink( $post->ID ),
				);

				// Add meta fields. Iterate over keys (the array values are the
				// header labels, used only for the header row above).
				foreach ( array_keys( $meta_fields ) as $key ) {
					$value = \WBListora\Core\Meta_Handler::get_value( $post->ID, $key );
					$row[] = is_array( $value ) ? wp_json_encode( $value ) : (string) $value;
				}

				fputcsv( $handle, array_map( array( __CLASS__, 'sanitize_csv_field' ), $row ), ',', '"', '\\' );
			}

			// Keep memory flat across a large export.
			wp_cache_flush_runtime();
		}
	}

	/**
	 * Export listings to a CSV file (WP-CLI).
	 *
	 * The file is a temporary file outside the uploads folder, so nothing is
	 * ever web-readable; the caller moves or deletes it.
	 *
	 * @param array<string, mixed> $args Export arguments.
	 * @return string|\WP_Error File path to the generated CSV.
	 */
	public static function export( array $args = array() ) {
		$ids = self::listing_ids( $args );

		if ( empty( $ids ) ) {
			return new \WP_Error( 'no_listings', __( 'No listings found to export.', 'wb-listora' ) );
		}

		$file_path = wp_tempnam( 'listora-export.csv' );
		$handle    = fopen( $file_path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- streams the CSV row by row, WP_Filesystem has no stream API.
		if ( false === $handle ) {
			return new \WP_Error( 'listora_export_unwritable', __( 'Could not open a temporary file for the export.', 'wb-listora' ) );
		}
		self::write( $args, $ids, $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- closes the stream opened above.

		return $file_path;
	}

	/**
	 * Send an export straight to the browser as a download.
	 *
	 * Rows go to php://output as they are built: no file is written anywhere,
	 * and the whole CSV is never held in memory.
	 *
	 * @param array<string, mixed> $args Export arguments.
	 * @return \WP_Error|void A WP_Error before anything is sent; otherwise ends the request.
	 */
	public static function stream( array $args = array() ) {
		$ids = self::listing_ids( $args );

		if ( empty( $ids ) ) {
			return new \WP_Error( 'no_listings', __( 'No listings found to export.', 'wb-listora' ) );
		}

		$handle = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- streams to the response.
		if ( false === $handle ) {
			return new \WP_Error( 'listora_export_unwritable', __( 'Could not open the response for the export.', 'wb-listora' ) );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="listora-export-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		self::write( $args, $ids, $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- closes the php://output stream.

		exit;
	}

	/**
	 * Neutralise CSV formula injection.
	 *
	 * A cell whose first character is one of `= + - @` (or a leading tab /
	 * carriage return) is interpreted by Excel / Sheets / LibreOffice as a
	 * formula when the exported file is opened — e.g. a listing titled
	 * `=CMD('calc')` would execute. Per OWASP guidance we prefix such a value
	 * with a single quote so the spreadsheet renders it as literal text; the
	 * quote is not part of the stored data, only the exported representation.
	 *
	 * @param mixed $value Cell value (cast to string).
	 * @return string Safe cell value.
	 */
	public static function sanitize_csv_field( $value ) {
		$value = (string) $value;

		if ( '' === $value ) {
			return $value;
		}

		if ( in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * Get the term names assigned to a post in a taxonomy as a clean string array.
	 *
	 * Guards the `wp_get_object_terms()` result against `WP_Error` (and the
	 * `int` no-such-taxonomy return) before plucking names, so the export row
	 * builder never hands a non-array to `wp_list_pluck()`.
	 *
	 * @param int    $post_id  Listing post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return string[] Term names (empty array when none / on error).
	 */
	private static function term_names( $post_id, $taxonomy ) {
		$terms = wp_get_object_terms( $post_id, $taxonomy );

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		// Names are stored HTML-encoded ("Bar &amp; Grill"); a spreadsheet wants the text.
		return array_map( 'wb_listora_decode_text', wp_list_pluck( $terms, 'name' ) );
	}
}
