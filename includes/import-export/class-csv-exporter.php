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
		$headers = array( 'ID', 'Title', 'Description', 'Status', 'Author', 'Date', 'Type', 'Categories', 'Tags', 'URL' );

		// Get meta field headers from listing type.
		$meta_fields = array();
		if ( $args['include_meta'] && $args['type'] ) {
			$registry = \WBListora\Core\Listing_Type_Registry::instance();
			$type     = $registry->get( $args['type'] );
			if ( $type ) {
				foreach ( $type->get_all_fields() as $field ) {
					$meta_fields[] = $field->get_key();
					$headers[]     = $field->get_label();
				}
			}
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
				implode( ', ', wp_list_pluck( wp_get_object_terms( $post->ID, 'listora_listing_type' ), 'name' ) ),
				implode( ', ', wp_list_pluck( wp_get_object_terms( $post->ID, 'listora_listing_cat' ), 'name' ) ),
				implode( ', ', wp_list_pluck( wp_get_object_terms( $post->ID, 'listora_listing_tag' ), 'name' ) ),
				get_permalink( $post->ID ),
			);

			// Add meta fields.
			foreach ( $meta_fields as $key ) {
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
