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
	 * Export listings to a CSV file.
	 *
	 * @param array $args Export arguments.
	 * @return string File path to the generated CSV.
	 */
	public static function export( array $args = array() ) {
		$args = wp_parse_args(
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

		$query_args = array(
			'post_type'      => 'listora_listing',
			'post_status'    => $args['status'],
			'posts_per_page' => $args['per_page'],
			'orderby'        => 'date',
			'order'          => 'DESC',
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

		$posts = get_posts( $query_args );

		if ( empty( $posts ) ) {
			return new \WP_Error( 'no_listings', __( 'No listings found to export.', 'wb-listora' ) );
		}

		// Build headers.
		$headers = array( 'ID', 'Title', 'Description', 'Status', 'Author', 'Date', 'Type', 'Categories', 'Tags', 'Location', 'URL' );

		// Collect the meta-field keys to export, plus per-key labels for the
		// header row. When a type is explicitly filtered, use just that type's
		// fields. When the export spans all types ("All Types" / "All Statuses"
		// dropdown), build the UNION across every listing type present in the
		// export so every row in a mixed-type CSV gets its full data column
		// set — the prior behaviour silently dropped every custom field for
		// multi-type exports, causing data loss on round-trip re-import.
		$meta_fields = array();
		if ( $args['include_meta'] ) {
			$registry = \WBListora\Core\Listing_Type_Registry::instance();

			if ( $args['type'] ) {
				$type = $registry->get( $args['type'] );
				if ( $type ) {
					foreach ( $type->get_all_fields() as $field ) {
						$meta_fields[ $field->get_key() ] = $field->get_label();
					}
				}
			} else {
				// Resolve every type slug that appears in this export's
				// post set, then UNION their fields. Keeps the column set
				// minimal — types absent from the result don't pollute
				// the header.
				$type_slugs = array();
				foreach ( $posts as $post ) {
					$post_types = wp_get_object_terms( $post->ID, 'listora_listing_type', array( 'fields' => 'slugs' ) );
					if ( is_array( $post_types ) ) {
						foreach ( $post_types as $slug ) {
							$type_slugs[ $slug ] = true;
						}
					}
				}

				foreach ( array_keys( $type_slugs ) as $slug ) {
					$type = $registry->get( $slug );
					if ( ! $type ) {
						continue;
					}
					foreach ( $type->get_all_fields() as $field ) {
						// Per-key dedupe — when two types share a field
						// key (common: `address`, `phone`), the first
						// label wins and both types' values land in the
						// same column on round-trip.
						$key = $field->get_key();
						if ( ! isset( $meta_fields[ $key ] ) ) {
							$meta_fields[ $key ] = $field->get_label();
						}
					}
				}
			}
		}

		foreach ( $meta_fields as $label ) {
			$headers[] = $label;
		}

		// Generate CSV.
		$upload_dir = wp_upload_dir();
		$file_name  = 'listora-export-' . gmdate( 'Y-m-d-His' ) . '.csv';
		$file_path  = $upload_dir['basedir'] . '/' . $file_name;

		$handle = fopen( $file_path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $handle, $headers );

		foreach ( $posts as $post ) {
			$row = array(
				$post->ID,
				$post->post_title,
				wp_strip_all_tags( $post->post_content ),
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
				if ( is_array( $value ) ) {
					$row[] = wp_json_encode( $value );
				} else {
					$row[] = (string) $value;
				}
			}

			fputcsv( $handle, $row );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return $file_path;
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

		return wp_list_pluck( $terms, 'name' );
	}

	/**
	 * Stream a CSV export as a download.
	 *
	 * @param array $args Export arguments.
	 */
	public static function download( array $args = array() ) {
		$file_path = self::export( $args );

		if ( is_wp_error( $file_path ) ) {
			wp_die( esc_html( $file_path->get_error_message() ) );
		}

		$file_name = basename( $file_path );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $file_name . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// Clean up temp file.
		wp_delete_file( $file_path );

		exit;
	}
}
