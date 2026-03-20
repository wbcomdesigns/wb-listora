<?php
/**
 * REST Submission Controller — handles frontend listing submissions.
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
 * Handles frontend listing creation and editing.
 */
class Submission_Controller extends WP_REST_Controller {

	protected $namespace = WB_LISTORA_REST_NAMESPACE;
	protected $rest_base = 'submit';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// POST /submit — create listing from frontend.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_listing' ),
					'permission_callback' => function () {
						return current_user_can( 'submit_listora_listing' );
					},
				),
			)
		);

		// PUT /submit/{id} — edit own listing.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_listing' ),
					'permission_callback' => function ( $request ) {
						$post = get_post( $request->get_param( 'id' ) );
						return $post && (int) $post->post_author === get_current_user_id();
					},
				),
			)
		);
	}

	/**
	 * Handle listing submission.
	 */
	public function submit_listing( $request ) {
		// Honeypot check.
		$hp = $request->get_param( 'listora_hp_field' );
		if ( ! empty( $hp ) ) {
			return new WP_Error( 'listora_spam', __( 'Submission blocked.', 'wb-listora' ), array( 'status' => 403 ) );
		}

		// Nonce check — validates when present (HTML form submissions).
		// REST API has its own CSRF protection via X-WP-Nonce header in permission_callback.
		$nonce = $request->get_param( 'listora_nonce' );
		if ( $nonce && ! wp_verify_nonce( $nonce, 'listora_submit_listing' ) ) {
			return new WP_Error( 'listora_nonce_failed', __( 'Security check failed.', 'wb-listora' ), array( 'status' => 403 ) );
		}

		$title       = sanitize_text_field( $request->get_param( 'title' ) ?? '' );
		$description = sanitize_textarea_field( $request->get_param( 'description' ) ?? '' );
		$type_slug   = sanitize_text_field( $request->get_param( 'listing_type' ) ?? '' );
		$category    = absint( $request->get_param( 'category' ) ?? 0 );
		$tags        = sanitize_text_field( $request->get_param( 'tags' ) ?? '' );
		$status      = $request->get_param( 'status' ) === 'draft' ? 'draft' : $this->get_submission_status();

		if ( empty( $title ) ) {
			return new WP_Error( 'listora_title_required', __( 'Title is required.', 'wb-listora' ), array( 'status' => 400 ) );
		}

		// Create the post.
		$post_data = array(
			'post_type'    => 'listora_listing',
			'post_title'   => $title,
			'post_content' => $description,
			'post_status'  => $status,
			'post_author'  => get_current_user_id(),
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set listing type.
		if ( $type_slug ) {
			wp_set_object_terms( $post_id, $type_slug, 'listora_listing_type' );
		}

		// Set category.
		if ( $category > 0 ) {
			wp_set_object_terms( $post_id, array( $category ), 'listora_listing_cat' );
		}

		// Set tags.
		if ( $tags ) {
			$tag_array = array_map( 'trim', explode( ',', $tags ) );
			wp_set_object_terms( $post_id, $tag_array, 'listora_listing_tag' );
		}

		// Set featured image.
		$featured_image = absint( $request->get_param( 'featured_image' ) ?? 0 );
		if ( $featured_image > 0 ) {
			set_post_thumbnail( $post_id, $featured_image );
		}

		// Set gallery.
		$gallery = $request->get_param( 'gallery' );
		if ( $gallery ) {
			$gallery_ids = array_map( 'absint', explode( ',', $gallery ) );
			\WBListora\Core\Meta_Handler::set_value( $post_id, 'gallery', $gallery_ids );
		}

		// Set video.
		$video = esc_url_raw( $request->get_param( 'video' ) ?? '' );
		if ( $video ) {
			\WBListora\Core\Meta_Handler::set_value( $post_id, 'video', $video );
		}

		// Save type-specific meta fields.
		$this->save_meta_fields( $post_id, $type_slug, $request );

		/**
		 * Fires after a listing is submitted from the frontend.
		 *
		 * @param int             $post_id Post ID.
		 * @param string          $status  Post status.
		 * @param WP_REST_Request $request Request.
		 */
		do_action( 'wb_listora_listing_submitted', $post_id, $status, $request );

		return new WP_REST_Response(
			array(
				'id'      => $post_id,
				'status'  => $status,
				'url'     => get_permalink( $post_id ),
				'message' => 'draft' === $status
					? __( 'Draft saved.', 'wb-listora' )
					: __( 'Listing submitted successfully!', 'wb-listora' ),
			),
			201
		);
	}

	/**
	 * Update an existing listing.
	 */
	public function update_listing( $request ) {
		$post_id = $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new WP_Error( 'listora_not_found', __( 'Listing not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		$update_data = array( 'ID' => $post_id );

		$title = $request->get_param( 'title' );
		if ( null !== $title ) {
			$update_data['post_title'] = sanitize_text_field( $title );
		}

		$description = $request->get_param( 'description' );
		if ( null !== $description ) {
			$update_data['post_content'] = sanitize_textarea_field( $description );
		}

		wp_update_post( $update_data );

		// Update type-specific meta.
		$types     = wp_get_object_terms( $post_id, 'listora_listing_type', array( 'fields' => 'slugs' ) );
		$type_slug = ! is_wp_error( $types ) && ! empty( $types ) ? $types[0] : '';

		$this->save_meta_fields( $post_id, $type_slug, $request );

		return new WP_REST_Response(
			array(
				'id'      => $post_id,
				'status'  => get_post_status( $post_id ),
				'url'     => get_permalink( $post_id ),
				'message' => __( 'Listing updated.', 'wb-listora' ),
			),
			200
		);
	}

	/**
	 * Save type-specific meta fields from the request.
	 */
	private function save_meta_fields( $post_id, $type_slug, $request ) {
		if ( ! $type_slug ) {
			return;
		}

		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get( $type_slug );

		if ( ! $type ) {
			return;
		}

		$all_params = $request->get_params();

		foreach ( $type->get_all_fields() as $field ) {
			$param_key = 'meta_' . $field->get_key();

			if ( ! isset( $all_params[ $param_key ] ) ) {
				continue;
			}

			$value    = $all_params[ $param_key ];
			$sanitize = $field->get_sanitize_callback();

			if ( is_callable( $sanitize ) ) {
				$value = call_user_func( $sanitize, $value );
			}

			\WBListora\Core\Meta_Handler::set_value( $post_id, $field->get_key(), $value );
		}
	}

	/**
	 * Determine the post status for a new submission.
	 */
	private function get_submission_status() {
		$moderation = wb_listora_get_setting( 'moderation', 'manual' );

		if ( 'auto_approve' === $moderation ) {
			return 'publish';
		}

		if ( current_user_can( 'publish_listora_listings' ) ) {
			return 'publish';
		}

		return 'pending';
	}
}
