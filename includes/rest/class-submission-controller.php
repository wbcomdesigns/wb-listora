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
					'permission_callback' => array( $this, 'submit_listing_permissions' ),
					'args'                => array(
						'confirmed_not_duplicate' => array(
							'type'              => 'boolean',
							'default'           => false,
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
						'duplicate_explanation'   => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		// POST /submit/check-duplicate — check for duplicate listings.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/check-duplicate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'check_duplicate_endpoint' ),
					'permission_callback' => function () {
						if ( ! is_user_logged_in() ) {
							return new \WP_Error(
								'listora_unauthorized',
								__( 'You do not have permission to perform this action.', 'wb-listora' ),
								array( 'status' => 401 )
							);
						}
						return true;
					},
					'args'                => array(
						'title' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'type'  => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'lat'   => array(
							'type'    => 'number',
							'default' => null,
						),
						'lng'   => array(
							'type'    => 'number',
							'default' => null,
						),
					),
				),
			)
		);

		// POST /submission/resend-verification — resend the verify-email link.
		register_rest_route(
			$this->namespace,
			'/submission/resend-verification',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'resend_verification_endpoint' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'listing_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'email'      => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_email',
						),
					),
				),
			)
		);

		// GET /submission/verify — REST mirror of the public verify URL.
		register_rest_route(
			$this->namespace,
			'/submission/verify',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'verify_endpoint' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'listing_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'token'      => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
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
						if ( ! is_user_logged_in() ) {
							return new \WP_Error(
								'listora_unauthorized',
								__( 'You do not have permission to perform this action.', 'wb-listora' ),
								array( 'status' => 401 )
							);
						}
						$post = get_post( $request->get_param( 'id' ) );
						if ( ! $post || (int) $post->post_author !== get_current_user_id() ) {
							return new \WP_Error(
								'listora_forbidden',
								__( 'You do not have permission to perform this action.', 'wb-listora' ),
								array( 'status' => 403 )
							);
						}
						return true;
					},
				),
			)
		);
	}

	/**
	 * Permission callback for listing submissions.
	 *
	 * Allows logged-in users with submit_listora_listing capability,
	 * or non-logged-in guests when guest submission is enabled and
	 * guest fields are present in the request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function submit_listing_permissions( $request ) {
		if ( current_user_can( 'submit_listora_listing' ) ) {
			return true;
		}

		// Allow guest submissions when enabled and guest fields are provided.
		if ( ! is_user_logged_in() && wb_listora_get_setting( 'enable_guest_submission', false ) ) {
			$guest_email = $request->get_param( 'listora_guest_email' );
			$guest_name  = $request->get_param( 'listora_guest_name' );

			if ( ! empty( $guest_email ) && ! empty( $guest_name ) ) {
				return true;
			}
		}

		return new \WP_Error(
			'listora_unauthorized',
			__( 'You do not have permission to perform this action.', 'wb-listora' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Handle listing submission.
	 *
	 * If a listing_id is present in the body and the current user owns that listing,
	 * this routes to update_listing() instead of creating a new post.
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

		// ─── Rate limiting ───
		// Centralised in \WBListora\Rate_Limiter so every public POST
		// endpoint shares the same per-user / per-IP transient counters.

		$rate_check = \WBListora\Rate_Limiter::check( 'submission' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		// ─── CAPTCHA verification ───

		$captcha_token    = sanitize_text_field( $request->get_param( 'listora_captcha_token' ) ?? '' );
		$captcha_provider = sanitize_text_field( $request->get_param( 'listora_captcha_provider' ) ?? '' );

		$captcha_result = \WBListora\Captcha::verify( $captcha_token, $captcha_provider );
		if ( is_wp_error( $captcha_result ) ) {
			return $captcha_result;
		}

		// ─── Guest registration ───

		$guest_author_id      = 0;
		$verification_required = false;
		if ( ! is_user_logged_in() && wb_listora_get_setting( 'enable_guest_submission', false ) ) {
			$guest_name  = sanitize_text_field( $request->get_param( 'listora_guest_name' ) ?? '' );
			$guest_email = sanitize_email( $request->get_param( 'listora_guest_email' ) ?? '' );

			if ( ! empty( $guest_name ) && ! empty( $guest_email ) ) {
				if ( ! is_email( $guest_email ) ) {
					return new WP_Error(
						'listora_invalid_email',
						__( 'Please provide a valid email address.', 'wb-listora' ),
						array( 'status' => 400 )
					);
				}

				if ( email_exists( $guest_email ) ) {
					return new WP_Error(
						'listora_email_exists',
						__( 'An account with this email already exists. Please log in.', 'wb-listora' ),
						array( 'status' => 409 )
					);
				}

				// Create a username from the email.
				$username = sanitize_user( current( explode( '@', $guest_email ) ), true );
				if ( username_exists( $username ) ) {
					$username = $username . wp_rand( 100, 999 );
				}

				$password    = wp_generate_password( 16, true );
				$new_user_id = wp_create_user( $username, $password, $guest_email );

				if ( is_wp_error( $new_user_id ) ) {
					return new WP_Error(
						'listora_registration_failed',
						__( 'Unable to create your account. Please try again.', 'wb-listora' ),
						array( 'status' => 500 )
					);
				}

				// Set display name.
				wp_update_user(
					array(
						'ID'           => $new_user_id,
						'display_name' => $guest_name,
						'first_name'   => $guest_name,
					)
				);

				// Grant the submit capability.
				$user = get_user_by( 'ID', $new_user_id );
				if ( $user ) {
					$user->add_cap( 'submit_listora_listing' );
				}

				$guest_author_id = $new_user_id;

				// Decide whether this guest must verify their email before
				// the listing publishes. Verification is opt-out — admins can
				// disable it from the Submissions tab to restore the legacy
				// "auto-login + send password reset" behaviour.
				$verification_required = (bool) wb_listora_get_setting( 'guest_email_verification', true );

				if ( ! $verification_required ) {
					// Legacy flow: send the password reset and log them in.
					wp_new_user_notification( $new_user_id, null, 'user' );
					wp_set_current_user( $new_user_id );
				}
				// Else: do NOT auto-login. The verification email is sent
				// once we know the listing post ID (after wp_insert_post).
			}
		}

		// Edit mode: route to update when listing_id is in the body and user owns it.
		$listing_id = absint( $request->get_param( 'listing_id' ) ?? 0 );
		if ( $listing_id > 0 ) {
			$existing = get_post( $listing_id );
			if (
				$existing &&
				'listora_listing' === $existing->post_type &&
				(int) $existing->post_author === get_current_user_id()
			) {
				// Inject the id param so update_listing() can read it.
				$request->set_param( 'id', $listing_id );
				return $this->update_listing( $request );
			}
			// listing_id present but not owner — treat as a new submission attempt and let it fall through to create.
		}

		$title       = sanitize_text_field( $request->get_param( 'title' ) ?? '' );
		$description = sanitize_textarea_field( $request->get_param( 'description' ) ?? '' );
		$type_slug   = sanitize_text_field( $request->get_param( 'listing_type' ) ?? '' );
		$category    = absint( $request->get_param( 'category' ) ?? 0 );
		$tags        = sanitize_text_field( $request->get_param( 'tags' ) ?? '' );

		// Force pending_verification when this submission requires email
		// verification — overrides moderation/auto_approve for the initial
		// state, then transitions on token consumption.
		if ( $verification_required ) {
			$status = 'pending_verification';
		} else {
			$status = $request->get_param( 'status' ) === 'draft' ? 'draft' : $this->get_submission_status();
		}

		if ( empty( $title ) ) {
			return new WP_Error( 'listora_title_required', __( 'Title is required.', 'wb-listora' ), array( 'status' => 400 ) );
		}

		// Duplicate check — skip if the client has confirmed it is not a duplicate.
		$confirmed_not_duplicate = rest_sanitize_boolean( $request->get_param( 'confirmed_not_duplicate' ) );
		if ( ! $confirmed_not_duplicate ) {
			$lat = $request->get_param( 'lat' );
			$lng = $request->get_param( 'lng' );

			$duplicates = $this->check_duplicates(
				$title,
				$type_slug,
				null !== $lat ? (float) $lat : null,
				null !== $lng ? (float) $lng : null
			);

			if ( ! empty( $duplicates ) ) {
				return new WP_REST_Response(
					array(
						'code'       => 'listora_duplicate_detected',
						'message'    => __( 'Potential duplicate listing(s) found. Please confirm this is not a duplicate to proceed.', 'wb-listora' ),
						'duplicates' => $duplicates,
					),
					409
				);
			}
		} else {
			// Bypassing duplicate check requires a substantive explanation so
			// moderators can review WHY this is not actually a duplicate.
			$explanation_check = trim( (string) ( $request->get_param( 'duplicate_explanation' ) ?? '' ) );
			if ( strlen( $explanation_check ) < 20 ) {
				return new WP_Error(
					'listora_duplicate_explanation_required',
					__( 'Please explain how your business is different from the listed duplicates (at least 20 characters).', 'wb-listora' ),
					array( 'status' => 400 )
				);
			}
		}

		// Create the post.
		$author_id = $guest_author_id > 0 ? $guest_author_id : get_current_user_id();

		/**
		 * Filters whether to allow creating a listing. Return WP_Error to abort.
		 *
		 * @param bool|WP_Error   $check   True to proceed, WP_Error to abort.
		 * @param string          $title   Listing title.
		 * @param WP_REST_Request $request REST request.
		 */
		$check = apply_filters( 'wb_listora_before_create_listing', true, $title, $request );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$post_data = array(
			'post_type'    => 'listora_listing',
			'post_title'   => $title,
			'post_content' => $description,
			'post_status'  => $status,
			'post_author'  => $author_id,
		);

		// Wrap multi-step write in a transaction to prevent orphaned data.
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		try {
			$post_id = wp_insert_post( $post_data, true );

			if ( is_wp_error( $post_id ) ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

			// Persist the user-supplied "different business" explanation when
			// they bypassed the duplicate check. Stored as post meta so admins
			// can review it during moderation. Also flag the listing so the
			// admin column can surface it without a meta_query on text.
			if ( $confirmed_not_duplicate ) {
				$explanation = sanitize_textarea_field( (string) ( $request->get_param( 'duplicate_explanation' ) ?? '' ) );
				if ( '' !== $explanation ) {
					update_post_meta( $post_id, '_listora_duplicate_explanation', $explanation );
				}
				update_post_meta( $post_id, '_listora_duplicate_confirmed', '1' );
			}

			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return new WP_Error(
				'listora_submission_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}

		/**
		 * Fires after a listing is submitted from the frontend.
		 *
		 * Skipped while a listing sits in pending_verification — the admin
		 * notification fires instead from the verification handler once the
		 * email has been confirmed, so admins are never asked to review a
		 * listing that may still be abandoned.
		 *
		 * @param int             $post_id Post ID.
		 * @param string          $status  Post status.
		 * @param WP_REST_Request $request Request.
		 */
		if ( 'pending_verification' !== $status ) {
			do_action( 'wb_listora_listing_submitted', $post_id, $status, $request );
		}

		/**
		 * Fires after a listing is created via the submission form.
		 *
		 * @param int             $post_id Post ID.
		 * @param WP_REST_Request $request REST request.
		 */
		do_action( 'wb_listora_after_create_listing', $post_id, $request );

		// Dispatch the verification email now that the listing exists.
		if ( $verification_required && 'pending_verification' === $status ) {
			\WBListora\Workflow\Email_Verification::send_verification_email( $post_id );

			$response_data = array(
				'id'                    => $post_id,
				'listing_id'            => $post_id,
				'status'                => $status,
				'verification_required' => true,
				'message'               => __( 'Check your inbox to verify your email and publish your listing.', 'wb-listora' ),
				'email'                 => isset( $guest_email ) ? $guest_email : '',
			);

			/**
			 * Filters the listing-submission REST response data when verification is required.
			 *
			 * @param array           $response_data Response payload.
			 * @param \WP_Post        $post          Post object.
			 * @param WP_REST_Request $request       REST request.
			 */
			$response_data = apply_filters( 'wb_listora_rest_prepare_listing', $response_data, get_post( $post_id ), $request );

			return new WP_REST_Response( $response_data, 202 );
		}

		$response_data = array(
			'id'      => $post_id,
			'status'  => $status,
			'url'     => get_permalink( $post_id ),
			'message' => 'draft' === $status
				? __( 'Draft saved.', 'wb-listora' )
				: __( 'Listing submitted successfully!', 'wb-listora' ),
		);

		/**
		 * Filters the listing submission REST response data.
		 *
		 * @param array           $response_data Response data.
		 * @param \WP_Post        $post          Post object.
		 * @param WP_REST_Request $request       REST request.
		 */
		$response_data = apply_filters( 'wb_listora_rest_prepare_listing', $response_data, get_post( $post_id ), $request );

		return new WP_REST_Response( $response_data, 201 );
	}

	/**
	 * Update an existing listing.
	 */
	public function update_listing( $request ) {
		// Nonce check — same nonce used for the submission form.
		$nonce = $request->get_param( 'listora_nonce' );
		if ( $nonce && ! wp_verify_nonce( $nonce, 'listora_submit_listing' ) ) {
			return new WP_Error( 'listora_nonce_failed', __( 'Security check failed.', 'wb-listora' ), array( 'status' => 403 ) );
		}

		$post_id = $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new WP_Error( 'listora_not_found', __( 'Listing not found.', 'wb-listora' ), array( 'status' => 404 ) );
		}

		/**
		 * Filters whether to allow updating a listing. Return WP_Error to abort.
		 *
		 * @param bool|WP_Error   $check      True to proceed, WP_Error to abort.
		 * @param int             $post_id    Post ID.
		 * @param WP_REST_Request $request    REST request.
		 */
		$check = apply_filters( 'wb_listora_before_update_listing', true, $post_id, $request );
		if ( is_wp_error( $check ) ) {
			return $check;
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

		// Update category.
		$category = $request->get_param( 'category' );
		if ( null !== $category ) {
			$category_id = absint( $category );
			if ( $category_id > 0 ) {
				wp_set_object_terms( $post_id, array( $category_id ), 'listora_listing_cat' );
			}
		}

		// Update tags.
		$tags = $request->get_param( 'tags' );
		if ( null !== $tags ) {
			$tags_sanitized = sanitize_text_field( $tags );
			if ( $tags_sanitized ) {
				$tag_array = array_map( 'trim', explode( ',', $tags_sanitized ) );
				wp_set_object_terms( $post_id, $tag_array, 'listora_listing_tag' );
			} else {
				wp_set_object_terms( $post_id, array(), 'listora_listing_tag' );
			}
		}

		// Update featured image.
		$featured_image = $request->get_param( 'featured_image' );
		if ( null !== $featured_image ) {
			$image_id = absint( $featured_image );
			if ( $image_id > 0 ) {
				set_post_thumbnail( $post_id, $image_id );
			}
		}

		// Update gallery.
		$gallery = $request->get_param( 'gallery' );
		if ( null !== $gallery ) {
			if ( $gallery ) {
				$gallery_ids = array_filter( array_map( 'absint', explode( ',', $gallery ) ) );
				\WBListora\Core\Meta_Handler::set_value( $post_id, 'gallery', $gallery_ids );
			} else {
				\WBListora\Core\Meta_Handler::set_value( $post_id, 'gallery', array() );
			}
		}

		// Update video.
		$video = $request->get_param( 'video' );
		if ( null !== $video ) {
			\WBListora\Core\Meta_Handler::set_value( $post_id, 'video', esc_url_raw( $video ) );
		}

		// Update type-specific meta. Use submitted type or fall back to the post's existing type.
		$type_slug_param = sanitize_text_field( $request->get_param( 'listing_type' ) ?? '' );
		if ( $type_slug_param ) {
			$type_slug = $type_slug_param;
		} else {
			$types     = wp_get_object_terms( $post_id, 'listora_listing_type', array( 'fields' => 'slugs' ) );
			$type_slug = ! is_wp_error( $types ) && ! empty( $types ) ? $types[0] : '';
		}

		$this->save_meta_fields( $post_id, $type_slug, $request );

		/**
		 * Fires after a listing is updated from the frontend.
		 *
		 * @param int             $post_id Post ID.
		 * @param string          $status  Post status.
		 * @param WP_REST_Request $request Request.
		 */
		do_action( 'wb_listora_listing_updated', $post_id, get_post_status( $post_id ), $request );

		/**
		 * Fires after a listing is updated via the submission form.
		 *
		 * @param int             $post_id Post ID.
		 * @param WP_REST_Request $request REST request.
		 */
		do_action( 'wb_listora_after_update_listing', $post_id, $request );

		$response_data = array(
			'id'      => $post_id,
			'status'  => get_post_status( $post_id ),
			'url'     => get_permalink( $post_id ),
			'message' => __( 'Your listing has been updated.', 'wb-listora' ),
		);

		/**
		 * Filters the listing update REST response data.
		 *
		 * @param array           $response_data Response data.
		 * @param \WP_Post        $post          Post object.
		 * @param WP_REST_Request $request       REST request.
		 */
		$response_data = apply_filters( 'wb_listora_rest_prepare_listing', $response_data, get_post( $post_id ), $request );

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * REST endpoint: check for duplicate listings before submission.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function check_duplicate_endpoint( $request ) {
		$title = sanitize_text_field( $request->get_param( 'title' ) );
		$type  = sanitize_text_field( $request->get_param( 'type' ) ?? '' );
		$lat   = $request->get_param( 'lat' );
		$lng   = $request->get_param( 'lng' );

		$lat = null !== $lat ? (float) $lat : null;
		$lng = null !== $lng ? (float) $lng : null;

		$duplicates = $this->check_duplicates( $title, $type, $lat, $lng );

		return new WP_REST_Response(
			array(
				'duplicates' => $duplicates,
				'has_match'  => ! empty( $duplicates ),
			),
			200
		);
	}

	/**
	 * Check for potential duplicate listings by title similarity and geo proximity.
	 *
	 * Phase 1: Title similarity — compares against existing listings of the same type.
	 * Phase 2: Geo proximity — if lat/lng provided and no title matches, checks within 100m.
	 *
	 * @param string     $title Listing title.
	 * @param string     $type  Listing type slug.
	 * @param float|null $lat   Latitude.
	 * @param float|null $lng   Longitude.
	 * @return array Array of potential duplicate listings.
	 */
	private function check_duplicates( $title, $type, $lat = null, $lng = null ) {
		global $wpdb;

		$duplicates = array();

		if ( empty( $title ) ) {
			return $duplicates;
		}

		// Phase 1: Title similarity — use SQL LIKE for initial filtering, then refine with similar_text.
		// This avoids loading 100 rows and doing O(n) string comparisons in PHP.
		$like_title = '%' . $wpdb->esc_like( $title ) . '%';

		if ( $type ) {
			$existing = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_title FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
					WHERE p.post_type = 'listora_listing'
					AND p.post_status IN ('publish', 'pending', 'draft')
					AND tt.taxonomy = 'listora_listing_type'
					AND t.slug = %s
					AND p.post_title LIKE %s
					LIMIT 20",
					$type,
					$like_title
				)
			);
		} else {
			$existing = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_title FROM {$wpdb->posts} p
					WHERE p.post_type = 'listora_listing'
					AND p.post_status IN ('publish', 'pending', 'draft')
					AND p.post_title LIKE %s
					LIMIT 20",
					$like_title
				)
			);
		}

		if ( $existing ) {
			$title_lower = strtolower( $title );

			foreach ( $existing as $post ) {
				similar_text( $title_lower, strtolower( $post->post_title ), $percent );

				if ( $percent > 80 ) {
					$duplicates[] = array(
						'id'         => (int) $post->ID,
						'title'      => $post->post_title,
						'similarity' => round( $percent ),
						'url'        => get_permalink( $post->ID ),
					);
				}
			}
		}

		// Phase 2: If lat/lng provided, check for nearby listings with similar names.
		if ( null !== $lat && null !== $lng && empty( $duplicates ) ) {
			$nearby = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT g.post_id, p.post_title,
					( 6371000 * acos(
						cos( radians( %f ) ) * cos( radians( g.latitude ) )
						* cos( radians( g.longitude ) - radians( %f ) )
						+ sin( radians( %f ) ) * sin( radians( g.latitude ) )
					) ) AS distance
					FROM {$wpdb->prefix}listora_geo g
					INNER JOIN {$wpdb->posts} p ON g.post_id = p.ID
					WHERE p.post_status IN ('publish', 'pending')
					HAVING distance < 100
					ORDER BY distance
					LIMIT 5",
					$lat,
					$lng,
					$lat
				)
			);

			if ( $nearby ) {
				$title_lower = strtolower( $title );

				foreach ( $nearby as $near ) {
					similar_text( $title_lower, strtolower( $near->post_title ), $percent );

					if ( $percent > 60 ) {
						$duplicates[] = array(
							'id'         => (int) $near->post_id,
							'title'      => $near->post_title,
							'similarity' => round( $percent ),
							'distance'   => round( (float) $near->distance ),
							'url'        => get_permalink( $near->post_id ),
						);
					}
				}
			}
		}

		return $duplicates;
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
	 * REST: resend verification email.
	 *
	 * Accepts `{ listing_id, email? }`. When the listing is owned by a
	 * logged-in user we trust the cookie. For guests we require the email
	 * address to match the listing author.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function resend_verification_endpoint( $request ) {
		$listing_id = absint( $request->get_param( 'listing_id' ) );
		$email      = sanitize_email( $request->get_param( 'email' ) ?? '' );

		$post = $listing_id ? get_post( $listing_id ) : null;

		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new WP_REST_Response(
				array(
					'sent'  => false,
					'error' => 'not_found',
				),
				404
			);
		}

		if ( ! \WBListora\Workflow\Email_Verification::is_pending_verification( $listing_id ) ) {
			return new WP_REST_Response(
				array(
					'sent'  => false,
					'error' => 'not_pending',
				),
				400
			);
		}

		// Identify the requester. Either the logged-in author OR a guest who
		// supplies the matching email address.
		$author = get_user_by( 'id', (int) $post->post_author );
		if ( ! $author ) {
			return new WP_REST_Response(
				array(
					'sent'  => false,
					'error' => 'no_author',
				),
				404
			);
		}

		$is_owner    = is_user_logged_in() && get_current_user_id() === (int) $author->ID;
		$email_match = $email && strtolower( $email ) === strtolower( $author->user_email );

		if ( ! $is_owner && ! $email_match ) {
			return new WP_REST_Response(
				array(
					'sent'  => false,
					'error' => 'forbidden',
				),
				403
			);
		}

		$result = \WBListora\Workflow\Email_Verification::resend_verification( $listing_id );
		$status = ! empty( $result['sent'] ) ? 200 : ( 'rate_limited' === ( $result['error'] ?? '' ) ? 429 : 400 );

		return new WP_REST_Response( $result, $status );
	}

	/**
	 * REST: verify an email-verification token.
	 *
	 * Mirror of the public /?listora-verify=1 URL — apps and SPAs can call
	 * this directly and avoid HTML.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function verify_endpoint( $request ) {
		$listing_id = absint( $request->get_param( 'listing_id' ) );
		$token      = (string) $request->get_param( 'token' );

		$post = $listing_id ? get_post( $listing_id ) : null;
		if ( ! $post || 'listora_listing' !== $post->post_type ) {
			return new WP_REST_Response( array( 'verified' => false, 'error' => 'not_found' ), 404 );
		}

		if ( ! \WBListora\Workflow\Email_Verification::is_pending_verification( $listing_id ) ) {
			return new WP_REST_Response( array( 'verified' => false, 'error' => 'not_pending' ), 400 );
		}

		if ( \WBListora\Workflow\Email_Verification::is_expired( $listing_id ) ) {
			return new WP_REST_Response( array( 'verified' => false, 'error' => 'expired' ), 410 );
		}

		if ( ! \WBListora\Workflow\Email_Verification::verify_token( $listing_id, $token ) ) {
			return new WP_REST_Response( array( 'verified' => false, 'error' => 'invalid_token' ), 400 );
		}

		$moderation = wb_listora_get_setting( 'moderation', 'manual' );
		$new_status = ( 'auto_approve' === $moderation ) ? 'publish' : 'pending';

		wp_update_post(
			array(
				'ID'          => $listing_id,
				'post_status' => $new_status,
			)
		);

		\WBListora\Workflow\Email_Verification::consume_token( $listing_id );

		do_action( 'wb_listora_after_email_verified', $listing_id, $new_status );
		do_action( 'wb_listora_listing_submitted', $listing_id, $new_status, $request );
		if ( 'pending' === $new_status ) {
			do_action( 'wb_listora_listing_pending_admin', $listing_id );
		}

		return new WP_REST_Response(
			array(
				'verified'   => true,
				'status'     => $new_status,
				'listing_id' => $listing_id,
				'url'        => get_permalink( $listing_id ),
			),
			200
		);
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
