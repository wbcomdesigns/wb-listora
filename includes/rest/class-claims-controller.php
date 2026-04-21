<?php
/**
 * REST Claims Controller.
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

/**
 * Handles listing claim requests — submit, review, approve/reject.
 */
class Claims_Controller extends WP_REST_Controller {

	protected $namespace = WB_LISTORA_REST_NAMESPACE;
	protected $rest_base = 'claims';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// POST /claims — submit a claim.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_claim' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
					'args'                => array(
						'listing_id' => array(
							'type'     => 'integer',
							'required' => true,
						),
						'proof_text' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'role'       => array(
							'type'              => 'string',
							'default'           => 'owner',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				// GET /claims — admin list all claims.
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_claims' ),
					'permission_callback' => array( $this, 'admin_permissions' ),
					'args'                => array(
						'status'   => array(
							'type'    => 'string',
							'default' => '',
							'enum'    => array( '', 'pending', 'approved', 'rejected' ),
						),
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
			)
		);

		// GET /claims/mine — logged-in user's own claim history.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/mine',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_my_claims' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
			)
		);

		// PUT /claims/{id} — approve or reject.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_claim' ),
					'permission_callback' => array( $this, 'admin_permissions' ),
					'args'                => array(
						'id'          => array(
							'type'     => 'integer',
							'required' => true,
						),
						'status'      => array(
							'type'     => 'string',
							'required' => true,
							'enum'     => array( 'approved', 'rejected' ),
						),
						'admin_notes' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Submit a claim request.
	 */
	public function submit_claim( $request ) {
		global $wpdb;
		$prefix     = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$user_id    = get_current_user_id();
		$listing_id = $request->get_param( 'listing_id' );

		// Check claiming is enabled.
		if ( ! wb_listora_get_setting( 'enable_claiming', true ) ) {
			return new WP_Error( 'listora_claims_disabled', __( 'Claiming is not enabled.', 'wb-listora' ), array( 'status' => 403 ) );
		}

		// Check listing exists.
		$post = get_post( $listing_id );
		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new WP_Error( 'listora_invalid_listing', __( 'Listing not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		// Check not already claimed.
		$is_claimed = get_post_meta( $listing_id, '_listora_is_claimed', true );
		if ( $is_claimed ) {
			return new WP_Error( 'listora_already_claimed', __( 'This listing has already been claimed.', 'wb-listora' ), array( 'status' => 409 ) );
		}

		// Check if user is already the author.
		if ( (int) $post->post_author === $user_id ) {
			return new WP_Error( 'listora_own_listing', __( 'You already own this listing.', 'wb-listora' ), array( 'status' => 409 ) );
		}

		// Check for pending claim by this user.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$prefix}claims WHERE listing_id = %d AND user_id = %d AND status = 'pending'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$listing_id,
				$user_id
			)
		);

		if ( $existing ) {
			return new WP_Error( 'listora_claim_pending', __( 'You already have a pending claim for this listing.', 'wb-listora' ), array( 'status' => 409 ) );
		}

		/**
		 * Filters whether to allow submitting a claim. Return WP_Error to abort.
		 *
		 * @param bool|WP_Error   $check      True to proceed, WP_Error to abort.
		 * @param int             $listing_id Listing ID.
		 * @param WP_REST_Request $request    REST request.
		 */
		$check = apply_filters( 'wb_listora_before_submit_claim', true, $listing_id, $request );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Handle proof file upload.
		$proof_file_ids = array();
		$files          = $request->get_file_params();

		if ( ! empty( $files['proof_file'] ) ) {
			$proof_file_result = $this->handle_proof_file_upload( $files['proof_file'] );

			if ( is_wp_error( $proof_file_result ) ) {
				return $proof_file_result;
			}

			$proof_file_ids[] = $proof_file_result;
		}

		$wpdb->insert(
			"{$prefix}claims", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array(
				'listing_id'  => $listing_id,
				'user_id'     => $user_id,
				'status'      => 'pending',
				'proof_text'  => $request->get_param( 'proof_text' ),
				'proof_files' => ! empty( $proof_file_ids ) ? wp_json_encode( $proof_file_ids ) : null,
				'created_at'  => current_time( 'mysql', true ),
				'updated_at'  => current_time( 'mysql', true ),
			)
		);

		$claim_id = $wpdb->insert_id;

		/**
		 * Fires after a claim is submitted.
		 *
		 * @param int $claim_id   Claim ID.
		 * @param int $listing_id Listing ID.
		 * @param int $user_id    User ID.
		 */
		do_action( 'wb_listora_claim_submitted', $claim_id, $listing_id, $user_id );

		/**
		 * Fires after a claim is submitted.
		 *
		 * @param int             $claim_id   Claim ID.
		 * @param int             $listing_id Listing ID.
		 * @param WP_REST_Request $request    REST request.
		 */
		do_action( 'wb_listora_after_submit_claim', $claim_id, $listing_id, $request );

		$response_data = array(
			'id'      => $claim_id,
			'status'  => 'pending',
			'message' => __( 'Your claim has been submitted and is under review.', 'wb-listora' ),
		);

		if ( ! empty( $proof_file_ids ) ) {
			$response_data['proof_file_url'] = wp_get_attachment_url( $proof_file_ids[0] );
		}

		/**
		 * Filters the claim submission REST response data.
		 *
		 * @param array           $response_data Response data.
		 * @param int             $claim_id      Claim ID.
		 * @param WP_REST_Request $request       REST request.
		 */
		$response_data = apply_filters( 'wb_listora_rest_prepare_claim', $response_data, $claim_id, $request );

		return new WP_REST_Response( $response_data, 201 );
	}

	/**
	 * Handle proof file upload for a claim.
	 *
	 * Accepts image or PDF files up to 5 MB. Creates a WP attachment.
	 *
	 * @param array $file The $_FILES entry for proof_file.
	 * @return int|WP_Error Attachment ID on success, WP_Error on failure.
	 */
	private function handle_proof_file_upload( $file ) {
		// Validate upload error.
		if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error(
				'listora_proof_upload_error',
				/* translators: %d: PHP upload error code */
				sprintf( __( 'File upload error (code %d).', 'wb-listora' ), (int) $file['error'] ),
				array( 'status' => 400 )
			);
		}

		// Validate file exists.
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error(
				'listora_proof_no_file',
				__( 'No proof file received.', 'wb-listora' ),
				array( 'status' => 400 )
			);
		}

		// Validate file size (5 MB max).
		$max_size = 5 * MB_IN_BYTES;
		if ( $file['size'] > $max_size ) {
			return new WP_Error(
				'listora_proof_file_too_large',
				__( 'Proof file must be 5 MB or smaller.', 'wb-listora' ),
				array( 'status' => 400 )
			);
		}

		// Validate MIME type — images and PDF only.
		$allowed_mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
			'pdf'          => 'application/pdf',
		);

		$filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_mimes );
		if ( empty( $filetype['type'] ) ) {
			return new WP_Error(
				'listora_proof_invalid_type',
				__( 'Invalid file type. Accepted formats: JPEG, PNG, GIF, WebP, PDF.', 'wb-listora' ),
				array( 'status' => 400 )
			);
		}

		// Load required functions.
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => $allowed_mimes,
			)
		);

		if ( isset( $upload['error'] ) ) {
			return new WP_Error(
				'listora_proof_upload_failed',
				$upload['error'],
				array( 'status' => 500 )
			);
		}

		// Create WP attachment.
		$attachment_data = array(
			'post_title'     => sanitize_file_name( $file['name'] ),
			'post_mime_type' => $upload['type'],
			'post_status'    => 'private',
			'post_content'   => '',
		);

		$attachment_id = wp_insert_attachment( $attachment_data, $upload['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}

	/**
	 * Get claims list (admin).
	 */
	public function get_claims( $request ) {
		global $wpdb;
		$prefix   = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$status   = $request->get_param( 'status' );
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = '1=1';
		$params = array();

		if ( $status ) {
			$where   .= ' AND c.status = %s';
			$params[] = $status;
		}

		$params[] = $per_page;
		$params[] = $offset;

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}claims c WHERE {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...( $status ? array( $status ) : array() )
			)
		);

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- dynamic WHERE with spread operator.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT c.*, p.post_title as listing_title, u.display_name as user_name, u.user_email
			FROM {$prefix}claims c
			LEFT JOIN {$wpdb->posts} p ON c.listing_id = p.ID
			LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
			WHERE {$where}
			ORDER BY c.created_at DESC LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		);

		$claims = array_map(
			function ( $row ) use ( $request ) {
				$proof_file_urls = array();
				if ( ! empty( $row['proof_files'] ) ) {
					$file_ids = json_decode( $row['proof_files'], true );
					if ( is_array( $file_ids ) ) {
						foreach ( $file_ids as $att_id ) {
							$url = wp_get_attachment_url( (int) $att_id );
							if ( $url ) {
								$proof_file_urls[] = array(
									'id'   => (int) $att_id,
									'url'  => $url,
									'type' => get_post_mime_type( (int) $att_id ),
								);
							}
						}
					}
				}

				$claim_data = array(
					'id'            => (int) $row['id'],
					'listing_id'    => (int) $row['listing_id'],
					'listing_title' => $row['listing_title'] ?: '',
					'listing_url'   => get_permalink( (int) $row['listing_id'] ),
					'user_id'       => (int) $row['user_id'],
					'user_name'     => $row['user_name'] ?: '',
					'user_email'    => $row['user_email'] ?: '',
					'status'        => $row['status'],
					'proof_text'    => $row['proof_text'],
					'proof_files'   => $proof_file_urls,
					'admin_notes'   => $row['admin_notes'] ?: '',
					'created_at'    => $row['created_at'],
				);

				/**
				 * Filters a single claim in the REST response list.
				 *
				 * @param array           $claim_data Claim data.
				 * @param int             $claim_id   Claim ID.
				 * @param WP_REST_Request $request    REST request.
				 */
				return apply_filters( 'wb_listora_rest_prepare_claim', $claim_data, (int) $row['id'], $request );
			},
			$rows
		);

		$has_more = ( $offset + count( $rows ) ) < $total;

		$response = new WP_REST_Response(
			array(
				'claims'   => $claims,
				'total'    => $total,
				'pages'    => (int) ceil( $total / $per_page ),
				'has_more' => $has_more,
			),
			200
		);

		$response->header( 'X-WP-Total', $total );
		return $response;
	}

	/**
	 * Return the claims submitted by the current logged-in user.
	 *
	 * Used by the dashboard Claims tab so users can see where each request stands.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function get_my_claims( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$prefix   = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$user_id  = get_current_user_id();
		$per_page = (int) $request->get_param( 'per_page' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT c.*, p.post_title AS listing_title
				FROM {$prefix}claims c
				LEFT JOIN {$wpdb->posts} p ON c.listing_id = p.ID
				WHERE c.user_id = %d
				ORDER BY c.created_at DESC
				LIMIT %d",
				$user_id,
				$per_page
			),
			ARRAY_A
		);

		$claims = array_map(
			function ( $row ) use ( $request ) {
				$claim_data = array(
					'id'            => (int) $row['id'],
					'listing_id'    => (int) $row['listing_id'],
					'listing_title' => $row['listing_title'] ?: '',
					'listing_url'   => get_permalink( (int) $row['listing_id'] ),
					'status'        => $row['status'],
					'admin_notes'   => $row['admin_notes'] ?: '',
					'created_at'    => $row['created_at'],
					'updated_at'    => $row['updated_at'] ?? null,
				);

				return apply_filters( 'wb_listora_rest_prepare_claim', $claim_data, (int) $row['id'], $request );
			},
			$rows
		);

		$total = count( $claims );

		return new WP_REST_Response(
			array(
				'claims'   => $claims,
				'total'    => $total,
				'pages'    => $total > 0 ? 1 : 0,
				'has_more' => false,
			),
			200
		);
	}

	/**
	 * Approve or reject a claim.
	 */
	public function update_claim( $request ) {
		global $wpdb;
		$prefix      = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$claim_id    = $request->get_param( 'id' );
		$new_status  = $request->get_param( 'status' );
		$admin_notes = $request->get_param( 'admin_notes' );

		$claim = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$prefix}claims WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$claim_id
			),
			ARRAY_A
		);

		if ( ! $claim ) {
			return new WP_Error( 'listora_claim_not_found', __( 'Claim not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		/**
		 * Filters whether to allow updating a claim. Return WP_Error to abort.
		 *
		 * @param bool|WP_Error   $check     True to proceed, WP_Error to abort.
		 * @param int             $claim_id  Claim ID.
		 * @param WP_REST_Request $request   REST request.
		 */
		$check = apply_filters( 'wb_listora_before_update_claim', true, $claim_id, $request );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Update claim status.
		$wpdb->update(
			"{$prefix}claims", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array(
				'status'      => $new_status,
				'admin_notes' => $admin_notes,
				'reviewed_by' => get_current_user_id(),
				'updated_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => $claim_id )
		);

		// If approved: transfer ownership + set claimed flag.
		if ( 'approved' === $new_status ) {
			$listing_id = (int) $claim['listing_id'];
			$claimant   = (int) $claim['user_id'];

			// Transfer post authorship.
			wp_update_post(
				array(
					'ID'          => $listing_id,
					'post_author' => $claimant,
				)
			);

			// Set claimed flag.
			update_post_meta( $listing_id, '_listora_is_claimed', true );

			// Update search index.
			$wpdb->update(
				"{$prefix}search_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array(
					'is_claimed' => 1,
					'author_id'  => $claimant,
				),
				array( 'listing_id' => $listing_id )
			);

			/**
			 * Fires after a claim is approved.
			 *
			 * @param int $claim_id   Claim ID.
			 * @param int $listing_id Listing ID.
			 * @param int $user_id    New owner user ID.
			 */
			do_action( 'wb_listora_claim_approved', $claim_id, $listing_id, $claimant );
		} else {
			/**
			 * Fires after a claim is rejected.
			 *
			 * @param int $claim_id   Claim ID.
			 * @param int $listing_id Listing ID.
			 */
			do_action( 'wb_listora_claim_rejected', $claim_id, (int) $claim['listing_id'] );
		}

		/**
		 * Fires after a claim is updated (approved or rejected).
		 *
		 * @param int             $claim_id   Claim ID.
		 * @param string          $new_status New status (approved or rejected).
		 * @param WP_REST_Request $request    REST request.
		 */
		do_action( 'wb_listora_after_update_claim', $claim_id, $new_status, $request );

		$response_data = array(
			'id'      => $claim_id,
			'status'  => $new_status,
			'message' => 'approved' === $new_status
				? __( 'Claim approved. Listing ownership transferred.', 'wb-listora' )
				: __( 'Claim rejected.', 'wb-listora' ),
		);

		/**
		 * Filters the claim update REST response data.
		 *
		 * @param array           $response_data Response data.
		 * @param int             $claim_id      Claim ID.
		 * @param WP_REST_Request $request       REST request.
		 */
		$response_data = apply_filters( 'wb_listora_rest_prepare_claim', $response_data, $claim_id, $request );

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Admin permission check.
	 */
	public function admin_permissions() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'listora_unauthorized',
				__( 'You do not have permission to perform this action.', 'wb-listora' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'manage_listora_claims' ) ) {
			return new \WP_Error(
				'listora_forbidden',
				__( 'You do not have permission to perform this action.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check that the user is logged in, returning WP_Error if not.
	 *
	 * @return bool|\WP_Error
	 */
	public function logged_in_permissions() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'listora_unauthorized',
				__( 'You do not have permission to perform this action.', 'wb-listora' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}
}
