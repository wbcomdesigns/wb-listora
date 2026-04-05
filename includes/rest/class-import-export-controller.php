<?php
/**
 * REST Import/Export Controller.
 *
 * Replaces admin_post_listora_export_csv and admin_post_listora_import_csv
 * with proper REST endpoints.
 *
 * @package WBListora\REST
 */

namespace WBListora\REST;

defined( 'ABSPATH' ) || exit;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WBListora\ImportExport\CSV_Exporter;
use WBListora\ImportExport\CSV_Importer;

/**
 * Handles CSV export and import via REST.
 */
class Import_Export_Controller extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = WB_LISTORA_REST_NAMESPACE;

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET /listora/v1/export/csv
		register_rest_route(
			$this->namespace,
			'/export/csv',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_csv' ),
					'permission_callback' => array( $this, 'manage_options_permissions' ),
					'args'                => array(
						'type'         => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => 'Listing type slug to filter by.',
						),
						'status'       => array(
							'type'              => 'string',
							'default'           => 'publish',
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => 'Post status to export.',
						),
						'category'     => array(
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
							'description'       => 'Category term ID to filter by.',
						),
						'date_from'    => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => 'Start date for date range filter (YYYY-MM-DD).',
						),
						'date_to'      => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => 'End date for date range filter (YYYY-MM-DD).',
						),
						'include_meta' => array(
							'type'    => 'boolean',
							'default' => true,
							'description' => 'Whether to include meta fields in the export.',
						),
					),
				),
			)
		);

		// POST /listora/v1/import/csv
		register_rest_route(
			$this->namespace,
			'/import/csv',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_csv' ),
					'permission_callback' => array( $this, 'manage_options_permissions' ),
					'args'                => array(
						'type_slug' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => 'Listing type slug for imported listings.',
						),
						'mapping'   => array(
							'type'        => 'object',
							'required'    => true,
							'description' => 'Column index to field key mapping (e.g. {"0":"title","1":"description"}).',
						),
						'dry_run'   => array(
							'type'    => 'boolean',
							'default' => false,
							'description' => 'If true, validate only without creating listings.',
						),
					),
				),
			)
		);
	}

	/**
	 * Export listings to CSV and stream as download.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function export_csv( $request ) {
		$args = array(
			'type'         => $request->get_param( 'type' ),
			'status'       => $request->get_param( 'status' ),
			'category'     => $request->get_param( 'category' ),
			'date_from'    => $request->get_param( 'date_from' ),
			'date_to'      => $request->get_param( 'date_to' ),
			'per_page'     => -1,
			'include_meta' => $request->get_param( 'include_meta' ),
		);

		$file_path = CSV_Exporter::export( $args );

		if ( is_wp_error( $file_path ) ) {
			return new WP_Error(
				'listora_export_failed',
				$file_path->get_error_message(),
				array( 'status' => 400 )
			);
		}

		$file_name = basename( $file_path );

		// Read the file contents into memory and clean up the temp file.
		$contents = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		wp_delete_file( $file_path );

		if ( false === $contents ) {
			return new WP_Error(
				'listora_export_read_failed',
				__( 'Failed to read export file.', 'wb-listora' ),
				array( 'status' => 500 )
			);
		}

		// Send CSV response with download headers.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $file_name . '"' );
		header( 'Content-Length: ' . strlen( $contents ) );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV binary data.
		exit;
	}

	/**
	 * Import listings from an uploaded CSV file.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function import_csv( $request ) {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) || empty( $files['file']['tmp_name'] ) ) {
			return new WP_Error(
				'listora_import_no_file',
				__( 'No CSV file uploaded. Send the file as "file" in multipart/form-data.', 'wb-listora' ),
				array( 'status' => 400 )
			);
		}

		$file = $files['file'];

		// Validate file type.
		$mime_types = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' );
		if ( ! empty( $file['type'] ) && ! in_array( $file['type'], $mime_types, true ) ) {
			return new WP_Error(
				'listora_import_invalid_type',
				__( 'Invalid file type. Please upload a CSV file.', 'wb-listora' ),
				array( 'status' => 400 )
			);
		}

		// Validate upload error.
		if ( ! empty( $file['error'] ) ) {
			return new WP_Error(
				'listora_import_upload_error',
				/* translators: %d: PHP upload error code */
				sprintf( __( 'File upload error (code %d).', 'wb-listora' ), (int) $file['error'] ),
				array( 'status' => 400 )
			);
		}

		$type_slug = $request->get_param( 'type_slug' );
		$mapping   = $request->get_param( 'mapping' );
		$dry_run   = (bool) $request->get_param( 'dry_run' );

		// Convert mapping keys to integers (JSON objects always have string keys).
		$int_mapping = array();
		foreach ( $mapping as $col_idx => $field_key ) {
			$int_mapping[ (int) $col_idx ] = sanitize_text_field( $field_key );
		}

		$result = CSV_Importer::import( $file['tmp_name'], $type_slug, $int_mapping, $dry_run );

		return new WP_REST_Response(
			array(
				'imported' => $result['imported'],
				'errors'   => $result['errors'],
				'skipped'  => $result['skipped'],
				'messages' => $result['messages'],
				'dry_run'  => $dry_run,
			),
			200
		);
	}

	/**
	 * Permission check: manage_options.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function manage_options_permissions( $request ) {
		return current_user_can( 'manage_options' );
	}
}
