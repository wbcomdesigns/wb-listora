<?php
/**
 * REST: photo uploads for members' own listings.
 *
 * @package WBListora\REST
 * @since   2.1.2
 */

namespace WBListora\REST;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Attachments_Controller;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

/**
 * POST /media: the one upload door for the submission wizard and the dashboard.
 *
 * Members never get core `upload_files`, which would open the Media menu and
 * the admin's library to them. Their photos are flagged
 * {@see \WBListora\Plugin::MEMBER_MEDIA_META} and kept out of the admin's library.
 */
class Media_Controller extends WP_REST_Controller {

	/**
	 * Accepted image types. Core checks the real file contents against this list.
	 */
	private const MIMES = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
		'gif'          => 'image/gif',
	);

	protected $namespace = WB_LISTORA_REST_NAMESPACE;
	protected $rest_base = 'media';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'upload' ),
					'permission_callback' => array( $this, 'upload_permissions' ),
				),
			)
		);
	}

	/**
	 * Anyone who may add a listing, or who already owns one (their role may have
	 * been unticked since, and they still edit it).
	 *
	 * @return true|WP_Error
	 */
	public function upload_permissions() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'listora_unauthorized', __( 'Please sign in to upload photos.', 'wb-listora' ), array( 'status' => 401 ) );
		}

		$owns_listing = (bool) get_posts(
			array(
				'post_type'      => 'listora_listing',
				'post_status'    => 'any',
				'author'         => get_current_user_id(),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		if ( current_user_can( 'submit_listora_listing' ) || current_user_can( 'upload_files' ) || $owns_listing ) {
			return true;
		}

		return new WP_Error( 'listora_forbidden', __( 'You do not have permission to upload photos.', 'wb-listora' ), array( 'status' => 403 ) );
	}

	/**
	 * Store the image in `file` and return it in the /wp/v2/media shape.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function upload( $request ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$files = $request->get_file_params();
		$file  = isset( $files['file'] ) && is_array( $files['file'] ) ? $files['file'] : array();
		$check = wp_check_filetype_and_ext( (string) ( $file['tmp_name'] ?? '' ), (string) ( $file['name'] ?? '' ), self::MIMES );
		if ( empty( $check['type'] ) ) {
			return new WP_Error( 'listora_invalid_image', __( 'Please upload a JPEG, PNG, WebP or GIF image.', 'wb-listora' ), array( 'status' => 400 ) );
		}

		$id = media_handle_upload(
			'file',
			0,
			array(),
			array(
				'test_form' => false,
				'mimes'     => self::MIMES,
			)
		);
		if ( is_wp_error( $id ) ) {
			$id->add_data( array( 'status' => 400 ) );
			return $id;
		}

		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wb_listora_mark_member_media( $id );
		}

		$response = ( new WP_REST_Attachments_Controller( 'attachment' ) )->prepare_item_for_response( get_post( $id ), $request );
		$response->set_status( 201 );
		return $response;
	}
}
