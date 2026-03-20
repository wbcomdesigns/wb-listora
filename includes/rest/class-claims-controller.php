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
					'permission_callback' => 'is_user_logged_in',
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
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
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
				"SELECT id FROM {$prefix}claims WHERE listing_id = %d AND user_id = %d AND status = 'pending'",
				$listing_id,
				$user_id
			)
		);

		if ( $existing ) {
			return new WP_Error( 'listora_claim_pending', __( 'You already have a pending claim for this listing.', 'wb-listora' ), array( 'status' => 409 ) );
		}

		$wpdb->insert(
			"{$prefix}claims",
			array(
				'listing_id' => $listing_id,
				'user_id'    => $user_id,
				'status'     => 'pending',
				'proof_text' => $request->get_param( 'proof_text' ),
				'created_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
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

		return new WP_REST_Response(
			array(
				'id'      => $claim_id,
				'status'  => 'pending',
				'message' => __( 'Your claim has been submitted and is under review.', 'wb-listora' ),
			),
			201
		);
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
				"SELECT COUNT(*) FROM {$prefix}claims c WHERE {$where}",
				...( $status ? array( $status ) : array() )
			)
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
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
			function ( $row ) {
				return array(
					'id'            => (int) $row['id'],
					'listing_id'    => (int) $row['listing_id'],
					'listing_title' => $row['listing_title'] ?: '',
					'listing_url'   => get_permalink( (int) $row['listing_id'] ),
					'user_id'       => (int) $row['user_id'],
					'user_name'     => $row['user_name'] ?: '',
					'user_email'    => $row['user_email'] ?: '',
					'status'        => $row['status'],
					'proof_text'    => $row['proof_text'],
					'admin_notes'   => $row['admin_notes'] ?: '',
					'created_at'    => $row['created_at'],
				);
			},
			$rows
		);

		$response = new WP_REST_Response(
			array(
				'claims' => $claims,
				'total'  => $total,
				'pages'  => (int) ceil( $total / $per_page ),
			),
			200
		);

		$response->header( 'X-WP-Total', $total );
		return $response;
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
				"SELECT * FROM {$prefix}claims WHERE id = %d",
				$claim_id
			),
			ARRAY_A
		);

		if ( ! $claim ) {
			return new WP_Error( 'listora_claim_not_found', __( 'Claim not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		// Update claim status.
		$wpdb->update(
			"{$prefix}claims",
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
				"{$prefix}search_index",
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

		return new WP_REST_Response(
			array(
				'id'      => $claim_id,
				'status'  => $new_status,
				'message' => 'approved' === $new_status
					? __( 'Claim approved. Listing ownership transferred.', 'wb-listora' )
					: __( 'Claim rejected.', 'wb-listora' ),
			),
			200
		);
	}

	/**
	 * Admin permission check.
	 */
	public function admin_permissions() {
		return current_user_can( 'manage_listora_claims' );
	}
}
